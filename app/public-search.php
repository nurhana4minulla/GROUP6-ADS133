<?php
declare(strict_types=1);

function search_page(?string $query, ?string $kind, array $results): void {
    ?>
    <section class="reading search-page">
      <h1>Find a project or service.</h1>
      <form class="site-search" action="index.php" method="get" role="search" aria-label="Search Prime Pool" data-live-search>
        <input type="hidden" name="page" value="search">
        <label for="site-query">Search by name, location, or service</label>
        <div class="search-controls">
          <input type="search" class="form-control" id="site-query" name="q" maxlength="100" value="<?= e($query) ?>" placeholder="e.g. infinity pool">
          <button class="btn btn-primary" type="submit">Search</button>
        </div>
        <fieldset class="search-types">
          <legend class="visually-hidden">Result type</legend>
          <?php foreach([''=>'All','portfolio'=>'Projects','service'=>'Services','company'=>'Company'] as $type=>$label): ?>
          <label>
            <input class="visually-hidden" type="radio" name="type" value="<?= e($type) ?>"<?= ($kind??'')===$type?' checked':'' ?>>
            <span><?= e($label) ?></span>
          </label>
          <?php endforeach; ?>
        </fieldset>
      </form>
      <div id="site-search-results"><?php search_results($query,$kind,$results); ?></div>
      <p class="visually-hidden" role="status" data-search-announcement></p>
    </section>
    <?php
}

function search_results(?string $query, ?string $kind, array $results): void {
    if ($query===null && $kind===null): ?>
      <nav class="search-starters" aria-label="Search suggestions">
        <p>Try a search</p>
        <?php foreach(['Infinity pool','Zamboanga City','Pool maintenance'] as $term): ?>
        <a href="index.php?page=search&amp;q=<?= e(rawurlencode($term)) ?>"><?= e($term) ?></a>
        <?php endforeach; ?>
      </nav>
    <?php elseif (!$results): ?>
      <p class="search-status" data-result-summary>No results<?= $query!==null?' for “'.e($query).'”':'' ?>.</p>
      <p>Try a project name, a nearby city, or a service. Choose All to search across every type.</p>
    <?php else:
      $count=count($results);
      $summary=$count>40?'Showing the first 40 results':$count.($count===1?' result':' results');
      ?>
      <p class="search-status" data-result-summary><?= e($summary) ?><?= $query!==null?' for “'.e($query).'”':'' ?></p>
      <ol class="search-results">
        <?php foreach(array_slice($results,0,40) as $result):
          $type=['company'=>'Company','service'=>'Service','portfolio'=>'Project'][$result['kind']];
          $meta=array_filter([$type,$result['location']??$result['category']]);
          $hasImage=$result['image_id']!==null;
        ?>
        <li>
          <a class="search-hit<?= $hasImage?' search-hit-illustrated':'' ?>" href="<?= e(search_result_url($result)) ?>">
            <?php if($hasImage): ?>
              <img src="<?= e(image_url(['id'=>$result['image_id'],'path'=>$result['image_path']])) ?>" width="120" height="84" alt="" loading="lazy">
            <?php endif; ?>
            <div class="search-hit-copy">
              <p class="page-context"><?= e(implode(' · ',$meta)) ?></p>
              <h2><?= e($result['title']) ?></h2>
              <p><?= e(mb_strimwidth(preg_replace('/s+/u',' ',$result['description']),0,145,'…')) ?></p>
            </div>
            <span class="work-arrow" aria-hidden="true">↗</span>
          </a>
        </li>
        <?php endforeach; ?>
      </ol>
    <?php endif;
}
