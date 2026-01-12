<?php
require_once 'db/db_connect.php';

header('Content-Type: application/json');

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    // Берем всё из таблицы files по ID
    $stmt = $pdo->prepare("SELECT * FROM files WHERE id = ?");
    $stmt->execute([$id]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($file && $file['file_status'] === 'moved') {
        // Если файл перемещен, ищем последнюю запись об этом в истории
        $histStmt = $pdo->prepare("
            SELECT old_path, new_path 
            FROM file_changes 
            WHERE file_id = ? AND change_type = 'moved' 
            ORDER BY changed_at DESC LIMIT 1
        ");
        $histStmt->execute([$file['id']]);
        $history = $histStmt->fetch(PDO::FETCH_ASSOC);

        if ($history) {
            $file['old_path'] = $history['old_path'];
            $file['new_path'] = $history['new_path'];
        }
    }

echo json_encode($file);
}
exit;
?>