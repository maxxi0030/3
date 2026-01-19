<?php
session_start();
require_once __DIR__ . '/../db/db_connect.php';

header('Content-Type: application/json');

// Получаем последний успешный скан
$sql = "SELECT 
            files_found,
            files_added,
            files_updated,
            files_deleted,
            files_moved,
            scan_finished_at
        FROM scan_history 
        WHERE status = 'success' 
        ORDER BY scan_finished_at DESC 
        LIMIT 1";

$scan = $pdo->query($sql)->fetch(PDO::FETCH_ASSOC);

if ($scan) {
    echo json_encode($scan);
} else {
    echo json_encode([
        'files_found' => 0,
        'files_added' => 0,
        'files_updated' => 0,
        'files_deleted' => 0,
        'files_moved' => 0
    ]);
}
?>