<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
require ROOT.'/app/content.php';
require ROOT.'/app/view.php';
$page='home'; $error=null; $company=[]; $projects=[]; $services=[]; $project=null; $category=null;
try {
    $page=choice($_GET,'page',['home','about','services','projects','project','contact'],false)??'home';
    $company=array_column(content_list('company'),null,'slug');
    if (in_array($page,['home','projects'],true)) $projects=content_list('portfolio');
    if ($page==='services') $services=content_list('service');
    if ($page==='projects') $category=field($_GET,'category',100);
    if ($page==='project') $project=content_entry('portfolio',id($_GET['id']??null));
} catch(Throwable $e) { $error=safe_error($e); http_response_code(status_code($e)); }
require ROOT.'/app/public-layout.php';
page_start($project['title']??($page==='home'?'Pool design and construction':ucfirst($page)));
public_header($page);
?>
<main id="main">
<?php if ($error): ?>
<section class="reading"><h1>Page unavailable</h1><?php notice($error); ?></section>
<?php elseif ($page==='home'): ?>
<section class="intro">
  <p class="eyebrow"><?= e($company['hero']['description']??'') ?></p>
  <h1><?= e($company['hero']['title']??'Prime Pool Peninsula') ?></h1>
  <a class="text-arrow" href="index.php?page=about">About the company <span aria-hidden="true">↗</span></a>
</section>
<?php
$bySlug=array_column($projects,null,'slug');
$feature=array_values(array_filter([$bySlug['lapaz']??null,$bySlug['copacabana']??null,$bySlug['marj-infinity']??null,$bySlug['filinvest']??null]));
if (count($feature)<4) $feature=array_slice($projects,0,4);
?>
<?php if ($feature): ?>
<section class="portfolio-section" aria-label="Selected projects">
  <div class="section-line"><span>01 / Selected projects</span><a href="index.php?page=projects">Project archive ↗</a></div>
  <div class="work-grid"><?php foreach ($feature as $work) work_card($work); ?></div>
</section>
<?php endif; ?>
<section class="end-cta">
  <p class="eyebrow">02 / Your project</p>
  <h2>Let’s talk about what you want to build.</h2>
  <a class="text-arrow" href="booking.php">Book an appointment <span aria-hidden="true">↗</span></a>
</section>
<?php elseif ($page==='about'): ?>
<section class="reading">
  <p class="eyebrow">The company / Established 2021</p>
  <h1><?= e($company['about']['title']??'About') ?></h1>
  <p class="lede"><?= e($company['about']['description']??'') ?></p>
  <?php foreach($company as $entry): if (!in_array($entry['slug'],['hero','about','contact','email','phone'],true)): ?>
    <article class="line-item"><h2><?= e($entry['title']) ?></h2><p><?= nl2br(e($entry['description'])) ?></p></article>
  <?php endif; endforeach; ?>
</section>
<?php elseif ($page==='services'): ?>
<section class="reading"><p class="eyebrow">What we do</p><h1>Services</h1>
<?php foreach ($services as $i=>$service): ?>
  <article class="line-item"><p class="eyebrow"><?= sprintf('%02d',$i+1) ?></p><h2><?= e($service['title']) ?></h2><p><?= nl2br(e($service['description'])) ?></p>
  <?php if($service['images']): ?><div class="service-gallery"><?php foreach($service['images'] as $image): ?><a href="<?= e(image_url($image)) ?>"><img src="<?= e(image_url($image)) ?>" alt="<?= e($image['alt']) ?>" loading="lazy"></a><?php endforeach; ?></div><?php endif; ?></article>
<?php endforeach; ?>
<a class="text-arrow" href="booking.php">Discuss your project ↗</a></section>
<?php elseif ($page==='projects'): ?>
<section class="reading archive-intro"><p class="eyebrow">Prime Pool Peninsula</p><h1>Project archive</h1><p class="lede">Pools, resorts and engineering projects across Mindanao.</p></section>
<?php
$categories=array_values(array_unique(array_filter(array_column($projects,'category'))));
sort($categories);
$filtered=$category ? array_values(array_filter($projects,static fn($work)=>$work['category']===$category)) : $projects;
?>
<form class="archive-filter admin-actions" action="index.php" method="get" data-archive-filter>
  <input type="hidden" name="page" value="projects">
  <label for="category">Category</label>
  <select class="form-select" name="category" id="category"><option value="">All projects</option>
    <?php foreach($categories as $option): ?><option<?= $option===$category?' selected':'' ?>><?= e($option) ?></option><?php endforeach; ?>
  </select>
  <button class="btn btn-outline-primary" type="submit">Filter</button>
  <span class="small" aria-live="polite" data-archive-count><?= count($filtered) ?> projects</span>
</form>
<div class="work-grid archive-grid" data-archive-grid><?php foreach($filtered as $work) work_card($work); ?></div>
<?php elseif ($page==='project' && $project): ?>
<section class="reading archive-intro">
  <a class="back" href="index.php?page=projects">← Project archive</a>
  <p class="eyebrow"><?= e($project['category']) ?> / <?= e($project['location']) ?></p>
  <h1><?= e($project['title']) ?></h1><p class="lede"><?= nl2br(e($project['description'])) ?></p>
  <?php if($project['project_date']): ?><p><?= e($project['project_date']) ?></p><?php endif; ?>
</section>
<div class="project-images"><?php foreach($project['images'] as $image): ?><img src="<?= e(image_url($image)) ?>" alt="<?= e($image['alt']) ?>"><?php endforeach; ?></div>
<?php elseif ($page==='contact'): ?>
<section class="reading">
  <p class="eyebrow">Contact / Zamboanga City</p>
  <h1><?= e($company['contact']['title']??'Contact') ?></h1>
  <p class="lede"><?= e($company['contact']['description']??'') ?></p>
  <?php if ($email=$company['email']['description']??null): ?><p><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></p><?php endif; ?>
  <?php if ($phone=$company['phone']['description']??null): ?><p><?= e($phone) ?></p><?php endif; ?>
  <a class="text-arrow" href="booking.php">Book an appointment ↗</a>
</section>
<?php endif; ?>
</main>
<?php public_footer($company); page_end(); ?>
