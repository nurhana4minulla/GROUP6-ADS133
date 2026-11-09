<?php
declare(strict_types=1);
function admin_title(string $section,string $title): void {
    echo '<p class="eyebrow">'.e($section).'</p><h1>'.e($title).'</h1>';
}
function dashboard(): void {
    $metrics=[
        'Pending requests'=>one("SELECT COUNT(*) AS n FROM requests WHERE status='pending'")['n'],
        'Today’s appointments'=>one("SELECT COUNT(*) AS n FROM requests r JOIN slots s ON s.id=r.slot_id WHERE r.status='approved' AND s.starts_at>=CURDATE() AND s.starts_at<DATE_ADD(CURDATE(),INTERVAL 1 DAY)")['n'],
        'Ongoing projects'=>one("SELECT COUNT(*) AS n FROM projects WHERE status='ongoing'")['n'],
        'Requests converted to projects'=>one('SELECT COUNT(*) AS n FROM projects')['n'],
        'Inquiry only'=>one("SELECT COUNT(*) AS n FROM meetings WHERE outcome='inquiry'")['n'],
    ];
    admin_title('Operations / Philippine time','Dashboard');
    ?>
    <div class="metrics"><?php foreach ($metrics as $label=>$n): ?><div class="metric"><strong><?= e($n) ?></strong><?= e($label) ?></div><?php endforeach; ?></div>
    <section class="panel"><h2>Next appointments</h2><?php request_table(array_slice(request_list(['period'=>'upcoming','status'=>'approved']),0,8),false); ?></section>
    <?php
}
function request_table(array $list,bool $select): void {
    if (!$list) { echo '<p class="text-secondary mb-0">No requests match this view.</p>'; return; }
    ?>
    <div class="table-responsive"><table class="table align-middle">
      <thead><tr><?php if($select): ?><th scope="col">Print</th><?php endif; ?><th scope="col">Form number</th><th scope="col">Received</th><th scope="col">Client</th><th scope="col">Appointment</th><th scope="col">Status</th></tr></thead>
      <tbody><?php foreach ($list as $request): ?><tr>
        <?php if($select): ?><td><input class="form-check-input" type="checkbox" name="ids[]" value="<?= (int)$request['id'] ?>" aria-label="Select <?= e(form_no($request)) ?> for printing"></td><?php endif; ?>
        <td><a href="admin.php?tab=request&amp;id=<?= (int)$request['id'] ?>"><?= e(form_no($request)) ?></a></td>
        <td><?= e(human_date($request['received_at'])) ?></td><td><?= e($request['client_name']) ?><br><small><?= e($request['mobile']) ?></small></td>
        <td><?= e(human_date($request['starts_at'])) ?></td><td><?php badge($request['status']); if($request['outcome']==='inquiry') badge('inquiry'); ?></td>
      </tr><?php endforeach; ?></tbody>
    </table></div>
    <?php
}
function requests_screen(): void {
    $list=request_list($_GET);
    admin_title('Appointments / Inbox','Client requests');
    ?>
    <form class="panel filterbar mb-3" method="get">
      <input type="hidden" name="tab" value="requests">
      <?php input('Form number or client','q',$_GET['q']??''); input('Date received','date',$_GET['date']??'','date');
      select_field('Status','status',['pending','approved','declined','inquiry'],$_GET['status']??'');
      select_field('Appointment time','period',['upcoming','past'],$_GET['period']??''); button('Filter'); ?>
      <a class="btn btn-outline-secondary" href="admin.php?tab=requests">Clear</a>
    </form>
    <section class="panel">
      <?php if($list): ?><form method="post" action="admin.php?tab=print"><?= csrf_input() ?><input type="hidden" name="action" value="print">
        <?php request_table($list,true); button('Print selected forms','btn-outline-primary'); ?>
      </form><?php else: request_table([],false); endif; ?>
      <?php $page=max(1,min(10000,(int)($_GET['page']??1))); ?>
      <div class="admin-actions mt-3">
        <?php if($page>1): ?><a href="?<?= e(http_build_query(array_replace($_GET,['page'=>$page-1]))) ?>">← Previous</a><?php endif; ?>
        <?php if(count($list)===50): ?><a href="?<?= e(http_build_query(array_replace($_GET,['page'=>$page+1]))) ?>">Next →</a><?php endif; ?>
      </div>
    </section>
    <?php
}
function slots_screen(): void {
    $edit=isset($_GET['id'])?one('SELECT * FROM slots WHERE id=?',[id($_GET['id'])]):null;
    $list=rows("SELECT s.*,r.status FROM slots s LEFT JOIN requests r ON r.active_slot=s.id ORDER BY s.starts_at>=NOW() DESC,IF(s.starts_at>=NOW(),s.starts_at,NULL) ASC,s.starts_at DESC LIMIT 200");
    admin_title('Appointments / Schedule','Availability');
    ?>
    <div class="split">
      <section class="panel table-responsive"><h2>Appointment slots</h2>
      <table class="table"><thead><tr><th scope="col">Date and time</th><th scope="col">State</th><th scope="col">Action</th></tr></thead><tbody>
      <?php foreach($list as $slot): ?><tr><td><?= e(human_date($slot['starts_at'])) ?></td>
      <td><?php badge($slot['status']==='approved'?'booked':($slot['status']??($slot['starts_at']<=date('Y-m-d H:i:s')?'past':($slot['is_open']?'open':'closed')))); ?></td>
      <td><a href="admin.php?tab=slots&amp;id=<?= (int)$slot['id'] ?>">Edit</a></td></tr><?php endforeach; ?>
      </tbody></table><?php if(!$list): ?><p>No slots yet.</p><?php endif; ?></section>
      <section class="panel"><h2><?= $edit?'Edit slot':'Create a slot' ?></h2><p>Times use Philippine time.</p>
        <?php form_start('slot',$edit?(int)$edit['id']:null);
        input('Date and time','starts_at',local_time($edit['starts_at']??null),'datetime-local',true);
        select_field('Availability','is_open',['1'=>'Open','0'=>'Closed / blocked'],$edit['is_open']??1,true);
        button('Save slot'); ?></form>
        <?php if($edit): ?><a class="d-inline-block mt-3" href="admin.php?tab=slots">Create a new slot ↗</a><?php endif; ?>
      </section>
    </div>
    <?php
}
function local_time(?string $value): string { return $value ? date('Y-m-d\TH:i',strtotime($value)) : ''; }
function facts(array $fields): void {
    echo '<dl class="facts">';
    foreach($fields as $label=>$value) echo '<dt>'.e($label).'</dt><dd>'.e($value===null || $value===''?'—':$value).'</dd>';
    echo '</dl>';
}
function chosen(string $value,?string $other): string { return $other ? "$value — $other" : $value; }
function chosen_list(array $items,string $key): string {
    return implode(', ',array_map(static fn($item)=>chosen($item[$key],$item['other_detail']),$items));
}
function request_facts(array $request): void {
    facts(['Client'=>$request['client_name'],'Client type'=>ucfirst($request['client_kind']),'Contact person'=>$request['contact_person'],
      'Mobile'=>$request['mobile'],'Email'=>$request['email'],'Contact method'=>chosen($request['contact_method'],$request['contact_other']),
      'Site city or area'=>$request['site_area'],'Project name'=>$request['project_name'],'Project type'=>chosen($request['project_type'],$request['project_other']),
      'Nature of request'=>chosen($request['nature'],$request['nature_other']),'Project note'=>$request['notes']]);
    if ($request['pool']||$request['features']||$request['facilities']) {
        $p=$request['pool']??[];
        echo '<h2>Pool and site details</h2>';
        facts(['Pool type'=>chosen($p['pool_type']??'',$p['type_other']??null),'Pool shape'=>chosen($p['shape']??'',$p['shape_other']??null),
           'Length (m)'=>$p['length_m']??null,'Width (m)'=>$p['width_m']??null,'Depth (m)'=>$p['depth_m']??null,
           'Pool features'=>chosen_list($request['features'],'feature'),'Existing facilities'=>chosen_list($request['facilities'],'facility')]);
    }
}
function request_screen(int $id): void {
    $request=request_detail($id);$meeting=$request['meeting']??[];$project=$request['project']??[];
    ?>
    <a class="back" href="admin.php?tab=requests">← Client requests</a>
    <?php admin_title('Client request / '.ucfirst($request['status']),$request['form_number']); ?>
    <div class="admin-actions mb-3">
      <?php badge($request['status']); if(($meeting['outcome']??null)==='inquiry') badge('inquiry'); ?>
      <a class="btn btn-outline-primary" href="admin.php?tab=print&amp;id=<?= $id ?>">Print request</a>
      <?php if($request['status']==='pending'): ?>
      <form method="post" class="admin-actions"><?= csrf_input() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="decision">
        <button class="btn btn-primary" name="decision" value="approved">Approve</button>
        <button class="btn btn-outline-danger" name="decision" value="declined" data-confirm="Decline this request and release its slot?">Decline</button>
      </form><?php endif; ?>
    </div>
    <div class="split">
      <section class="panel"><h2>Client and project</h2>
        <?php facts(['Received'=>human_date($request['received_at']),'Appointment'=>human_date($request['starts_at']).' PHT']); request_facts($request); ?>
      </section>
      <?php if($request['status']==='approved'): ?>
      <div class="stack">
        <section class="panel"><h2>Meeting outcome</h2>
          <?php form_start('meeting',$id);
          select_field('Outcome','outcome',['proceed'=>'Wants to proceed','inquiry'=>'Inquiry only'],$meeting['outcome']??'',true);
          select_field('Site accessibility','accessibility',ACCESS,$meeting['accessibility']??'');
          input('Full site address','full_address',$meeting['full_address']??'','text',false,255);
          textarea('Meeting notes','meeting_notes',$meeting['notes']??''); ?>
          <input type="hidden" name="update_facilities" value="1">
          <?php check_group('Confirm existing facilities','facilities',FACILITIES,['facilities'=>array_column($request['facilities'],'facility')]); ?>
          <div data-other="facilities[]"><?php input('Other existing facilities','facility_other',array_column($request['facilities'],'other_detail','facility')['Other']??''); ?></div>
          <label><input type="checkbox" class="form-check-input" name="needs_visit" value="1"<?= !empty($meeting['visit'])?' checked':'' ?>> Site visit needed</label>
          <div data-condition="needs_visit" data-equals="1"><?php input('Agreed site visit date and time','visit',local_time($meeting['visit']??null),'datetime-local'); ?></div>
          <?php button('Save meeting'); ?></form>
        </section>
        <?php if(($meeting['outcome']??null)==='proceed'): ?>
        <section class="panel"><h2><?= $project?'Update project':'Create pool project' ?></h2>
          <?php form_start('project',$id);
          select_field('Project status','project_status',$project?['ongoing','completed']:['ongoing'],$project['status']??'ongoing',true);
          input('Start date','started_on',$project['started_on']??date('Y-m-d'),'date',true); ?>
          <div data-condition="project_status" data-equals="completed"><?php input('Completion date','completed_on',$project['completed_on']??'','date'); ?></div>
          <?php textarea('Project notes','project_notes',$project['notes']??''); button('Save project'); ?></form>
        </section>
        <?php endif; ?>
      </div><?php endif; ?>
    </div>
    <?php
}
function render_print(array $requests,array $company): void {
    page_start('Print Client Request Form','admin');
    ?>
    <main id="main">
    <div class="admin-actions p-3 no-print"><a href="admin.php?tab=requests">← Client requests</a><button class="btn btn-primary" type="button" data-print>Print / Save as PDF</button></div>
    <?php foreach($requests as $request): ?>
      <article class="print-sheet">
        <header><img src="assets/print-brand.png" alt="Prime Peninsula Supply and Services Corp.">
          <h1>Client Request Form (CRF)</h1>
          <div><?= e($company['contact']['description']??'') ?> · <?= e($company['email']['description']??'') ?></div>
        </header>
        <?php facts(['Form number'=>$request['form_number'],'Date received'=>human_date($request['received_at']),'Appointment'=>human_date($request['starts_at']).' PHT','Status'=>ucfirst($request['status'])]); ?>
        <h2>Client and project information</h2><?php request_facts($request); ?>
        <?php if($m=$request['meeting']): ?>
          <h2>Site and meeting information</h2>
          <?php facts(['Outcome'=>$m['outcome']==='proceed'?'Wants to proceed':'Inquiry only','Site accessibility'=>$m['accessibility'],
            'Full site address'=>$m['full_address'],'Site inspection'=>$m['visit']?human_date($m['visit']).' PHT':'Not scheduled','Notes'=>$m['notes']]); ?>
        <?php endif; ?>
      </article>
    <?php endforeach; ?>
    </main>
    <?php page_end();
}
function projects_screen(): void {
    $status=choice($_GET,'status',['ongoing','completed'],false);$q=field($_GET,'q',100);
    $sql='SELECT p.*,r.id,r.received_at,r.project_name,r.client_name,r.project_type FROM projects p JOIN requests r ON r.id=p.request_id WHERE 1=1';
    $params=[];
    if($status) { $sql.=' AND p.status=?'; $params[]=$status; }
    if($q) { $sql.=" AND (r.project_name LIKE ? OR r.client_name LIKE ? OR CONCAT('PSA-',YEAR(r.received_at),'-',LPAD(r.id,GREATEST(4,LENGTH(r.id)),'0')) LIKE ?)"; $params=[...$params,...array_fill(0,3,'%'.$q.'%')]; }
    $list=rows($sql.' ORDER BY p.started_on DESC,p.request_id DESC LIMIT 200',$params);
    admin_title('Operations / Tracking','Pool projects');
    ?>
    <form class="panel filterbar mb-3" method="get"><input type="hidden" name="tab" value="projects">
    <?php input('Project, form number or client','q',$q); select_field('Status','status',['ongoing','completed'],$status); button('Filter'); ?></form>
    <section class="panel table-responsive"><table class="table"><thead><tr><th scope="col">Project</th><th scope="col">Client</th><th scope="col">Start</th><th scope="col">Status</th><th scope="col">Form number</th></tr></thead><tbody>
    <?php foreach($list as $p): ?><tr><td><?= e($p['project_name']) ?><br><small><?= e($p['project_type']) ?></small></td><td><?= e($p['client_name']) ?></td>
    <td><?= e($p['started_on']) ?></td><td><?php badge($p['status']); ?></td><td><a href="admin.php?tab=request&amp;id=<?= (int)$p['id'] ?>"><?= e(form_no($p)) ?></a></td></tr><?php endforeach; ?>
    </tbody></table><?php if(!$list): ?><p>No projects match this view.</p><?php endif; ?></section>
    <?php
}
require __DIR__.'/admin-content-views.php';
