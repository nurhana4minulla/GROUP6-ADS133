<?php
if (PHP_SAPI !== 'cli') exit;
require dirname(__DIR__).'/app/bootstrap.php';
require ROOT.'/app/accounts.php';
try { echo "One-time code (30 minutes): ".reset_token(email_input(['email'=>$argv[1]??''])).PHP_EOL; }
catch(Throwable $error) { fwrite(STDERR,safe_error($error).PHP_EOL); exit(1); }
