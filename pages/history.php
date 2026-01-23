<?php
/**
 * Расширенная версия страницы истории изменений
 * с фильтрацией, поиском и пагинацией
 */

// 1. ИСПРАВЛЕНИЕ: Используем 'p' для пагинации, так как 'page' занят роутером
$page_num = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1; 
$per_page = 20;
$offset = ($page_num - 1) * $per_page;

// Параметры фильтрации
$change_type = $_GET['change_type'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$search = $_GET['search'] ?? '';

// Построение SQL запроса с фильтрами
$where_conditions = [];
$params = [];

if (!empty($change_type)) {
    $where_conditions[] = "fc.change_type = :change_type";
    $params[':change_type'] = $change_type;
}

if (!empty($date_from)) {
    $where_conditions[] = "DATE(fc.changed_at) >= :date_from";
    $params[':date_from'] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "DATE(fc.changed_at) <= :date_to";
    $params[':date_to'] = $date_to;
}

if (!empty($search)) {
    $where_conditions[] = "(f.file_name ILIKE :search OR fc.old_path ILIKE :search OR fc.new_path ILIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

$where_sql = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Подсчёт общего количества записей
$count_query = "
    SELECT COUNT(*) as total
    FROM file_changes fc
    LEFT JOIN files f ON fc.file_id = f.id
    $where_sql
";

$count_stmt = $pdo->prepare($count_query);
$count_stmt->execute($params);
$total_records = $count_stmt->fetch()['total'] ?? 0;
$total_pages = $total_records > 0 ? ceil($total_records / $per_page) : 1;

// Проверка корректности номера страницы
if ($page_num > $total_pages && $total_pages > 0) {
    $page_num = $total_pages;
    $offset = ($page_num - 1) * $per_page;
}

// Получение данных с пагинацией
$query = "
    SELECT 
        fc.id,
        fc.change_type,
        fc.old_path,
        fc.new_path,
        fc.changed_at,
        fc.details,
        f.file_name,
        f.file_path,
        f.file_extension,
        f.file_size,
        c.name as client_name,
        sh.scan_started_at
    FROM file_changes fc
    LEFT JOIN files f ON fc.file_id = f.id
    LEFT JOIN clients c ON f.client_id = c.id
    LEFT JOIN scan_history sh ON fc.scan_history_id = sh.id
    $where_sql
    ORDER BY fc.changed_at DESC
    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$history_data = $stmt->fetchAll();

// Получение статистики
$stats_query = "
    SELECT 
        change_type,
        COUNT(*) as count
    FROM file_changes
    GROUP BY change_type
";
$stats_stmt = $pdo->query($stats_query);
$stats = $stats_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

function formatFileSize($bytes) {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return round($bytes / 1024, 2) . ' KB';
    if ($bytes < 1073741824) return round($bytes / 1048576, 2) . ' MB';
    return round($bytes / 1073741824, 2) . ' GB';
}

// 2. ИСПРАВЛЕНИЕ: Функция для генерации URL пагинации, сохраняя текущий раздел
function getUrl($pageNum, $params) {
    $query = $_GET;
    $query['p'] = $pageNum; // Используем 'p' для пагинации
    $query['page'] = 'history'; // Гарантируем, что остаемся в модуле history
    return '?' . http_build_query($query);
}


function highlightPathDifference($oldPath, $newPath, $isOldPath = false) {
    if (empty($oldPath) || empty($newPath)) {
        return htmlspecialchars($newPath ?? $oldPath ?? '');
    }
    
    // Нормализуем пути
    $oldPath = str_replace('\\', '/', $oldPath);
    $newPath = str_replace('\\', '/', $newPath);
    
    // Разбиваем пути на части
    $oldParts = explode('/', $oldPath);
    $newParts = explode('/', $newPath);
    
    $result = '';
    $highlightClass = $isOldPath ? 'path-highlight-old' : 'path-highlight-new';
    
    // Для старого пути используем oldParts, для нового - newParts
    $partsToUse = $isOldPath ? $oldParts : $newParts;
    $partsToCompare = $isOldPath ? $newParts : $oldParts;
    
    for ($i = 0; $i < count($partsToUse); $i++) {
        // Если части не совпадают, выделяем
        if (!isset($partsToCompare[$i]) || $partsToCompare[$i] !== $partsToUse[$i]) {
            $result .= '<span class="' . $highlightClass . '">' . htmlspecialchars($partsToUse[$i]) . '</span>';
        } else {
            $result .= htmlspecialchars($partsToUse[$i]);
        }
        
        // Добавляем разделитель, если это не последний элемент
        if ($i < count($partsToUse) - 1) {
            $result .= '/';
        }
    }
    
    return $result;
}








?>

<link rel="stylesheet" href="history.css">

<header class="top-bar">
    <div class="header-left">
        <h1>
            <span class="material-icons-round">history</span>
            История изменений
        </h1>
        <span class="records-count"><?= number_format($total_records, 0, ',', ' ') ?> событий</span>
    </div>
    <div class="header-actions">
        <button class="btn-filter" onclick="toggleFilters()">
            <span class="material-icons-round">filter_list</span>
            Фильтры
            <?php if ($change_type || $date_from || $date_to || $search): ?>
                <span class="filter-badge"><?= count(array_filter([$change_type, $date_from, $date_to, $search])) ?></span>
            <?php endif; ?>
        </button>
    </div>
</header>

<div class="filters-panel" id="filtersPanel" style="<?= ($change_type || $date_from || $date_to || $search) ? '' : 'display: none;' ?>">
    <form method="GET" action="">
        <input type="hidden" name="page" value="history">
        
        <div class="filter-group">
            <label>Поиск:</label>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Имя файла или путь...">
        </div>
        <div class="filter-group">
            <label>Тип изменения:</label>
            <select name="change_type">
                <option value="">Все типы</option>
                <option value="added" <?= $change_type === 'added' ? 'selected' : '' ?>>✓ Добавлено</option>
                <option value="deleted" <?= $change_type === 'deleted' ? 'selected' : '' ?>>✗ Удалено</option>
                <option value="moved" <?= $change_type === 'moved' ? 'selected' : '' ?>>→ Перемещено</option>
                <option value="updated" <?= $change_type === 'updated' ? 'selected' : '' ?>>↻ Обновлено</option>
            </select>
        </div>
        <div class="filter-group">
            <label>Период:</label>
            <div class="date-range">
                <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>" placeholder="От">
                <span>—</span>
                <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>" placeholder="До">
            </div>
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn-apply">
                <span class="material-icons-round">check</span>
                Применить
            </button>
            <a href="?page=history" class="btn-reset">
                <span class="material-icons-round">clear</span>
                Сбросить
            </a>
        </div>
    </form>
</div>

<div class="history-container">
    <?php if (empty($history_data)): ?>
        <div class="empty-state">
            <span class="material-icons-round">search_off</span>
            <p>Записей не найдено</p>
            <small>Попробуйте изменить параметры фильтрации</small>
            <?php if ($change_type || $date_from || $date_to || $search): ?>
                <a href="?page=history" class="btn-reset-filters">Сбросить фильтры</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="timeline">
            <?php 
            $current_date = '';
            foreach ($history_data as $event): 
                $icon = 'add_circle';
                $class = 'event-added';
                $action_text = 'Добавлен';
                
                switch ($event['change_type']) {
                    case 'deleted':
                        $icon = 'delete';
                        $class = 'event-deleted';
                        $action_text = 'Удалён';
                        break;
                    case 'moved':
                        $icon = 'drive_file_move';
                        $class = 'event-moved';
                        $action_text = 'Перемещён';
                        break;
                    case 'updated':
                        $icon = 'update';
                        $class = 'event-updated';
                        $action_text = 'Обновлён';
                        break;
                }
                
                $timestamp = strtotime($event['changed_at']);
                $date = date('d.m.Y', $timestamp);
                $time = date('H:i', $timestamp);
                
                if ($current_date !== $date):
                    $current_date = $date;
            ?>
                <div class="date-separator">
                    <span><?= $date ?></span>
                </div>
            <?php endif; ?>
            
            <div class="timeline-item <?= $class ?>">
                <div class="timeline-icon">
                    <span class="material-icons-round"><?= $icon ?></span>
                </div>
                <div class="timeline-content">
                    <div class="timeline-header">
                        <div class="timeline-title">
                            <strong><?= htmlspecialchars($event['file_name'] ?? basename($event['new_path'] ?? $event['old_path'])) ?></strong>
                            <span class="action-badge"><?= $action_text ?></span>
                            <?php if ($event['file_extension']): ?>
                                <span class="extension-badge"><?= strtoupper($event['file_extension']) ?></span>
                            <?php endif; ?>
                        </div>
                        <span class="timeline-time"><?= $time ?></span>
                    </div>
                    
                    <?php if ($event['change_type'] === 'moved'): ?>
                        <div class="path-change">
                            <p class="timeline-path old-path">
                                <span class="path-label">Было:</span>
                                <?= highlightPathDifference($event['old_path'], $event['new_path'], true) ?>
                            </p>
                            <span class="material-icons-round path-arrow">arrow_downward</span>
                            <p class="timeline-path new-path">
                                <span class="path-label">Стало:</span>
                                <?= highlightPathDifference($event['old_path'], $event['new_path'], false) ?>
                            </p>
                        </div>
                    <?php else: ?>
                        <p class="timeline-path">
                            <?= htmlspecialchars($event['new_path'] ?? $event['old_path'] ?? $event['file_path']) ?>
                        </p>
                    <?php endif; ?>
                    
                    <div class="event-meta">
                        <?php if ($event['file_size']): ?>
                            <span class="meta-item">
                                <span class="material-icons-round">storage</span>
                                <?= formatFileSize($event['file_size']) ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($event['client_name']): ?>
                            <span class="meta-item">
                                <span class="material-icons-round">person</span>
                                <?= htmlspecialchars($event['client_name']) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!empty($event['details'])): ?>
                        <div class="event-details">
                            <button class="btn-details" onclick="toggleDetails(<?= $event['id'] ?>)">
                                <span class="material-icons-round">info</span>
                                Детали
                            </button>
                            <div class="details-content" id="details-<?= $event['id'] ?>" style="display: none;">
                                <pre><?= htmlspecialchars(json_encode(json_decode($event['details']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page_num > 1): ?>
                <a href="<?= getUrl(1, $_GET) ?>" class="page-link">
                    <span class="material-icons-round">first_page</span>
                </a>
                <a href="<?= getUrl($page_num - 1, $_GET) ?>" class="page-link">
                    <span class="material-icons-round">chevron_left</span>
                </a>
            <?php endif; ?>
            
            <span class="page-info">
                Страница <?= $page_num ?> из <?= $total_pages ?>
            </span>
            
            <?php if ($page_num < $total_pages): ?>
                <a href="<?= getUrl($page_num + 1, $_GET) ?>" class="page-link">
                    <span class="material-icons-round">chevron_right</span>
                </a>
                <a href="<?= getUrl($total_pages, $_GET) ?>" class="page-link">
                    <span class="material-icons-round">last_page</span>
                </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <div class="stats-panel">
            <h3>Общая статистика</h3>
            <div class="stats-grid">
                <div class="stat-item stat-added">
                    <span class="material-icons-round">add_circle</span>
                    <div>
                        <strong><?= number_format($stats['added'] ?? 0, 0, ',', ' ') ?></strong>
                        <span>Добавлено</span>
                    </div>
                </div>
                <div class="stat-item stat-deleted">
                    <span class="material-icons-round">delete</span>
                    <div>
                        <strong><?= number_format($stats['deleted'] ?? 0, 0, ',', ' ') ?></strong>
                        <span>Удалено</span>
                    </div>
                </div>
                <div class="stat-item stat-moved">
                    <span class="material-icons-round">drive_file_move</span>
                    <div>
                        <strong><?= number_format($stats['moved'] ?? 0, 0, ',', ' ') ?></strong>
                        <span>Перемещено</span>
                    </div>
                </div>
                <div class="stat-item stat-updated">
                    <span class="material-icons-round">update</span>
                    <div>
                        <strong><?= number_format($stats['updated'] ?? 0, 0, ',', ' ') ?></strong>
                        <span>Обновлено</span>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
