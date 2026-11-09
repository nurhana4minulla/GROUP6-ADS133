<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
require ROOT.'/app/booking.php';
require ROOT.'/app/content.php';
require ROOT.'/app/accounts.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function api_data(): array {
    if (!str_contains($_SERVER['CONTENT_TYPE']??'','application/json')) return $_POST;
    $raw=file_get_contents('php://input',false,null,0,65537);
    if (strlen($raw)>65536) fail('Request body is too large.',413);
    if ($raw==='' && method()==='DELETE') return [];
    $data=json_decode($raw,true);
    if (!is_array($data) || !str_starts_with(ltrim($raw),'{')) fail('Send a JSON object.',400);
    return $data;
}
try {
    $resource=choice($_GET,'resource',[...CONTENT_KINDS,'availability','slots','requests','booking','session','projects']);
    $id=isset($_GET['id'])?id($_GET['id']):null;
    $method=method();
    if ($method==='GET') {
        $result=match($resource) {
            'company','service','portfolio' => (function () use ($resource,$id) {
                $manage=($_GET['manage']??'')==='1';
                if ($manage) require_admin();
                return $id?content_entry($resource,$id,$manage):content_list($resource,$manage);
            })(),
            'availability'=>availability(),
            'session'=>['csrf'=>csrf(),'admin'=>admin()],
            'requests'=>(function () use ($id) { require_admin(); return $id?request_detail($id):request_list($_GET); })(),
            'slots'=>(function () { require_admin(); return rows('SELECT * FROM slots ORDER BY starts_at DESC LIMIT 200'); })(),
            'projects'=>(function () {
                require_admin();
                return rows('SELECT p.*,r.project_name,r.received_at,r.client_name FROM projects p JOIN requests r ON r.id=p.request_id ORDER BY p.started_on DESC LIMIT 200');
            })(),
            default=>fail('Method not allowed.',405)
        };
    } else {
        $data=api_data();
        check_csrf($data);
        if ($resource==='booking' && $method==='POST') {
            rate('booking-ip',remote_ip(),20);
            $result=submit_request($data);
            http_response_code(201);
        } elseif ($resource==='session' && $method==='POST') {
            login($data);
            $result=['csrf'=>csrf(),'admin'=>admin()];
        } else {
            $user=require_admin();
            if (in_array($resource,CONTENT_KINDS,true)) {
                if ($method==='DELETE' && $id) {
                    delete_content($user,$resource,$id);
                    $result=['deleted'=>$id];
                } elseif (($method==='POST' && !$id) || ($method==='PATCH' && $id)) {
                    $saved=save_content($user,$resource,$id,$data);
                    $result=content_entry($resource,$saved,true);
                    if (!$id) http_response_code(201);
                } else fail('Method not allowed.',405);
            } elseif ($resource==='slots' && (($method==='POST' && !$id) || ($method==='PATCH' && $id))) {
                $result=['id'=>save_slot($user,$data,$id)];
                if (!$id) http_response_code(201);
            } elseif ($resource==='requests' && $method==='PATCH' && $id) {
                if (($data['action']??'')==='meeting') record_meeting($user,$id,$data);
                else decision($user,$id,choice($data,'decision',['approved','declined']));
                $result=request_detail($id);
            } elseif ($resource==='projects' && $id && in_array($method,['POST','PATCH'],true)) {
                save_project($user,$id,$data);
                $result=request_detail($id)['project'];
            } else fail('Method not allowed.',405);
        }
    }
    echo json_encode(['data'=>$result],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
} catch (Throwable $error) {
    http_response_code(status_code($error));
    echo json_encode(['error'=>safe_error($error)],JSON_INVALID_UTF8_SUBSTITUTE);
}
