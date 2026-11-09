<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
require ROOT.'/app/content.php';
require ROOT.'/app/view.php';
$page='home'; $error=null; $company=[]; $projects=[]; $services=[]; $project=null; $category=null; $query=null; $results=[];
try {
    $page=choice($_GET,'page',['home','about','services','projects','project','contact','search'],false)??'home';
    $company=array_column(content_list('company'),null,'slug');
    if (in_array($page,['home','projects'],true)) $projects=content_list('portfolio');
    if ($page==='services') $services=content_list('service');
    if ($page==='projects') $category=field($_GET,'category',100);
    if ($page==='search' && ($query=field($_GET,'q',100))!==null) $results=search_content($query);
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
  <h1><?= e($company['hero']['title']??'Prime Pool Peninsula') ?></h1>
  <div class="intro-details">
    <a class="text-arrow" href="index.php?page=about">About the company <span aria-hidden="true">↗</span></a>
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
  <a class="text-arrow" href="booking.php">Book an appointment <span aria-hidden="true">↗</span></a>
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
<form class="archive-filter" action="index.php" method="get" data-archive-filter>
  <input type="hidden" name="page" value="projects">
  <label for="category">Category</label>
  <select class="form-select" name="category" id="category"><option value="">All projects</option>
    <?php foreach($categories as $option): ?><option<?= $option===$category?' selected':'' ?>><?= e($option) ?></option><?php endforeach; ?>
  </select>
  <button class="btn btn-outline-primary" type="submit">Filter</button>
  <span class="archive-count" aria-live="polite" data-archive-count><?= count($filtered) ?> projects</span>
</form>
<div class="work-grid archive-grid" data-archive-grid><?php foreach($filtered as $work) work_card($work); ?></div>
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
<?php elseif ($page==='search'): ?>
<section class="reading search-page">
  <h1>Search</h1>
  <form class="site-search" action="index.php" method="get" role="search" aria-label="Search the Prime Pool website">
    <input type="hidden" name="page" value="search">
    <label for="site-query">Projects, services or company information</label>
    <div class="search-controls">
      <input type="search" class="form-control" id="site-query" name="q" maxlength="100" value="<?= e($query) ?>" required>
      <button class="btn btn-primary" type="submit">Search</button>
    </div>
  </form>
  <?php if($query!==null): ?>
    <?php if(!$results): ?>
      <p class="search-status">No results for <strong><?= e($query) ?></strong>. Try a project name, a place, or a service.</p>
    <?php else: ?>
      <p class="search-status"><?= count($results)>40?'Showing the first 40 results':count($results).' '.(count($results)===1?'result':'results') ?> for <strong><?= e($query) ?></strong></p>
      <ol class="search-results">
      <?php foreach(array_slice($results,0,40) as $result): ?>
        <li>
          <a href="<?= e(search_result_url($result)) ?>">
            <span class="page-context"><?= e(['company'=>'Company','service'=>'Service','portfolio'=>'Project'][$result['kind']]) ?></span>
            <h2><?= e($result['title']) ?></h2>
            <span class="work-arrow" aria-hidden="true">↗</span>
            <p><?= e(mb_strimwidth(preg_replace('/\s+/u',' ',$result['description']),0,155,'…')) ?></p>
          </a>
        </li>
      <?php endforeach; ?>
      </ol>
    <?php endif; ?>
  <?php endif; ?>
</section>
<?php elseif ($page==='contact'): ?>
<section class="reading editorial-page">
  <p class="page-context">Zamboanga City</p>
  <h1><?= e($company['contact']['title']??'Contact') ?></h1>
  <p class="lede"><?= e($company['contact']['description']??'') ?></p>
  <dl class="contact-list">
    <?php if ($email=$company['email']['description']??null): ?><div><dt>Email</dt><dd><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></dd></div><?php endif; ?>
    <?php if ($phone=$company['phone']['description']??null): ?><div><dt>Phone</dt><dd><?= e($phone) ?></dd></div><?php endif; ?>
  </dl>
  <a class="text-arrow" href="booking.php">Book an appointment <span aria-hidden="true">↗</span></a>
</section>
<?php endif; ?>
</main>
<?php public_footer($company); page_end(); ?>
