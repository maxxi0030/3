<?php
// Возвращает массив с частями запроса и привязками для безопасного выполнения
function buildFilters(array $params): array {
    $where = [];
    $bindings = [];
    $order_by = 'created_at DESC';

    // Поиск по имени
    if (!empty($params['search'])) {
        $where[] = "file_name ILIKE :search";
        $bindings[':search'] = '%' . $params['search'] . '%';
    }

    // Фильтр по статусу
    $allowedStatuses = ['new','active','deleted','moved','updated','source_off'];
    if (!empty($params['status']) && in_array($params['status'], $allowedStatuses, true)) {
        $where[] = "file_status = :status";
        $bindings[':status'] = $params['status'];
    }

    // Фильтр по префиксу пути (например, источник)
    // if (!empty($params['path_prefix'])) {
    //     $where[] = "file_path LIKE :path_prefix";
    //     $bindings[':path_prefix'] = rtrim($params['path_prefix'], '/') . '%';
    // }

    // Фильтр по клиенту (client_id)
    if (!empty($params['client_id'])) {
        // приводим к целому — защита от инъекций
        $clientId = (int)$params['client_id'];
        if ($clientId > 0) {
            $where[] = "client_id = :client_id";
            $bindings[':client_id'] = $clientId;
        }
    }

    // Сортировки (предусмотренные варианты)
    if (!empty($params['sort'])) {
        switch ($params['sort']) {
            case 'date_asc': $order_by = 'updated_at ASC'; break;
            case 'date_desc': $order_by = 'updated_at DESC'; break;
            case 'size_asc': $order_by = 'file_size ASC'; break;
            case 'size_desc': $order_by = 'file_size DESC'; break;
            case 'name_asc': $order_by = 'file_name ASC'; break;
            case 'name_desc': $order_by = 'file_name DESC'; break;
        }
    }

    // Доп. фильтры: min/max размера (в байтах)
    // if (isset($params['size_min']) && $params['size_min'] !== '') {
    //     $where[] = "file_size >= :size_min";
    //     $bindings[':size_min'] = (int)$params['size_min'];
    // }
    // if (isset($params['size_max']) && $params['size_max'] !== '') {
    //     $where[] = "file_size <= :size_max";
    //     $bindings[':size_max'] = (int)$params['size_max'];
    // }

    $where_sql = '';
    if (!empty($where)) {
        $where_sql = 'WHERE ' . implode(' AND ', $where);
    }

    return [
        'where' => $where_sql,
        'order_by' => $order_by,
        'bindings' => $bindings
    ];
}

?>