<?php
// require_once 'admin.php';
// варик с БД

// ФУНКЦИЯ ПОЛУЧЕНИЯ ПУТЕЙ ИЗ БД чтобы проверить есть ли пути для сканирования ваще
function getSavedPaths($pdo) {
    $stmt = $pdo->query("SELECT * FROM scan_paths ORDER BY created_at DESC");
    return $stmt->fetchAll();
}

// Используем в admin.php
$saved_paths = getSavedPaths($pdo);

$stats = [
    'total' => 0, 
    'size_gb' => 0
];

// 1. ПОДСЧЕТ СТАТИСТИКИ (через SQL быстрее, чем через PHP цикл)
$statQuery = $pdo->query("
    SELECT 
        COUNT(*) as total, 
        SUM(file_size) as total_bytes 
    FROM files 
    WHERE file_status NOT IN ('deleted', 'source_off')
");
$statData = $statQuery->fetch();

$stats['total'] = $statData['total'] ?? 0;
$stats['size_gb'] = round(($statData['total_bytes'] ?? 0) / 1073741824, 2);


// 2. ПОИСК И ФИЛЬТРАЦИЯ
// Загружаем список клиентов для фильтра
$clientsStmt = $pdo->query("SELECT id, name FROM clients ORDER BY name");
$clientsList = $clientsStmt->fetchAll(PDO::FETCH_ASSOC);

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$files = [];



// Подключаем сборщик условий фильтрации
require_once __DIR__ . '/../bc/filter_sort.php';

// Собираем параметры от GET
$params = [
    'search' => isset($_GET['search']) ? trim($_GET['search']) : '',
    'status' => isset($_GET['status']) ? trim($_GET['status']) : '',
    'client_id' => isset($_GET['client_id']) ? trim($_GET['client_id']) : '',
    'sort' => isset($_GET['sort']) ? trim($_GET['sort']) : '',
    // 'size_min' => isset($_GET['size_min']) ? trim($_GET['size_min']) : '',
    // 'size_max' => isset($_GET['size_max']) ? trim($_GET['size_max']) : ''
];

// Преобразуем размеры из MB (пользователь вводит MB) в байты для сравнения в БД
// if ($params['size_min'] !== '') {
//     $params['size_min'] = (int)$params['size_min'] * 1048576;
// }
// if ($params['size_max'] !== '') {
//     $params['size_max'] = (int)$params['size_max'] * 1048576;
// }

$filterParts = buildFilters($params);

// Изменяем LIMIT с 100 на 5 для первой страницы + 1 дополнительная для проверки hasMore
$sql = "SELECT f.*, c.name as client_name FROM files f LEFT JOIN clients c ON f.client_id = c.id " . $filterParts['where'] . " ORDER BY " . $filterParts['order_by'] . " LIMIT 21";
$stmt = $pdo->prepare($sql);
$stmt->execute($filterParts['bindings']);
$filesAll = $stmt->fetchAll();

// Проверяем, есть ли еще файлы
$hasMore = count($filesAll) > 20;
// Берем только первые 5 файлов для показа
$files = array_slice($filesAll, 0, 20);




?>




<!-- ШАПКА ДЭШБОРДА -->
<header class="top-bar">
    <div>
        <h1>Файловое хранилище</h1>
        <p class="subtitle">
            Всего: <b><?=$stats['total'] ?></b> | 
            Объем: <b><?=$stats['size_gb'] ?> GB</b>
            <!-- тут можно потом добавить статистику удаленных и перемещенных файлов -->
        </p>
    </div>

    <div class="scan-container">
        <button id="scanBtnDashboard" 
            class="btn-primary sml-btn"
            onclick="startScan(this)" 
            <?= empty($saved_paths) ? 'disabled' : '' ?>>
            <span class="material-icons-round">sync</span>
            <span class="btn-text">Сканировать</span>
        </button>
        <div id="lastScanInfo" style="font-size: 11px; color: var(--text-secondary); margin-top: 4px;">
            Last scan: <span id="lastTime">--:--</span>
        </div>
    </div>
</header>

<div id="ajax-message-container"></div>

<!-- <div id="ajax-message-container"></div> -->



<!-- ПАНЕЛЬ С ПОИСКОМ И ФИЛЬТРАЦИЕЙ -->

<!-- ЭТО ВСЕ У НАС ПОЯВЛЯЕТСЯ ЕСЛИ ЕСТЬ ХОТЬ ОДИН ФАЙЛ -->
<div class="toolbar">

    <!-- Форма поиска -->
    <form action="" method="GET" class="search-form">
        <input type="hidden" name="page" value="dashboard">

        <div class="search-wrap">
            <span class="material-icons-round">search</span>

            <input
                type="text"
                name="search"
                id="searchInput"
                placeholder="Поиск файла..."
                value="<?= htmlspecialchars($search) ?>"
                autocomplete="off"
            >

            <button type="submit" class="btn-search">Найти</button>

            <?php if ($search): ?>
                <a href="?page=dashboard" class="btn-icon" title="Сбросить">
                    <span class="material-icons-round">close</span>
                </a>
            <?php endif; ?>
        </div>
    </form>


    <!-- Форма фильтров и сортировки -->
    <form action="" method="GET" class="filters-form">
        <input type="hidden" name="page" value="dashboard">
        <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">

        <div class="filters">

            <select name="status" aria-label="Статус">
                <option value="" <?= $params['status'] === '' ? 'selected' : '' ?>>Все статусы</option>
                <option value="active" <?= $params['status'] === 'active' ? 'selected' : '' ?>>Ок</option>
                <option value="new" <?= $params['status'] === 'new' ? 'selected' : '' ?>>Новый</option>
                <option value="deleted" <?= $params['status'] === 'deleted' ? 'selected' : '' ?>>Удален</option>
                <option value="moved" <?= $params['status'] === 'moved' ? 'selected' : '' ?>>Перемещен</option>
                <option value="updated" <?= $params['status'] === 'updated' ? 'selected' : '' ?>>Обновлен</option>
                <option value="source_off" <?= $params['status'] === 'source_off' ? 'selected' : '' ?>>Источник отключен</option>
            </select>

            <select name="sort" aria-label="Сортировка">
                <option value="" <?= $params['sort'] === '' ? 'selected' : '' ?>>По умолчанию</option>
                <option value="date_desc" <?= $params['sort'] === 'date_desc' ? 'selected' : '' ?>>Дата, новые</option>
                <option value="date_asc" <?= $params['sort'] === 'date_asc' ? 'selected' : '' ?>>Дата, старые</option>
                <option value="size_desc" <?= $params['sort'] === 'size_desc' ? 'selected' : '' ?>>Размер ↓</option>
                <option value="size_asc" <?= $params['sort'] === 'size_asc' ? 'selected' : '' ?>>Размер ↑</option>
                <option value="name_asc" <?= $params['sort'] === 'name_asc' ? 'selected' : '' ?>>Имя A→Z</option>
                <option value="name_desc" <?= $params['sort'] === 'name_desc' ? 'selected' : '' ?>>Имя Z→A</option>
            </select>


            <select name="client_id" aria-label="Клиент">
                <option value="" <?= $params['client_id'] === '' ? 'selected' : '' ?>>Клиенты</option>
                <?php foreach ($clientsList as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= ((string)$params['client_id'] === (string)$c['id']) ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                <?php endforeach; ?>
            </select>


            <!-- кнопка не нужна так как у нас автосамбит -->
            <!-- <button type="submit" class="btn-search">Применить</button> -->
        </div>
    </form>

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
                    <th width="120">Клиент</th>
                    <th width="100" class="hide-mobile">Размер</th>
                    <th width="120">Статус</th>
                    <th width="150" class="hide-mobile">Добавлено</th>
                    <th width="100">Действия</th>
                </tr>
            </thead>


            <!-- тело таблицы -->
            <tbody>
                
                <!-- проверяем есть ли ваще Файлы -->
                <?php if (empty($files)): ?>
                        <tr>
                            <td colspan="6" class="empty-state">
                                <span class="material-icons-round">folder_off</span>
                                <p>Файлы не найдены</p>
                            </td>
                        </tr>

                <!-- если они есть то выводим инфу -->
                <?php else: ?>
                    <?php foreach ($files as $file): ?>

                        <!-- // ЗАЩИТА: Если запись битая, пропускаем её, чтобы не ломать верстку -->
                        <?php 
                            // 1. Считаем размер
                            $formattedSize = isset($file['file_size']) 
                                ? round($file['file_size'] / 1048576, 2) . ' MB' 
                                : '0 MB';
                            
                            // 2. Форматируем дату
                            $formattedDate = date('d.m.Y H:i', strtotime($file['created_at']));

                            // 3. отображаемй текст статуса 
                            $statusMap = [
                                'new'     => 'Новый', 
                                'active'  => 'Ок', 
                                'deleted' => 'Удален', 
                                'moved'   => 'Перемещен',
                                'updated' => 'Обновлен'
                            ];
                            $statusText = $statusMap[$file['file_status']] ?? $file['file_status'];

                            // 4. КАРТА КЛАССОВ для статусов 
                            $classMap = [
                                'active'  => 'exists',  // БД 'active' -> CSS '.badge.exists'
                                'new'     => 'new',     // БД 'new'    -> CSS '.badge.new'
                                'deleted' => 'deleted', // БД 'deleted'-> CSS '.badge.deleted'
                                'moved'   => 'moved',    // БД 'moved'  -> CSS '.badge.moved'
                                'updated' => 'updated'
                            ];
                            $currentClass = $classMap[$file['file_status']] ?? 'source_off';



                            
                            // Защита: пропускаем битые записи
                            if (!isset($file['file_name'])) continue;
                        ?>


                        <tr class="<?= $file['file_status'] === 'deleted' ? 'row-deleted' : '' ?>">
                            

                            <!-- имя файла -->
                            <td class="cell-name">
                                <div class="file-icon">
                                    <span class="material-icons-round">description</span>
                                </div>
                                <?= htmlspecialchars($file['file_name'], ENT_QUOTES, 'UTF-8') ?>
                            </td>



                                
                            <!-- клиент -->
                            <td class="client_name"><?= !empty($file['client_name']) ? htmlspecialchars($file['client_name']) : '' ?></td>

                                
                            <!-- размер файла -->
                            <td class="cell-size hide-mobile"><?= $formattedSize ?></td>
                                

                            <!-- для стилей -->
                            <td>
                                <span class="badge <?= htmlspecialchars($currentClass) ?>">
                                    <?= htmlspecialchars($statusText) ?>
                                </span>
                            </td>
                            
                            <!-- Дата добавления -->
                            <td class="cell-date hide-mobile"><?= $formattedDate ?></td>
                            
                            <!-- Действия -->
                            <td>
                                <div class="action-group">
                                    <!-- открытие окна с инфой (функция посредник)-->
                                    <button class="btn-icon" onclick="loadFileAndShowInfo(this, <?= $file['id'] ?>)" title="Информация">
                                        <span class="material-icons-round">info</span>
                                    </button>

                                    <!-- кнопка добавить клиента -->
                                    <button class="btn-icon" onclick="addClient(this, <?= $file['id'] ?>)" title="Добавить клиента">
                                        <span class="material-icons-round">people</span>
                                    </button>


                                    <!-- кнопка открыть папку с файлом -->
                                    <button class="btn-icon open-folder-btn"
                                            title="Открыть папку" 
                                            onclick="openInExplorer('<?php echo addslashes($file['file_path']); ?>')"
                                            <?= $file['file_status'] === 'deleted' ? 'disabled' : '' ?>>
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

<!-- Кнопка "Загрузить еще" -->
<?php if ($hasMore && !empty($files)): ?>
    <div class="load-more-container">
        <button id="loadMoreBtn" class="btn-secondary" style="width: 100%; justify-content: center;">
            <span class="material-icons-round">expand_more</span>
            <span class="btn-text">Загрузить еще</span>
        </button>
    </div>
<?php endif; ?>
