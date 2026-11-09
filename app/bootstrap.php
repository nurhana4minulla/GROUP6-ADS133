<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Manila');
ini_set('display_errors', '0');
define('ROOT', dirname(__DIR__));

function config(): array {
    static $config;
    if ($config===null) {
        $config=array_replace(require ROOT.'/config.example.php',
            is_file(ROOT.'/config.local.php') ? require ROOT.'/config.local.php' : []);
        $override=getenv('PRIME_POOL_DATABASE');
        if ($override!==false && preg_match('/^[a-zA-Z0-9_]+$/D',$override)) $config['database']=$override;
    }
    return $config;
}

function db(): PDO {
    static $pdo;
    $c = config();
    return $pdo ??= new PDO("mysql:host={$c['host']};port={$c['port']};dbname={$c['database']};charset=utf8mb4",
        $c['username'], $c['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET SESSION time_zone = '+08:00', foreign_key_checks = 1,
            sql_mode = CONCAT_WS(',', NULLIF(@@SESSION.sql_mode, ''), 'STRICT_TRANS_TABLES')"]);
}

function run(string $sql, array $params = []): PDOStatement {
    $statement = db()->prepare($sql);
    $statement->execute($params);
    return $statement;
}
function rows(string $sql, array $params = []): array { return run($sql, $params)->fetchAll(); }
function one(string $sql, array $params = []): ?array { return run($sql, $params)->fetch() ?: null; }
function tx(callable $work): mixed {
    $pdo = db();
    $pdo->beginTransaction();
    try { $result = $work(); $pdo->commit(); return $result; }
    catch (Throwable $error) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $error; }
}

final class Problem extends RuntimeException {}
function fail(string $message, int $status = 422): never { throw new Problem($message, $status); }
function e(mixed $value): string { return htmlspecialchars(is_scalar($value) ? (string)$value : '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function field(array $data, string $key, int $max = 160, bool $required = false): ?string {
    $value = $data[$key] ?? '';
    if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) fail("Invalid ".str_replace('_',' ',$key).".");
    $value = trim($value);
    if (mb_strlen($value) > $max || ($required && $value === '')) fail("Check the ".str_replace('_',' ',$key)." field.");
    return $value === '' ? null : $value;
}
function choice(array $data, string $key, array $choices, bool $required = true): ?string {
    $value = field($data, $key, 100, $required);
    if ($value !== null && !in_array($value, $choices, true)) fail("Choose a valid ".str_replace('_',' ',$key).".");
    return $value;
}
function id(mixed $value): int {
    if ((!is_int($value) && !is_string($value)) || !filter_var($value, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1,'max_range'=>2147483647]])) fail('Invalid record identifier.');
    return (int)$value;
}
function date_input(array $data, string $key, bool $time = false, bool $required = true): ?string {
    $v = field($data,$key,19,$required);
    if ($v === null) return null;
    $format = $time ? 'Y-m-d\\TH:i' : 'Y-m-d';
    $date = DateTimeImmutable::createFromFormat('!'.$format,$v);
    if (!$date || $date->format($format) !== $v || $date->format('Y') < '2000') fail('Enter a valid date and time.');
    return $date->format($time ? 'Y-m-d H:i:s' : 'Y-m-d');
}
function human_date(string $date): string { return (new DateTimeImmutable($date))->format('M j, Y · g:i A'); }
function form_no(array $request): string { return sprintf('PSA-%s-%04d',substr($request['received_at'],0,4),$request['id']); }
function redirect(string $to): never { header('Location: '.$to, true, 303); exit; }
function method(): string { return $_SERVER['REQUEST_METHOD'] ?? 'GET'; }
function flash(string $message): void { $_SESSION['notice'] = $message; }

if (PHP_SAPI !== 'cli') {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
    ini_set('session.use_strict_mode','1');
    // Browser cookies are shared across localhost ports. Keep each database's sessions separate.
    $sessionConfig=config();
    $sessionScope=implode(':',[$sessionConfig['host'],$sessionConfig['port'],$sessionConfig['database']]);
    session_name('prime_pool_'.substr(hash('sha256',$sessionScope),0,20));
    session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off','samesite'=>'Lax']);
    session_start();
}
function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function csrf_input(): string { return '<input type="hidden" name="csrf" value="'.e(csrf()).'">'; }
function check_csrf(array $data): void {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $data['csrf'] ?? '';
    if (!is_string($token) || !hash_equals(csrf(),$token)) fail('Your session changed. Reload this page and try again.',403);
}
function admin(): ?array {
    if (empty($_SESSION['admin_id'])) return null;
    return one('SELECT id,email,role FROM admins WHERE id=? AND session_version=?',[$_SESSION['admin_id'],$_SESSION['version']]);
}
function require_admin(bool $owner = false): array {
    $user = admin();
    if (!$user) fail('Sign in to continue.',401);
    if ($owner && $user['role'] !== 'owner') fail('This action requires an owner account.',403);
    return $user;
}
function rate(string $scope, string $subject, int $limit): void {
    $bucket = intdiv(time(),900);
    $key = hash('sha256',$subject);
    $result = run('INSERT INTO rate_limits VALUES (?,?,?,1) ON DUPLICATE KEY UPDATE hits=IF(hits < ?, hits+1, hits)',
        [$scope,$key,$bucket,$limit]);
    if (!$result->rowCount()) fail('Too many attempts. Please try again in 15 minutes.',429);
}
function remote_ip(): string { return $_SERVER['REMOTE_ADDR'] ?? 'local'; }
function audit(array $user, string $action, string $target): void {
    run('INSERT INTO activity(admin_id,action,target) VALUES (?,?,?)',[$user['id'],$action,$target]);
}
function safe_error(Throwable $error): string {
    if ($error instanceof Problem) return $error->getMessage();
    if ($error instanceof PDOException && ($error->errorInfo[1] ?? null) === 1062) return 'That record already exists or was just reserved.';
    error_log((string)$error);
    return 'We could not complete the request. Try again or contact the administrator.';
}
function status_code(Throwable $error): int {
    return $error instanceof Problem ? $error->getCode() :
        ($error instanceof PDOException && ($error->errorInfo[1] ?? null) === 1062 ? 409 : 500);
}
