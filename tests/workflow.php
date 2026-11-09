<?php
declare(strict_types=1);
$base=$argv[1]??'http://127.0.0.1:8096';
$email=$argv[2]??'';
$password=getenv('PRIME_POOL_TEST_PASSWORD');
if (!$password || !$email) {
    fwrite(STDERR,"Supply the admin email and PRIME_POOL_TEST_PASSWORD. Use a disposable demo database.\n");
    exit(1);
}
function verify(bool $ok,string $label): void { if(!$ok) throw new RuntimeException($label); }
function request(string $path,string $method='GET',?array $data=null,?int $expect=200,string $profile='public',bool $multipart=false): array {
    global $base;
    static $sessions=[];
    $h=curl_init($base.'/'.$path);
    $headers=['X-CSRF-Token: '.($sessions[$profile]['csrf']??'')];
    if (!$multipart) $headers[]='Content-Type: application/json';
    curl_setopt_array($h,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_CUSTOMREQUEST=>$method,
        CURLOPT_HTTPHEADER=>$headers,CURLOPT_COOKIE=>$sessions[$profile]['cookie']??'',CURLOPT_TIMEOUT=>8]);
    if($data!==null) curl_setopt($h,CURLOPT_POSTFIELDS,$multipart?$data:json_encode((object)$data,JSON_THROW_ON_ERROR));
    $response=curl_exec($h);
    verify(is_string($response),'HTTP request failed: '.curl_error($h));
    $status=curl_getinfo($h,CURLINFO_RESPONSE_CODE);
    $headerLength=curl_getinfo($h,CURLINFO_HEADER_SIZE);
    curl_close($h);
    $head=substr($response,0,$headerLength);
    $body=substr($response,$headerLength);
    if(preg_match('/^Set-Cookie:\s*([^;]+)/mi',$head,$match)) $sessions[$profile]['cookie']=$match[1];
    verify($status===$expect,"Expected $expect, got $status for $method $path: ".substr($body,0,250));
    $json=json_decode($body,true);
    if(isset($json['data']['csrf'])) $sessions[$profile]['csrf']=$json['data']['csrf'];
    return $json??['html'=>$body];
}
function api(string $resource,string $method='GET',?array $data=null,int $expect=200,string $profile='public'): array {
    return request('api.php?resource='.$resource,$method,$data,$expect,$profile);
}
api('session');
api('session',profile:'staff');
api('session','POST',['email'=>$email,'password'=>$password],profile:'staff');
api('requests',expect:401);
$slots=api('availability')['data'];
verify(count($slots)>=2,'At least two slots are required');
$slot=(int)$slots[0]['id'];
$data=['slot_id'=>$slot,'client_kind'=>'company','client_name'=>'ADS Demo Client','mobile'=>'+639991234567','contact_method'=>'Email',
 'site_area'=>'Zamboanga City','project_type'=>'Residential Pool','nature'=>'New Construction','features'=>['Waterfall','Other'],
 'feature_other'=>'A fictional water feature','facilities'=>['Existing Pool']];
api('booking','POST',$data,422);
$data+=['contact_person'=>'Test Contact','email'=>'fake@example.test'];
$first=api('booking','POST',$data,201)['data'];
verify((bool)preg_match('/^PSA-\d{4}-\d{4,}$/',$first['form_number']),'Request number was not assigned');
verify(!in_array($slot,array_map('intval',array_column(api('availability')['data'],'id')),true),'Pending slot remained visible');
api('booking','POST',$data,409);
api('requests&id='.$first['id'],'PATCH',['decision'=>'declined'],profile:'staff');
verify(in_array($slot,array_map('intval',array_column(api('availability')['data'],'id')),true),'Declined slot was not released');
$second=api('booking','POST',$data,201)['data'];
$id=$second['id'];
api('requests&id='.$id,'PATCH',['decision'=>'approved'],profile:'staff');
api('requests&id='.$id,'PATCH',['decision'=>'declined'],409,'staff');
$future=(new DateTimeImmutable('+5 days',new DateTimeZone('Asia/Manila')))->format('Y-m-d').'T09:00';
$meeting=api('requests&id='.$id,'PATCH',['action'=>'meeting','outcome'=>'proceed','needs_visit'=>'1','visit'=>$future],profile:'staff')['data'];
verify($meeting['meeting']['visit']!==null && count($meeting['features'])===2,'Meeting or optional features were lost');
$today=(new DateTimeImmutable('now',new DateTimeZone('Asia/Manila')))->format('Y-m-d');
api('projects&id='.$id,'POST',['project_status'=>'ongoing','started_on'=>$today],profile:'staff');
$project=api('projects&id='.$id,'PATCH',['project_status'=>'completed','started_on'=>$today,'completed_on'=>$today],profile:'staff')['data'];
verify($project['status']==='completed','Project was not completed');
$print=request('admin.php?tab=print&id='.$id,profile:'staff')['html'];
verify(str_contains($print,'Client Request Form') && str_contains($print,$second['form_number']),'Print form missing reference');
$cms=['title'=>'Disposable test project','slug'=>'test-'.bin2hex(random_bytes(5)),'description'=>'Fixture','published'=>'0'];
$record=api('portfolio','POST',$cms,201,'staff')['data']['id'];
api('portfolio&id='.$record,expect:404);
request('admin.php?tab=cms&kind=portfolio&id='.$record,'POST',[
    'action'=>'image','kind'=>'portfolio','id'=>$record,'alt'=>'Fixture photograph',
    'image'=>new CURLFile(dirname(__DIR__).'/public/assets/lapaz-1.webp','image/webp','demo.webp')
],303,'staff',true);
$uploaded=api('portfolio&manage=1&id='.$record,profile:'staff')['data']['images'][0];
$disk=dirname(__DIR__).'/storage/'.$uploaded['path'];
verify(is_file($disk),'Upload was not stored');
request('media.php?id='.$uploaded['id'],expect:404);
api('portfolio&id='.$record,'PATCH',['published'=>'1'],profile:'staff');
api('portfolio&id='.$record);
request('media.php?id='.$uploaded['id']);
$batch=request('admin.php?tab=print','POST',['action'=>'print','ids[0]'=>$first['id'],'ids[1]'=>$id],200,'staff',true)['html'];
verify(substr_count($batch,'class="print-sheet"')===2,'Batch print did not contain both requests');
api('portfolio&id='.$record,'DELETE',[],profile:'staff');
api('portfolio&id='.$record,expect:404);
clearstatcache(true,$disk);
verify(!is_file($disk),'Deleted upload remained on disk');
foreach(['dashboard','slots','requests','projects','cms','messages','activity','accounts'] as $tab) {
    $body=request('admin.php?tab='.$tab,profile:'staff')['html'];
    verify(!str_contains($body,'could not complete') && str_contains($body,'Operations'),"Staff tab failed: $tab");
}
$staffEmail='test-'.bin2hex(random_bytes(4)).'@example.test';
$initial=bin2hex(random_bytes(9));
$newPassword=bin2hex(random_bytes(9));
request('admin.php?tab=accounts','POST',['action'=>'staff','email'=>$staffEmail,'password'=>$initial],303,'staff',true);
api('session',profile:'limited');
api('session','POST',['email'=>$staffEmail,'password'=>$initial],profile:'limited');
request('admin.php?tab=accounts',expect:403,profile:'limited');
$issued=request('admin.php?tab=accounts','POST',['action'=>'issue_reset','email'=>$staffEmail],200,'staff',true)['html'];
verify((bool)preg_match('/<code>([a-f0-9]{64})<\/code>/',$issued,$match),'Reset code not shown to owner');
request('admin.php?tab=reset','POST',['action'=>'reset','token'=>$match[1],'password'=>$newPassword],303,'public',true);
api('requests',expect:401,profile:'limited');
api('session',profile:'limited');
api('session','POST',['email'=>$staffEmail,'password'=>$newPassword],profile:'limited');
echo "Booking, appointment decisions, project tracking, single/batch print, CMS CRUD, protected uploads, staff roles and password reset passed.\n";
