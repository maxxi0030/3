<?php
/**
 * QUICK SCANNER
 * 
 * Когда запускается:
 * - Кнопка "Quick Scan" (доступна всем)
 * - Только для путей где full_scan_completed = true
 * 
 * Что делает:
 * - Использует size + mtime для определения изменений (быстро)
 * - Пропускает неизменённые файлы
 * - Использует partial_hash для поиска перемещений
 * - Хеширует только новые файлы
 * - Находит: new, moved, updated, deleted, source_off
 */

function runQuickScan($pdo) {
    set_time_limit(7200); // 2 часа
    ini_set('memory_limit', '2048M');
    
    $stats = ['new' => 0, 'moved' => 0, 'updated' => 0, 'deleted' => 0, 'skipped' => 0, 'errors' => 0];
    
    // ============================================
    // 0. СОЗДАЁМ ЗАПИСЬ О НАЧАЛЕ СКАНИРОВАНИЯ
    // ============================================
    $scanStartStmt = $pdo->prepare("
        INSERT INTO scan_history 
        (scan_path_id, scan_started_at, status, scan_type) 
        VALUES (NULL, CURRENT_TIMESTAMP, 'running', 'quick')
        RETURNING id
    ");
    $scanStartStmt->execute();
    $currentScanId = $scanStartStmt->fetchColumn();
    
    try {
        $pdo->beginTransaction();
        
        // ============================================
        // 1. ПОЛУЧАЕМ АКТИВНЫЕ ПУТИ
        // ============================================
        $stmt = $pdo->query("SELECT id, path FROM scan_paths WHERE is_active = true");
        $roots = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($roots)) {
            throw new Exception("Нет активных путей для сканирования");
        }
        
        // Проверяем что для всех путей был выполнен full scan
        $notCompleted = $pdo->query("
            SELECT COUNT(*) FROM scan_paths 
            WHERE is_active = true AND full_scan_completed = false
        ")->fetchColumn();
        
        if ($notCompleted > 0) {
            throw new Exception("Сначала выполните Full Scan для всех путей");
        }
        
        $activePathIds = array_column($roots, 'id');
        $placeholders = implode(',', array_fill(0, count($activePathIds), '?'));
        
        // ============================================
        // 2. ЗАГРУЖАЕМ ВСЕ ФАЙЛЫ ИЗ БД ОДИН РАЗ
        // ============================================
        $dbFiles = [];
        $stmt = $pdo->query("
            SELECT id, file_path, file_name, file_size, mtime, partial_hash, scan_path_id, file_status 
            FROM files
        ");
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $dbFiles[$row['file_path']] = $row;
        }
        
        // ============================================
        // 3. СБРАСЫВАЕМ ФЛАГИ
        // ============================================
        $resetStmt = $pdo->prepare("
            UPDATE files 
            SET temp_found = false 
            WHERE scan_path_id IN ($placeholders) 
               OR scan_path_id IS NULL 
               OR file_status = 'source_off'
        ");
        $resetStmt->execute($activePathIds);
        
        // ============================================
        // 4. СКАНИРОВАНИЕ ФАЙЛОВОЙ СИСТЕМЫ
        // ============================================
        $pending_new = []; // Потенциально новые файлы
        $batchUpdates = [];
        $processedCount = 0;
        
        foreach ($roots as $rootData) {
            $root = rtrim($rootData['path'], '/\\');
            $scanPathId = $rootData['id'];
            
            if (!is_dir($root)) {
                error_log("Quick scan: путь не существует: $root");
                continue;
            }
            
            try {
                $dir_iterator = new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS);
                $iterator = new RecursiveIteratorIterator(
                    $dir_iterator, 
                    RecursiveIteratorIterator::LEAVES_ONLY,
                    RecursiveIteratorIterator::CATCH_GET_CHILD
                );
                
                foreach ($iterator as $info) {
                    try {
                        if (!$info->isReadable() || $info->isDir()) continue;
                        
                        $path = str_replace('\\', '/', $info->getPathname());
                        $name = $info->getFilename();
                        $size = $info->getSize();
                        $mtime = $info->getMTime();
                        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                        
                        $processedCount++;
                        
                        // Обновляем прогресс каждые 500 файлов
                        if ($processedCount % 500 == 0) {
                            $pdo->prepare("
                                UPDATE scan_history 
                                SET progress_current = ?
                                WHERE id = ?
                            ")->execute([$processedCount, $currentScanId]);
                        }
                        
                        // ------------------------------------------
                        // A. ФАЙЛ НАЙДЕН ПО ПУТИ В БД
                        // ------------------------------------------
                        if (isset($dbFiles[$path])) {
                            $existing = $dbFiles[$path];
                            
                            // Сравниваем size + mtime
                            if ($existing['file_size'] == $size && $existing['mtime'] == $mtime) {
                                // Файл не изменился - пропускаем
                                $dbFiles[$path]['temp_found'] = true;
                                
                                // Но если статус не active - обновляем статус
                                if ($existing['file_status'] !== 'active') {
                                    $batchUpdates[] = [
                                        'id' => $existing['id'],
                                        'status' => 'active',
                                        'temp_found' => true
                                    ];
                                } else {
                                    $stats['skipped']++;
                                }
                                
                            } else {
                                // Размер или mtime изменились - файл обновлён
                                // Пересчитываем хеш
                                $newHash = calculatePartialHash($path);
                                
                                if ($newHash) {
                                    $batchUpdates[] = [
                                        'id' => $existing['id'],
                                        'size' => $size,
                                        'mtime' => $mtime,
                                        'hash' => $newHash,
                                        'status' => 'updated',
                                        'temp_found' => true
                                    ];
                                    $stats['updated']++;
                                } else {
                                    error_log("Quick scan: не удалось пересчитать хеш для: $path");
                                    $stats['errors']++;
                                }
                                
                                $dbFiles[$path]['temp_found'] = true;
                            }
                            
                        } else {
                            // ------------------------------------------
                            // B. ФАЙЛ НЕ НАЙДЕН ПО ПУТИ
                            // ------------------------------------------
                            // Считаем хеш и добавляем в pending для анализа перемещений
                            $partialHash = calculatePartialHash($path);
                            
                            if ($partialHash) {
                                $pending_new[] = [
                                    'scan_path_id' => $scanPathId,
                                    'name' => $name,
                                    'path' => $path,
                                    'extension' => $ext,
                                    'size' => $size,
                                    'mtime' => $mtime,
                                    'hash' => $partialHash
                                ];
                            } else {
                                error_log("Quick scan: не удалось посчитать хеш для нового файла: $path");
                                $stats['errors']++;
                            }
                        }
                        
                    } catch (Exception $e) {
                        error_log("Quick scan: ошибка обработки файла " . $info->getPathname() . ": " . $e->getMessage());
                        $stats['errors']++;
                        continue;
                    }
                }
                
            } catch (Exception $e) {
                error_log("Quick scan: ошибка обхода пути $root: " . $e->getMessage());
                $stats['errors']++;
                continue;
            }
        }
        
        // ============================================
        // 5. ПРИМЕНЯЕМ BATCH UPDATES
        // ============================================
        if (!empty($batchUpdates)) {
            foreach ($batchUpdates as $upd) {
                if (isset($upd['hash'])) {
                    // Обновление с новым хешем
                    $stmt = $pdo->prepare("
                        UPDATE files SET 
                            file_size = ?,
                            mtime = ?,
                            partial_hash = ?,
                            file_status = ?,
                            temp_found = ?,
                            updated_at = CURRENT_TIMESTAMP
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $upd['size'], 
                        $upd['mtime'], 
                        $upd['hash'], 
                        $upd['status'], 
                        $upd['temp_found'], 
                        $upd['id']
                    ]);
                } else {
                    // Просто обновление статуса
                    $stmt = $pdo->prepare("
                        UPDATE files SET 
                            file_status = ?,
                            temp_found = ?,
                            updated_at = CURRENT_TIMESTAMP
                        WHERE id = ?
                    ");
                    $stmt->execute([$upd['status'], $upd['temp_found'], $upd['id']]);
                }
            }
        }
        
        // Массово обновляем temp_found для пропущенных файлов
        $pdo->prepare("
            UPDATE files 
            SET temp_found = true 
            WHERE file_status = 'active' 
              AND temp_found = false 
              AND scan_path_id IN ($placeholders)
        ")->execute($activePathIds);
        
        // ============================================
        // 6. АНАЛИЗ ПЕРЕМЕЩЕНИЙ/НОВЫХ ФАЙЛОВ
        // ============================================
        
        // Создаём lookup для потерянных файлов
        $lostFilesLookup = [];
        $stmt = $pdo->query("
            SELECT id, file_path, file_name, file_size, partial_hash, scan_path_id, file_status 
            FROM files 
            WHERE temp_found = false 
               OR file_status = 'source_off'
        ");
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $key = $row['partial_hash'] . '_' . $row['file_size'];
            $lostFilesLookup[$key] = $row;
        }
        
        // Обрабатываем pending файлы
        $batchInserts = [];
        $batchMoves = [];
        
        foreach ($pending_new as $item) {
            $key = $item['hash'] . '_' . $item['size'];
            
            if (isset($lostFilesLookup[$key])) {
                // Найден потерянный файл с таким же хешем и размером - это перемещение
                $lostFile = $lostFilesLookup[$key];
                $oldPath = $lostFile['file_path'];
                $oldStatus = $lostFile['file_status'];
                
                // Определяем новый статус
                if ($oldStatus === 'source_off' || $lostFile['scan_path_id'] === null) {
                    $newStatus = 'active';
                    $changeType = 'restored';
                } else {
                    $newStatus = 'moved';
                    $changeType = 'moved';
                }
                
                $batchMoves[] = [
                    'id' => $lostFile['id'],
                    'path' => $item['path'],
                    'scan_path_id' => $item['scan_path_id'],
                    'mtime' => $item['mtime'],
                    'status' => $newStatus,
                    'old_path' => $oldPath,
                    'change_type' => $changeType
                ];
                
                if ($changeType === 'moved') {
                    $stats['moved']++;
                }
                
                // Убираем из lookup чтобы не использовать дважды
                unset($lostFilesLookup[$key]);
                
            } else {
                // Новый файл
                $batchInserts[] = $item;
                $stats['new']++;
            }
        }
        
        // Применяем перемещения
        if (!empty($batchMoves)) {
            $logMove = $pdo->prepare("
                INSERT INTO file_changes (scan_history_id, file_id, change_type, old_path, new_path)
                VALUES (?, ?, ?, ?, ?)
            ");
            
            foreach ($batchMoves as $move) {
                // Обновляем файл
                $stmt = $pdo->prepare("
                    UPDATE files SET 
                        file_path = ?,
                        scan_path_id = ?,
                        mtime = ?,
                        file_status = ?,
                        temp_found = true,
                        updated_at = CURRENT_TIMESTAMP 
                    WHERE id = ?
                ");
                $stmt->execute([
                    $move['path'], 
                    $move['scan_path_id'],
                    $move['mtime'],
                    $move['status'], 
                    $move['id']
                ]);
                
                // Логируем
                $logMove->execute([
                    $currentScanId,
                    $move['id'],
                    $move['change_type'],
                    $move['old_path'],
                    $move['path']
                ]);
            }
        }
        
        // Применяем вставки новых файлов
        if (!empty($batchInserts)) {
            $chunkSize = 500;
            foreach (array_chunk($batchInserts, $chunkSize) as $chunk) {
                $values = [];
                $params = [];
                
                foreach ($chunk as $item) {
                    $values[] = "(?, ?, ?, ?, ?, ?, ?, 'new', true)";
                    $params[] = $item['scan_path_id'];
                    $params[] = $item['name'];
                    $params[] = $item['path'];
                    $params[] = $item['extension'];
                    $params[] = $item['size'];
                    $params[] = $item['mtime'];
                    $params[] = $item['hash'];
                }
                
                $sql = "INSERT INTO files (scan_path_id, file_name, file_path, file_extension, file_size, mtime, partial_hash, file_status, temp_found) 
                        VALUES " . implode(', ', $values);
                $pdo->prepare($sql)->execute($params);
            }
        }
        
        // ============================================
        // 7. ОБРАБОТКА УДАЛЁННЫХ ФАЙЛОВ
        // ============================================
        
        // Получаем список удалённых файлов из активных путей
        $stmtDeleted = $pdo->prepare("
            SELECT id, file_path FROM files 
            WHERE temp_found = false 
              AND file_status NOT IN ('deleted', 'source_off')
              AND scan_path_id IN ($placeholders)
        ");
        $stmtDeleted->execute($activePathIds);
        $deletedFiles = $stmtDeleted->fetchAll(PDO::FETCH_ASSOC);
        
        $stats['deleted'] = count($deletedFiles);
        
        // Логируем удалённые файлы
        if ($stats['deleted'] > 0) {
            $logDelete = $pdo->prepare("
                INSERT INTO file_changes (scan_history_id, file_id, change_type, old_path)
                VALUES (?, ?, 'deleted', ?)
            ");
            
            foreach ($deletedFiles as $deletedFile) {
                $logDelete->execute([
                    $currentScanId,
                    $deletedFile['id'],
                    $deletedFile['file_path']
                ]);
            }
        }
        
        // Массово обновляем статус для удалённых файлов
        $updateDeletedStmt = $pdo->prepare("
            UPDATE files 
            SET file_status = 'deleted', updated_at = CURRENT_TIMESTAMP
            WHERE temp_found = false 
              AND file_status NOT IN ('deleted', 'source_off')
              AND scan_path_id IN ($placeholders)
        ");
        $updateDeletedStmt->execute($activePathIds);
        
        // ============================================
        // 8. ОБРАБОТКА SOURCE_OFF
        // ============================================
        
        // Помечаем файлы из неактивных путей
        $pdo->exec("
            UPDATE files 
            SET file_status = 'source_off', updated_at = CURRENT_TIMESTAMP
            WHERE file_status != 'source_off'
              AND scan_path_id IN (SELECT id FROM scan_paths WHERE is_active = false)
        ");
        
        // Помечаем файлы, чьи пути были удалены
        $pdo->exec("
            UPDATE files 
            SET file_status = 'source_off', updated_at = CURRENT_TIMESTAMP
            WHERE file_status != 'source_off'
              AND (scan_path_id IS NULL OR scan_path_id NOT IN (SELECT id FROM scan_paths))
        ");
        
        // ============================================
        // 9. ОБНОВЛЯЕМ ВРЕМЯ ПОСЛЕДНЕГО СКАНА
        // ============================================
        $pdo->exec("UPDATE scan_paths SET last_quick_scan_at = CURRENT_TIMESTAMP WHERE is_active = true");
        
        // ============================================
        // 10. ФИНАЛИЗАЦИЯ
        // ============================================
        $updateScanStmt = $pdo->prepare("
            UPDATE scan_history SET 
                scan_finished_at = CURRENT_TIMESTAMP,
                files_found = ?,
                files_added = ?,
                files_updated = ?,
                files_deleted = ?,
                files_moved = ?,
                status = 'success'
            WHERE id = ?
        ");
        $updateScanStmt->execute([
            $processedCount,
            $stats['new'],
            $stats['updated'],
            $stats['deleted'],
            $stats['moved'],
            $currentScanId
        ]);
        
        $pdo->commit();
        
        return [
            'status' => 'success',
            'message' => 'Quick сканирование завершено',
            'stats' => $stats,
            'scan_id' => $currentScanId
        ];
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        $pdo->prepare("
            UPDATE scan_history SET 
                status = 'error', 
                error_message = ?,
                scan_finished_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ")->execute([$e->getMessage(), $currentScanId]);
        
        return [
            'status' => 'error',
            'message' => 'Ошибка при quick сканировании: ' . $e->getMessage()
        ];
    }
}



?>