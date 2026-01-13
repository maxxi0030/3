<?php
require 'db/db_connect.php';

if ($_POST['action'] === 'rename') {
    $stmt = $db->prepare("
        UPDATE clients
        SET name = ?, updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$_POST['name'], $_POST['id']]);
}

if ($_POST['action'] === 'delete') {
    $stmt = $db->prepare("DELETE FROM clients WHERE id=?");
    $stmt->execute([$_POST['id']]);
}
?>