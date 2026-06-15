<?php
require __DIR__ . '/config.php';

if (current_user_id()) {
    header('Location: cabinet.php');
    exit;
}

$error = '';
$login = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';
    $user = $db->one('SELECT id, password FROM users WHERE login = ?', [$login]);
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = (int) $user['id'];
        header('Location: cabinet.php');
        exit;
    }
    $error = 'Неверный логин или пароль';
}

$page_title = 'Вход';
require __DIR__ . '/includes/header.php';
?>
<div class="card">
    <h2>Вход</h2>
    <?php if ($error): ?><div class="alert alert--error"><?= h($error) ?></div><?php endif; ?>
    <form class="form" method="post" novalidate>
        <div class="field">
            <label>Логин</label>
            <input type="text" name="login" value="<?= h($login) ?>">
        </div>
        <div class="field">
            <label>Пароль</label>
            <input type="password" name="password">
        </div>
        <button class="btn" type="submit">Войти</button>
        <div class="link-row"><a href="register.php">Еще не зарегистрированы? Регистрация</a></div>
    </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
