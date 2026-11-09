<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
require ROOT.'/app/content.php';
require ROOT.'/app/view.php';
$page='home'; $error=null; $company=[]; $projects=[]; $services=[]; $project=null; $category=null; $query=null; $results=[]; $searchType=null; $nextProject=null; $archiveView='grid';
try {
    $page=choice($_GET,'page',['home','about','services','projects','project','contact','search'],false)??'home';
    $company=array_column(content_list('company'),null,'slug');
    if (in_array($page,['home','projects'],true)) $projects=content_list('portfolio');
    if ($page==='services') $services=content_list('service');
    if ($page==='projects') {
        $category=field($_GET,'category',100);
        $archiveView=choice($_GET,'view',['grid','index'],false)??'grid';
    }
    if ($page==='search') {
        $query=field($_GET,'q',100);
        $searchType=choice($_GET,'type',CONTENT_KINDS,false);
        if ($query!==null || $searchType!==null) $results=search_content($query??'',$searchType);
    }
    if ($page==='project') {
        $project=content_entry('portfolio',id($_GET['id']??null));
        $nextProject=next_portfolio($project);
    }
} catch(Throwable $e) { $error=safe_error($e); http_response_code(status_code($e)); }
require ROOT.'/app/public-layout.php';
if ($page==='search') {
    require ROOT.'/app/public-search.php';
    if (($_GET['fragment']??null)==='results') {
        if (!$error) search_results($query,$searchType,$results);
        exit;
    }
}
page_start($project['title']??($page==='home'?'Pool design and construction':ucfirst($page)));
public_header($page);
?>
<main id="main">
<?php if ($error): ?>
<section class="reading"><h1>Page unavailable</h1><?php notice($error); ?></section>
<?php elseif ($page==='home'): ?>
<section class="intro">
  <h1><?= e($company['hero']['title']??'Prime Pool Peninsula') ?></h1>
  <div class="intro-details">
    <div class="intro-actions">
      <a class="booking-action" href="booking.php">Book an appointment <span class="booking-arrow" aria-hidden="true">↗</span></a>
      <a class="intro-about" href="index.php?page=about">About the company</a>
    </div>
    <?php if(!empty($company['hero']['description'])): ?><p><?= e($company['hero']['description']) ?></p><?php endif; ?>
  </div>
</section>
<?php
$bySlug=array_column($projects,null,'slug');
$feature=array_values(array_filter([$bySlug['lapaz']??null,$bySlug['copacabana']??null,$bySlug['marj-infinity']??null,$bySlug['filinvest']??null]));
if (count($feature)<4) $feature=array_slice($projects,0,4);
?>
<?php if ($feature): ?>
<section class="portfolio-section" aria-label="Selected projects">
  <div class="section-line"><h2>Selected projects</h2><a href="index.php?page=projects">Project archive <span aria-hidden="true">↗</span></a></div>
  <div class="work-grid"><?php foreach ($feature as $index=>$work) work_card($work,$index===0); ?></div>
</section>
<?php endif; ?>
<section class="end-cta">
  <h2>Let’s talk about what you want to build.</h2>
  <a class="booking-action" href="booking.php">Book an appointment <span class="booking-arrow" aria-hidden="true">↗</span></a>
</section>
<?php elseif ($page==='about'): ?>
<section class="reading editorial-page">
  <p class="page-context">Zamboanga City · Established 2021</p>
  <h1><?= e($company['about']['title']??'About') ?></h1>
  <p class="lede"><?= e($company['about']['description']??'') ?></p>
  <?php foreach($company as $entry): if (!in_array($entry['slug'],['hero','about','contact','email','phone'],true)): ?>
    <article class="line-item company-row" id="entry-<?= (int)$entry['id'] ?>"><h2><?= e($entry['title']) ?></h2><p><?= nl2br(e($entry['description'])) ?></p></article>
  <?php endif; endforeach; ?>
</section>
<?php elseif ($page==='services'): ?>
<section class="reading editorial-page"><h1>Services</h1>
<?php foreach ($services as $i=>$service): ?>
  <article class="line-item service-row" id="entry-<?= (int)$service['id'] ?>">
    <span class="service-index" aria-hidden="true"><?= sprintf('%02d',$i+1) ?></span>
    <h2><?= e($service['title']) ?></h2>
    <div>
      <p><?= nl2br(e($service['description'])) ?></p>
      <?php if($service['images']): ?><div class="service-gallery"><?php foreach($service['images'] as $image): ?><a href="<?= e(image_url($image)) ?>"><img src="<?= e(image_url($image)) ?>" alt="<?= e($image['alt']) ?>" loading="lazy"></a><?php endforeach; ?></div><?php endif; ?>
    </div>
  </article>
<?php endforeach; ?>
<a class="text-arrow" href="booking.php">Discuss your project <span aria-hidden="true">↗</span></a></section>
<?php elseif ($page==='projects'): ?>
<section class="reading archive-intro"><h1>Project archive</h1><p class="lede">Pools, resorts and engineering projects across Mindanao.</p></section>
<?php
$categories=array_values(array_unique(array_filter(array_column($projects,'category'))));
sort($categories);
$filtered=$category ? array_values(array_filter($projects,static fn($work)=>$work['category']===$category)) : $projects;
?>
<div class="archive-toolbar">
  <div class="archive-toolbar-top">
    <p class="archive-count" aria-live="polite" data-archive-count><?= count($filtered) ?> <?= count($filtered)===1?'project':'projects' ?></p>
    <nav class="archive-views" aria-label="Project archive view">
      <?php foreach(['grid'=>'Grid','index'=>'Index'] as $view=>$label):
        $href='index.php?'.http_build_query(array_filter(['page'=>'projects','view'=>$view,'category'=>$category])); ?>
        <a href="<?= e($href) ?>" data-archive-view<?= $archiveView===$view?' aria-current="page"':'' ?>><?= e($label) ?></a>
      <?php endforeach; ?>
    </nav>
  </div>
  <nav class="archive-categories" aria-label="Filter projects by category"<?= $archiveView==='grid'?' data-archive-filter':'' ?>>
    <?php foreach(['',...$categories] as $option):
      $href='index.php?'.http_build_query(array_filter(['page'=>'projects','view'=>$archiveView,'category'=>$option]));
    ?>
      <a href="<?= e($href) ?>" data-category="<?= e($option) ?>"<?= ($category??'')===$option?' aria-current="true"':'' ?>><?= e($option===''?'All projects':$option) ?></a>
    <?php endforeach; ?>
  </nav>
</div>
<?php if(!$filtered): ?><p class="archive-empty">No projects in this category.</p><?php endif; ?>
<?php if($archiveView==='index'): ?>
<ol class="project-index"><?php foreach($filtered as $work) project_index_item($work); ?></ol>
<?php else: ?>
<div class="work-grid archive-grid" data-archive-grid><?php foreach($filtered as $work) work_card($work); ?></div>
<?php endif; ?>
<?php elseif ($page==='project' && $project): ?>
<section class="reading archive-intro">
  <a class="back" href="index.php?page=projects">← Project archive</a>
  <?php $projectMeta=array_filter([$project['category'],$project['location']]); ?>
  <?php if($projectMeta): ?><p class="page-context"><?= e(implode(' · ',$projectMeta)) ?></p><?php endif; ?>
  <h1><?= e($project['title']) ?></h1><p class="lede"><?= nl2br(e($project['description'])) ?></p>
  <?php if($project['project_date']): ?><p><?= e($project['project_date']) ?></p><?php endif; ?>
</section>
<?php if($project['images']): ?>
<div class="project-images"><?php foreach($project['images'] as $i=>$image): ?><img src="<?= e(image_url($image)) ?>" alt="<?= e($image['alt']) ?>"<?= $i===0?' fetchpriority="high"':' loading="lazy"' ?>><?php endforeach; ?></div>
<?php endif; ?>
<?php if($nextProject): ?>
<a class="next-project" href="index.php?page=project&amp;id=<?= (int)$nextProject['id'] ?>">
  <span>Next project</span>
  <strong><?= e($nextProject['title']) ?></strong>
  <span class="work-arrow" aria-hidden="true">↗</span>
</a>
<?php endif; ?>
<?php elseif ($page==='search'): ?>
<?php search_page($query,$searchType,$results); ?>
<?php elseif ($page==='contact'): ?>
<section class="reading editorial-page">
  <p class="page-context">Zamboanga City</p>
  <h1><?= e($company['contact']['title']??'Contact') ?></h1>
  <p class="lede"><?= e($company['contact']['description']??'') ?></p>
  <dl class="contact-list">
    <?php if ($email=$company['email']['description']??null): ?><div><dt>Email</dt><dd><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></dd></div><?php endif; ?>
    <?php if ($phone=$company['phone']['description']??null): ?><div><dt>Phone</dt><dd><?= e($phone) ?></dd></div><?php endif; ?>
  </dl>
  <a class="booking-action" href="booking.php">Book an appointment <span class="booking-arrow" aria-hidden="true">↗</span></a>
</section>
<?php endif; ?>
</main>
<?php public_footer($company); page_end(); ?>
