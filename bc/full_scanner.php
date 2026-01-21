<?php
/**
 * FULL SCANNER
 * 
 * Когда запускается:
 * - Автоматически: новый путь добавлен (full_scan_completed = false)
 * - Вручную: админ нажал кнопку "Full Scan" для конкретного пути
 * 
 * Что делает:
 * - Считает partial_hash (первые 64KB) для всех файлов
 * - Находит новые файлы (статус 'new')
 * - При повторном запуске может найти изменённые (хеш изменился)
 */

function runFullScan($pdo, $specificPathId = null) {
    set_time_limit(7200); // 2 часа
    ini_set('memory_limit', '2048M');
    
    $stats = ['new' => 0, 'updated' => 0, 'errors' => 0];
    
    // ============================================
    // 0. СОЗДАЁМ ЗАПИСЬ О НАЧАЛЕ СКАНИРОВАНИЯ
    // ============================================
    $scanStartStmt = $pdo->prepare("
        INSERT INTO scan_history 
        (scan_path_id, scan_started_at, status, scan_type) 
        VALUES (?, CURRENT_TIMESTAMP, 'running', 'full')
        RETURNING id
    ");
    $scanStartStmt->execute([$specificPathId]);
    $currentScanId = $scanStartStmt->fetchColumn();
    
    try {
        $pdo->beginTransaction();
        
        // ============================================
        // 1. ПОЛУЧАЕМ ПУТИ ДЛЯ СКАНИРОВАНИЯ
        // ============================================
        if ($specificPathId) {
            // Конкретный путь (ручной запуск)
            $stmt = $pdo->prepare("SELECT id, path FROM scan_paths WHERE id = ? AND is_active = true");
            $stmt->execute([$specificPathId]);
            $roots = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // Все пути где full_scan_completed = false (автоматический запуск)
            $stmt = $pdo->query("SELECT id, path FROM scan_paths WHERE full_scan_completed = false AND is_active = true");
            $roots = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        if (empty($roots)) {
            throw new Exception("Нет путей для полного сканирования");
        }
        
        // ============================================
        // 2. ЗАГРУЖАЕМ СУЩЕСТВУЮЩИЕ ФАЙЛЫ ИЗ БД
        // ============================================
        $dbFiles = [];
        $pathIds = array_column($roots, 'id');
        $placeholders = implode(',', array_fill(0, count($pathIds), '?'));
        
        $stmt = $pdo->prepare("
            SELECT id, file_path, file_size, partial_hash, file_status 
            FROM files 
            WHERE scan_path_id IN ($placeholders)
        ");
        $stmt->execute($pathIds);
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $dbFiles[$row['file_path']] = $row;
        }
        
        // ============================================
        // 3. СКАНИРОВАНИЕ ФАЙЛОВОЙ СИСТЕМЫ
        // ============================================
        $batchInserts = [];
        $batchUpdates = [];
        $processedCount = 0;
        
        foreach ($roots as $rootData) {
            $root = rtrim($rootData['path'], '/\\');
            $scanPathId = $rootData['id'];
            
            if (!is_dir($root)) {
                error_log("Full scan: путь не существует: $root");
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
                        
                        // Считаем partial hash (первые 64KB)
                        $partialHash = calculatePartialHash($path);
                        
                        if (!$partialHash) {
                            error_log("Full scan: не удалось посчитать хеш для: $path");
                            $stats['errors']++;
                            continue;
                        }
                        
                        $processedCount++;
                        
                        // Обновляем прогресс каждые 500 файлов
                        if ($processedCount % 500 == 0) {
                            $pdo->prepare("
                                UPDATE scan_history 
                                SET progress_current = ?
                                WHERE id = ?
                            ")->execute([$processedCount, $currentScanId]);
                        }
                        
                        // Проверяем существует ли файл в БД
                        if (isset($dbFiles[$path])) {
                            // Файл существует - проверяем изменился ли хеш
                            $existing = $dbFiles[$path];
                            
                            if ($existing['partial_hash'] !== $partialHash) {
                                // Хеш изменился - файл обновлён
                                $batchUpdates[] = [
                                    'id' => $existing['id'],
                                    'size' => $size,
                                    'mtime' => $mtime,
                                    'hash' => $partialHash,
                                    'status' => 'updated'
                                ];
                                $stats['updated']++;
                            } else {
                                // Хеш не изменился - просто обновляем mtime если нужно
                                if ($existing['file_status'] !== 'active') {
                                    $batchUpdates[] = [
                                        'id' => $existing['id'],
                                        'mtime' => $mtime,
                                        'status' => 'active'
                                    ];
                                }
                            }
                        } else {
                            // Новый файл
                            $batchInserts[] = [
                                'scan_path_id' => $scanPathId,
                                'name' => $name,
                                'path' => $path,
                                'extension' => $ext,
                                'size' => $size,
                                'mtime' => $mtime,
                                'hash' => $partialHash
                            ];
                            $stats['new']++;
                        }
                        
                    } catch (Exception $e) {
                        error_log("Full scan: ошибка обработки файла " . $info->getPathname() . ": " . $e->getMessage());
                        $stats['errors']++;
                        continue;
                    }
                }
                
            } catch (Exception $e) {
                error_log("Full scan: ошибка обхода пути $root: " . $e->getMessage());
                $stats['errors']++;
                continue;
            }
        }
        
        // ============================================
        // 4. BATCH INSERT
        // ============================================
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
        // 5. BATCH UPDATE
        // ============================================
        if (!empty($batchUpdates)) {
            foreach ($batchUpdates as $upd) {
                if (isset($upd['hash'])) {
                    // Обновление с новым хешем (файл изменился)
                    $stmt = $pdo->prepare("
                        UPDATE files SET 
                            file_size = ?, 
                            mtime = ?,
                            partial_hash = ?,
                            file_status = ?, 
                            updated_at = CURRENT_TIMESTAMP
                        WHERE id = ?
                    ");
                    $stmt->execute([$upd['size'], $upd['mtime'], $upd['hash'], $upd['status'], $upd['id']]);
                } else {
                    // Простое обновление статуса
                    $stmt = $pdo->prepare("
                        UPDATE files SET 
                            mtime = ?,
                            file_status = ?, 
                            updated_at = CURRENT_TIMESTAMP
                        WHERE id = ?
                    ");
                    $stmt->execute([$upd['mtime'], $upd['status'], $upd['id']]);
                }
            }
        }
        
        // ============================================
        // 6. ОБНОВЛЯЕМ СТАТУС ПУТЕЙ
        // ============================================
        foreach ($roots as $rootData) {
            $pdo->prepare("
                UPDATE scan_paths 
                SET full_scan_completed = true,
                    last_full_scan_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ")->execute([$rootData['id']]);
        }
        
        // ============================================
        // 7. ФИНАЛИЗАЦИЯ
        // ============================================
        $updateScanStmt = $pdo->prepare("
            UPDATE scan_history SET 
                scan_finished_at = CURRENT_TIMESTAMP,
                files_found = ?,
                files_added = ?,
                files_updated = ?,
                status = 'success'
            WHERE id = ?
        ");
        $updateScanStmt->execute([
            $processedCount,
            $stats['new'],
            $stats['updated'],
            $currentScanId
        ]);
        
        $pdo->commit();
        
        return [
            'status' => 'success',
            'message' => 'Полное сканирование завершено',
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
            'message' => 'Ошибка при полном сканировании: ' . $e->getMessage()
        ];
    }
}

/**
 * Вычисляет partial hash (MD5 первых 64KB файла)
 */
function calculatePartialHash($filePath) {
    try {
        $fp = @fopen($filePath, 'rb');
        if (!$fp) {
            return null;
        }
        
        $data = fread($fp, 65536); // 64KB
        fclose($fp);
        
        if ($data === false || strlen($data) === 0) {
            return null;
        }
        
        return md5($data);
        
    } catch (Exception $e) {
        error_log("Ошибка хеширования файла $filePath: " . $e->getMessage());
        return null;
    }
}

?>