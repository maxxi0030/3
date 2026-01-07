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
    if ($_POST['action'] === 'start_full_scan') {
        // $message = runIncrementalScan($paths_file, $data_file); для первого сканнера 

        // проверка есть ли хоть один путь
        if (empty($saved_paths)){
            $message = "Ошибка: Добавьте хотя бы один путь для сканирования.";
        } else {
            $result = runIncrementalScan($paths_file, $data_file);

            // вывод соо из статистики
            if (is_array($result)) {
                $s = $result['stats'];
                $message = "<b>" . $result['message'] . "</b><br>";
                $message .= "Новых: {$s['new']} | Обновлено: {$s['updated']} | Перемещено: {$s['moved']} | Удалено: {$s['deleted']}";
            } else {
                // если вернулась строка - ошибка
                $message = $result;
            }
        }
    }
}


?>

<header class="top-bar">
    <h1>Панель администратора</h1>
</header>

<?php if (isset($message)): ?>
    <div style="padding: 12px; background: #EEF2FF; color: var(--accent); border-radius: 12px; margin-bottom: 24px; border: 1px solid var(--border);">
        <span class="material-icons-round" style="vertical-align: middle; font-size: 18px;">info</span>
        <?= $message ?>
    </div>
<?php endif; ?>

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
                <li style="padding: 20px; color: var(--text-secondary); text-align: center;">Список путей пуст</li>
            <?php else: ?>
                <?php foreach ($saved_paths as $p): ?>
                    <li class="path-item">
                        <span style="font-family: monospace;"><?= htmlspecialchars($p) ?></span>
                        <form method="POST" style="margin: 0;" onsubmit="return confirm('Удалить этот путь?')">
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
        <h3>Управление сканером</h3>
        <p style="color: var(--text-secondary); margin-bottom: 20px; font-size: 14px;">
            Сканер проверит все папки выше, найдет новые файлы и обновит статус старых.
        </p>
        <form method="POST">
            <input type="hidden" name="action" value="start_full_scan">

            <?php if (empty($saved_paths)): ?>
                <p style="color: #EF4444; font-size: 12px; margin-top: 8px;">
                    <span class="material-icons-round" style="font-size: 14px; vertical-align: middle;">warning</span>
                    Сначала добавьте хотя бы одну папку
                </p>
            <?php endif; ?>

            <button type="submit" class="btn-primary big-btn" <?= empty($saved_paths) ? 'disabled style="opacity: 0.5; cursor: not-allowed;"' : '' ?>>
                <span class="material-icons-round">radar</span>
                Запустить сканирование
            </button>
        </form>
    </div>
</div>