<?php
declare(strict_types=1);
function cms_screen(): void {
    $kind=choice($_GET,'kind',CONTENT_KINDS,false)??'portfolio';
    $id=isset($_GET['id'])?id($_GET['id']):null;
    $entry=$id?content_entry($kind,$id,true):[];
    $entries=content_list($kind,true);
    admin_title('Public website / Content management','Website CMS');
    ?>
    <div class="admin-actions mb-3">
    <?php foreach(['portfolio'=>'Portfolio','service'=>'Services','company'=>'Company information'] as $key=>$label): ?>
      <a class="btn <?= $kind===$key?'btn-primary':'btn-outline-primary' ?>" href="admin.php?tab=cms&amp;kind=<?= e($key) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
    </div>
    <div class="split">
      <section class="panel table-responsive">
        <h2><?= e(ucfirst($kind)) ?> entries</h2>
        <table class="table"><thead><tr><th scope="col">Title</th><th scope="col">State</th><th scope="col">Order</th></tr></thead>
        <tbody><?php foreach($entries as $item): ?>
          <tr><td><a href="admin.php?tab=cms&amp;kind=<?= e($kind) ?>&amp;id=<?= (int)$item['id'] ?>"><?= e($item['title']) ?></a></td>
          <td><?php badge($item['published']?'published':'draft'); ?></td><td><?= e($item['sort_order']) ?></td></tr>
        <?php endforeach; ?></tbody></table>
        <a class="btn btn-outline-primary" href="admin.php?tab=cms&amp;kind=<?= e($kind) ?>">New entry</a>
      </section>
      <div class="stack">
      <section class="panel"><h2><?= $id?'Edit entry':'Create entry' ?></h2>
        <?php form_start('content',$id); ?>
        <input type="hidden" name="kind" value="<?= e($kind) ?>">
        <?php input('Title','title',$entry['title']??'','text',true,180);
        input('Slug (lowercase, hyphens allowed)','slug',$entry['slug']??'','text',true,100);
        textarea('Description','description',$entry['description']??'',10000,true);
        if($kind==='portfolio') {
            input('Category','category',$entry['category']??'','text',false,100);
            input('Location','location',$entry['location']??'','text',false,180);
            input('Project date (if known)','project_date',$entry['project_date']??'','date');
        }
        input('Display order','sort_order',$entry['sort_order']??0,'number',true);
        select_field('Publication','published',['1'=>'Published','0'=>'Unpublished'],$entry['published']??0,true);
        button('Save entry'); ?></form>
        <?php if($id): ?>
          <form method="post" class="mt-3" data-cms-api><?= csrf_input() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="kind" value="<?= e($kind) ?>">
            <button class="btn btn-outline-danger" name="action" value="delete_content" data-confirm="Delete this entry and remove its images from the website?">Delete entry</button>
          </form>
        <?php endif; ?>
      </section>
      <?php if($id): ?>
      <section class="panel"><h2>Images</h2>
        <div class="admin-images mb-3"><?php foreach($entry['images'] as $image): ?>
          <div><img src="<?= e(image_url($image)) ?>" alt="<?= e($image['alt']) ?>">
            <p class="small my-1"><?= e($image['alt']) ?></p>
            <?php form_start('delete_image',$id); ?>
              <input type="hidden" name="kind" value="<?= e($kind) ?>"><input type="hidden" name="image_id" value="<?= (int)$image['id'] ?>">
              <?php button('Remove','btn-sm btn-outline-danger'); ?>
            </form>
          </div>
        <?php endforeach; ?></div>
        <form method="post" enctype="multipart/form-data" class="stack">
          <?= csrf_input() ?><input type="hidden" name="action" value="image"><input type="hidden" name="kind" value="<?= e($kind) ?>"><input type="hidden" name="id" value="<?= $id ?>">
          <label class="field"><span>Image file (JPEG, PNG, WebP, up to 8 MB)</span><input class="form-control" type="file" name="image" accept="image/jpeg,image/png,image/webp" required></label>
          <?php input('Alternative text','alt','','text',true,180); button('Upload image'); ?>
        </form>
      </section>
      <?php endif; ?>
      </div>
    </div>
    <?php
}
function messages_screen(): void {
    $templates=rows('SELECT * FROM message_templates ORDER BY event');
    $messages=rows('SELECT m.*,r.id AS form_id,r.received_at FROM messages m JOIN requests r ON r.id=m.request_id ORDER BY m.id DESC LIMIT 200');
    admin_title('Communication / SMS and Viber','Messages');
    ?>
    <p>Delivery mode <strong><?= config()['message_mode']==='live'?'Live gateway':'Demo log' ?></strong></p>
    <div class="split">
      <section class="panel table-responsive"><h2>Send log</h2>
      <table class="table"><thead><tr><th scope="col">Form</th><th scope="col">Message</th><th scope="col">Due</th><th scope="col">State</th></tr></thead><tbody>
      <?php foreach($messages as $m): ?>
        <tr><td><a href="admin.php?tab=request&amp;id=<?= (int)$m['form_id'] ?>"><?= e(form_no(['id'=>$m['form_id'],'received_at'=>$m['received_at']])) ?></a></td>
        <td><strong><?= e(ucfirst(str_replace('_',' ',$m['event']))) ?> · <?= e(strtoupper($m['channel'])) ?></strong><br><?= e($m['body']) ?></td>
        <td><?= e(human_date($m['due_at'])) ?></td><td><?php badge($m['status']); ?>
        <?php if($m['processed_at']): ?><br><small><?= e(human_date($m['processed_at'])) ?></small><?php endif; ?>
        <?php if($m['error']): ?><br><small><?= e($m['error']) ?></small><?php endif; ?></td></tr>
      <?php endforeach; ?>
      </tbody></table><?php if(!$messages): ?><p>No messages have been queued.</p><?php endif; ?></section>
      <section class="panel"><h2>Message templates</h2>
      <p class="small">Available fields <code>{form}</code>, <code>{appointment}</code>, and <code>{visit}</code> for site visits.</p>
      <?php foreach($templates as $t): ?>
        <?php form_start('template'); ?><input type="hidden" name="event" value="<?= e($t['event']) ?>">
        <?php textarea(ucfirst(str_replace('_',' ',$t['event'])),'body',$t['body'],500,true); button('Save template','btn-sm btn-outline-primary'); ?>
        </form><hr>
      <?php endforeach; ?>
      </section>
    </div>
    <?php
}
function activity_screen(): void {
    $list=rows('SELECT a.*,u.email FROM activity a LEFT JOIN admins u ON u.id=a.admin_id ORDER BY a.id DESC LIMIT 200');
    admin_title('Operations / Audit trail','Activity');
    ?>
    <section class="panel table-responsive"><table class="table"><thead><tr><th scope="col">Time</th><th scope="col">Account</th><th scope="col">Action</th><th scope="col">Record</th></tr></thead>
    <tbody><?php foreach($list as $a): ?><tr><td><?= e(human_date($a['happened_at'])) ?></td><td><?= e($a['email']??'System') ?></td><td><?= e($a['action']) ?></td><td><?= e($a['target']) ?></td></tr><?php endforeach; ?></tbody>
    </table></section>
    <?php
}
function accounts_screen(array $user,?string $code): void {
    $accounts=rows('SELECT email,role FROM admins ORDER BY role,email');
    admin_title('Access / Owner controls','Accounts');
    ?>
    <div class="split">
      <section class="panel"><h2>Staff accounts</h2><ul><?php foreach($accounts as $account): ?><li><?= e($account['email']) ?> · <?= e($account['role']) ?></li><?php endforeach; ?></ul></section>
      <div class="stack"><section class="panel"><h2>Add staff</h2>
        <?php form_start('staff'); input('Email','email','','email',true,254); input('Initial password (12–72 characters)','password','','password',true,72); button('Create account'); ?></form>
      </section>
      <section class="panel"><h2>Issue password reset code</h2><p>Give the code to the account holder privately. It expires in 30 minutes.</p>
        <?php if($code): ?><p class="alert alert-success"><code><?= e($code) ?></code></p><?php endif; ?>
        <?php form_start('issue_reset'); input('Account email','email','','email',true,254); button('Generate code'); ?></form>
      </section></div>
    </div>
    <?php
}
