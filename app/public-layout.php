<?php
declare(strict_types=1);

function search_result_url(array $entry): string {
    $id=(int)$entry['id'];
    return match ($entry['kind']) {
        'portfolio' => 'index.php?page=project&id='.$id,
        'service' => 'index.php?page=services#entry-'.$id,
        default => match ($entry['slug']) {
            'hero' => 'index.php',
            'about' => 'index.php?page=about',
            'contact', 'email', 'phone' => 'index.php?page=contact',
            default => 'index.php?page=about#entry-'.$id,
        },
    };
}
function public_header(string $page): void {
    $links=['about'=>'Company','services'=>'Services','projects'=>'Projects','contact'=>'Contact','search'=>'Search'];
    ?>
    <header class="mast">
      <a class="wordmark" href="index.php" aria-label="Prime Pool Peninsula home">
        <img src="assets/brand.png" width="45" height="45" alt=""><span>Prime Pool<br>Peninsula</span>
      </a>
      <button class="menu-toggle" type="button" aria-controls="main-nav" aria-expanded="false" hidden><span class="menu-label">Menu</span> <span class="menu-symbol" aria-hidden="true">+</span></button>
      <nav class="main-nav" id="main-nav" aria-label="Main navigation">
        <?php foreach ($links as $slug=>$label): ?><a href="index.php?page=<?= e($slug) ?>"<?= $page===$slug?' aria-current="page"':'' ?>><?= e($label) ?></a><?php endforeach; ?>
      </nav>
      <a class="booking-link booking-action" href="booking.php" aria-label="Book an appointment"<?= $page==='booking'?' aria-current="page"':'' ?>><span class="booking-label">Book<span class="booking-long"> an appointment</span></span><span class="booking-arrow" aria-hidden="true">↗</span></a>
    </header>
    <?php
}
function public_footer(array $company): void {
    ?>
    <footer class="public-footer">
      <a href="index.php" class="footer-logo"><img src="assets/brand-wide.png" alt="Prime Pool Peninsula Supply and Services" width="216" height="108"></a>
      <div><p class="footer-label">Office</p><p><?= e($company['contact']['description']??'') ?></p></div>
      <div><p class="footer-label">Contact</p>
      <?php if ($email=$company['email']['description']??null): ?><p><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></p><?php endif; ?>
      <?php if ($phone=$company['phone']['description']??null): ?><p><?= e($phone) ?></p><?php endif; ?></div>
      <a class="footer-staff" href="admin.php">Staff sign-in <span aria-hidden="true">↗</span></a>
    </footer>
    <?php
}
function work_card(array $work, bool $priority=false): void {
    $image=$work['images'][0]??null;
    ?>
    <article class="work-card">
      <a href="index.php?page=project&amp;id=<?= (int)$work['id'] ?>">
        <div class="work-media">
          <?php if($image): ?><img src="<?= e(image_url($image)) ?>" alt="<?= e($image['alt']) ?>"<?= $priority?' fetchpriority="high"':' loading="lazy"' ?>><?php else: ?><div class="work-placeholder"></div><?php endif; ?>
        </div>
        <div class="work-caption"><div><h2><?= e($work['title']) ?></h2><?php if($work['location']): ?><p><?= e($work['location']) ?></p><?php endif; ?></div><span class="work-arrow" aria-hidden="true">↗</span></div>
      </a>
    </article>
    <?php
}


function project_index_item(array $work): void {
    $image=$work['images'][0]??null;
    ?>
    <li>
      <a href="index.php?page=project&amp;id=<?= (int)$work['id'] ?>">
        <span class="index-title"><?= e($work['title']) ?></span>
        <span class="index-location"><?= e($work['location']) ?></span>
        <?php if($image): ?>
          <img src="<?= e(image_url($image)) ?>" alt="" width="120" height="72" loading="lazy">
        <?php endif; ?>
        <span class="work-arrow" aria-hidden="true">↗</span>
      </a>
    </li>
    <?php
}
