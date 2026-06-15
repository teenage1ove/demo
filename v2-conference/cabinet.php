<?php
require __DIR__ . '/config.php';
require_login();

$uid = current_user_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['application_id'])) {
    $appId = (int) $_POST['application_id'];
    $text = trim($_POST['review_text'] ?? '');
    $app = $db->one('SELECT id, status FROM applications WHERE id = ? AND user_id = ?', [$appId, $uid]);
    if ($app && $app['status'] !== $THEME['statuses'][0] && $text !== '') {
        $exists = $db->value('SELECT id FROM reviews WHERE application_id = ?', [$appId]);
        if (!$exists) {
            $db->insert(
                'INSERT INTO reviews (application_id, user_id, review_text) VALUES (?, ?, ?)',
                [$appId, $uid, $text]
            );
        }
    }
    header('Location: cabinet.php');
    exit;
}

$apps = $db->all(
    'SELECT a.id, a.start_date, a.payment_method, a.status, a.created_at,
            i.name AS item_name, r.review_text
     FROM applications a
     JOIN items i ON i.id = a.item_id
     LEFT JOIN reviews r ON r.application_id = a.id
     WHERE a.user_id = ?
     ORDER BY a.created_at DESC',
    [$uid]
);

$user = $db->one('SELECT login, fio FROM users WHERE id = ?', [$uid]);

$badge = [
    $THEME['statuses'][0] => 'badge--new',
    $THEME['statuses'][1] => 'badge--progress',
    $THEME['statuses'][2] => 'badge--done',
];

$page_title = 'Личный кабинет';
require __DIR__ . '/includes/header.php';
?>
<section class="hero">
    <h2>Здравствуйте, <?= h($user['fio'] ?: $user['login']) ?></h2>
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
        <span class="slider__dot"></span><span class="slider__dot"></span>
        <span class="slider__dot"></span><span class="slider__dot"></span>
    </div>
</div>

<section class="card">
    <h2>Мои заявки</h2>
    <?php if (!$apps): ?>
        <p class="small">Пока нет заявок. <a href="application.php">Оформить заявку</a></p>
    <?php else: ?>
        <div class="app-list">
            <?php foreach ($apps as $app): ?>
                <div class="app-item">
                    <div class="app-item__top">
                        <strong><?= h($app['item_name']) ?></strong>
                        <span class="badge <?= $badge[$app['status']] ?? 'badge--new' ?>"><?= h($app['status']) ?></span>
                    </div>
                    <div class="app-item__meta">
                        Начало: <?= h(ru_date($app['start_date'])) ?> · <?= h($app['payment_method']) ?>
                    </div>
                    <?php if ($app['review_text']): ?>
                        <p class="small">Ваш отзыв: <?= h($app['review_text']) ?></p>
                    <?php elseif ($app['status'] !== $THEME['statuses'][0]): ?>
                        <form class="form" method="post" style="margin-top:8px">
                            <input type="hidden" name="application_id" value="<?= (int) $app['id'] ?>">
                            <div class="field">
                                <textarea name="review_text" rows="2" placeholder="Оставить отзыв"></textarea>
                            </div>
                            <button class="btn btn--sm" type="submit">Отправить отзыв</button>
                        </form>
                    <?php else: ?>
                        <p class="small">Отзыв можно оставить после смены статуса.</p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<a class="btn" href="application.php">Новая заявка</a>

<?php if (isset($_GET['created'])): ?>
    <div class="toast">Заявка отправлена</div>
<?php endif; ?>

<script src="js/slider.js"></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
