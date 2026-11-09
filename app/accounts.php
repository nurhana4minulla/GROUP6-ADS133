<?php
declare(strict_types=1);

function email_input(array $data): string {
    $email=strtolower(field($data,'email',254,true));
    if (!filter_var($email,FILTER_VALIDATE_EMAIL)) fail('Enter a valid email address.');
    return $email;
}
function password_input(array $data): string {
    $password=$data['password']??null;
    if (!is_string($password) || strlen($password)<12 || strlen($password)>72 || str_contains($password,"\0")) fail('Use a password between 12 and 72 bytes.');
    return $password;
}
function login(array $data): void {
    $email=email_input($data);
    rate('login-ip',remote_ip(),30);
    rate('login-account',$email,10);
    $password=$data['password']??'';
    $user=one('SELECT * FROM admins WHERE email=?',[$email]);
    $dummy='$2y$10$QhH1l3oagBRGD4rKZiwwUuVIRG5LIx6IayFxF4trCDAwH5IdgYw4.';
    if (!is_string($password) || strlen($password)>72 || !password_verify($password,$user['password_hash']??$dummy) || !$user) fail('The email or password is incorrect.',401);
    session_regenerate_id(true);
    $_SESSION=['admin_id'=>$user['id'],'version'=>$user['session_version']];
    audit($user,'sign in','Account '.$user['id']);
}
function create_staff(array $owner,array $data): void {
    $email=email_input($data);
    $password=password_input($data);
    tx(function () use ($owner,$email,$password) {
        run('INSERT INTO admins(email,password_hash) VALUES (?,?)',[$email,password_hash($password,PASSWORD_DEFAULT)]);
        audit($owner,'create staff','Account '.db()->lastInsertId());
    });
}
function reset_token(string $email,?array $owner=null): string {
    return tx(function () use ($email,$owner) {
        $user=one('SELECT id FROM admins WHERE email=? FOR UPDATE',[$email]);
        if (!$user) fail('Account not found.',404);
        $token=bin2hex(random_bytes(32));
        run('DELETE FROM reset_tokens WHERE admin_id=?',[$user['id']]);
        run('INSERT INTO reset_tokens VALUES (?,?,DATE_ADD(NOW(),INTERVAL 30 MINUTE))',[hash('sha256',$token),$user['id']]);
        if ($owner) audit($owner,'issue password reset','Account '.$user['id']);
        return $token;
    });
}
function reset_password(array $data): void {
    rate('reset-ip',remote_ip(),15);
    $token=field($data,'token',64,true);
    $password=password_input($data);
    if (!ctype_xdigit($token)||strlen($token)!==64) fail('Reset code is invalid or expired.');
    tx(function () use ($token,$password) {
        $hash=hash('sha256',$token);
        $initial=one('SELECT admin_id FROM reset_tokens WHERE token_hash=? AND expires_at>NOW()',[$hash]);
        if (!$initial) fail('Reset code is invalid or expired.');
        one('SELECT id FROM admins WHERE id=? FOR UPDATE',[$initial['admin_id']]);
        $user=one('SELECT admin_id FROM reset_tokens WHERE token_hash=? AND expires_at>NOW() FOR UPDATE',[$hash]);
        if (!$user) fail('Reset code is invalid or expired.');
        run('UPDATE admins SET password_hash=?,session_version=session_version+1 WHERE id=?',[password_hash($password,PASSWORD_DEFAULT),$user['admin_id']]);
        run('DELETE FROM reset_tokens WHERE admin_id=?',[$user['admin_id']]);
        audit(['id'=>$user['admin_id']],'reset password','Account '.$user['admin_id']);
    });
}
