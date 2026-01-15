<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../db/db_connect.php';
require_once __DIR__ . '/../bc/filter_sort.php';

try {
    // ============================================================
    // ВАЛИДАЦИЯ И ОБРАБОТКА ПАРАМЕТРОВ
    // ============================================================
    
    // Номер страницы (обязательный параметр) - приводим к целому числу
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    if ($page < 1) {
        $page = 1;
    }
    
    // Количество записей на странице (по умолчанию 5)
    $perPage = 20;
    
    // Калкулируем OFFSET
    $offset = ($page - 1) * $perPage;
    
    // Параметры фильтрации (приходят через GET)
    $params = [
        'search' => isset($_GET['search']) ? trim($_GET['search']) : '',
        'status' => isset($_GET['status']) ? trim($_GET['status']) : '',
        'client_id' => isset($_GET['client_id']) ? trim($_GET['client_id']) : '',
        'sort' => isset($_GET['sort']) ? trim($_GET['sort']) : '',
    ];
    
    // ============================================================
    // ПОСТРОЕНИЕ SQL-ЗАПРОСА С ФИЛЬТРАЦИЕЙ
    // ============================================================
    
    $filterParts = buildFilters($params);
    
    // Основной запрос: берем на 1 больше, чтобы понять, есть ли еще записи
    $sql = "SELECT f.*, c.name as client_name 
            FROM files f 
            LEFT JOIN clients c ON f.client_id = c.id 
            " . $filterParts['where'] . " 
            ORDER BY " . $filterParts['order_by'] . " 
            LIMIT :limit OFFSET :offset";
    
    $stmt = $pdo->prepare($sql);
    
    // Привязываем все параметры фильтрации
    foreach ($filterParts['bindings'] as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    // Привязываем LIMIT и OFFSET (для PDO они должны быть integer)
    $stmt->bindValue(':limit', $perPage + 1, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ============================================================
    // ОПРЕДЕЛЯЕМ, ЕСТЬ ЛИ ЕЩЕ ЗАПИСИ
    // ============================================================
    
    $hasMore = false;
    if (count($results) > $perPage) {
        $hasMore = true;
        // Убираем "лишнюю" запись
        array_pop($results);
    }
    
    // ============================================================
    // ФОРМАТИРОВАНИЕ ДАННЫХ ДЛЯ ВЫВОДА
    // ============================================================
    
    $files = [];
    foreach ($results as $file) {
        // Защита от битых записей
        if (!isset($file['file_name'])) {
            continue;
        }
        
        // Форматируем размер
        $fileSize = isset($file['file_size']) ? $file['file_size'] : 0;
        if ($fileSize >= 1073741824) {
            $formattedSize = round($fileSize / 1073741824, 2) . ' GB';
        } elseif ($fileSize >= 1048576) {
            $formattedSize = round($fileSize / 1048576, 2) . ' MB';
        } elseif ($fileSize >= 1024) {
            $formattedSize = round($fileSize / 1024, 2) . ' KB';
        } else {
            $formattedSize = $fileSize . ' B';
        }
        
        // Форматируем дату
        $formattedDate = date('d.m.Y H:i', strtotime($file['created_at']));
        
        // Текст статуса
        $statusMap = [
            'active' => 'Ок',
            'new' => 'Новый',
            'deleted' => 'Удален',
            'moved' => 'Перемещен',
            'updated' => 'Обновлен',
            'source_off' => 'Источник отключен'
        ];
        $statusText = $statusMap[$file['file_status']] ?? $file['file_status'];
        
        $files[] = [
            'id' => (int)$file['id'],
            'name' => $file['file_name'],
            'size' => $formattedSize,
            'size_bytes' => (int)$fileSize,
            'path' => $file['file_path'] ?? '',
            'old_path' => $file['old_path'] ?? null,
            'status' => $file['file_status'],
            'status_text' => $statusText,
            'client_name' => $file['client_name'],
            'date' => $formattedDate,
            'created_at' => $file['created_at']
        ];
    }
    
    // ============================================================
    // ВОЗВРАЩАЕМ ОТВЕТ
    // ============================================================
    
    echo json_encode([
        'status' => 'success',
        'data' => $files,
        'hasMore' => $hasMore,
        'page' => $page,
        'perPage' => $perPage
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Ошибка загрузки данных: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}