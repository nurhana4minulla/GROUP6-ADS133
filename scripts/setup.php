<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
require dirname(__DIR__).'/app/bootstrap.php';

$email = $argv[1] ?? '';
$demo = in_array('--demo',$argv,true);
if (!filter_var($email,FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR,"Usage: php scripts/setup.php admin@example.test [--demo]\n");
    exit(1);
}
$c = config();
if (!preg_match('/^[a-zA-Z0-9_]+$/',$c['database'])) throw new RuntimeException('Invalid database name.');
$server = new PDO("mysql:host={$c['host']};port={$c['port']};charset=utf8mb4",$c['username'],$c['password']);
$server->exec("CREATE DATABASE IF NOT EXISTS `{$c['database']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
db()->exec(file_get_contents(ROOT.'/database/schema.sql'));
tx(function () {
    $seed=run("INSERT IGNORE INTO setup_state VALUES ('initial-company-content')");
    if ($seed->rowCount())
        foreach (['company','portfolio'] as $file) db()->exec(file_get_contents(ROOT.'/database/'.$file.'.sql'));
});
if (!one('SELECT id FROM admins WHERE email=?',[$email])) {
    if (one("SELECT id FROM admins WHERE role='owner' LIMIT 1")) {
        fwrite(STDERR,"An owner already exists. Use the Accounts screen to add staff.\n");
        exit(1);
    }
    $password = bin2hex(random_bytes(10));
    run("INSERT INTO admins(email,password_hash,role) VALUES (?,?,'owner')",[$email,password_hash($password,PASSWORD_DEFAULT)]);
    echo "Owner: $email\nGenerated password: $password\n";
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
