<?php

function runIncrementalScan($pdo) {
    // Увеличиваем лимиты для тяжелой работы
    set_time_limit(1200);
    ini_set('memory_limit', '512M');

    $stats = ['new' => 0, 'moved' => 0, 'deleted' => 0, 'updated' => 0];

    // ============================================
    // 0. СОЗДАЁМ ЗАПИСЬ О НАЧАЛЕ СКАНИРОВАНИЯ
    // ============================================
    $scanStartStmt = $pdo->prepare("
        INSERT INTO scan_history 
        (scan_path_id, scan_started_at, status) 
        VALUES (NULL, CURRENT_TIMESTAMP, 'running')
        RETURNING id
    ");
    $scanStartStmt->execute();
    $currentScanId = $scanStartStmt->fetchColumn();

    try {
        // ============================================
        // 1. ПОЛУЧАЕМ ПУТИ ДЛЯ СКАНИРОВАНИЯ (ЭТО ПЕРВОЕ!)
        // ============================================
        $stmt = $pdo->query("SELECT id, path FROM scan_paths");
        $roots = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($roots)) {
            throw new Exception("Нет активных путей для сканирования (таблица scan_paths пуста)");
        }

        // Формируем ID для SQL запросов
        $activePathIds = array_column($roots, 'id');
        $placeholders = implode(',', array_fill(0, count($activePathIds), '?'));

        // ============================================
        // 2. ЗАГРУЖАЕМ "КАРТУ" СУЩЕСТВУЮЩИХ ФАЙЛОВ
        // ============================================
        // Теперь $placeholders и $activePathIds существуют, можно делать запрос
        $existingFilesMap = [];
        
        // Берем файлы из активных путей ИЛИ те, что потеряли путь (scan_path_id IS NULL)
        $sqlFiles = "SELECT id, file_path, file_size, file_status, scan_path_id 
                     FROM files 
                     WHERE scan_path_id IN ($placeholders) OR scan_path_id IS NULL";
        
        $stmtFiles = $pdo->prepare($sqlFiles);
        $stmtFiles->execute($activePathIds);

        while ($row = $stmtFiles->fetch(PDO::FETCH_ASSOC)) {
            // Ключ = путь файла. Это позволит искать файл за 0.0001 сек.
            $existingFilesMap[$row['file_path']] = $row;
        }

        // Инициализируем массивы для изменений
        $filesToUpdate = [];    // Изменился размер/статус
        $filesToInsert = [];    // Новые файлы
        $potentialNewFiles = [];// Кандидаты в новые (для проверки перемещений)
        $foundFileIds = [];     // ID файлов, которые реально существуют на диске

        // ============================================
        // 3. СКАНИРОВАНИЕ ДИСКА (RAM Only)
        // ============================================
        
        foreach ($roots as $rootData) {
            $root = rtrim($rootData['path'], '/\\');
            $scanPathId = $rootData['id'];

            if (!is_dir($root)) {
                // Логируем, но не останавливаем весь процесс
                error_log("Путь недоступен: $root");
                continue;
            }

            $dir_iterator = new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS);
            $iterator = new RecursiveIteratorIterator($dir_iterator, RecursiveIteratorIterator::LEAVES_ONLY, RecursiveIteratorIterator::CATCH_GET_CHILD);

            foreach ($iterator as $info) {
                if (!$info->isReadable()) continue;

                $path = str_replace('\\', '/', $info->getPathname());
                $size = $info->getSize();
                $name = $info->getFilename();
                
                // ПРОВЕРКА В ПАМЯТИ (Вместо SQL)
                if (isset($existingFilesMap[$path])) {
                    // --- Файл уже есть в базе ---
                    $existing = $existingFilesMap[$path];
                    $foundFileIds[$existing['id']] = true; // Отмечаем: "Я видел этот файл, не удаляй его"

                    // Проверяем изменения
                    if ($existing['file_size'] != $size) {
                        $filesToUpdate[] = [
                            'id' => $existing['id'],
                            'size' => $size,
                            'status' => 'updated',
                            'old_size' => $existing['file_size']
                        ];
                        $stats['updated']++;
                    } elseif ($existing['file_status'] !== 'active') {
                        // Если файл был помечен как удаленный, но мы его нашли
                        $filesToUpdate[] = [
                            'id' => $existing['id'],
                            'size' => $size,
                            'status' => 'active',
                            'old_size' => $size
                        ];
                    }

                } else {
                    // --- Файла нет в базе по этому пути ---
                    $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                    $potentialNewFiles[] = [
                        'scan_path_id' => $scanPathId,
                        'name' => $name,
                        'path' => $path,
                        'extension' => $extension,
                        'size' => $size
                    ];
                }
            }
        }

        // ============================================
        // 4. АНАЛИЗ ПЕРЕМЕЩЕНИЙ (RAM Only)
        // ============================================
        $filesToMove = [];
        $lostFilesMap = []; // "Потеряшки": есть в БД, но не найдены по старому пути

        // Собираем список потерянных файлов из existingFilesMap
        foreach ($existingFilesMap as $path => $file) {
            // Если ID нет в найденных И статус не "источник отключен"
            if (!isset($foundFileIds[$file['id']]) && $file['file_status'] !== 'source_off') {
                // Группируем по "Имя_Размер" для поиска дубликатов
                $key = basename($path) . '_' . $file['file_size'];
                $lostFilesMap[$key][] = $file;
            }
        }

        // Проверяем новых кандидатов: не являются ли они перемещенными старыми?
        foreach ($potentialNewFiles as $newItem) {
            $key = $newItem['name'] . '_' . $newItem['size'];

            if (isset($lostFilesMap[$key]) && count($lostFilesMap[$key]) > 0) {
                // УРА! Это перемещение
                $lostFile = array_shift($lostFilesMap[$key]); // Берем первого совпавшего
                
                $filesToMove[] = [
                    'id' => $lostFile['id'],
                    'new_path' => $newItem['path'],
                    'scan_path_id' => $newItem['scan_path_id'],
                    'old_path' => $lostFile['file_path']
                ];
                
                $foundFileIds[$lostFile['id']] = true; // Теперь он найден (чтобы не удалился)
                $stats['moved']++;
            } else {
                // Это точно новый файл
                $filesToInsert[] = $newItem;
                $stats['new']++;
            }
        }

        // ============================================
        // 5. МАССОВАЯ ЗАПИСЬ В БД (ТРАНЗАКЦИЯ)
        // ============================================
        $pdo->beginTransaction();

        // 5.1 Updates
        if (!empty($filesToUpdate)) {
            $updStmt = $pdo->prepare("UPDATE files SET file_size = ?, file_status = ?, temp_found = true, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            // Логирование изменений размера
            $logStmt = $pdo->prepare("INSERT INTO file_changes (scan_history_id, file_id, change_type, old_path, new_path, details) VALUES (?, ?, 'updated', (SELECT file_path FROM files WHERE id=?), (SELECT file_path FROM files WHERE id=?), ?::jsonb)");
            
            foreach ($filesToUpdate as $item) {
                $updStmt->execute([$item['size'], $item['status'], $item['id']]);
                
                if ($item['status'] === 'updated') {
                     $logStmt->execute([
                        $currentScanId, $item['id'], $item['id'], $item['id'], 
                        json_encode(['old_size' => $item['old_size'], 'new_size' => $item['size']])
                    ]);
                }
            }
        }

        // 5.2 Moves
        if (!empty($filesToMove)) {
            $movStmt = $pdo->prepare("UPDATE files SET file_path = ?, scan_path_id = ?, file_status = 'moved', temp_found = true, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $logMov = $pdo->prepare("INSERT INTO file_changes (scan_history_id, file_id, change_type, old_path, new_path) VALUES (?, ?, 'moved', ?, ?)");
            
            foreach ($filesToMove as $item) {
                $movStmt->execute([$item['new_path'], $item['scan_path_id'], $item['id']]);
                $logMov->execute([$currentScanId, $item['id'], $item['old_path'], $item['new_path']]);
            }
        }

        // 5.3 Inserts (Чанками по 500)
        if (!empty($filesToInsert)) {
            foreach (array_chunk($filesToInsert, 500) as $chunk) {
                $placeholdersInsert = [];
                $paramsInsert = [];
                foreach ($chunk as $f) {
                    $placeholdersInsert[] = "(?, ?, ?, ?, ?, 'new', true)";
                    array_push($paramsInsert, $f['scan_path_id'], $f['name'], $f['path'], $f['extension'], $f['size']);
                }
                $sql = "INSERT INTO files (scan_path_id, file_name, file_path, file_extension, file_size, file_status, temp_found) VALUES " . implode(',', $placeholdersInsert);
                $pdo->prepare($sql)->execute($paramsInsert);
            }
        }

        // 5.4 Deletes (Кого нет в foundFileIds)
        $idsToDelete = [];
        foreach ($existingFilesMap as $path => $file) {
            // Если не нашли И статус не 'source_off' И не 'deleted' (чтобы сто раз не удалять)
            if (!isset($foundFileIds[$file['id']]) && 
                $file['file_status'] !== 'source_off' && 
                $file['file_status'] !== 'deleted') {
                $idsToDelete[] = $file['id'];
            }
        }

        if (!empty($idsToDelete)) {
            foreach (array_chunk($idsToDelete, 1000) as $chunkIds) {
                $inQuery = implode(',', array_fill(0, count($chunkIds), '?'));
                
                // Лог удаления
                $logDelSql = "INSERT INTO file_changes (scan_history_id, file_id, change_type, old_path) 
                              SELECT ?, id, 'deleted', file_path FROM files WHERE id IN ($inQuery)";
                $pdo->prepare($logDelSql)->execute(array_merge([$currentScanId], $chunkIds));

                // Обновление статуса
                $pdo->prepare("UPDATE files SET file_status = 'deleted', updated_at = CURRENT_TIMESTAMP, temp_found = false WHERE id IN ($inQuery)")
                    ->execute($chunkIds);
            }
            $stats['deleted'] = count($idsToDelete);
        }

        // 5.5 Отметка source_off для тех, чьи пути удалены из админки (опционально)
        // Это можно делать отдельным кроном, но можно и здесь
        
        $pdo->commit();

        // ============================================
        // 6. ФИНАЛИЗАЦИЯ
        // ============================================
        $pdo->prepare("
            UPDATE scan_history SET 
                scan_finished_at = CURRENT_TIMESTAMP,
                files_found = (SELECT COUNT(*) FROM files WHERE file_status != 'deleted' AND file_status != 'source_off'),
                files_added = ?, files_updated = ?, files_deleted = ?, files_moved = ?,
                status = 'success'
            WHERE id = ?
        ")->execute([$stats['new'], $stats['updated'], $stats['deleted'], $stats['moved'], $currentScanId]);

        // Обновляем время последнего скана для путей
        $pdo->prepare("UPDATE scan_paths SET last_scanned_at = CURRENT_TIMESTAMP WHERE id IN ($placeholders)")->execute($activePathIds);

        return [
            'status' => 'success',
            'message' => 'Сканирование завершено',
            'stats' => $stats
        ];

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        // Записываем ошибку
        $pdo->prepare("UPDATE scan_history SET status = 'error', error_message = ?, scan_finished_at = CURRENT_TIMESTAMP WHERE id = ?")
            ->execute([$e->getMessage(), $currentScanId]);
        
        return [
            'status' => 'error',
            'message' => 'Ошибка: ' . $e->getMessage()
        ];
    }
}

?>