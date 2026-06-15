<?php
require __DIR__ . '/config.php';
require_login();

$items = $db->all('SELECT id, name, category FROM items ORDER BY id');
$errors = [];
$old = ['item_id' => '', 'start_date' => '', 'payment_method' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['item_id'] = $_POST['item_id'] ?? '';
    $old['start_date'] = trim($_POST['start_date'] ?? '');
    $old['payment_method'] = $_POST['payment_method'] ?? '';

    $valid_item = $db->value('SELECT id FROM items WHERE id = ?', [$old['item_id']]);
    if (!$valid_item) {
        $errors['item_id'] = 'Выберите ' . mb_strtolower($THEME['item_label']);
    }

    $date_sql = null;
    if (!preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $old['start_date'])) {
        $errors['start_date'] = 'Формат даты: ДД.ММ.ГГГГ';
    } else {
        [$d, $m, $y] = explode('.', $old['start_date']);
        if (!checkdate((int) $m, (int) $d, (int) $y)) {
            $errors['start_date'] = 'Некорректная дата';
        } else {
            $date_sql = "$y-$m-$d";
        }
    }

    if (!in_array($old['payment_method'], $THEME['payments'], true)) {
        $errors['payment_method'] = 'Выберите способ оплаты';
    }

    if (!$errors) {
        $db->insert(
            'INSERT INTO applications (user_id, item_id, start_date, payment_method, status) VALUES (?, ?, ?, ?, ?)',
            [current_user_id(), $old['item_id'], $date_sql, $old['payment_method'], $THEME['statuses'][0]]
        );
        header('Location: cabinet.php?created=1');
        exit;
    }
}

$page_title = 'Новая заявка';
require __DIR__ . '/includes/header.php';
?>
<div class="card">
    <h2>Оформление заявки</h2>
    <form class="form" method="post" novalidate>
        <div class="field">
            <label><?= h($THEME['item_label']) ?></label>
            <select name="item_id">
                <option value="">— выберите —</option>
                <?php foreach ($items as $item): ?>
                    <option value="<?= (int) $item['id'] ?>" <?= $old['item_id'] == $item['id'] ? 'selected' : '' ?>>
                        <?= h($item['name']) ?> (<?= h($item['category']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['item_id'])): ?><span class="hint"><?= h($errors['item_id']) ?></span><?php endif; ?>
        </div>
        <div class="field">
            <label>Дата начала (ДД.ММ.ГГГГ)</label>
            <input type="text" name="start_date" placeholder="01.09.2026" value="<?= h($old['start_date']) ?>">
            <?php if (isset($errors['start_date'])): ?><span class="hint"><?= h($errors['start_date']) ?></span><?php endif; ?>
        </div>
        <div class="field">
            <label>Способ оплаты</label>
            <select name="payment_method">
                <option value="">— выберите —</option>
                <?php foreach ($THEME['payments'] as $pay): ?>
                    <option value="<?= h($pay) ?>" <?= $old['payment_method'] === $pay ? 'selected' : '' ?>><?= h($pay) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['payment_method'])): ?><span class="hint"><?= h($errors['payment_method']) ?></span><?php endif; ?>
        </div>
        <button class="btn" type="submit">Отправить заявку</button>
    </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
