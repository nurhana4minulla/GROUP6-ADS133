<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
require dirname(__DIR__).'/app/bootstrap.php';
$c=config();
if (!in_array($c['message_mode'],['demo','live'],true)) throw new RuntimeException('Invalid message mode.');
if ($c['message_mode']==='live' && (!str_starts_with($c['message_gateway'],'https://') || $c['message_token']===''))
    throw new RuntimeException('Live mode needs an HTTPS gateway and bearer token.');
run("UPDATE messages SET status='cancelled',processed_at=NOW() WHERE status='queued' AND expires_at<=NOW()");
run("UPDATE messages SET status='failed',error='Delivery result unknown; check the provider log before retrying.' WHERE status='sending' AND processed_at<DATE_SUB(NOW(),INTERVAL 15 MINUTE)");
run('DELETE FROM rate_limits WHERE bucket<?',[intdiv(time(),900)-96]);
$due=rows("SELECT * FROM messages WHERE status='queued' AND due_at<=NOW() ORDER BY due_at,id LIMIT 100");
$processed=0;
foreach ($due as $message) {
    if (!run("UPDATE messages SET status='sending',processed_at=NOW() WHERE id=? AND status='queued' AND due_at<=NOW() AND (expires_at IS NULL OR expires_at>NOW())",[$message['id']])->rowCount()) continue;
    $message=one('SELECT * FROM messages WHERE id=?',[$message['id']]);
    $status='simulated';
    $error=null;
    if ($c['message_mode']==='live') {
        $ch=curl_init($c['message_gateway']);
        curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15,
            CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.$c['message_token'],'Idempotency-Key: prime-pool-'.$message['id']],
            CURLOPT_POSTFIELDS=>json_encode(['channel'=>$message['channel'],'to'=>$message['recipient'],'text'=>$message['body']],JSON_THROW_ON_ERROR)]);
        $response=curl_exec($ch);
        $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
        curl_close($ch);
        $status=$response!==false && $code>=200 && $code<300?'sent':'failed';
        if ($status==='failed') $error=$code?'Gateway HTTP '.$code:'Gateway connection failed.';
    }
    run('UPDATE messages SET status=?,processed_at=NOW(),error=? WHERE id=?',[$status,$error,$message['id']]);
    $processed++;
}
echo "Processed $processed due message(s) in {$c['message_mode']} mode.\n";
