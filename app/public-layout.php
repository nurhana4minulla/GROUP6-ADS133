<?php
declare(strict_types=1);
function public_header(string $page): void {
    $links=['about'=>'Company','services'=>'Services','projects'=>'Projects','contact'=>'Contact'];
    ?>
    <header class="mast">
      <a class="wordmark" href="index.php" aria-label="Prime Pool Peninsula home">
        <img src="assets/brand.png" width="45" height="45" alt=""><span>Prime Pool<br>Peninsula</span>
      </a>
      <button class="menu-toggle" type="button" aria-controls="main-nav" aria-expanded="false" hidden>Menu <span aria-hidden="true">+</span></button>
      <nav class="main-nav" id="main-nav" aria-label="Main navigation">
        <?php foreach ($links as $slug=>$label): ?><a href="index.php?page=<?= e($slug) ?>"<?= $page===$slug?' aria-current="page"':'' ?>><?= e($label) ?></a><?php endforeach; ?>
      </nav>
      <a class="booking-link" href="booking.php"<?= $page==='booking'?' aria-current="page"':'' ?>>Book an appointment <span aria-hidden="true">↗</span></a>
    </header>
    <?php
}
function public_footer(array $company): void {
    ?>
    <footer class="public-footer">
      <a href="index.php" class="footer-logo"><img src="assets/brand-wide.png" alt="Prime Pool Peninsula Supply and Services" width="216" height="108"></a>
      <div><p class="eyebrow">Office</p><p><?= e($company['contact']['description']??'') ?></p></div>
      <div><p class="eyebrow">Contact</p>
      <?php if ($email=$company['email']['description']??null): ?><p><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></p><?php endif; ?>
      <?php if ($phone=$company['phone']['description']??null): ?><p><?= e($phone) ?></p><?php endif; ?></div>
      <a href="admin.php">Staff sign-in ↗</a>
    </footer>
    <?php
}
function work_card(array $work): void {
    $image=$work['images'][0]??null;
    ?>
    <article class="work-card">
      <a href="index.php?page=project&amp;id=<?= (int)$work['id'] ?>">
        <?php if($image): ?><img src="<?= e(image_url($image)) ?>" alt="<?= e($image['alt']) ?>" loading="lazy"><?php else: ?><div class="work-placeholder"></div><?php endif; ?>
        <div class="work-caption"><div><h2><?= e($work['title']) ?></h2><p><?= e($work['location']) ?></p></div><span aria-hidden="true">↗</span></div>
      </a>
    </article>
    <?php
}
