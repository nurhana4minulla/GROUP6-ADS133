<?php
require dirname(__DIR__).'/app/bootstrap.php';
try {
    $image=one('SELECT i.path,c.published FROM content_images i JOIN content c ON c.id=i.content_id WHERE i.id=?',[id($_GET['id']??null)]);
    if (!$image || (!$image['published'] && !admin()) || !preg_match('/^[a-f0-9]{40}\.(jpg|png|webp)$/D',$image['path'],$match)) fail('Image not found.',404);
    $file=ROOT.'/storage/'.$image['path'];
    if (!is_file($file)) fail('Image not found.',404);
    header('Cache-Control: private, no-store');
    header('Content-Type: image/'.($match[1]==='jpg'?'jpeg':$match[1]));
    header('Content-Length: '.filesize($file));
    readfile($file);
} catch(Throwable $error) { http_response_code(status_code($error)); }
