<?php
// --- 1. ЗАГРУЗКА ДАННЫХ ---
$json_file = 'data.json';
$all_files = [];
$stats = [
    'total' => 0,
    'deleted' => 0,
    'moved' => 0,
    'size_gb' => 0
];

if (file_exists($json_file)) {
    $content = file_get_contents($json_file);
    $decoded = json_decode($content, true);
    if (is_array($decoded)) {
        $all_files = $decoded;
    }
}

// --- 2. ПОДСЧЕТ СТАТИСТИКИ (Для шапки) ---
foreach ($all_files as $f) {
    // Пропускаем битые записи
    if (!isset($f['status'])) continue;

    if ($f['status'] !== 'deleted' && $f['status'] !== 'source_off') {
        $stats['total']++;
        // Суммируем размер (грубо, в байтах, если они есть)
        if (isset($f['bytes'])) {
            $stats['size_gb'] += $f['bytes'];
        }
    }
    if ($f['status'] === 'deleted') $stats['deleted']++;
    if ($f['status'] === 'moved') $stats['moved']++;
}
// Переводим байты в ГБ для отображения
$stats['size_gb'] = round($stats['size_gb'] / 1073741824, 2);


// --- 3. ПОИСК И ФИЛЬТРАЦИЯ ---
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$files = [];

if ($search !== '') {
    foreach ($all_files as $f) {
        // Проверка на целостность данных перед поиском
        if (!isset($f['name'])) continue;

        if (mb_stripos($f['name'], $search) !== false) {
            $files[] = $f;
        }
    }
} else {
    $files = $all_files;
}
?>

<header class="top-bar">
    <div>
        <h1>Файловое хранилище</h1>
        <p class="subtitle">
            Всего: <b><?= $stats['total'] ?></b> | 
            Объем: <b><?= $stats['size_gb'] ?> GB</b> | 
            <span style="color: #e53935;">Удалено: <?= $stats['deleted'] ?></span> |
            <span style="color: #3573e5ff;">Перемещено: <?= $stats['moved'] ?></span>
        </p>
    </div>

    <!-- тут добавим настоящую кнопку  -->
    <!-- <a href="?page=admin" class="btn-primary">
        <span class="material-icons-round">sync</span> Сканировать
    </a> -->
</header>

<div class="toolbar">
    <form action="" method="GET" class="search-wrap">
        <input type="hidden" name="page" value="dashboard">
        <span class="material-icons-round">search</span>
        <input type="text" name="search" id="searchInput" placeholder="Поиск файла..." value="<?= htmlspecialchars($search) ?>">
        <button type="submit" class="btn-search">Найти</button>
        <?php if($search): ?>
            <a href="?page=dashboard" class="btn-icon" title="Сбросить"><span class="material-icons-round">close</span></a>
        <?php endif; ?>
    </form>
    
    <div class="filters">
        </div>
</div>

<div class="table-card">
    <div class="table-responsive">
        <table class="file-table">
            <thead>
                <tr>
                    <th>Имя файла</th>
                    <th>Путь</th>
                    <th width="100">Размер</th>
                    <th width="120">Статус</th>
                    <th width="150">Дата</th>
                    <th width="100">Действия</th>
                </tr>
            </thead>

            <tbody id="fileList">
                <?php if (empty($files)): ?>
                    <tr>
                        <td colspan="6" class="empty-state">
                            <span class="material-icons-round">folder_off</span>
                            <p>Файлы не найдены</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($files as $file): ?>
                        <?php 
                            // ЗАЩИТА: Если запись битая, пропускаем её, чтобы не ломать верстку
                            if (!isset($file['name']) || !isset($file['path'])) continue; 
                        ?>
                        <tr class="<?= $file['status'] === 'deleted' ? 'row-deleted' : '' ?>">
                            <td class="cell-name">
                                <div class="file-icon">
                                    <span class="material-icons-round">description</span>
                                </div>
                                <?= htmlspecialchars($file['name'], ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            
                            <td class="cell-path">
                                <span title="<?= htmlspecialchars($file['path'], ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars($file['path'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            
                            <td class="cell-size"><?= $file['size'] ?? '-' ?></td>
                            
                            <td>
                                <span class="badge <?= $file['status'] ?? 'unknown' ?>">
                                    <?= $file['status_text'] ?? '???' ?>
                                </span>
                            </td>
                            
                            <td class="cell-date"><?= $file['date'] ?? '-' ?></td>
                            
                            <td>
                                <div class="action-group">
                                    <a href="?page=dashboard&info_id=<?= $file['id'] ?? 0 ?>" class="btn-icon">
                                        <span class="material-icons-round">info</span>
                                    </a>
                                    <button class="btn-icon" title="Открыть папку">
                                        <span class="material-icons-round">folder</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>