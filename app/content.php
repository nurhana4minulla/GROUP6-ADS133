<?php
declare(strict_types=1);
const CONTENT_KINDS=['company','service','portfolio'];

function content_list(string $kind, bool $includeHidden=false): array {
    $entries=rows('SELECT * FROM content WHERE kind=?'.($includeHidden?'':' AND published=1').' ORDER BY sort_order,id',[$kind]);
    $images=rows('SELECT i.* FROM content_images i JOIN content c ON c.id=i.content_id WHERE c.kind=?'.($includeHidden?'':' AND c.published=1').' ORDER BY i.sort_order,i.id',[$kind]);
    $groups=[];
    foreach($images as $image) $groups[$image['content_id']][]=$image;
    foreach($entries as &$entry) $entry['images']=$groups[$entry['id']]??[];
    return $entries;
}
function search_content(string $query, ?string $kind=null): array {
    $terms=preg_split('/\s+/u',$query,-1,PREG_SPLIT_NO_EMPTY);
    $pattern=static fn(string $term): string => strtr($term,['!'=>'!!','%'=>'!%','_'=>'!_']);
    $where=['c.published=1'];
    $params=[];
    if ($kind!==null) {
        $where[]='c.kind=?';
        $params[]=$kind;
    }
    foreach ($terms as $term) {
        $where[]="CONCAT_WS(' ',c.title,c.description,c.category,c.location) LIKE ? ESCAPE '!'";
        $params[]='%'.$pattern($term).'%';
    }
    $rank='';
    if ($terms) {
        $rank="CASE WHEN c.title=? THEN 0 WHEN c.title LIKE ? ESCAPE '!' THEN 1
            WHEN c.title LIKE ? ESCAPE '!' THEN 2 ELSE 3 END, ";
        array_push($params,$query,$pattern($query).'%','%'.$pattern($query).'%');
    }
    return rows("SELECT c.id,c.kind,c.slug,c.title,c.description,c.category,c.location,i.id AS image_id,i.path AS image_path
        FROM content c LEFT JOIN content_images i ON i.id=(
            SELECT id FROM content_images WHERE content_id=c.id ORDER BY sort_order,id LIMIT 1
        ) WHERE ".implode(' AND ',$where)." ORDER BY ".$rank."c.sort_order,c.id LIMIT 41",$params);
}
function next_portfolio(array $entry): ?array {
    return one("SELECT id,title,location FROM content WHERE kind='portfolio' AND published=1 AND id<>?
        ORDER BY (sort_order < ? OR (sort_order=? AND id<?)),sort_order,id LIMIT 1",
        [$entry['id'],$entry['sort_order'],$entry['sort_order'],$entry['id']]);
}
function content_entry(string $kind,int $id,bool $includeHidden=false): array {
    $entry=one('SELECT * FROM content WHERE kind=? AND id=?'.($includeHidden?'':' AND published=1'),[$kind,$id]);
    if (!$entry) fail('Content entry not found.',404);
    $entry['images']=rows('SELECT * FROM content_images WHERE content_id=? ORDER BY sort_order,id',[$id]);
    return $entry;
}
function save_content(array $user,string $kind,?int $id,array $data): int {
    return tx(function () use ($user,$kind,$id,$data) {
        if ($id) {
            $prior=one('SELECT * FROM content WHERE kind=? AND id=? FOR UPDATE',[$kind,$id]);
            if (!$prior) fail('Content entry not found.',404);
            $prior['published']=(string)$prior['published'];
            $data=array_replace($prior,$data);
        }
        $slug=field($data,'slug',100,true);
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D',$slug)) fail('Use lowercase letters, numbers and hyphens in the slug.');
        $title=field($data,'title',180,true);
        $description=field($data,'description',10000,true);
        $category=field($data,'category',100);
        $location=field($data,'location',180);
        $date=date_input($data,'project_date',false,false);
        $published=choice($data,'published',['0','1'])==='1';
        $sort=filter_var($data['sort_order']??0,FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>100000]]);
        if ($sort===false) fail('Enter a valid display order.');
        $values=[$slug,$title,$description,$category,$location,$date,(int)$published,$sort];
        if ($id) {
            run('UPDATE content SET slug=?,title=?,description=?,category=?,location=?,project_date=?,published=?,sort_order=? WHERE id=? AND kind=?',[...$values,$id,$kind]);
        } else {
            run('INSERT INTO content(slug,title,description,category,location,project_date,published,sort_order,kind) VALUES (?,?,?,?,?,?,?,?,?)',[...$values,$kind]);
            $id=(int)db()->lastInsertId();
        }
        audit($user,'save '.$kind,$kind.' '.$id);
        return $id;
    });
}
function delete_content(array $user,string $kind,int $id): void {
    $paths=tx(function () use ($user,$kind,$id) {
        if (!one('SELECT id FROM content WHERE kind=? AND id=? FOR UPDATE',[$kind,$id])) fail('Content entry not found.',404);
        $paths=array_column(rows('SELECT path FROM content_images WHERE content_id=?',[$id]),'path');
        run('DELETE FROM content WHERE id=?',[$id]);
        audit($user,'delete '.$kind,$kind.' '.$id);
        return $paths;
    });
    foreach ($paths as $path) remove_upload($path);
}
function remove_upload(string $path): void {
    if (!preg_match('/^[a-f0-9]{40}\.(jpg|png|webp)$/D',$path)) return;
    $file=ROOT.'/storage/'.$path;
    if (is_file($file) && !@unlink($file)) error_log('Could not remove CMS upload: '.$path);
}
function image_url(array $image): string {
    return str_starts_with($image['path'],'assets/')?$image['path']:'media.php?id='.$image['id'];
}
function add_image(array $user,int $id,array $data,array $file): void {
    $alt=field($data,'alt',180,true);
    if (($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK || $file['size']>8*1024*1024 || !is_uploaded_file($file['tmp_name'])) fail('Choose a JPEG, PNG or WebP image under 8 MB.');
    $info=@getimagesize($file['tmp_name']);
    $extensions=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    if (!$info || !isset($extensions[$info['mime']]) || max($info[0],$info[1])>8000) fail('Choose a valid JPEG, PNG or WebP image up to 8000 pixels.');
    $dir=ROOT.'/storage';
    if (!is_dir($dir) && !mkdir($dir,0700,true) && !is_dir($dir)) fail('Image storage is unavailable.',500);
    $name=bin2hex(random_bytes(20)).'.'.$extensions[$info['mime']];
    if (!move_uploaded_file($file['tmp_name'],$dir.'/'.$name)) fail('Could not store the image.',500);
    try {
        tx(function () use ($user,$id,$alt,$name) {
            if (!one('SELECT id FROM content WHERE id=? FOR UPDATE',[$id])) fail('Content entry not found.',404);
            $order=(int)one('SELECT COALESCE(MAX(sort_order),0) AS n FROM content_images WHERE content_id=?',[$id])['n']+1;
            run('INSERT INTO content_images(content_id,path,alt,sort_order) VALUES (?,?,?,?)',[$id,$name,$alt,$order]);
            audit($user,'upload image','Content '.$id);
        });
    } catch(Throwable $error) { unlink($dir.'/'.$name); throw $error; }
}
function delete_image(array $user,int $id,int $content): void {
    $path=tx(function () use ($user,$id,$content) {
        if (!one('SELECT id FROM content WHERE id=? FOR UPDATE',[$content])) fail('Content entry not found.',404);
        $image=one('SELECT path FROM content_images WHERE id=? AND content_id=?',[$id,$content]);
        if (!$image) fail('Image not found.',404);
        run('DELETE FROM content_images WHERE id=?',[$id]);
        audit($user,'remove image','Image '.$id);
        return $image['path'];
    });
    remove_upload($path);
}
