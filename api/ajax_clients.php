<?php
// Простая API для списка клиентов и привязки клиента к файлу
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../db/db_connect.php';

$action = $_REQUEST['action'] ?? '';

// дропдаун список
if ($action === 'list') {
    try {
        $stmt = $pdo->query("SELECT id, name FROM clients ORDER BY name");
        $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'clients' => $clients]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// привязка клинта к файлу
if ($action === 'assign' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $file_id = isset($_POST['file_id']) ? (int)$_POST['file_id'] : 0;
    $client_id = isset($_POST['client_id']) ? (int)$_POST['client_id'] : 0;

    if ($file_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid file_id']);
        exit;
    }

    try {
        if ($client_id > 0) {
            $stmt = $pdo->prepare("UPDATE files SET client_id = :client_id, updated_at = NOW() WHERE id = :id");
            $stmt->execute([':client_id' => $client_id, ':id' => $file_id]);
        } else {
            // allow unassign
            $stmt = $pdo->prepare("UPDATE files SET client_id = NULL, updated_at = NOW() WHERE id = :id");
            $stmt->execute([':id' => $file_id]);
        }

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action']);

?>
