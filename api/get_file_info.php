<?php
require_once __DIR__ . '/../db/db_connect.php';

header('Content-Type: application/json');

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    $stmt = $pdo->prepare("
        SELECT 
            id,
            file_name,
            file_size,
            file_path,
            file_status,
            file_created_at,
            file_modified_at,
            created_at,
            updated_at
        FROM files 
        WHERE id = ?
    ");
    $stmt->execute([$id]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($file && $file['file_status'] === 'moved') {
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
        }
    }

    echo json_encode($file);
}
exit;
?>