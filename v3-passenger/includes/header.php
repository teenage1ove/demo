<?php
require_once __DIR__ . '/../config.php';
$page_title = $page_title ?? $THEME['site_name'];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title><?= h($page_title) ?> — <?= h($THEME['site_name']) ?></title>
    <link rel="stylesheet" href="css/theme.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="screen">
    <header class="app-header">
        <a class="brand" href="index.php">
            <img src="assets/logo.png" alt="logo" class="brand__logo">
            <span class="brand__name"><?= h($THEME['site_name']) ?></span>
        </a>
        <nav class="nav">
            <a href="index.php">Главная</a>
            <?php if (current_user_id()): ?>
                <a href="application.php">Заявка</a>
                <a href="cabinet.php">Кабинет</a>
                <a href="logout.php">Выход</a>
            <?php else: ?>
                <a href="login.php">Вход</a>
            <?php endif; ?>
        </nav>
    </header>
    <main class="app-main">
