<?php
require __DIR__ . '/config.php';
$items = $db->all('SELECT id, name, category FROM items ORDER BY id');
$page_title = 'Главная';
require __DIR__ . '/includes/header.php';
?>
<section class="hero">
    <h1><?= h($THEME['site_name']) ?></h1>
    <p><?= h($THEME['tagline']) ?></p>
</section>

<div class="slider">
    <div class="slider__track">
        <div class="slider__slide"><img src="assets/slide1.jpg" alt="slide"></div>
        <div class="slider__slide"><img src="assets/slide2.jpg" alt="slide"></div>
        <div class="slider__slide"><img src="assets/slide3.jpg" alt="slide"></div>
        <div class="slider__slide"><img src="assets/slide4.jpg" alt="slide"></div>
    </div>
    <button class="slider__btn slider__btn--prev" type="button" aria-label="Назад">‹</button>
    <button class="slider__btn slider__btn--next" type="button" aria-label="Вперёд">›</button>
    <div class="slider__dots">
        <span class="slider__dot"></span>
        <span class="slider__dot"></span>
        <span class="slider__dot"></span>
        <span class="slider__dot"></span>
    </div>
</div>

<section class="card">
    <h2><?= h($THEME['item_plural']) ?></h2>
    <div class="app-list">
        <?php foreach ($items as $item): ?>
            <div class="app-item">
                <div class="app-item__top">
                    <strong><?= h($item['name']) ?></strong>
                </div>
                <div class="app-item__meta"><?= h($item['category']) ?></div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<a class="btn" href="<?= current_user_id() ? 'application.php' : 'register.php' ?>">
    Оставить заявку
</a>

<script src="js/slider.js"></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
