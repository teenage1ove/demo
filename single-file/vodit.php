<?php
/* =====================================================================
   ВАРИАНТ (ЗАМЕНИ ПОД ВАРИАНТ): название, БД, сущность, статусы, позиции
   ===================================================================== */
$THEME = [
    'site_name'   => 'Водить.РФ',
    'tagline'     => 'Курсы вождения речного транспорта',
    'db_name'     => 'vodit_rf',
    'item_label'  => 'Вид транспорта',
    'item_plural' => 'Виды транспорта',
    'show_birth'  => true,
    'statuses'    => ['Новая', 'Идет обучение', 'Обучение завершено'],
    'payments'    => ['Банковская карта', 'Наличные', 'Счёт для юридических лиц'],
    'items'       => [
        ['Вождение катера', 'Катер'],
        ['Вождение моторной лодки', 'Катер'],
        ['Управление круизным лайнером', 'Круизный лайнер'],
        ['Управление парусной яхтой', 'Яхта'],
        ['Управление моторной яхтой', 'Яхта'],
    ],
];
/* ЗАМЕНИ ПОД ВАРИАНТ: палитра (HEX) и радиус скругления */
$PAL = [
    'primary' => '#007BFF', 'dark' => '#0059b8', 'accent' => '#4CCFE0',
    'bg' => '#eef4fb', 'surface' => '#ffffff', 'text' => '#15243a',
    'muted' => '#6C757D', 'border' => '#d6e3f2', 'danger' => '#d64550',
    'radius' => '14px',
];
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
define('ADMIN_LOGIN', 'Admin26');
define('ADMIN_PASSWORD', 'Demo20');

/* =====================================================================
   MYSQL: схема и автосоздание базы данных при первом запуске
   ===================================================================== */
$SCHEMA = "
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  login VARCHAR(50) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  fio VARCHAR(200) NOT NULL,
  phone VARCHAR(20) NOT NULL,
  email VARCHAR(100) NOT NULL,
  birth_date DATE DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  category VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS applications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  item_id INT NOT NULL,
  start_date DATE NOT NULL,
  payment_method VARCHAR(50) NOT NULL,
  status VARCHAR(50) NOT NULL DEFAULT 'Новая',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (item_id) REFERENCES items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS reviews (
  id INT AUTO_INCREMENT PRIMARY KEY,
  application_id INT NOT NULL UNIQUE,
  user_id INT NOT NULL,
  review_text TEXT NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

class Db
{
    private mysqli $c;
    public function __construct(string $h, string $u, string $p, string $name, string $schema, array $seed)
    {
        $this->c = new mysqli($h, $u, $p);
        if ($this->c->connect_errno) {
            die('Ошибка подключения к MySQL: ' . $this->c->connect_error);
        }
        $this->c->set_charset('utf8mb4');
        $this->c->query("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $this->c->select_db($name);
        $this->c->multi_query($schema);
        while ($this->c->more_results() && $this->c->next_result()) {;}
        $this->seed($seed);
    }
    private function seed(array $items): void
    {
        if ((int) $this->value('SELECT COUNT(*) FROM items') === 0) {
            foreach ($items as $it) {
                $this->run('INSERT INTO items(name, category) VALUES(?,?)', $it);
            }
        }
        if ((int) $this->value('SELECT COUNT(*) FROM users') === 0) {
            $this->run('INSERT INTO users(login,password,fio,phone,email) VALUES(?,?,?,?,?)', [
                'test12', password_hash('test1234', PASSWORD_DEFAULT),
                'Наумова Софья Михайловна', '79998567744', 'test1@mail.ru',
            ]);
        }
    }
    private function run(string $sql, array $p): mysqli_stmt
    {
        $st = $this->c->prepare($sql);
        if ($p) { $st->bind_param(str_repeat('s', count($p)), ...$p); }
        $st->execute();
        return $st;
    }
    public function all(string $sql, array $p = []): array { return $this->run($sql, $p)->get_result()->fetch_all(MYSQLI_ASSOC); }
    public function one(string $sql, array $p = []): ?array { return $this->run($sql, $p)->get_result()->fetch_assoc() ?: null; }
    public function value(string $sql, array $p = []) { $r = $this->run($sql, $p)->get_result()->fetch_row(); return $r ? $r[0] : null; }
    public function insert(string $sql, array $p = []): int { return (int) $this->run($sql, $p)->insert_id; }
    public function exec(string $sql, array $p = []): int { return (int) $this->run($sql, $p)->affected_rows; }
}

/* =====================================================================
   PHP: логика — сессии, роутер, обработка форм
   ===================================================================== */
session_start();
$db = new Db($DB_HOST, $DB_USER, $DB_PASS, $THEME['db_name'], $SCHEMA, $THEME['items']);

function h($v): string { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
function uid(): ?int { return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null; }
function ru_date(?string $d): string { $t = $d ? strtotime($d) : false; return $t ? date('d.m.Y', $t) : (string) $d; }
function redirect(string $to): void { header("Location: ?page=$to"); exit; }

$page = $_POST['page'] ?? $_GET['page'] ?? 'index';
$flash = '';
$errors = [];
$old = [];

/* --- выход --- */
if ($page === 'logout') { session_destroy(); header('Location: ?page=index'); exit; }
if (($_GET['adminout'] ?? '') === '1') { unset($_SESSION['is_admin']); redirect('admin'); }

/* --- регистрация --- */
if ($page === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (['login', 'fio', 'phone', 'email', 'birth_date'] as $k) { $old[$k] = trim($_POST[$k] ?? ''); }
    $pwd = $_POST['password'] ?? '';
    if (!preg_match('/^[A-Za-z0-9]{6,}$/', $old['login'])) { $errors['login'] = 'Латиница и цифры, минимум 6 символов'; }
    elseif ($db->value('SELECT id FROM users WHERE login=?', [$old['login']])) { $errors['login'] = 'Логин уже занят'; }
    if (mb_strlen($pwd) < 8) { $errors['password'] = 'Пароль минимум 8 символов'; }
    if ($old['fio'] === '') { $errors['fio'] = 'Укажите ФИО'; }
    if ($old['phone'] === '') { $errors['phone'] = 'Укажите телефон'; }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) { $errors['email'] = 'Некорректный e-mail'; }
    if ($THEME['show_birth'] && $old['birth_date'] === '') { $errors['birth_date'] = 'Укажите дату рождения'; }
    if (!$errors) {
        $_SESSION['user_id'] = $db->insert(
            'INSERT INTO users(login,password,fio,phone,email,birth_date) VALUES(?,?,?,?,?,?)',
            [$old['login'], password_hash($pwd, PASSWORD_DEFAULT), $old['fio'], $old['phone'], $old['email'], $old['birth_date'] ?: null]
        );
        redirect('cabinet');
    }
}

/* --- вход --- */
if ($page === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['login'] = trim($_POST['login'] ?? '');
    $u = $db->one('SELECT id,password FROM users WHERE login=?', [$old['login']]);
    if ($u && password_verify($_POST['password'] ?? '', $u['password'])) {
        $_SESSION['user_id'] = (int) $u['id'];
        redirect('cabinet');
    }
    $errors['form'] = 'Неверный логин или пароль';
}

/* --- оформление заявки --- */
if ($page === 'application' && $_SERVER['REQUEST_METHOD'] === 'POST' && uid()) {
    $old['item_id'] = $_POST['item_id'] ?? '';
    $old['start_date'] = trim($_POST['start_date'] ?? '');
    $old['payment_method'] = $_POST['payment_method'] ?? '';
    $date = null;
    if (!$db->value('SELECT id FROM items WHERE id=?', [$old['item_id']])) { $errors['item_id'] = 'Выберите ' . mb_strtolower($THEME['item_label']); }
    if (!preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $old['start_date'])) { $errors['start_date'] = 'Формат: ДД.ММ.ГГГГ'; }
    else { [$d, $m, $y] = explode('.', $old['start_date']); if (!checkdate((int) $m, (int) $d, (int) $y)) { $errors['start_date'] = 'Некорректная дата'; } else { $date = "$y-$m-$d"; } }
    if (!in_array($old['payment_method'], $THEME['payments'], true)) { $errors['payment_method'] = 'Выберите способ оплаты'; }
    if (!$errors) {
        $db->insert('INSERT INTO applications(user_id,item_id,start_date,payment_method,status) VALUES(?,?,?,?,?)',
            [uid(), $old['item_id'], $date, $old['payment_method'], $THEME['statuses'][0]]);
        header('Location: ?page=cabinet&created=1'); exit;
    }
}

/* --- отзыв --- */
if ($page === 'cabinet' && $_SERVER['REQUEST_METHOD'] === 'POST' && uid() && isset($_POST['application_id'])) {
    $aid = (int) $_POST['application_id'];
    $txt = trim($_POST['review_text'] ?? '');
    $a = $db->one('SELECT status FROM applications WHERE id=? AND user_id=?', [$aid, uid()]);
    if ($a && $a['status'] !== $THEME['statuses'][0] && $txt !== '' && !$db->value('SELECT id FROM reviews WHERE application_id=?', [$aid])) {
        $db->insert('INSERT INTO reviews(application_id,user_id,review_text) VALUES(?,?,?)', [$aid, uid(), $txt]);
    }
    redirect('cabinet');
}

/* --- админ: вход и смена статуса --- */
if ($page === 'admin' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_login'])) {
    if ($_POST['admin_login'] === ADMIN_LOGIN && ($_POST['admin_password'] ?? '') === ADMIN_PASSWORD) { $_SESSION['is_admin'] = true; redirect('admin'); }
    $errors['form'] = 'Неверный логин или пароль администратора';
}
if ($page === 'admin' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_status']) && !empty($_SESSION['is_admin'])) {
    if (in_array($_POST['status'] ?? '', $THEME['statuses'], true)) {
        $db->exec('UPDATE applications SET status=? WHERE id=?', [$_POST['status'], (int) $_POST['application_id']]);
        $flash = 'Статус обновлён';
    }
}

/* доступ только для авторизованных */
if (in_array($page, ['cabinet', 'application'], true) && !uid()) { redirect('login'); }

$badge = [$THEME['statuses'][0] => 'b-new', $THEME['statuses'][1] => 'b-prog', $THEME['statuses'][2] => 'b-done'];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
<title><?= h($THEME['site_name']) ?></title>
<!-- =====================================================================
     CSS: оформление (палитра берётся из $PAL, мобильный экран 390x844)
     ===================================================================== -->
<style>
:root{
  --primary:<?= $PAL['primary'] ?>;--dark:<?= $PAL['dark'] ?>;--accent:<?= $PAL['accent'] ?>;
  --bg:<?= $PAL['bg'] ?>;--surface:<?= $PAL['surface'] ?>;--text:<?= $PAL['text'] ?>;
  --muted:<?= $PAL['muted'] ?>;--border:<?= $PAL['border'] ?>;--danger:<?= $PAL['danger'] ?>;--radius:<?= $PAL['radius'] ?>;
}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:"Rubik",system-ui,Arial,sans-serif;font-size:16px;color:var(--text);background:var(--bg);display:flex;justify-content:center;min-height:100vh}
.screen{width:100%;max-width:390px;min-height:100vh;display:flex;flex-direction:column;box-shadow:0 0 24px rgba(0,0,0,.08);background:var(--bg)}
h1{font-size:36px;line-height:1.1}h2{font-size:24px}h3{font-size:18px}small,.small{font-size:12px;color:var(--muted)}
a{color:var(--dark);text-decoration:none}
header.top{display:flex;align-items:center;justify-content:space-between;padding:14px 16px;background:var(--primary);color:#fff;position:sticky;top:0;z-index:10}
.brand{color:#fff;font-size:18px;font-weight:600}
nav a{color:#fff;font-size:13px;margin-left:12px}
main{flex:1;padding:16px;display:flex;flex-direction:column;gap:16px}
footer.bot{display:flex;justify-content:space-between;padding:12px 16px;background:var(--surface);border-top:1px solid var(--border);font-size:12px;color:var(--muted)}
.hero{text-align:center}.hero p{color:var(--muted);margin-top:8px}
.card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:16px}
.card h2,.card h3{margin-bottom:10px}
.slider{position:relative;border-radius:var(--radius);overflow:hidden;background:#000}
.track{display:flex;transition:transform .4s ease}
.slide{min-width:100%;aspect-ratio:16/10;display:flex;align-items:center;justify-content:center;color:#fff;font-size:22px;font-weight:600}
.sbtn{position:absolute;top:50%;transform:translateY(-50%);border:none;background:rgba(0,0,0,.45);color:#fff;width:34px;height:34px;border-radius:50%;font-size:18px;cursor:pointer}
.sprev{left:8px}.snext{right:8px}
.dots{position:absolute;bottom:8px;left:0;right:0;display:flex;justify-content:center;gap:6px}
.dot{width:8px;height:8px;border-radius:50%;background:rgba(255,255,255,.5)}.dot.on{background:#fff}
.form{display:flex;flex-direction:column;gap:12px}
.field{display:flex;flex-direction:column;gap:4px}
.field label{font-size:13px;font-weight:500}
.field input,.field select,.field textarea{font-family:inherit;font-size:15px;padding:10px 12px;border:1px solid var(--border);border-radius:10px;background:var(--surface);color:var(--text)}
.field input:focus,.field select:focus,.field textarea:focus{outline:none;border-color:var(--primary)}
.hint{font-size:12px;color:var(--danger)}
.btn{font-family:inherit;font-size:15px;font-weight:500;padding:12px 16px;border:none;border-radius:10px;background:var(--primary);color:#fff;cursor:pointer;text-align:center}
.btn:hover{background:var(--dark)}.btn.sm{padding:7px 10px;font-size:13px}.btn.ghost{background:transparent;color:var(--dark);border:1px solid var(--border)}
.link{text-align:center;font-size:13px}
.alert{padding:10px 12px;border-radius:10px;font-size:13px;background:#fdecee;color:var(--danger);border:1px solid #f6c9ce}
.badge{display:inline-block;padding:3px 8px;border-radius:999px;font-size:11px;font-weight:500}
.b-new{background:#eef1f4;color:var(--muted)}.b-prog{background:#fff3da;color:#9a6b00}.b-done{background:#e7f7ef;color:var(--dark)}
.list{display:flex;flex-direction:column;gap:10px}
.item{border:1px solid var(--border);border-radius:12px;padding:12px;background:var(--surface)}
.item .row{display:flex;justify-content:space-between;align-items:center;gap:8px}
.meta{font-size:12px;color:var(--muted);margin-top:4px}
.toolbar{display:flex;flex-wrap:wrap;gap:8px}
.toolbar input,.toolbar select{padding:8px 10px;border:1px solid var(--border);border-radius:8px;font-size:13px;font-family:inherit}
table{width:100%;border-collapse:collapse;font-size:13px}
th,td{text-align:left;padding:8px 6px;border-bottom:1px solid var(--border);vertical-align:top}
.pag{display:flex;gap:6px;justify-content:center;flex-wrap:wrap;margin-top:12px}
.pag a,.pag span{padding:6px 10px;border:1px solid var(--border);border-radius:8px;font-size:13px}
.pag .on{background:var(--primary);color:#fff;border-color:var(--primary)}
.toast{position:fixed;left:50%;bottom:24px;transform:translateX(-50%);background:var(--text);color:#fff;padding:10px 16px;border-radius:10px;font-size:13px;z-index:50}
</style>
</head>
<body>
<div class="screen">
<header class="top">
    <a class="brand" href="?page=index"><?= h($THEME['site_name']) ?></a>
    <nav>
        <a href="?page=index">Главная</a>
        <?php if (uid()): ?>
            <a href="?page=application">Заявка</a><a href="?page=cabinet">Кабинет</a><a href="?page=logout">Выход</a>
        <?php else: ?>
            <a href="?page=login">Вход</a>
        <?php endif; ?>
    </nav>
</header>
<main>
<?php
/* =====================================================================
   HTML: страницы (рендер по $page)
   ===================================================================== */
if ($page === 'index'):
    $items = $db->all('SELECT name,category FROM items ORDER BY id');
?>
    <section class="hero"><h1><?= h($THEME['site_name']) ?></h1><p><?= h($THEME['tagline']) ?></p></section>
    <div class="slider">
        <div class="track">
            <?php for ($i = 1; $i <= 4; $i++): ?>
                <div class="slide" style="background:linear-gradient(135deg,var(--primary),var(--accent))"><?= h($THEME['site_name']) ?> · <?= $i ?></div>
            <?php endfor; ?>
        </div>
        <button class="sbtn sprev" type="button" onclick="slide(-1)">‹</button>
        <button class="sbtn snext" type="button" onclick="slide(1)">›</button>
        <div class="dots"><span class="dot on"></span><span class="dot"></span><span class="dot"></span><span class="dot"></span></div>
    </div>
    <section class="card">
        <h2><?= h($THEME['item_plural']) ?></h2>
        <div class="list">
            <?php foreach ($items as $it): ?>
                <div class="item"><div class="row"><strong><?= h($it['name']) ?></strong></div><div class="meta"><?= h($it['category']) ?></div></div>
            <?php endforeach; ?>
        </div>
    </section>
    <a class="btn" href="?page=<?= uid() ? 'application' : 'register' ?>">Оставить заявку</a>

<?php elseif ($page === 'register'): ?>
    <div class="card">
        <h2>Регистрация</h2>
        <form class="form" method="post" novalidate>
            <input type="hidden" name="page" value="register">
            <div class="field"><label>Логин</label><input type="text" name="login" value="<?= h($old['login'] ?? '') ?>"><?php if (isset($errors['login'])): ?><span class="hint"><?= h($errors['login']) ?></span><?php endif; ?></div>
            <div class="field"><label>Пароль</label><input type="password" name="password"><?php if (isset($errors['password'])): ?><span class="hint"><?= h($errors['password']) ?></span><?php endif; ?></div>
            <div class="field"><label>ФИО</label><input type="text" name="fio" value="<?= h($old['fio'] ?? '') ?>"><?php if (isset($errors['fio'])): ?><span class="hint"><?= h($errors['fio']) ?></span><?php endif; ?></div>
            <?php if ($THEME['show_birth']): ?>
            <div class="field"><label>Дата рождения</label><input type="date" name="birth_date" value="<?= h($old['birth_date'] ?? '') ?>"><?php if (isset($errors['birth_date'])): ?><span class="hint"><?= h($errors['birth_date']) ?></span><?php endif; ?></div>
            <?php endif; ?>
            <div class="field"><label>Телефон</label><input type="text" name="phone" value="<?= h($old['phone'] ?? '') ?>"><?php if (isset($errors['phone'])): ?><span class="hint"><?= h($errors['phone']) ?></span><?php endif; ?></div>
            <div class="field"><label>E-mail</label><input type="email" name="email" value="<?= h($old['email'] ?? '') ?>"><?php if (isset($errors['email'])): ?><span class="hint"><?= h($errors['email']) ?></span><?php endif; ?></div>
            <button class="btn" type="submit">Зарегистрироваться</button>
            <div class="link"><a href="?page=login">Уже есть аккаунт? Вход</a></div>
        </form>
    </div>

<?php elseif ($page === 'login'): ?>
    <div class="card">
        <h2>Вход</h2>
        <?php if (isset($errors['form'])): ?><div class="alert"><?= h($errors['form']) ?></div><?php endif; ?>
        <form class="form" method="post" novalidate>
            <input type="hidden" name="page" value="login">
            <div class="field"><label>Логин</label><input type="text" name="login" value="<?= h($old['login'] ?? '') ?>"></div>
            <div class="field"><label>Пароль</label><input type="password" name="password"></div>
            <button class="btn" type="submit">Войти</button>
            <div class="link"><a href="?page=register">Еще не зарегистрированы? Регистрация</a></div>
        </form>
    </div>

<?php elseif ($page === 'application'):
    $items = $db->all('SELECT id,name,category FROM items ORDER BY id');
?>
    <div class="card">
        <h2>Оформление заявки</h2>
        <form class="form" method="post" novalidate>
            <input type="hidden" name="page" value="application">
            <div class="field"><label><?= h($THEME['item_label']) ?></label>
                <select name="item_id"><option value="">— выберите —</option>
                <?php foreach ($items as $it): ?><option value="<?= (int) $it['id'] ?>" <?= ($old['item_id'] ?? '') == $it['id'] ? 'selected' : '' ?>><?= h($it['name']) ?> (<?= h($it['category']) ?>)</option><?php endforeach; ?>
                </select><?php if (isset($errors['item_id'])): ?><span class="hint"><?= h($errors['item_id']) ?></span><?php endif; ?>
            </div>
            <div class="field"><label>Дата начала (ДД.ММ.ГГГГ)</label><input type="text" name="start_date" placeholder="01.09.2026" value="<?= h($old['start_date'] ?? '') ?>"><?php if (isset($errors['start_date'])): ?><span class="hint"><?= h($errors['start_date']) ?></span><?php endif; ?></div>
            <div class="field"><label>Способ оплаты</label>
                <select name="payment_method"><option value="">— выберите —</option>
                <?php foreach ($THEME['payments'] as $p): ?><option value="<?= h($p) ?>" <?= ($old['payment_method'] ?? '') === $p ? 'selected' : '' ?>><?= h($p) ?></option><?php endforeach; ?>
                </select><?php if (isset($errors['payment_method'])): ?><span class="hint"><?= h($errors['payment_method']) ?></span><?php endif; ?>
            </div>
            <button class="btn" type="submit">Отправить заявку</button>
        </form>
    </div>

<?php elseif ($page === 'cabinet'):
    $apps = $db->all('SELECT a.id,a.start_date,a.payment_method,a.status,i.name item_name,r.review_text
        FROM applications a JOIN items i ON i.id=a.item_id LEFT JOIN reviews r ON r.application_id=a.id
        WHERE a.user_id=? ORDER BY a.created_at DESC', [uid()]);
    $me = $db->one('SELECT login,fio FROM users WHERE id=?', [uid()]);
?>
    <section class="hero"><h2>Здравствуйте, <?= h($me['fio'] ?: $me['login']) ?></h2></section>
    <div class="slider">
        <div class="track">
            <?php for ($i = 1; $i <= 4; $i++): ?><div class="slide" style="background:linear-gradient(135deg,var(--primary),var(--accent))"><?= h($THEME['site_name']) ?> · <?= $i ?></div><?php endfor; ?>
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
                <?php elseif ($a['status'] !== $THEME['statuses'][0]): ?>
                    <form class="form" method="post" style="margin-top:8px">
                        <input type="hidden" name="page" value="cabinet"><input type="hidden" name="application_id" value="<?= (int) $a['id'] ?>">
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

<?php elseif ($page === 'admin'):
    if (empty($_SESSION['is_admin'])): ?>
        <div class="card">
            <h2>Панель администратора</h2>
            <?php if (isset($errors['form'])): ?><div class="alert"><?= h($errors['form']) ?></div><?php endif; ?>
            <form class="form" method="post" novalidate>
                <input type="hidden" name="page" value="admin">
                <div class="field"><label>Логин</label><input type="text" name="admin_login"></div>
                <div class="field"><label>Пароль</label><input type="password" name="admin_password"></div>
                <button class="btn" type="submit">Войти</button>
            </form>
        </div>
    <?php else:
        $fStatus = $_GET['status'] ?? ''; $q = trim($_GET['q'] ?? ''); $sort = $_GET['sort'] ?? 'created'; $pg = max(1, (int) ($_GET['p'] ?? 1)); $per = 8;
        $order = ['created' => 'a.created_at DESC', 'date' => 'a.start_date ASC', 'status' => 'a.status ASC'][$sort] ?? 'a.created_at DESC';
        $w = []; $pr = [];
        if (in_array($fStatus, $THEME['statuses'], true)) { $w[] = 'a.status=?'; $pr[] = $fStatus; }
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
            <form class="toolbar" method="get" style="margin-top:10px">
                <input type="hidden" name="page" value="admin">
                <input type="text" name="q" placeholder="Поиск: логин / ФИО" value="<?= h($q) ?>">
                <select name="status"><option value="">Все статусы</option><?php foreach ($THEME['statuses'] as $s): ?><option value="<?= h($s) ?>" <?= $fStatus === $s ? 'selected' : '' ?>><?= h($s) ?></option><?php endforeach; ?></select>
                <select name="sort"><option value="created" <?= $sort === 'created' ? 'selected' : '' ?>>По дате создания</option><option value="date" <?= $sort === 'date' ? 'selected' : '' ?>>По дате начала</option><option value="status" <?= $sort === 'status' ? 'selected' : '' ?>>По статусу</option></select>
                <button class="btn sm" type="submit">Применить</button>
            </form>
        </div>
        <div class="card">
            <table>
                <thead><tr><th>#</th><th>Пользователь</th><th><?= h($THEME['item_label']) ?></th><th>Дата</th><th>Статус</th></tr></thead>
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
                                <select name="status"><?php foreach ($THEME['statuses'] as $s): ?><option value="<?= h($s) ?>" <?= $r['status'] === $s ? 'selected' : '' ?>><?= h($s) ?></option><?php endforeach; ?></select>
                                <button class="btn sm" type="submit" name="change_status" value="1">OK</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php if ($pages > 1): ?><div class="pag"><?php for ($i = 1; $i <= $pages; $i++): ?><?php if ($i === $pg): ?><span class="on"><?= $i ?></span><?php else: ?><a href="<?= h($base(['p' => $i])) ?>"><?= $i ?></a><?php endif; ?><?php endfor; ?></div><?php endif; ?>
        </div>
        <?php if ($flash): ?><div class="toast"><?= h($flash) ?></div><?php endif; ?>
    <?php endif; ?>
<?php endif; ?>
</main>
<footer class="bot"><span>© <?= date('Y') ?> <?= h($THEME['site_name']) ?></span><a href="?page=admin">Администрирование</a></footer>
</div>
<!-- JS: слайдер (автосмена 3с, вперёд/назад) -->
<script>
var idx=0;function render(){var t=document.querySelector('.track');if(!t)return;var n=t.children.length;idx=(idx+n)%n;t.style.transform='translateX(-'+(idx*100)+'%)';document.querySelectorAll('.dot').forEach(function(d,i){d.classList.toggle('on',i===idx)});}
function slide(s){idx+=s;render();clearInterval(window.__t);window.__t=setInterval(function(){idx++;render()},3000);}
if(document.querySelector('.track')){render();window.__t=setInterval(function(){idx++;render()},3000);}
</script>
</body>
</html>
