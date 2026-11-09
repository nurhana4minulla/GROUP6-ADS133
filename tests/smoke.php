<?php
declare(strict_types=1);
$base = $argv[1] ?? 'http://127.0.0.1:8096';
function expect(bool $ok, string $message): void {
    if (!$ok) { fwrite(STDERR, "FAIL: $message\n"); exit(1); }
}
$home = @file_get_contents("$base/index.php");
expect(is_string($home) && str_contains($home, 'Prime Pool Peninsula'), 'public home loads');
$portfolio = json_decode((string) @file_get_contents("$base/api.php?resource=portfolio"), true);
expect(count($portfolio['data'] ?? []) >= 10, 'published portfolio API uses seeded data');
$admin = @file_get_contents("$base/api.php?resource=requests");
expect($admin === false && str_contains($http_response_header[0] ?? '', '401'), 'private API requires login');
echo "Public home, portfolio API, and admin access checks passed.\n";
