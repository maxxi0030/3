<?php
// require_once 'bc/scanner2.php';
require_once __DIR__ . '/../bc/scanner2.php';

// БД
$message = null;

// 1. ЗАГРУЗКА ПУТЕЙ ИЗ БАЗЫ
$stmt = $pdo->query("SELECT * FROM scan_paths ORDER BY created_at DESC");
$saved_paths = $stmt->fetchAll();

// 2. ОБРАБОТКА POST-ЗАПРОСОВ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // ДОБАВЛЕНИЕ ПУТИ
    if ($_POST['action'] === 'add_new_path') {
        $new_path = trim($_POST['path']);
        $new_path = str_replace('\\', '/', $new_path); // Нормализация
        
        if (!empty($new_path)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO scan_paths (path) VALUES (?)");
                $stmt->execute([$new_path]);

                // Получаем новый id добавленного пути
                $newScanPathId = $pdo->lastInsertId();

                // Попытаемся восстановить связь для файлов, которые раньше были от этого источника
                // и при удалении получили статус 'source_off'.
                $updateFiles = $pdo->prepare(
                    "UPDATE files SET scan_path_id = ?, 
                    -- file_status = 'active', 
                    -- temp_found = true,
                    updated_at = CURRENT_TIMESTAMP WHERE file_path LIKE ? AND file_status = 'source_off'"
                );
                // $like = $new_path . '%';
                // $updateFiles->execute([$newScanPathId, $like]);
                $normalizedPath = rtrim($new_path, '/') . '/';
                $like = $normalizedPath . '%';
                $updateFiles->execute([$newScanPathId, $like]);

                $affected = $updateFiles->rowCount();

                $message = "Путь успешно добавлен.";
                if ($affected > 0) {
                    $message .= " Вернули статус для {$affected} файлов.";
                }

                // Обновляем список для вывода ниже
                $saved_paths = $pdo->query("SELECT * FROM scan_paths ORDER BY created_at DESC")->fetchAll();
            } catch (PDOException $e) {
                // Если сработал UNIQUE в базе (путь уже есть)
                $message = "Ошибка: такой путь уже существует.";
            }
        }
    }





    // УДАЛЕНИЕ ПУТИ
    // if ($_POST['action'] === 'delete_path') {
    //     $id_to_delete = (int)$_POST['path_id']; // Удаляем по ID
        
    //     $stmt = $pdo->prepare("DELETE FROM scan_paths WHERE id = ?");
    //     $stmt->execute([$id_to_delete]);
    //     $message = "Путь удален из источников.";
        
    //     // Обновляем список
    //     $saved_paths = $pdo->query("SELECT * FROM scan_paths ORDER BY created_at DESC")->fetchAll();
    // }


    // УДАЛЕНИЕ ПУТИ
    if ($_POST['action'] === 'delete_path') {
        $id_to_delete = (int)$_POST['path_id']; // Удаляем по ID
        

        
        // 1. Помечаем все файлы из этого источника как 'source_off'
        $markFilesStmt = $pdo->prepare("
            UPDATE files 
            SET file_status = 'source_off', 
                updated_at = CURRENT_TIMESTAMP 
            WHERE scan_path_id = ?
        ");
        $markFilesStmt->execute([$id_to_delete]);
        
        // 2. Убираем связь с удаляемым путём (чтобы не нарушать foreign key)
        $unlinkFilesStmt = $pdo->prepare("
            UPDATE files 
            SET scan_path_id = NULL 
            WHERE scan_path_id = ?
        ");
        $unlinkFilesStmt->execute([$id_to_delete]);
        
        
        // 3. Теперь можно безопасно удалить путь
        $stmt = $pdo->prepare("DELETE FROM scan_paths WHERE id = ?");
        $stmt->execute([$id_to_delete]);
        $message = "Путь удален из источников.";
        
        // Обновляем список
        $saved_paths = $pdo->query("SELECT * FROM scan_paths ORDER BY created_at DESC")->fetchAll();
    }



    // TRUNCATE TABLE
    if ($_POST['action'] === 'truncate_files') {
        try {
            $pdo->exec("TRUNCATE TABLE files CASCADE");
            $message = "✓ Таблица успешно очищена";
            
            // Обновляем список
            $saved_paths = $pdo->query("SELECT * FROM scan_paths ORDER BY created_at DESC")->fetchAll();
        } catch (PDOException $e) {
            $message = "✗ Ошибка при очистке таблицы: " . $e->getMessage();
        }
    }
}

    




?>




<header class="top-bar">
    <h1>Панель администратора</h1>

    <form method="POST" onsubmit="return confirm('⚠️⚠️⚠️ ВНИМАНИЕ! ⚠️⚠️⚠️\n\nВы собираетесь удалить ВСЕ данные из таблицы files!\n\nЭто действие НЕОБРАТИМО!\n\nПродолжить?');">
        <input type="hidden" name="action" value="truncate_files">
        <button type="submit" class="danger-btn truncate-btn">TRUNCATE CASCADE</button>
    </form>
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
            <input type="text" name="path" placeholder="Вставьте путь..." required>
            <button type="submit" class="btn-primary">Добавить</button>
        </form>

        <ul class="paths-list">
            <?php if (empty($saved_paths)): ?>
                <li class="path-empty">Список путей пуст</li>
            <?php else: ?>
                <?php foreach ($saved_paths as $p): ?>
                    <li class="path-item">
                        <span class="path-text"><?= htmlspecialchars($p['path']) ?></span>
                        
                        <form method="POST" class="m-0" onsubmit="return confirm('Удалить этот путь?')">
                            <input type="hidden" name="action" value="delete_path">
                            <input type="hidden" name="path_id" value="<?= $p['id'] ?>">
                            
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
                Последний скан: <span id="lastTime"></span>
            </div>
        </div>


</div>

<!-- 
сейчас тут удаляется путь напрямую из бд. надо сделать так чтобы была возможность удалить путь для скана,
но в бд все будет сохранятся - и если этот путь вернется, то мы сверим что это тот же путь - и проблем не будет.  



-->