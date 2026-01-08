<?php

// ЗАГРУЗКА ДАННЫХ
$json_file = 'data.json';
$all_files = [];
$stats = [
    'total' => 0, // всего активных файлов 
    'deleted' => 0,
    'moved' => 0,
    'size_gb' => 0
];


// проверяем существует ли ваще файл
if (file_exists($json_file)) {
    $content = file_get_contents($json_file);
    $decoded = json_decode($content, true);
    if (is_array($decoded)) {
        $all_files = $decoded;
    }
}


// ПОДСЧЕТ СТАТИСТИКИ ДЛЯ шапки
foreach ($all_files as $f) {
    // Пропускаем битые записи - если статуса нет - то пропускаем чтобы не сломать статистику
    if (!isset($f['status'])) continue;

    if ($f['status'] !== 'deleted' && $f['status'] !== 'source_off') {
        $stats['total']++;
        // Суммируем размер (грубо, в байтах, если они есть)
        if (isset($f['bytes'])) {
            $stats['size_gb'] += $f['bytes'];
        }
    }

    // можно считать еще все перемещенные и удаленные, но думаю этому нету места на дэшборде

    // if ($f['status'] === 'deleted') $stats['deleted']++;
    // if ($f['status'] === 'moved') $stats['moved']++;
}
// переводим байты в ГБ для отображения
$stats['size_gb'] = round($stats['size_gb'] / 1073741824, 2);




// ПОСИК И ФИЛЬТРАЦИЯ 
// сюда потом поключим отдельную логику которая будет отвечать за это все..

//  * УЛУЧШЕНИЯ, КОТОРЫЕ МОЖНО ДОБАВИТЬ:
//  * 
//  * 1. Поиск по нескольким полям (имя + путь)
//  * 2. Фильтр по статусу (только существующие, только удаленные)
//  * 3. Фильтр по дате (за последний месяц, год)
//  * 4. Сортировка результатов (по имени, по дате, по размеру)
//  * 5. Пагинация (если файлов много - показывать по 50 штук)


// но пока что оставим это 

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




// обрабоотка кнопки перехода к файлу


?>



<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Файловое хранилище</title>
    
    <!-- Material Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Основные стили -->
    <link rel="stylesheet" href="style.css">


</head>
<body>


<!-- ШАПКА ДЭШБОРДА -->
<header class="top-bar">
    <div>
        <h1>Файловое хранилище</h1>
        <p class="subtitle">
            Всего: <b><?= $stats['total'] ?></b> | 
            Объем: <b><?= $stats['size_gb'] ?> GB</b>
            <!-- тут можно потом добавить статистику удаленных и перемещенных файлов -->
        </p>
    </div>

    <!-- тут добавим настоящую кнопку  -->
    <a href="?page=admin" class="btn-dashboard">
        <span class="material-icons-round">sync</span> Сканировать
    </a>
</header>






<!-- ПАНЕЛЬ С ПОИСКОМ И ФИЛЬТРАЦИЕЙ -->

<!-- ЭТО ВСЕ У НАС ПОЯВЛЯЕТСЯ ЕСЛИ ЕСТЬ ХОТЬ ОДИН ФАЙЛ -->

<!-- тут еще все будет добавляться и менять потому что появится отдельная логика для поиска и фильтрации -->
<div class="toolbar">
    <!-- поисковая строка -->
    <form action="" method="GET" class="search-wrap">
        <input type="hidden" name="page" value="dashboard">
        <span class="material-icons-round">search</span>
        <input type="text" name="search" id="searchInput" placeholder="Поиск файла..." value="<?= htmlspecialchars($search) ?>"  autocomplete="off" required>
        <button type="submit" class="btn-search">Найти</button>
        <?php if($search): ?>
            <a href="?page=dashboard" class="btn-icon" title="Сбросить"><span class="material-icons-round">close</span></a>
        <?php endif; ?>
    </form>
    


    <!-- фильтры -->
    <div class="filters">
        <!-- по статусу -->

        <!-- по дате -->

        <!-- по размеру -->

        <!-- по типу файла -->



    </div>

</div>





<!-- ТАБЛИЦА С файлами -->
<div class="table-card">
    <div class="table-responsive">
        <table class="file-table">

            <!-- шапка таблицы -->
            <thead>
                <tr>
                    <th>Имя файла</th>
                    <!-- <th class="hide-mobile">Путь</th> -->
                    <th width="100" class="hide-mobile">Размер</th>
                    <th width="120">Статус</th>
                    <th width="150">Дата</th>
                    <th width="100">Действия</th>
                </tr>
            </thead>


            <!-- тело таблицы -->
            <tbody>
                
                <!-- проверяем есть ли ваще Файлы -->
                <?php if (empty($files)): ?>
                        <tr>
                            <td colspan="5" class="empty-state">
                                <span class="material-icons-round">folder_off</span>
                                <p>Файлы не найдены</p>
                            </td>
                        </tr>

                <!-- если они есть то выводим инфу -->
                <?php else: ?>
                    <?php foreach ($files as $file): ?>

                        <!-- // ЗАЩИТА: Если запись битая, пропускаем её, чтобы не ломать верстку -->
                        <?php 
                            if (!isset($file['name']) || !isset($file['path'])) continue; 
                        ?>


                        <tr class="<?= $file['status'] === 'deleted' ? 'row-deleted' : '' ?>">
                            

                            <!-- имя файла -->
                            <td class="cell-name">
                                <div class="file-icon">
                                    <span class="material-icons-round">description</span>
                                </div>
                                <?= htmlspecialchars($file['name'], ENT_QUOTES, 'UTF-8') ?>
                            </td>
                                
                            <!-- путь файла -->

                                
                            <!-- размер файла -->
                            <td class="cell-size hide-mobile"><?= $file['size'] ?? '-' ?></td>
                                

                            <!-- для стилей -->
                            <td>
                                <span class="badge <?= $file['status'] ?? 'unknown' ?>">
                                    <?= $file['status_text'] ?? '???' ?>
                                </span>
                            </td>
                            
                            <!-- Дата добавления -->
                            <td class="cell-date"><?= $file['date'] ?? '-' ?></td>
                            
                            <!-- Действия -->
                            <td>
                                <div class="action-group">
                                    <!-- открытие окна с инфой -->
                                    <a href="?page=dashboard&info_id=<?= $file['id'] ?? 0 ?>" class="btn-icon">
                                       <span class="material-icons-round">info</span>
                                    </a>

                                    <!-- кнопка открыть папку с файлом -->
                                    <button class="btn-icon" 
                                            title="Открыть папку" 
                                            onclick="openInExplorer('<?php echo addslashes($file['path']); ?>')">
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
</body>
</html>