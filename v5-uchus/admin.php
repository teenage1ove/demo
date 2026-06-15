<?php
require __DIR__ . '/config.php';

if (isset($_GET['logout'])) {
    unset($_SESSION['is_admin']);
    header('Location: admin.php');
    exit;
}

$login_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_login'])) {
    if ($_POST['admin_login'] === ADMIN_LOGIN && ($_POST['admin_password'] ?? '') === ADMIN_PASSWORD) {
        $_SESSION['is_admin'] = true;
        header('Location: admin.php');
        exit;
    }
    $login_error = 'Неверный логин или пароль администратора';
}

if (empty($_SESSION['is_admin'])) {
    $page_title = 'Вход администратора';
    require __DIR__ . '/includes/header.php';
    ?>
    <div class="card">
        <h2>Панель администратора</h2>
        <?php if ($login_error): ?><div class="alert alert--error"><?= h($login_error) ?></div><?php endif; ?>
        <form class="form" method="post" novalidate>
            <div class="field">
                <label>Логин</label>
                <input type="text" name="admin_login">
            </div>
            <div class="field">
                <label>Пароль</label>
                <input type="password" name="admin_password">
            </div>
            <button class="btn" type="submit">Войти</button>
        </form>
    </div>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

// смена статуса
$updated = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_status'])) {
    $appId = (int) $_POST['application_id'];
    $status = $_POST['status'] ?? '';
    if (in_array($status, $THEME['statuses'], true)) {
        $db->exec('UPDATE applications SET status = ? WHERE id = ?', [$status, $appId]);
        $updated = true;
    }
}

// фильтры / поиск / сортировка / пагинация
$filterStatus = $_GET['status'] ?? '';
$search = trim($_GET['q'] ?? '');
$sort = $_GET['sort'] ?? 'created';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 8;

$sortMap = [
    'created' => 'a.created_at DESC',
    'date'    => 'a.start_date ASC',
    'status'  => 'a.status ASC',
];
$orderBy = $sortMap[$sort] ?? $sortMap['created'];

$where = [];
$params = [];
if (in_array($filterStatus, $THEME['statuses'], true)) {
    $where[] = 'a.status = ?';
    $params[] = $filterStatus;
}
if ($search !== '') {
    $where[] = '(u.login LIKE ? OR u.fio LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = (int) $db->value(
    "SELECT COUNT(*) FROM applications a JOIN users u ON u.id = a.user_id $whereSql",
    $params
);
$pages = max(1, (int) ceil($total / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;

$rows = $db->all(
    "SELECT a.id, a.start_date, a.payment_method, a.status, a.created_at,
            u.login, u.fio, i.name AS item_name
     FROM applications a
     JOIN users u ON u.id = a.user_id
     JOIN items i ON i.id = a.item_id
     $whereSql
     ORDER BY $orderBy
     LIMIT $perPage OFFSET $offset",
    $params
);

function admin_link(array $over): string
{
    $q = array_merge(['status' => $_GET['status'] ?? '', 'q' => $_GET['q'] ?? '', 'sort' => $_GET['sort'] ?? 'created', 'page' => $_GET['page'] ?? 1], $over);
    return 'admin.php?' . http_build_query($q);
}

$page_title = 'Заявки';
require __DIR__ . '/includes/header.php';
?>
<div class="card">
    <div class="app-item__top">
        <h2>Заявки</h2>
        <a class="btn btn--ghost btn--sm" href="admin.php?logout=1">Выход</a>
    </div>

    <form class="toolbar" method="get" style="margin-top:10px">
        <input type="text" name="q" placeholder="Поиск: логин / ФИО" value="<?= h($search) ?>">
        <select name="status">
            <option value="">Все статусы</option>
            <?php foreach ($THEME['statuses'] as $st): ?>
                <option value="<?= h($st) ?>" <?= $filterStatus === $st ? 'selected' : '' ?>><?= h($st) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="sort">
            <option value="created" <?= $sort === 'created' ? 'selected' : '' ?>>По дате создания</option>
            <option value="date" <?= $sort === 'date' ? 'selected' : '' ?>>По дате начала</option>
            <option value="status" <?= $sort === 'status' ? 'selected' : '' ?>>По статусу</option>
        </select>
        <button class="btn btn--sm" type="submit">Применить</button>
    </form>
</div>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>#</th><th>Пользователь</th><th><?= h($THEME['item_label']) ?></th><th>Дата</th><th>Статус</th></tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="5" class="small">Ничего не найдено</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= (int) $row['id'] ?></td>
                    <td><?= h($row['fio']) ?><br><span class="small"><?= h($row['login']) ?></span></td>
                    <td><?= h($row['item_name']) ?></td>
                    <td><?= h(ru_date($row['start_date'])) ?></td>
                    <td>
                        <form method="post" class="form">
                            <input type="hidden" name="application_id" value="<?= (int) $row['id'] ?>">
                            <select name="status">
                                <?php foreach ($THEME['statuses'] as $st): ?>
                                    <option value="<?= h($st) ?>" <?= $row['status'] === $st ? 'selected' : '' ?>><?= h($st) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn btn--sm" type="submit" name="change_status" value="1">OK</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pages > 1): ?>
        <div class="pagination" style="margin-top:12px">
            <?php for ($p = 1; $p <= $pages; $p++): ?>
                <?php if ($p === $page): ?>
                    <span class="is-active"><?= $p ?></span>
                <?php else: ?>
                    <a href="<?= h(admin_link(['page' => $p])) ?>"><?= $p ?></a>
                <?php endif; ?>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

<?php if ($updated): ?><div class="toast">Статус обновлён</div><?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
