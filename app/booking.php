<?php
declare(strict_types=1);

const CONTACT = ['Call','SMS','Messenger','Email','Viber','Other'];
const TYPES = ['Residential Pool','Commercial Pool','Resort / Hotel','Government Project','School / Institutional','Water Supply / Drilling','Water Treatment System','Swimming Pool Renovation','Pool Equipment Supply','Pool Maintenance','Solar / Electrical System','Other'];
const NATURE = ['New Construction','Renovation / Rehabilitation','Supply Only','Design Only','Design & Construction','Installation Only','Repair / Replacement','Consultation','Other'];
const POOL_TYPES = ['Skimmer','Overflow','Infinity','Indoor','Outdoor','Other'];
const SHAPES = ['Rectangular','L-Shape','Freeform','Custom','Other'];
const FEATURES = ['Jacuzzi / Spa','Kiddie Pool','Waterfall','Water Curtain','Fountain','Pool Lighting','Pool Heating','Automatic Pool Cover','Salt Chlorination','Filtration System','Automatic Dosing System','Other'];
const FACILITIES = ['None','Existing Pool','Existing Water System','Existing Electrical System','Existing Pump Room','Existing Plumbing','Other'];
const ACCESS = ['Easily Accessible','Limited Access','Difficult Access'];
const EVENTS = ['confirmation','appointment_reminder','site_reminder','ongoing','completed'];

function availability(): array {
    return rows("SELECT s.id,s.starts_at FROM slots s LEFT JOIN requests r ON r.active_slot=s.id
        WHERE s.is_open=1 AND r.id IS NULL AND s.starts_at>NOW() ORDER BY s.starts_at LIMIT 180");
}
function options_input(array $data, string $key, array $options): array {
    $values = $data[$key] ?? [];
    if (!is_array($values) || count($values) > count($options)) fail('Check the '.str_replace('_',' ',$key).' selection.');
    foreach ($values as $value) if (!in_array($value,$options,true)) fail('Invalid selection.');
    return array_values(array_unique($values));
}
function dimension(array $data, string $key): ?string {
    $value = field($data,$key,8);
    if ($value !== null && (!preg_match('/^\d{1,5}(\.\d{1,2})?$/D',$value) || (float)$value <= 0)) fail('Pool measurements must be positive numbers in metres.');
    return $value;
}
function submit_request(array $data): array {
    if (field($data,'website') !== null) fail('Request rejected.');
    $slot = id($data['slot_id'] ?? null);
    $name = field($data,'client_name',160,true);
    $kind = choice($data,'client_kind',['individual','company']);
    $person = field($data,'contact_person',160,$kind === 'company');
    $mobile = preg_replace('/[\s()-]/','',field($data,'mobile',30,true));
    if (!preg_match('/^(?:09|\+639|639)\d{9}$/D',$mobile)) fail('Enter a valid Philippine mobile number.');
    if (str_starts_with($mobile,'09')) $mobile = '+63'.substr($mobile,1);
    if (str_starts_with($mobile,'63')) $mobile = '+'.$mobile;
    $contact = choice($data,'contact_method',CONTACT);
    $contactOther = $contact === 'Other' ? field($data,'contact_other',160,true) : null;
    $email = field($data,'email',254,$contact === 'Email');
    if ($email !== null && !filter_var($email,FILTER_VALIDATE_EMAIL)) fail('Enter a valid email address.');
    $site = field($data,'site_area',160,true);
    $type = choice($data,'project_type',TYPES);
    $typeOther = $type === 'Other' ? field($data,'project_other',160,true) : null;
    $nature = choice($data,'nature',NATURE);
    $natureOther = $nature === 'Other' ? field($data,'nature_other',160,true) : null;
    $project = field($data,'project_name',200) ?? mb_substr($name.' — '.($typeOther ?? $type),0,200);
    $notes = field($data,'notes',3000);
    $pool = choice($data,'pool_type',POOL_TYPES,false);
    $shape = choice($data,'shape',SHAPES,false);
    $poolData = [$pool,$pool==='Other'?field($data,'pool_other',160,true):null,$shape,
        $shape==='Other'?field($data,'shape_other',160,true):null,dimension($data,'length_m'),dimension($data,'width_m'),dimension($data,'depth_m')];
    $features = options_input($data,'features',FEATURES);
    $facilities = options_input($data,'facilities',FACILITIES);
    if (in_array('None',$facilities,true) && count($facilities)>1) fail('Choose “None” by itself for existing facilities.');
    $featureOther = in_array('Other',$features,true) ? field($data,'feature_other',160,true) : null;
    $facilityOther = in_array('Other',$facilities,true) ? field($data,'facility_other',160,true) : null;
    rate('booking-phone',$mobile,5);
    return tx(function () use ($slot,$name,$kind,$person,$mobile,$email,$contact,$contactOther,$site,$project,$type,$typeOther,$nature,$natureOther,$notes,$poolData,$features,$facilities,$featureOther,$facilityOther) {
        $time = one('SELECT starts_at FROM slots WHERE id=? AND is_open=1 AND starts_at>NOW() FOR UPDATE',[$slot]);
        if (!$time || one('SELECT id FROM requests WHERE active_slot=?',[$slot])) fail('That appointment time is no longer available. Choose another time.',409);
        run('INSERT INTO requests(slot_id,client_name,client_kind,contact_person,mobile,email,contact_method,contact_other,site_area,project_name,project_type,project_other,nature,nature_other,notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [$slot,$name,$kind,$person,$mobile,$email,$contact,$contactOther,$site,$project,$type,$typeOther,$nature,$natureOther,$notes]);
        $requestId = (int)db()->lastInsertId();
        if (array_filter($poolData,static fn($v)=>$v!==null)) run('INSERT INTO pool_details VALUES (?,?,?,?,?,?,?,?)',[$requestId,...$poolData]);
        foreach ($features as $v) run('INSERT INTO request_features VALUES (?,?,?)',[$requestId,$v,$v==='Other'?$featureOther:null]);
        foreach ($facilities as $v) run('INSERT INTO request_facilities VALUES (?,?,?)',[$requestId,$v,$v==='Other'?$facilityOther:null]);
        $request = one('SELECT id,received_at,status FROM requests WHERE id=?',[$requestId]);
        return $request + $time + ['form_number'=>form_no($request)];
    });
}
function request_list(array $filter): array {
    $sql = 'SELECT r.*,s.starts_at,m.outcome,v.starts_at AS visit,p.status AS project_status FROM requests r JOIN slots s ON s.id=r.slot_id
        LEFT JOIN meetings m ON m.request_id=r.id LEFT JOIN site_visits v ON v.request_id=r.id LEFT JOIN projects p ON p.request_id=r.id WHERE 1=1';
    $params = [];
    if ($q = field($filter,'q',100)) {
        $sql .= " AND (CONCAT('PSA-',YEAR(r.received_at),'-',LPAD(r.id,GREATEST(4,LENGTH(r.id)),'0')) LIKE ? OR r.client_name LIKE ?)";
        $params[] = '%'.$q.'%'; $params[] = '%'.$q.'%';
    }
    if ($date = date_input($filter,'date',false,false)) { $sql .= ' AND r.received_at>=? AND r.received_at<DATE_ADD(?,INTERVAL 1 DAY)'; array_push($params,$date,$date); }
    if ($status = choice($filter,'status',['pending','approved','declined','inquiry'],false)) {
        $sql .= $status === 'inquiry' ? " AND m.outcome='inquiry'" : ' AND r.status=?';
        if ($status !== 'inquiry') $params[]=$status;
    }
    if ($period = choice($filter,'period',['upcoming','past'],false)) $sql .= $period==='upcoming'?' AND s.starts_at>=NOW()':' AND s.starts_at<NOW()';
    $page = max(1,min(10000,(int)($filter['page'] ?? 1)));
    $offset = 50*($page-1);
    return rows($sql.' ORDER BY '.($period==='upcoming'?'s.starts_at ASC':'r.received_at DESC').",r.id DESC LIMIT 50 OFFSET $offset",$params);
}
function request_detail(int $id): array {
    $request = one('SELECT r.*,s.starts_at FROM requests r JOIN slots s ON s.id=r.slot_id WHERE r.id=?',[$id]);
    if (!$request) fail('Request not found.',404);
    return $request + ['form_number'=>form_no($request),'pool'=>one('SELECT * FROM pool_details WHERE request_id=?',[$id]),
        'features'=>rows('SELECT feature,other_detail FROM request_features WHERE request_id=? ORDER BY feature',[$id]),
        'facilities'=>rows('SELECT facility,other_detail FROM request_facilities WHERE request_id=? ORDER BY facility',[$id]),
        'meeting'=>one('SELECT m.*,v.starts_at AS visit FROM meetings m LEFT JOIN site_visits v ON v.request_id=m.request_id WHERE m.request_id=?',[$id]),
        'project'=>one('SELECT * FROM projects WHERE request_id=?',[$id])];
}
function schedule_message(array $request, string $event, ?string $date = null): void {
    $due = new DateTimeImmutable();
    $expires = null;
    if ($date !== null) {
        $when = new DateTimeImmutable($date);
        $dayBefore=$when->modify('-1 day')->setTime(9,0);
        if ($dayBefore>$due) $due=$dayBefore;
        $expires = $when->setTime(0,0)->format('Y-m-d H:i:s');
    }
    $template = one('SELECT body FROM message_templates WHERE event=?',[$event])['body'];
    $body = strtr($template,['{form}'=>form_no($request),'{appointment}'=>human_date($request['starts_at']),'{visit}'=>$date ? human_date($date) : '']);
    if (mb_strlen($body)>600) fail('The resulting message is too long. Shorten its template.');
    $occurrence=$event==='site_reminder' ? $date : '';
    if ($event==='site_reminder')
        run("UPDATE messages SET status='cancelled',processed_at=NOW() WHERE request_id=? AND event=? AND occurrence<>? AND status='queued'",[$request['id'],$event,$occurrence]);
    run("INSERT INTO messages(request_id,event,occurrence,due_at,expires_at,channel,recipient,body) VALUES (?,?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE due_at=IF(status IN ('queued','cancelled'),VALUES(due_at),due_at),
        expires_at=IF(status IN ('queued','cancelled'),VALUES(expires_at),expires_at),
        body=IF(status IN ('queued','cancelled'),VALUES(body),body),
        status=IF(status='cancelled','queued',status)",
        [$request['id'],$event,$occurrence,$due->format('Y-m-d H:i:s'),$expires,$request['contact_method']==='Viber'?'viber':'sms',$request['mobile'],$body]);
}
function decision(array $user, int $id, string $decision): void {
    if (!in_array($decision,['approved','declined'],true)) fail('Invalid decision.');
    tx(function () use ($user,$id,$decision) {
        $record = one('SELECT slot_id FROM requests WHERE id=?',[$id]);
        if (!$record) fail('Request not found.',404);
        $slot = one('SELECT starts_at FROM slots WHERE id=? FOR UPDATE',[$record['slot_id']]);
        $request = one('SELECT * FROM requests WHERE id=? FOR UPDATE',[$id]) + $slot;
        if ($request['status'] !== 'pending') fail('This request already has a decision.',409);
        if ($decision==='approved' && $request['starts_at'] <= date('Y-m-d H:i:s')) fail('This appointment time has passed.',409);
        run('UPDATE requests SET status=? WHERE id=?',[$decision,$id]);
        if ($decision === 'approved') {
            schedule_message($request,'confirmation');
            schedule_message($request,'appointment_reminder',$request['starts_at']);
        }
        audit($user,$decision,form_no($request));
    });
}
function save_slot(array $user, array $data, ?int $id): int {
    $open = choice($data,'is_open',['0','1']) === '1';
    $time = date_input($data,'starts_at',true);
    return tx(function () use ($user,$id,$open,$time) {
        if ($id) {
            $old = one('SELECT * FROM slots WHERE id=? FOR UPDATE',[$id]);
            if (!$old) fail('Slot not found.',404);
            if ($old['starts_at']!==$time && one('SELECT id FROM requests WHERE slot_id=? LIMIT 1',[$id])) fail('A requested slot keeps its original time. Close it and create a new slot.',409);
            if ($time!==$old['starts_at'] && $time<=date('Y-m-d H:i:s')) fail('Choose a future time.');
            run('UPDATE slots SET starts_at=?,is_open=? WHERE id=?',[$time,(int)$open,$id]);
        } else {
            if ($time<=date('Y-m-d H:i:s')) fail('Choose a future time.');
            run('INSERT INTO slots(starts_at,is_open) VALUES (?,?)',[$time,(int)$open]);
            $id=(int)db()->lastInsertId();
        }
        audit($user,'save slot','Slot '.$id);
        return $id;
    });
}
function record_meeting(array $user,int $id,array $data): void {
    $outcome = choice($data,'outcome',['proceed','inquiry']);
    $access = choice($data,'accessibility',ACCESS,false);
    $address = field($data,'full_address',255);
    $notes = field($data,'meeting_notes',3000);
    $needsVisit = ($data['needs_visit'] ?? '') === '1';
    $visit = $needsVisit && $outcome==='proceed' ? date_input($data,'visit',true) : null;
    $facilities=null;
    $facilityOther=null;
    if (($data['update_facilities']??'')==='1') {
        $facilities=options_input($data,'facilities',FACILITIES);
        if (in_array('None',$facilities,true) && count($facilities)>1) fail('Choose “None” by itself for existing facilities.');
        if (in_array('Other',$facilities,true)) $facilityOther=field($data,'facility_other',160,true);
    }
    tx(function () use ($user,$id,$outcome,$access,$address,$notes,$visit,$facilities,$facilityOther) {
        $locked = one('SELECT status FROM requests WHERE id=? FOR UPDATE',[$id]);
        if (!$locked || $locked['status'] !== 'approved') fail('Approve the appointment before recording its meeting.',409);
        $request = request_detail($id);
        if ($request['project'] && $outcome==='inquiry') fail('This request already has a project.',409);
        if ($visit && $visit !== ($request['meeting']['visit'] ?? null) && $visit<=date('Y-m-d H:i:s')) fail('Choose a future site visit time.');
        run('INSERT INTO meetings(request_id,outcome,accessibility,full_address,notes) VALUES (?,?,?,?,?)
            ON DUPLICATE KEY UPDATE outcome=VALUES(outcome),accessibility=VALUES(accessibility),full_address=VALUES(full_address),notes=VALUES(notes)',[$id,$outcome,$access,$address,$notes]);
        if ($facilities!==null) {
            run('DELETE FROM request_facilities WHERE request_id=?',[$id]);
            foreach ($facilities as $facility)
                run('INSERT INTO request_facilities VALUES (?,?,?)',[$id,$facility,$facility==='Other'?$facilityOther:null]);
        }
        if ($visit) {
            run('INSERT INTO site_visits VALUES (?,?) ON DUPLICATE KEY UPDATE starts_at=VALUES(starts_at)',[$id,$visit]);
            schedule_message($request,'site_reminder',$visit);
        } else {
            run('DELETE FROM site_visits WHERE request_id=?',[$id]);
            run("UPDATE messages SET status='cancelled' WHERE request_id=? AND event='site_reminder' AND status='queued'",[$id]);
        }
        audit($user,'save meeting',form_no($request));
    });
}
function save_project(array $user,int $id,array $data): void {
    $status=choice($data,'project_status',['ongoing','completed']);
    $started=date_input($data,'started_on');
    $completed=$status==='completed'?date_input($data,'completed_on'):null;
    if ($completed && $completed<$started) fail('Completion cannot be earlier than the start date.');
    $notes=field($data,'project_notes',3000);
    tx(function () use ($user,$id,$status,$started,$completed,$notes) {
        if (!one('SELECT id FROM requests WHERE id=? FOR UPDATE',[$id])) fail('Request not found.',404);
        $request=request_detail($id);
        if ($request['status']!=='approved' || ($request['meeting']['outcome']??null)!=='proceed') fail('Record a proceeding meeting first.',409);
        $previous=$request['project']['status']??null;
        if ((!$previous && $status!=='ongoing') || ($previous==='completed' && $status!=='completed')) fail('A project starts as ongoing and can then be completed.',409);
        run('INSERT INTO projects(request_id,status,started_on,completed_on,notes) VALUES (?,?,?,?,?)
            ON DUPLICATE KEY UPDATE status=VALUES(status),started_on=VALUES(started_on),completed_on=VALUES(completed_on),notes=VALUES(notes)',[$id,$status,$started,$completed,$notes]);
        if ($previous!==$status) schedule_message($request,$status);
        audit($user,'save project',form_no($request));
    });
}
