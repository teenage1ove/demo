<?php
/* ФАЙЛ: v3.sql 

SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS `passenger_rf` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `passenger_rf`;

DROP TABLE IF EXISTS `reviews`;
DROP TABLE IF EXISTS `applications`;
DROP TABLE IF EXISTS `items`;
DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `login` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `fio` VARCHAR(200) NOT NULL,
  `phone` VARCHAR(20) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `birth_date` DATE DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `category` VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `applications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `item_id` INT NOT NULL,
  `start_date` DATE NOT NULL,
  `payment_method` VARCHAR(50) NOT NULL,
  `status` VARCHAR(50) NOT NULL DEFAULT 'Новая',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`item_id`) REFERENCES `items` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `reviews` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `application_id` INT NOT NULL UNIQUE,
  `user_id` INT NOT NULL,
  `review_text` TEXT NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `items` (`name`, `category`) VALUES
('Вождение городского автобуса', 'Автобус'),
('Вождение междугороднего автобуса', 'Автобус'),
('Управление электробусом', 'Электробус'),
('Управление трамваем', 'Трамвай'),
('Вождение сочленённого автобуса', 'Автобус');

-- тестовый пользователь: логин test12 / пароль test1234
INSERT INTO `users` (`login`, `password`, `fio`, `phone`, `email`, `birth_date`) VALUES
('test12', '$2y$12$di3Sp90YCZywBqYGbqnMbOgPN1yCj243u.vSZdvdfraA2NRFzbsyC', 'Наумова Софья Михайловна', '79998567744', 'test1@mail.ru', '2000-03-21');

*/
?>

<?php
/*  ФАЙЛ: db.php  */
class Db
{
    private mysqli $c;

    public function __construct(string $h, string $u, string $p, string $name)
    {
        try {
            $this->c = new mysqli($h, $u, $p, $name);
        } catch (\mysqli_sql_exception $e) {
            die('Нет связи с базой «' . $name . '». Сначала выполни SQL в phpMyAdmin. (' . $e->getMessage() . ')');
        }
        $this->c->set_charset('utf8mb4');
    }

    private function run(string $sql, array $p): mysqli_stmt
    {
        $st = $this->c->prepare($sql);
        if ($p) {
            $st->bind_param(str_repeat('s', count($p)), ...$p);
        }
        $st->execute();
        return $st;
    }

    public function all(string $sql, array $p = []): array { return $this->run($sql, $p)->get_result()->fetch_all(MYSQLI_ASSOC); }
    public function one(string $sql, array $p = []): ?array { return $this->run($sql, $p)->get_result()->fetch_assoc() ?: null; }
    public function value(string $sql, array $p = []) { $r = $this->run($sql, $p)->get_result()->fetch_row(); return $r ? $r[0] : null; }
    public function insert(string $sql, array $p = []): int { return (int) $this->run($sql, $p)->insert_id; }
    public function exec(string $sql, array $p = []): int { return (int) $this->run($sql, $p)->affected_rows; }
}
?>

<?php
/*  ФАЙЛ: functions.php  */
function h($v): string { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }

function uid(): ?int { return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null; }

function redirect(string $to): void { header("Location: ?page=$to"); exit; }

function ru_date(?string $d): string { $t = $d ? strtotime($d) : false; return $t ? date('d.m.Y', $t) : (string) $d; }

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}
function csrf_verify(): bool
{
    return !empty($_SESSION['csrf']) && isset($_POST['csrf']) && hash_equals($_SESSION['csrf'], $_POST['csrf']);
}
?>

<?php
/*  ФАЙЛ: config.php  */
require __DIR__ . '/db.php';
require __DIR__ . '/functions.php';

session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();

$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
define('ADMIN_LOGIN', 'Admin26');
define('ADMIN_PASSWORD', 'Demo20');

$db = new Db($DB_HOST, $DB_USER, $DB_PASS, 'passenger_rf');
?>

<?php
/*  ФАЙЛ: index.php  */
require __DIR__ . '/config.php';

$page = $_POST['page'] ?? $_GET['page'] ?? 'index';
$flash = '';
$errors = [];
$old = [];

$is_post = $_SERVER['REQUEST_METHOD'] === 'POST';
if ($is_post && !csrf_verify()) {
    $errors['form'] = 'Сессия истекла, обновите страницу и повторите';
    $is_post = false;
}

if ($page === 'logout') { session_destroy(); header('Location: ?page=index'); exit; }
if (($_GET['adminout'] ?? '') === '1') { unset($_SESSION['is_admin']); redirect('admin'); }

if ($page === 'register' && $is_post) {
    foreach (['login', 'fio', 'phone', 'email', 'birth_date'] as $k) { $old[$k] = trim($_POST[$k] ?? ''); }
    $pwd = $_POST['password'] ?? '';
    if (!preg_match('/^[A-Za-z0-9]{6,}$/', $old['login'])) { $errors['login'] = 'Латиница и цифры, минимум 6 символов'; }
    elseif ($db->value('SELECT id FROM users WHERE login=?', [$old['login']])) { $errors['login'] = 'Логин уже занят'; }
    if (mb_strlen($pwd) < 8) { $errors['password'] = 'Пароль минимум 8 символов'; }
    if ($old['fio'] === '') { $errors['fio'] = 'Укажите ФИО'; }
    if ($old['phone'] === '') { $errors['phone'] = 'Укажите телефон'; }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) { $errors['email'] = 'Некорректный e-mail'; }
    if ($old['birth_date'] === '') { $errors['birth_date'] = 'Укажите дату рождения'; }
    if (!$errors) {
        $_SESSION['user_id'] = $db->insert(
            'INSERT INTO users(login,password,fio,phone,email,birth_date) VALUES(?,?,?,?,?,?)',
            [$old['login'], password_hash($pwd, PASSWORD_DEFAULT), $old['fio'], $old['phone'], $old['email'], $old['birth_date'] ?: null]
        );
        redirect('cabinet');
    }
}

if ($page === 'login' && $is_post) {
    $old['login'] = trim($_POST['login'] ?? '');
    $u = $db->one('SELECT id,password FROM users WHERE login=?', [$old['login']]);
    if ($u && password_verify($_POST['password'] ?? '', $u['password'])) {
        $_SESSION['user_id'] = (int) $u['id'];
        redirect('cabinet');
    }
    $errors['form'] = 'Неверный логин или пароль';
}

if ($page === 'application' && $is_post && uid()) {
    $old['item_id'] = $_POST['item_id'] ?? '';
    $old['start_date'] = trim($_POST['start_date'] ?? '');
    $old['payment_method'] = $_POST['payment_method'] ?? '';
    $date = null;
    if (!$db->value('SELECT id FROM items WHERE id=?', [$old['item_id']])) { $errors['item_id'] = 'Выберите вид транспорта'; }
    if (!preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $old['start_date'])) { $errors['start_date'] = 'Формат: ДД.ММ.ГГГГ'; }
    else { [$d, $m, $y] = explode('.', $old['start_date']); if (!checkdate((int) $m, (int) $d, (int) $y)) { $errors['start_date'] = 'Некорректная дата'; } else { $date = "$y-$m-$d"; } }
    if (!in_array($old['payment_method'], ['Банковская карта', 'Наличные', 'Счёт для юридических лиц'], true)) { $errors['payment_method'] = 'Выберите способ оплаты'; }
    if (!$errors) {
        $db->insert('INSERT INTO applications(user_id,item_id,start_date,payment_method,status) VALUES(?,?,?,?,?)',
            [uid(), $old['item_id'], $date, $old['payment_method'], 'Новая']);
        header('Location: ?page=cabinet&created=1'); exit;
    }
}

if ($page === 'cabinet' && $is_post && uid() && isset($_POST['application_id'])) {
    $aid = (int) $_POST['application_id'];
    $txt = trim($_POST['review_text'] ?? '');
    $a = $db->one('SELECT status FROM applications WHERE id=? AND user_id=?', [$aid, uid()]);
    if ($a && $a['status'] !== 'Новая' && $txt !== '' && !$db->value('SELECT id FROM reviews WHERE application_id=?', [$aid])) {
        $db->insert('INSERT INTO reviews(application_id,user_id,review_text) VALUES(?,?,?)', [$aid, uid(), $txt]);
    }
    redirect('cabinet');
}

if ($page === 'admin' && $is_post && isset($_POST['admin_login'])) {
    if ($_POST['admin_login'] === ADMIN_LOGIN && ($_POST['admin_password'] ?? '') === ADMIN_PASSWORD) { $_SESSION['is_admin'] = true; redirect('admin'); }
    $errors['form'] = 'Неверный логин или пароль администратора';
}
if ($page === 'admin' && $is_post && isset($_POST['change_status']) && !empty($_SESSION['is_admin'])) {
    if (in_array($_POST['status'] ?? '', ['Новая', 'Идет обучение', 'Обучение завершено'], true)) {
        $db->exec('UPDATE applications SET status=? WHERE id=?', [$_POST['status'], (int) $_POST['application_id']]);
        $flash = 'Статус обновлён';
    }
}

if (in_array($page, ['cabinet', 'application'], true) && !uid()) { redirect('login'); }

$badge = ['Новая' => 'b-new', 'Идет обучение' => 'b-prog', 'Обучение завершено' => 'b-done'];
$csrf = csrf_token();

$view = in_array($page, ['index', 'register', 'login', 'cabinet', 'application', 'admin'], true) ? $page : 'index';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
<title>Пассажирам.РФ</title>
<link rel="stylesheet" href="style.css">
<!--  При разбивке: оставь строку <link> выше, а блок <style>…</style> ниже вырежи в style.css  -->
<style>
:root {
    --primary: #0d8a8a;
    --dark:    #0a6a6a;
    --accent:  #f5a524;
    --bg:      #eef3f4;
    --surface: #ffffff;
    --text:    #14292a;
    --muted:   #6b8082;
    --border:  #dde8e8;
    --danger:  #d64550;
    --radius:  16px;
    --shadow:  0 6px 20px rgba(13, 138, 138, .10);
    --shadow-sm: 0 2px 8px rgba(20, 41, 42, .06);
}

* { margin: 0; padding: 0; box-sizing: border-box; }

body {
    font-family: "Rubik", system-ui, -apple-system, Segoe UI, Arial, sans-serif;
    font-size: 16px;
    line-height: 1.45;
    color: var(--text);
    background: var(--bg);
    display: flex;
    justify-content: center;
    min-height: 100vh;
    -webkit-font-smoothing: antialiased;
}

.screen {
    width: 100%;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    background: var(--bg);
}

.wrap { width: 100%; max-width: 640px; margin: 0 auto; }

h1 { font-size: 36px; font-weight: 700; line-height: 1.1; letter-spacing: -.5px; }
h2 { font-size: 24px; font-weight: 600; }
h3 { font-size: 18px; font-weight: 600; }
small, .small { font-size: 12px; color: var(--muted); }

a { color: var(--dark); text-decoration: none; }

.top {
    background: linear-gradient(135deg, var(--primary), var(--dark));
    color: #fff;
    position: sticky;
    top: 0;
    z-index: 10;
    box-shadow: var(--shadow-sm);
}
.top .wrap {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px;
}
.brand {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #fff;
    font-size: 18px;
    font-weight: 700;
}
.brand::before {
    content: "";
    width: 22px;
    height: 22px;
    border-radius: 7px;
    background: var(--accent);
    box-shadow: inset 0 0 0 3px rgba(255, 255, 255, .35);
}
.nav { display: flex; gap: 14px; }
.nav a { color: #fff; font-size: 13px; opacity: .9; }
.nav a:hover { opacity: 1; }

main { flex: 1; display: flex; justify-content: center; }
main .wrap { padding: 16px; display: flex; flex-direction: column; gap: 16px; }

.bot {
    background: var(--surface);
    border-top: 1px solid var(--border);
    font-size: 12px;
    color: var(--muted);
}
.bot .wrap {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 14px 16px;
}

.hero { text-align: center; padding: 8px 0 0; }
.hero p { color: var(--muted); margin-top: 6px; }

.card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 18px;
    box-shadow: var(--shadow-sm);
}
.card h2, .card h3 { margin-bottom: 12px; }

.slider { position: relative; border-radius: var(--radius); overflow: hidden; box-shadow: var(--shadow); }
.track { display: flex; transition: transform .45s cubic-bezier(.4, 0, .2, 1); }
.slide {
    min-width: 100%;
    aspect-ratio: 16 / 10;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 22px;
    font-weight: 700;
    letter-spacing: .5px;
    text-shadow: 0 2px 12px rgba(0, 0, 0, .25);
}
.sbtn {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    border: none;
    background: rgba(255, 255, 255, .9);
    color: var(--dark);
    width: 36px;
    height: 36px;
    border-radius: 50%;
    font-size: 20px;
    line-height: 1;
    cursor: pointer;
    box-shadow: var(--shadow-sm);
    transition: transform .15s;
}
.sbtn:hover { transform: translateY(-50%) scale(1.08); }
.sprev { left: 10px; }
.snext { right: 10px; }
.dots { position: absolute; bottom: 10px; left: 0; right: 0; display: flex; justify-content: center; gap: 6px; }
.dot { width: 7px; height: 7px; border-radius: 50%; background: rgba(255, 255, 255, .55); transition: width .25s; }
.dot.on { width: 18px; border-radius: 4px; background: #fff; }

.form { display: flex; flex-direction: column; gap: 14px; }
.field { display: flex; flex-direction: column; gap: 5px; }
.field label { font-size: 13px; font-weight: 500; color: var(--muted); }
.field input, .field select, .field textarea {
    font-family: inherit;
    font-size: 15px;
    padding: 11px 13px;
    border: 1.5px solid var(--border);
    border-radius: 11px;
    background: var(--surface);
    color: var(--text);
    transition: border-color .15s, box-shadow .15s;
}
.field input:focus, .field select:focus, .field textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(13, 138, 138, .14);
}
.field textarea { resize: vertical; }
.hint { font-size: 12px; color: var(--danger); }

.btn {
    font-family: inherit;
    font-size: 15px;
    font-weight: 600;
    padding: 13px 18px;
    border: none;
    border-radius: 12px;
    background: linear-gradient(135deg, var(--primary), var(--dark));
    color: #fff;
    cursor: pointer;
    text-align: center;
    box-shadow: var(--shadow);
    transition: transform .12s, box-shadow .12s, opacity .12s;
}
.btn:hover { transform: translateY(-1px); }
.btn:active { transform: translateY(0); opacity: .9; }
.btn.sm { padding: 8px 12px; font-size: 13px; border-radius: 9px; box-shadow: none; }
.btn.ghost { background: transparent; color: var(--dark); border: 1.5px solid var(--border); box-shadow: none; }

.link { text-align: center; font-size: 13px; }

.alert {
    padding: 11px 14px;
    border-radius: 11px;
    font-size: 13px;
    background: #fdecee;
    color: var(--danger);
    border: 1px solid #f6c9ce;
}

.badge { display: inline-block; padding: 4px 10px; border-radius: 999px; font-size: 11px; font-weight: 600; white-space: nowrap; }
.b-new { background: #eef1f4; color: var(--muted); }
.b-prog { background: #fff3da; color: #9a6b00; }
.b-done { background: #e3f6ec; color: #1f8a52; }

.list { display: flex; flex-direction: column; gap: 10px; }
.item { border: 1px solid var(--border); border-radius: 13px; padding: 13px; background: var(--surface); transition: box-shadow .15s; }
.item:hover { box-shadow: var(--shadow-sm); }
.item .row { display: flex; justify-content: space-between; align-items: center; gap: 8px; }
.meta { font-size: 12px; color: var(--muted); margin-top: 5px; }

.toolbar { display: flex; flex-wrap: wrap; gap: 8px; }
.toolbar input, .toolbar select {
    padding: 9px 11px;
    border: 1.5px solid var(--border);
    border-radius: 9px;
    font-size: 13px;
    font-family: inherit;
}
.table-wrap { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; font-size: 13px; }
th, td { text-align: left; padding: 9px 7px; border-bottom: 1px solid var(--border); vertical-align: top; }
th { color: var(--muted); font-weight: 600; }
.pag { display: flex; gap: 6px; justify-content: center; flex-wrap: wrap; margin-top: 14px; }
.pag a, .pag span { padding: 7px 11px; border: 1px solid var(--border); border-radius: 9px; font-size: 13px; }
.pag .on { background: var(--primary); color: #fff; border-color: var(--primary); }

.toast {
    position: fixed;
    left: 50%;
    bottom: 24px;
    transform: translateX(-50%);
    background: var(--text);
    color: #fff;
    padding: 12px 18px;
    border-radius: 12px;
    font-size: 13px;
    z-index: 50;
    box-shadow: 0 10px 30px rgba(0, 0, 0, .25);
    animation: toast-in .3s ease;
}
@keyframes toast-in { from { opacity: 0; transform: translate(-50%, 12px); } to { opacity: 1; transform: translate(-50%, 0); } }
</style>
</head>
<body>
<div class="screen">
<header class="top">
    <div class="wrap">
        <a class="brand" href="?page=index">Пассажирам.РФ</a>
        <nav class="nav">
            <a href="?page=index">Главная</a>
            <?php if (uid()): ?>
                <a href="?page=application">Заявка</a><a href="?page=cabinet">Кабинет</a><a href="?page=logout">Выход</a>
            <?php else: ?>
                <a href="?page=login">Вход</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
<main>
    <div class="wrap">
        <?php include __DIR__ . "/pages/$view.php"; ?>
    </div>
</main>
<footer class="bot"><div class="wrap"><span>© <?= date('Y') ?> Пассажирам.РФ</span><a href="?page=admin">Администрирование</a></div></footer>
</div>
<!--  При разбивке: замени блок <script>…</script> ниже на одну строку <script src="slider.js"></script>  -->
<script>
var idx = 0;
function render() {
    var t = document.querySelector('.track'); if (!t) return;
    var n = t.children.length; idx = (idx + n) % n;
    t.style.transform = 'translateX(-' + (idx * 100) + '%)';
    document.querySelectorAll('.dot').forEach(function (d, i) { d.classList.toggle('on', i === idx); });
}
function slide(s) { idx += s; render(); clearInterval(window.__t); window.__t = setInterval(function () { idx++; render(); }, 3000); }
if (document.querySelector('.track')) { render(); window.__t = setInterval(function () { idx++; render(); }, 3000); }
</script>
</body>
</html>

<?php
/* =====================================================================
   ДАЛЬШЕ — СТРАНИЦЫ. Каждый блок «ФАЙЛ: pages/<имя>.php» целиком скопируй
   в одноимённый файл в папке pages/. index.php подключает их через
   include __DIR__ . "/pages/$view.php".
   ===================================================================== */
?>

<?php /* ФАЙЛ: pages/index.php */
$items = $db->all('SELECT name,category FROM items ORDER BY id');
?>
<section class="hero"><h1>Пассажирам.РФ</h1><p>Курсы вождения городского пассажирского транспорта</p></section>
<div class="slider">
    <div class="track">
        <?php for ($i = 1; $i <= 4; $i++): ?>
            <div class="slide" style="background:linear-gradient(135deg,var(--primary),var(--accent))">Пассажирам.РФ · <?= $i ?></div>
        <?php endfor; ?>
    </div>
    <button class="sbtn sprev" type="button" onclick="slide(-1)">‹</button>
    <button class="sbtn snext" type="button" onclick="slide(1)">›</button>
    <div class="dots"><span class="dot on"></span><span class="dot"></span><span class="dot"></span><span class="dot"></span></div>
</div>
<section class="card">
    <h2>Виды транспорта</h2>
    <div class="list">
        <?php foreach ($items as $it): ?>
            <div class="item"><div class="row"><strong><?= h($it['name']) ?></strong></div><div class="meta"><?= h($it['category']) ?></div></div>
        <?php endforeach; ?>
    </div>
</section>
<a class="btn" href="?page=<?= uid() ? 'application' : 'register' ?>">Оставить заявку</a>

<?php /* ФАЙЛ: pages/register.php */ ?>
<div class="card">
    <h2>Регистрация</h2>
    <form class="form" method="post" novalidate>
        <input type="hidden" name="page" value="register">
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
        <div class="field"><label>Логин</label><input type="text" name="login" value="<?= h($old['login'] ?? '') ?>"><?php if (isset($errors['login'])): ?><span class="hint"><?= h($errors['login']) ?></span><?php endif; ?></div>
        <div class="field"><label>Пароль</label><input type="password" name="password"><?php if (isset($errors['password'])): ?><span class="hint"><?= h($errors['password']) ?></span><?php endif; ?></div>
        <div class="field"><label>ФИО</label><input type="text" name="fio" value="<?= h($old['fio'] ?? '') ?>"><?php if (isset($errors['fio'])): ?><span class="hint"><?= h($errors['fio']) ?></span><?php endif; ?></div>
        <div class="field"><label>Дата рождения</label><input type="date" name="birth_date" value="<?= h($old['birth_date'] ?? '') ?>"><?php if (isset($errors['birth_date'])): ?><span class="hint"><?= h($errors['birth_date']) ?></span><?php endif; ?></div>
        <div class="field"><label>Телефон</label><input type="text" name="phone" value="<?= h($old['phone'] ?? '') ?>"><?php if (isset($errors['phone'])): ?><span class="hint"><?= h($errors['phone']) ?></span><?php endif; ?></div>
        <div class="field"><label>E-mail</label><input type="email" name="email" value="<?= h($old['email'] ?? '') ?>"><?php if (isset($errors['email'])): ?><span class="hint"><?= h($errors['email']) ?></span><?php endif; ?></div>
        <?php if (isset($errors['form'])): ?><div class="alert"><?= h($errors['form']) ?></div><?php endif; ?>
        <button class="btn" type="submit">Зарегистрироваться</button>
        <div class="link"><a href="?page=login">Уже есть аккаунт? Вход</a></div>
    </form>
</div>

<?php /* ФАЙЛ: pages/login.php */ ?>
<div class="card">
    <h2>Вход</h2>
    <?php if (isset($errors['form'])): ?><div class="alert"><?= h($errors['form']) ?></div><?php endif; ?>
    <form class="form" method="post" novalidate>
        <input type="hidden" name="page" value="login">
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
        <div class="field"><label>Логин</label><input type="text" name="login" value="<?= h($old['login'] ?? '') ?>"></div>
        <div class="field"><label>Пароль</label><input type="password" name="password"></div>
        <button class="btn" type="submit">Войти</button>
        <div class="link"><a href="?page=register">Еще не зарегистрированы? Регистрация</a></div>
    </form>
</div>

<?php /* ФАЙЛ: pages/application.php */
$items = $db->all('SELECT id,name,category FROM items ORDER BY id');
?>
<div class="card">
    <h2>Оформление заявки</h2>
    <form class="form" method="post" novalidate>
        <input type="hidden" name="page" value="application">
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
        <div class="field"><label>Вид транспорта</label>
            <select name="item_id"><option value="">— выберите —</option>
            <?php foreach ($items as $it): ?><option value="<?= (int) $it['id'] ?>" <?= ($old['item_id'] ?? '') == $it['id'] ? 'selected' : '' ?>><?= h($it['name']) ?> (<?= h($it['category']) ?>)</option><?php endforeach; ?>
            </select><?php if (isset($errors['item_id'])): ?><span class="hint"><?= h($errors['item_id']) ?></span><?php endif; ?>
        </div>
        <div class="field"><label>Дата начала (ДД.ММ.ГГГГ)</label><input type="text" name="start_date" placeholder="01.09.2026" value="<?= h($old['start_date'] ?? '') ?>"><?php if (isset($errors['start_date'])): ?><span class="hint"><?= h($errors['start_date']) ?></span><?php endif; ?></div>
        <div class="field"><label>Способ оплаты</label>
            <select name="payment_method"><option value="">— выберите —</option>
            <?php foreach (['Банковская карта', 'Наличные', 'Счёт для юридических лиц'] as $p): ?><option value="<?= h($p) ?>" <?= ($old['payment_method'] ?? '') === $p ? 'selected' : '' ?>><?= h($p) ?></option><?php endforeach; ?>
            </select><?php if (isset($errors['payment_method'])): ?><span class="hint"><?= h($errors['payment_method']) ?></span><?php endif; ?>
        </div>
        <?php if (isset($errors['form'])): ?><div class="alert"><?= h($errors['form']) ?></div><?php endif; ?>
        <button class="btn" type="submit">Отправить заявку</button>
    </form>
</div>

<?php /* ФАЙЛ: pages/cabinet.php */
$apps = $db->all('SELECT a.id,a.start_date,a.payment_method,a.status,i.name item_name,r.review_text
    FROM applications a JOIN items i ON i.id=a.item_id LEFT JOIN reviews r ON r.application_id=a.id
    WHERE a.user_id=? ORDER BY a.created_at DESC', [uid()]);
$me = $db->one('SELECT login,fio FROM users WHERE id=?', [uid()]);
?>
<section class="hero"><h2>Здравствуйте, <?= h($me['fio'] ?: $me['login']) ?></h2></section>
<div class="slider">
    <div class="track">
        <?php for ($i = 1; $i <= 4; $i++): ?><div class="slide" style="background:linear-gradient(135deg,var(--primary),var(--accent))">Пассажирам.РФ · <?= $i ?></div><?php endfor; ?>
    </div>
    <button class="sbtn sprev" type="button" onclick="slide(-1)">‹</button>
    <button class="sbtn snext" type="button" onclick="slide(1)">›</button>
    <div class="dots"><span class="dot on"></span><span class="dot"></span><span class="dot"></span><span class="dot"></span></div>
</div>
<section class="card">
    <h2>Мои заявки</h2>
    <?php if (!$apps): ?><p class="small">Заявок пока нет. <a href="?page=application">Оформить</a></p>
    <?php else: ?>
    <div class="list">
        <?php foreach ($apps as $a): ?>
        <div class="item">
            <div class="row"><strong><?= h($a['item_name']) ?></strong><span class="badge <?= $badge[$a['status']] ?? 'b-new' ?>"><?= h($a['status']) ?></span></div>
            <div class="meta">Начало: <?= h(ru_date($a['start_date'])) ?> · <?= h($a['payment_method']) ?></div>
            <?php if ($a['review_text']): ?><p class="small">Ваш отзыв: <?= h($a['review_text']) ?></p>
            <?php elseif ($a['status'] !== 'Новая'): ?>
                <form class="form" method="post" style="margin-top:8px">
                    <input type="hidden" name="page" value="cabinet"><input type="hidden" name="application_id" value="<?= (int) $a['id'] ?>">
                    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                    <div class="field"><textarea name="review_text" rows="2" placeholder="Оставить отзыв"></textarea></div>
                    <button class="btn sm" type="submit">Отправить отзыв</button>
                </form>
            <?php else: ?><p class="small">Отзыв — после смены статуса администратором.</p><?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>
<a class="btn" href="?page=application">Новая заявка</a>
<?php if (isset($_GET['created'])): ?><div class="toast">Заявка отправлена</div><?php endif; ?>

<?php /* ФАЙЛ: pages/admin.php */ ?>
<?php if (empty($_SESSION['is_admin'])): ?>
    <div class="card">
        <h2>Панель администратора</h2>
        <?php if (isset($errors['form'])): ?><div class="alert"><?= h($errors['form']) ?></div><?php endif; ?>
        <form class="form" method="post" novalidate>
            <input type="hidden" name="page" value="admin">
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
            <div class="field"><label>Логин</label><input type="text" name="admin_login"></div>
            <div class="field"><label>Пароль</label><input type="password" name="admin_password"></div>
            <button class="btn" type="submit">Войти</button>
        </form>
    </div>
<?php else:
    $fStatus = $_GET['status'] ?? ''; $q = trim($_GET['q'] ?? ''); $sort = $_GET['sort'] ?? 'created'; $pg = max(1, (int) ($_GET['p'] ?? 1)); $per = 8;
    $order = ['created' => 'a.created_at DESC', 'date' => 'a.start_date ASC', 'status' => 'a.status ASC'][$sort] ?? 'a.created_at DESC';
    $w = []; $pr = [];
    if (in_array($fStatus, ['Новая', 'Идет обучение', 'Обучение завершено'], true)) { $w[] = 'a.status=?'; $pr[] = $fStatus; }
    if ($q !== '') { $w[] = '(u.login LIKE ? OR u.fio LIKE ?)'; $pr[] = "%$q%"; $pr[] = "%$q%"; }
    $ws = $w ? 'WHERE ' . implode(' AND ', $w) : '';
    $total = (int) $db->value("SELECT COUNT(*) FROM applications a JOIN users u ON u.id=a.user_id $ws", $pr);
    $pages = max(1, (int) ceil($total / $per)); $pg = min($pg, $pages); $off = ($pg - 1) * $per;
    $rows = $db->all("SELECT a.id,a.start_date,a.status,u.login,u.fio,i.name item_name
        FROM applications a JOIN users u ON u.id=a.user_id JOIN items i ON i.id=a.item_id
        $ws ORDER BY $order LIMIT $per OFFSET $off", $pr);
    $base = function ($o) use ($fStatus, $q, $sort) { return '?page=admin&' . http_build_query(array_merge(['status' => $fStatus, 'q' => $q, 'sort' => $sort], $o)); };
?>
    <div class="card">
        <div class="row" style="display:flex;justify-content:space-between;align-items:center"><h2>Заявки</h2><a class="btn ghost sm" href="?page=admin&adminout=1">Выход</a></div>
        <form class="toolbar" method="get" style="margin-top:12px">
            <input type="hidden" name="page" value="admin">
            <input type="text" name="q" placeholder="Поиск: логин / ФИО" value="<?= h($q) ?>">
            <select name="status"><option value="">Все статусы</option><?php foreach (['Новая', 'Идет обучение', 'Обучение завершено'] as $s): ?><option value="<?= h($s) ?>" <?= $fStatus === $s ? 'selected' : '' ?>><?= h($s) ?></option><?php endforeach; ?></select>
            <select name="sort"><option value="created" <?= $sort === 'created' ? 'selected' : '' ?>>По дате создания</option><option value="date" <?= $sort === 'date' ? 'selected' : '' ?>>По дате начала</option><option value="status" <?= $sort === 'status' ? 'selected' : '' ?>>По статусу</option></select>
            <button class="btn sm" type="submit">Применить</button>
        </form>
    </div>
    <div class="card">
        <div class="table-wrap">
            <table>
                <thead><tr><th>#</th><th>Пользователь</th><th>Вид транспорта</th><th>Дата</th><th>Статус</th></tr></thead>
                <tbody>
                <?php if (!$rows): ?><tr><td colspan="5" class="small">Ничего не найдено</td></tr><?php endif; ?>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= (int) $r['id'] ?></td>
                        <td><?= h($r['fio']) ?><br><span class="small"><?= h($r['login']) ?></span></td>
                        <td><?= h($r['item_name']) ?></td>
                        <td><?= h(ru_date($r['start_date'])) ?></td>
                        <td>
                            <form method="post" class="form">
                                <input type="hidden" name="page" value="admin"><input type="hidden" name="application_id" value="<?= (int) $r['id'] ?>">
                                <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                                <select name="status"><?php foreach (['Новая', 'Идет обучение', 'Обучение завершено'] as $s): ?><option value="<?= h($s) ?>" <?= $r['status'] === $s ? 'selected' : '' ?>><?= h($s) ?></option><?php endforeach; ?></select>
                                <button class="btn sm" type="submit" name="change_status" value="1">OK</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pages > 1): ?><div class="pag"><?php for ($i = 1; $i <= $pages; $i++): ?><?php if ($i === $pg): ?><span class="on"><?= $i ?></span><?php else: ?><a href="<?= h($base(['p' => $i])) ?>"><?= $i ?></a><?php endif; ?><?php endfor; ?></div><?php endif; ?>
    </div>
    <?php if ($flash): ?><div class="toast"><?= h($flash) ?></div><?php endif; ?>
<?php endif; ?>
