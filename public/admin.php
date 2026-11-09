<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
foreach (['booking','content','accounts','view','admin-views'] as $module) require ROOT.'/app/'.$module.'.php';
header('Cache-Control: no-store');
$tab='dashboard';$user=null;$error=null;$code=null;
$tabs=['dashboard'=>'Dashboard','requests'=>'Requests','slots'=>'Availability','projects'=>'Projects','cms'=>'Website CMS','messages'=>'Messages','activity'=>'Activity','accounts'=>'Accounts'];
try {
    $tab=choice($_GET,'tab',[...array_keys($tabs),'request','reset','print'],false)??'dashboard';
    $user=admin();
    if ($tab==='accounts' && $user && $user['role']!=='owner') {
        $tab='dashboard';
        fail('This action requires an owner account.',403);
    }
    if (method()==='POST') {
        check_csrf($_POST);
        $action=field($_POST,'action',40,true);
        if ($action==='login') { login($_POST); redirect('admin.php'); }
        if ($action==='reset') { reset_password($_POST); $_SESSION=[]; session_regenerate_id(true); flash('Password updated. Sign in with your new password.'); redirect('admin.php'); }
        $user=require_admin();
        if ($action==='logout') {
            audit($user,'sign out','Account '.$user['id']);
            $_SESSION=[]; session_regenerate_id(true); redirect('admin.php');
        }
        $record=isset($_POST['id'])?id($_POST['id']):null;
        $target='admin.php?tab='.$tab;
        switch($action) {
            case 'slot':
                save_slot($user,$_POST,$record); $target='admin.php?tab=slots'; break;
            case 'decision':
                $record=id($record); decision($user,$record,choice($_POST,'decision',['approved','declined']));
                $target='admin.php?tab=request&id='.$record; break;
            case 'meeting':
                $record=id($record); record_meeting($user,$record,$_POST);
                $target='admin.php?tab=request&id='.$record; break;
            case 'project':
                $record=id($record); save_project($user,$record,$_POST);
                $target='admin.php?tab=request&id='.$record; break;
            case 'content':
            case 'delete_content':
            case 'image':
            case 'delete_image':
                $kind=choice($_POST,'kind',CONTENT_KINDS);
                if ($action==='content') $record=save_content($user,$kind,$record,$_POST);
                else {
                    $record=id($record);
                    content_entry($kind,$record,true);
                    if ($action==='delete_content') { delete_content($user,$kind,$record); $record=null; }
                    elseif ($action==='image') add_image($user,$record,$_POST,$_FILES['image']??[]);
                    else delete_image($user,id($_POST['image_id']??null),$record);
                }
                $target='admin.php?tab=cms&kind='.$kind.($record?'&id='.$record:''); break;
            case 'template':
                $event=choice($_POST,'event',EVENTS);
                $body=field($_POST,'body',500,true);
                if ($event==='confirmation' && !str_contains($body,'{form}')) fail('Include {form} in the confirmation template.');
                if (mb_strlen(strtr($body,['{form}'=>str_repeat('X',19),'{appointment}'=>str_repeat('X',32),'{visit}'=>str_repeat('X',32)]))>600)
                    fail('The template is too long once its placeholders are filled.');
                tx(function () use ($event,$body,$user) {
                    run('UPDATE message_templates SET body=? WHERE event=?',[$body,$event]);
                    audit($user,'edit template',$event);
                });
                $target='admin.php?tab=messages'; break;
            case 'staff':
                create_staff(require_admin(true),$_POST); $target='admin.php?tab=accounts'; break;
            case 'issue_reset':
                $code=reset_token(email_input($_POST),require_admin(true)); break;
            case 'print':
                break;
            default: fail('Unknown action.',400);
        }
        if (!in_array($action,['print','issue_reset'],true)) { flash('Changes saved.'); redirect($target); }
    }
} catch(Throwable $e) { $error=safe_error($e); http_response_code(status_code($e)); }
if ($tab==='print' && $user) {
    if (!$error) {
        try {
            $ids=$_POST['ids']??(isset($_GET['id'])?[$_GET['id']]:[]);
            if (!is_array($ids)||!count($ids)||count($ids)>50) fail('Select between 1 and 50 request forms.');
            $print=array_map('request_detail',array_unique(array_map('id',$ids)));
            $company=array_column(content_list('company',true),null,'slug');
            render_print($print,$company);
            exit;
        } catch(Throwable $e) { $error=safe_error($e); http_response_code(status_code($e)); }
    }
    $tab='requests';
}
page_start($tab==='reset'?'Reset password':($user?($tabs[$tab]??'Request details'):'Staff sign-in'),'admin');
?>
<header class="admin-header">
  <a class="wordmark" href="<?= $user?'admin.php':'index.php' ?>"><img src="assets/brand.png" width="40" height="40" alt=""> Prime Pool / Operations</a>
  <?php if($user): ?>
  <nav aria-label="Staff navigation">
    <?php foreach($tabs as $key=>$label): if($key==='accounts' && $user['role']!=='owner') continue; ?>
      <a href="admin.php?tab=<?= e($key) ?>"<?= $tab===$key?' aria-current="page"':'' ?>><?= e($label) ?></a>
    <?php endforeach; ?>
  </nav>
  <form method="post" action="admin.php"><?= csrf_input() ?><button class="btn btn-sm btn-outline-secondary" name="action" value="logout">Sign out</button></form>
  <?php else: ?><a href="index.php">Company website ↗</a><?php endif; ?>
</header>
<main id="main" class="admin-main">
<?php notice($error); ?>
<?php if ($tab==='reset' || !$user): ?>
  <section class="panel admin-login">
  <?php if ($tab==='reset'): ?>
    <p class="eyebrow">Staff access</p><h1>Reset password</h1>
    <p>Enter the one-time code issued by your administrator and choose a new password.</p>
    <?php form_start('reset'); input('One-time code','token','','text',true,64); input('New password (12–72 characters)','password','','password',true,72); button('Update password'); ?></form>
    <a class="mt-3 d-inline-block" href="admin.php">Back to sign-in</a>
  <?php else: ?>
    <p class="eyebrow">Staff access</p><h1>Sign in</h1>
    <?php form_start('login'); input('Email','email',$_POST['email']??'','email',true,254); input('Password','password','','password',true,72); button('Sign in'); ?></form>
    <a class="mt-3 d-inline-block" href="admin.php?tab=reset">Use a password reset code</a>
  <?php endif; ?>
  </section>
<?php else: ?>
  <?php try {
      match($tab) {
          'dashboard'=>dashboard(),
          'requests'=>requests_screen(),
          'request'=>request_screen(id($_GET['id']??null)),
          'slots'=>slots_screen(),
          'projects'=>projects_screen(),
          'cms'=>cms_screen(),
          'messages'=>messages_screen(),
          'activity'=>activity_screen(),
          'accounts'=>accounts_screen(require_admin(true),$code),
          default=>null
      };
  } catch(Throwable $e) { notice(safe_error($e)); http_response_code(status_code($e)); } ?>
<?php endif; ?>
</main>
<?php page_end(); ?>
