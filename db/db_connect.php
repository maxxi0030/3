<?php
$host = 'localhost';
$dbname = 'file_manager';
$user = 'postgres';
$password = 'zxc';

try {
    $pdo = new PDO("pgsql:host=$host;dbname=$dbname", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // echo "Подключение успешно!";
} catch(PDOException $e) {
    echo "Ошибка подключения: " . $e->getMessage();
}
?>