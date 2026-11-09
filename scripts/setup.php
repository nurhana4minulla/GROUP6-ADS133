<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
require dirname(__DIR__).'/app/bootstrap.php';

$demoAccounts=($argv[1]??'')==='--demo-accounts';
$email=$argv[1]??'';
$demo=$demoAccounts || in_array('--demo',$argv,true);
if (!$demoAccounts && !filter_var($email,FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR,"Usage: php scripts/setup.php admin@example.test [--demo] or php scripts/setup.php --demo-accounts\n");
    exit(1);
}
$c=config();
if (!preg_match('/^[a-zA-Z0-9_]+$/D',$c['database'])) throw new RuntimeException('Invalid database name.');
if ($demoAccounts && ($c['message_mode']!=='demo' ||
    !in_array($c['host'],['127.0.0.1','localhost','::1'],true) ||
    !preg_match('/^prime_pool_ads_(?:demo|qa)(?:_[a-zA-Z0-9_]+)?$/D',$c['database']))) {
    fwrite(STDERR,"Demo accounts require a local prime_pool_ads_demo or prime_pool_ads_qa database with demo messaging.\n");
    exit(1);
}
$server = new PDO("mysql:host={$c['host']};port={$c['port']};charset=utf8mb4",$c['username'],$c['password']);
$server->exec("CREATE DATABASE IF NOT EXISTS `{$c['database']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
db()->exec(file_get_contents(ROOT.'/database/schema.sql'));
$lock='prime_pool_schema_'.substr(hash('sha256',$c['database']),0,24);
if ((int)one('SELECT GET_LOCK(?,10) AS acquired',[$lock])['acquired']!==1)
    throw new RuntimeException('Another setup is applying database changes. Try again shortly.');
try {
    foreach (require ROOT.'/database/integrity.php' as $table=>$checks) {
        foreach ($checks as $name=>$condition) {
            $exists=one("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
                WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=? AND CONSTRAINT_NAME=? AND CONSTRAINT_TYPE='CHECK'",[$table,$name]);
            if (!$exists) db()->exec("ALTER TABLE `$table` ADD CONSTRAINT `$name` CHECK ($condition)");
        }
    }
} finally {
    one('SELECT RELEASE_LOCK(?) AS released',[$lock]);
}
tx(function () {
    $seed=run("INSERT IGNORE INTO setup_state VALUES ('initial-company-content')");
    if ($seed->rowCount())
        foreach (['company','portfolio'] as $file) db()->exec(file_get_contents(ROOT.'/database/'.$file.'.sql'));
});
$accounts=$demoAccounts?['demo.owner@example.test'=>'owner','demo.staff@example.test'=>'staff']:[$email=>'owner'];
try {
    $messages=tx(function () use ($accounts,$demoAccounts) {
        one("SELECT name FROM setup_state WHERE name='initial-company-content' FOR UPDATE");
        if ($demoAccounts) run("INSERT IGNORE INTO setup_state VALUES ('demo-accounts')");
        $messages=[];
        foreach ($accounts as $email=>$role) {
            $existing=one('SELECT role FROM admins WHERE email=?',[$email]);
            if ($existing) {
                if ($existing['role']!==$role) fail("The existing $email account has a different role.");
                $messages[]="Existing $role: $email. Use the password already set or run scripts/reset.php.";
                continue;
            }
            if (!$demoAccounts && one("SELECT id FROM admins WHERE role='owner' LIMIT 1"))
                fail('An owner already exists. Use the Accounts screen to add staff.');
            $password=bin2hex(random_bytes(10));
            run('INSERT INTO admins(email,password_hash,role) VALUES (?,?,?)',[$email,password_hash($password,PASSWORD_DEFAULT),$role]);
            $messages[]=ucfirst($role).": $email\nGenerated password: $password";
        }
        return $messages;
    });
    foreach ($messages as $message) echo $message.PHP_EOL;
} catch (Problem $error) {
    fwrite(STDERR,$error->getMessage().PHP_EOL);
    exit(1);
}
if ($demo) {
    for ($day=1;$day<=12;$day++) {
        $date = new DateTimeImmutable("+$day days");
        if ((int)$date->format('N') > 5) continue;
        foreach (['09:00:00','11:00:00','14:00:00'] as $time)
            run('INSERT IGNORE INTO slots(starts_at) VALUES (?)',[$date->format('Y-m-d').' '.$time]);
    }
}
echo "Database and company content are ready.\n";
