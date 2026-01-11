<?php
require_once 'bc/scanner2.php';

$paths_file = 'paths.json';
$data_file = 'data.json';
$message = null;


// Загружаем существующие пути из JSON
$saved_paths = file_exists($paths_file) ? json_decode(file_get_contents($paths_file), true) : [];

// Обработка POST-запросов
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // ДОБАВЛЕНИЕ ПУТИ
    if ($_POST['action'] === 'add_new_path') {
        $new_path = trim($_POST['path']);
        // Простая нормализация: заменяем обратные слеши на прямые для единообразия
        $new_path = str_replace('\\', '/', $new_path);
        
        if (!empty($new_path) && !in_array($new_path, $saved_paths)) {
            $saved_paths[] = $new_path;
            file_put_contents($paths_file, json_encode($saved_paths, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            $message = "Путь успешно добавлен.";
        }
    }

    // УДАЛЕНИЕ ПУТИ
    if ($_POST['action'] === 'delete_path') {
        $path_to_delete = $_POST['path_value'];
        // Ищем индекс элемента в массиве
        if (($key = array_search($path_to_delete, $saved_paths)) !== false) {
            unset($saved_paths[$key]);
            // Переиндексируем массив и сохраняем
            $saved_paths = array_values($saved_paths);
            file_put_contents($paths_file, json_encode($saved_paths, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            $message = "Путь удален.";
        }
    }


    // кнопка сканировать
    // if ($_POST['action'] === 'start_full_scan') {
    //     // $message = runIncrementalScan($paths_file, $data_file); для первого сканнера 

    //     // проверка есть ли хоть один путь
    //     if (empty($saved_paths)){
    //         $message = "Ошибка: Добавьте хотя бы один путь для сканирования.";
    //     } else {
    //         $result = runIncrementalScan($paths_file, $data_file);

    //         // вывод соо из статистики
    //         if (is_array($result)) {
    //             $s = $result['stats'];
    //             $message = "<b>" . $result['message'] . "</b><br>";
    //             $message .= "Новых: {$s['new']} | Обновлено: {$s['updated']} | Перемещено: {$s['moved']} | Удалено: {$s['deleted']}";
    //         } else {
    //             // если вернулась строка - ошибка
    //             $message = $result;
    //         }
    //     }
    // }
}

?>
<header class="top-bar">
    <h1>Панель администратора</h1>
</header>

<?php if (isset($message)): ?>
    <div class="alert alert-info">
        <span class="material-icons-round">info</span>
        <?= $message ?>
    </div>
<?php endif; ?>

<div id="ajax-message-container"></div>

<div class="admin-grid">
    <div class="admin-card">
        <h3>Источники сканирования</h3>
        
        <form method="POST" class="add-path-box">
            <input type="hidden" name="action" value="add_new_path">
            <input type="text" name="path" placeholder="Вставьте путь, например: D:/Movies/Cartoons" required>
            <button type="submit" class="btn-primary">Добавить</button>
        </form>

        <ul class="paths-list">
            <?php if (empty($saved_paths)): ?>
                <li class="path-empty">Список путей пуст</li>
            <?php else: ?>
                <?php foreach ($saved_paths as $p): ?>
                    <li class="path-item">
                        <span class="path-text"><?= htmlspecialchars($p) ?></span>
                        <form method="POST" class="m-0" onsubmit="return confirm('Удалить этот путь?')">
                            <input type="hidden" name="action" value="delete_path">
                            <input type="hidden" name="path_value" value="<?= htmlspecialchars($p) ?>">
                            <button type="submit" class="btn-delete">
                                <span class="material-icons-round">delete</span>
                            </button>
                        </form>
                    </li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>
    </div>
<div class="admin-card">
        <!-- <h3>Управление сканером</h3>
        <p class="card-description">
            Сканер проверит все папки выше, найдет новые файлы и обновит базу данных.
        </p> -->

        <div class="scan-container">
            <button id="scanBtnDashboard" 
                    class="btn-primary big-btn" 
                    onclick="startScan(this)"
                    <?= empty($saved_paths) ? 'disabled' : '' ?>>
                <span class="material-icons-round">sync</span>
                <span class="btn-text">Запустить сканирование</span>
            </button>
            
            <div class="scan-footer">
                Последний скан: <span id="lastTime"><?= $_SESSION['last_scan_time'] ?? '--:--' ?></span>
            </div>
        </div>

        <div class="stats">
            <div class="stats-item">
                <span>Всего файлов:</span>
                <span class="stats-val" id="stat-total"><?= $stats['total'] ?></span>
            </div>
            <div class="stats-item">
                <span>Общий объем:</span>
                <span class="stats-val" id="stat-size"><b><?= $stats['size_gb'] ?></b> GB</span>
            </div>
            <!-- <div class="stats-item">
                <span>Новых / Удаленных:</span>
                <span class="stats-val">
                    <span class="badge exists" id="stat-new">0</span> / 
                    <span class="badge deleted" id="stat-missing">0</span>
                </span>
            </div> -->
        </div>
    </div>