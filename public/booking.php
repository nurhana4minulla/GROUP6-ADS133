<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
require ROOT.'/app/booking.php';
require ROOT.'/app/content.php';
require ROOT.'/app/view.php';
require ROOT.'/app/public-layout.php';
$error=null;$slots=[];$company=[];
$confirmation=$_SESSION['booking_confirmation']??null;
unset($_SESSION['booking_confirmation']);
$data=method()==='POST'?$_POST:[];
try {
    if (method()==='POST') {
        check_csrf($data);
        rate('booking-ip',remote_ip(),20);
        $_SESSION['booking_confirmation']=submit_request($data);
        redirect('booking.php');
    }
} catch(Throwable $e) { $error=safe_error($e); http_response_code(status_code($e)); }
try { $slots=availability(); $company=array_column(content_list('company'),null,'slug'); }
catch(Throwable $e) { $error=safe_error($e); http_response_code(status_code($e)); }
function other_field(string $name,string $control,array $data): void {
    echo '<div data-other="'.e($control).'">';
    input('If other, please specify',$name,$data[$name]??'');
    echo '</div>';
}
page_start('Book an appointment');
public_header('booking');
?>
<main id="main" class="reading booking">
  <p class="page-context">In-person consultation · Zamboanga City</p>
  <h1>Book an appointment</h1>
  <?php notice($error); ?>
  <?php if ($confirmation): ?>
    <section class="confirmation" role="status">
      <p class="page-context">Request received</p><h2><?= e($confirmation['form_number']) ?></h2>
      <p>Requested appointment: <strong><?= e(human_date($confirmation['starts_at'])) ?> PHT</strong></p>
      <p>Received <?= e(human_date($confirmation['received_at'])) ?> PHT. Keep your form number. Our team will review your request and contact you once approved.</p>
      <a href="index.php">Return to the company website ↗</a>
    </section>
  <?php elseif (!$slots): ?>
    <p class="lede">There are no appointment times available online right now. <a href="index.php?page=contact">Contact our team</a> to discuss your project.</p>
  <?php else: ?>
  <p class="lede">Choose an available time for an in-person consultation in Zamboanga City. We’ll confirm the appointment after reviewing your request.</p>
  <form method="post" class="stack booking-form">
    <?= csrf_input() ?>
    <div class="honeypot" aria-hidden="true"><label>Website <input name="website" tabindex="-1" autocomplete="off"></label></div>
    <?php $dates=[]; foreach($slots as $slot) $dates[substr($slot['starts_at'],0,10)][]=$slot; ?>
    <fieldset class="form-section"><legend><span class="step-number" aria-hidden="true">01</span> Appointment time</legend>
      <p>Open appointments · Philippine time</p>
      <div class="slot-list">
      <?php foreach($dates as $day=>$times): ?>
        <div class="slot-day"><strong><?= e(date('D, M j',strtotime($day))) ?></strong><div>
        <?php foreach($times as $slot): ?>
          <label class="slot-time"><input type="radio" name="slot_id" value="<?= (int)$slot['id'] ?>" required<?= (string)($data['slot_id']??'')===(string)$slot['id']?' checked':'' ?>>
          <span><?= e(date('g:i A',strtotime($slot['starts_at']))) ?></span></label>
        <?php endforeach; ?></div></div>
      <?php endforeach; ?>
      </div>
    </fieldset>
    <fieldset class="form-section"><legend><span class="step-number" aria-hidden="true">02</span> Client information</legend><div class="form-grid">
      <?php select_field('I’m booking as','client_kind',['individual'=>'An individual','company'=>'A company'],$data['client_kind']??'individual',true);
      input('Client name or company','client_name',$data['client_name']??'','text',true);
      echo '<div data-condition="client_kind" data-equals="company">';
      input('Contact person (required for companies)','contact_person',$data['contact_person']??'');
      echo '</div>';
      input('Mobile number','mobile',$data['mobile']??'','tel',true,30);
      select_field('Preferred contact method','contact_method',CONTACT,$data['contact_method']??'',true);
      other_field('contact_other','contact_method',$data);
      input('Email (required if Email is selected)','email',$data['email']??'','email',false,254);
      input('Project site city or area','site_area',$data['site_area']??'','text',true); ?>
    </div></fieldset>
    <fieldset class="form-section"><legend><span class="step-number" aria-hidden="true">03</span> Project information</legend><div class="form-grid">
      <?php select_field('Project type','project_type',TYPES,$data['project_type']??'',true);
      other_field('project_other','project_type',$data);
      select_field('Nature of request','nature',NATURE,$data['nature']??'',true);
      other_field('nature_other','nature',$data);
      input('Project name (optional)','project_name',$data['project_name']??'','text',false,200); ?>
    </div><?php textarea('Tell us about the project (optional)','notes',$data['notes']??''); ?></fieldset>
    <details class="form-section" <?= array_intersect(array_keys($data),['pool_type','features','facilities','shape']) && $error?'open':'' ?>>
      <summary><span class="step-number" aria-hidden="true">04</span> Optional pool and site details</summary>
      <div class="form-grid">
        <?php select_field('Pool type','pool_type',POOL_TYPES,$data['pool_type']??''); other_field('pool_other','pool_type',$data);
        select_field('Pool shape','shape',SHAPES,$data['shape']??''); other_field('shape_other','shape',$data);
        foreach(['length_m'=>'Length (m)','width_m'=>'Width (m)','depth_m'=>'Depth (m)'] as $k=>$v) input($v,$k,$data[$k]??'','text',false,8); ?>
      </div>
      <?php check_group('Pool features','features',FEATURES,$data); other_field('feature_other','features[]',$data);
      check_group('Existing facilities','facilities',FACILITIES,$data); other_field('facility_other','facilities[]',$data); ?>
    </details>
    <p class="small text-secondary">We use the details you provide to review your request and contact you about your appointment and project.</p>
    <div><?php button('Submit appointment request ↗'); ?></div>
  </form>
  <?php endif; ?>
</main>
<?php public_footer($company); page_end(); ?>
