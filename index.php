<?php
session_start();

require_once 'db/db_connect.php';

// Получаем текущую страницу из URL, по умолчанию 'dashboard'
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard2';

// Список разрешенных страниц (белый список для безопасности)
$allowed_pages = ['dashboard2', 'history', 'admin'];
if (!in_array($page, $allowed_pages)) {
    $page = 'dashboard2';
}



// Проверяем, передан ли ID файла для показа инфо
$show_info = isset($_GET['info_id']) ? (int)$_GET['info_id'] : null;

// Если ID есть, меняем класс панели с hidden на пустой
$info_panel_class = $show_info ? '' : 'hidden';


?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fi2 - <?= ucfirst($page) ?></title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
</head>
<body>

    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <?php include "pages/{$page}.php"; ?>
        </main>

        <?php include 'includes/overlays.php'; ?>
    </div>


    <script src="script.js"></script>
</body>
</html>