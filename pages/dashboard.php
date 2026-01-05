<?php

// пока что с json работаем

// уть к нашему JSON-файлу
$json_file = 'data.json';

// Проверяем, существует ли файл
if (file_exists($json_file)) {
    // Читаем содержимое файла в строку
    $json_data = file_get_contents($json_file);
    $all_files = json_decode($json_data, true);
} else {
    $all_files = [];
}

// ЛОГИКА ПОИСКА пока что такаяя
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$files = [];

if ($search !== '') {
    // Если есть поисковый запрос, фильтруем массив
    foreach ($all_files as $f) {
        // Ищем совпадение в имени (без учета регистра)
        if (mb_stripos($f['name'], $search) !== false) {
            $files[] = $f;
        }
    }
} else {
    // Если поиска нет — берем все файлы
    $files = $all_files;
}
?>

<header class="top-bar">
    <h1>Файловое хранилище</h1>
</header>

<div class="toolbar">
    <form action="" method="GET" class="search-wrap">
        <input type="hidden" name="page" value="dashboard">
        <span class="material-icons-round">search</span>
        <input type="text" name="search" id="searchInput" placeholder="Поиск файла..." value="<?= $_GET['search'] ?? '' ?>">
        <button type="submit" class="btn-search">Найти</button>
    </form>
    
    <div class="filters">
        </div>
</div>

<div class="table-card">
    <table class="file-table">
        <thead>
            <tr>
                <th>Имя файла</th>
                <th>Путь</th>
                <th width="100">Размер</th>
                <th width="120">Статус</th>
                <th width="150">Дата добавления</th>
                <th width="100">Действия</th>
            </tr>
        </thead>

        <tbody id="fileList">
            <?php if (empty($files)): ?>
                <tr>
                    <td colspan="6" style="text-align:center; padding: 40px;">Файлы не найдены</td>
                </tr>
            <?php else: ?>
                <?php foreach ($files as $file): ?>
                    <tr>
                        <td style="font-weight: 500;"><?= $file['name'] ?></td>
                        <td style="color: var(--text-secondary); font-family: monospace; font-size: 12px;">
                            <?= $file['path'] ?>
                        </td>
                        <td><?= $file['size'] ?></td>
                        <td>
                            <span class="badge <?= $file['status'] ?>">
                                <?= $file['status_text'] ?>
                            </span>
                        </td>
                        <td style="color: var(--text-secondary);"><?= $file['date'] ?></td>
                        <td>
                            <div class="action-group">
                                <a href="?page=dashboard&info_id=<?= $file['id'] ?>" class="btn-icon">
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