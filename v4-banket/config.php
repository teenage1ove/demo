<?php
session_start();

require __DIR__ . '/theme.php';
require __DIR__ . '/db.php';

$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';

$db = new Db($DB_HOST, $DB_USER, $DB_PASS, $THEME['db_name']);

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function current_user_id(): ?int
{
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

function require_login(): void
{
    if (!current_user_id()) {
        header('Location: login.php');
        exit;
    }
}

function require_admin(): void
{
    if (empty($_SESSION['is_admin'])) {
        header('Location: admin.php');
        exit;
    }
}

function ru_date(?string $date): string
{
    if (!$date) {
        return '';
    }
    $ts = strtotime($date);
    return $ts ? date('d.m.Y', $ts) : $date;
}
