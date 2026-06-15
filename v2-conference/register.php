<?php
require __DIR__ . '/config.php';

if (current_user_id()) {
    header('Location: cabinet.php');
    exit;
}

$errors = [];
$old = ['login' => '', 'fio' => '', 'phone' => '', 'email' => '', 'birth_date' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($old as $key => $_) {
        $old[$key] = trim($_POST[$key] ?? '');
    }
    $password = $_POST['password'] ?? '';

    if (!preg_match('/^[A-Za-z0-9]{6,}$/', $old['login'])) {
        $errors['login'] = 'Латинские буквы и цифры, минимум 6 символов';
    } elseif ($db->value('SELECT id FROM users WHERE login = ?', [$old['login']])) {
        $errors['login'] = 'Такой логин уже занят';
    }
    if (mb_strlen($password) < 8) {
        $errors['password'] = 'Пароль минимум 8 символов';
    }
    if ($old['fio'] === '') {
        $errors['fio'] = 'Укажите ФИО';
    }
    if ($old['phone'] === '') {
        $errors['phone'] = 'Укажите телефон';
    }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Некорректный e-mail';
    }
    if ($THEME['show_birthdate'] && $old['birth_date'] === '') {
        $errors['birth_date'] = 'Укажите дату рождения';
    }

    if (!$errors) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $id = $db->insert(
            'INSERT INTO users (login, password, fio, phone, email, birth_date) VALUES (?, ?, ?, ?, ?, ?)',
            [$old['login'], $hash, $old['fio'], $old['phone'], $old['email'], $old['birth_date'] ?: null]
        );
        $_SESSION['user_id'] = $id;
        header('Location: cabinet.php');
        exit;
    }
}

$page_title = 'Регистрация';
require __DIR__ . '/includes/header.php';
?>
<div class="card">
    <h2>Регистрация</h2>
    <form class="form" method="post" novalidate>
        <div class="field">
            <label>Логин</label>
            <input type="text" name="login" value="<?= h($old['login']) ?>">
            <?php if (isset($errors['login'])): ?><span class="hint"><?= h($errors['login']) ?></span><?php endif; ?>
        </div>
        <div class="field">
            <label>Пароль</label>
            <input type="password" name="password">
            <?php if (isset($errors['password'])): ?><span class="hint"><?= h($errors['password']) ?></span><?php endif; ?>
        </div>
        <div class="field">
            <label>ФИО</label>
            <input type="text" name="fio" value="<?= h($old['fio']) ?>">
            <?php if (isset($errors['fio'])): ?><span class="hint"><?= h($errors['fio']) ?></span><?php endif; ?>
        </div>
        <?php if ($THEME['show_birthdate']): ?>
        <div class="field">
            <label>Дата рождения</label>
            <input type="date" name="birth_date" value="<?= h($old['birth_date']) ?>">
            <?php if (isset($errors['birth_date'])): ?><span class="hint"><?= h($errors['birth_date']) ?></span><?php endif; ?>
        </div>
        <?php endif; ?>
        <div class="field">
            <label>Телефон</label>
            <input type="text" name="phone" value="<?= h($old['phone']) ?>">
            <?php if (isset($errors['phone'])): ?><span class="hint"><?= h($errors['phone']) ?></span><?php endif; ?>
        </div>
        <div class="field">
            <label>E-mail</label>
            <input type="email" name="email" value="<?= h($old['email']) ?>">
            <?php if (isset($errors['email'])): ?><span class="hint"><?= h($errors['email']) ?></span><?php endif; ?>
        </div>
        <button class="btn" type="submit">Зарегистрироваться</button>
        <div class="link-row"><a href="login.php">Уже есть аккаунт? Вход</a></div>
    </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
