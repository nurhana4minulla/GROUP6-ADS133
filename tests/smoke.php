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
$search = @file_get_contents("$base/index.php?page=search&q=maintenance&type=service&fragment=results");
expect(is_string($search) && str_contains($search, 'Pool maintenance') && !str_contains($search, '<html'), 'filtered live search renders an HTML fragment');
$projects = @file_get_contents("$base/index.php?page=search&q=La%20Paz&type=portfolio");
expect(is_string($projects) && str_contains($projects, 'search-hit-illustrated') && str_contains($projects, 'La Paz Infinity Pool'), 'published project search includes thumbnails');
echo "Public home, portfolio API, search, and admin access checks passed.\n";
