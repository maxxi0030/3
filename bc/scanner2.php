<?php

function runIncrementalScan(PDO $pdo) {

    set_time_limit(1200);
    ini_set('memory_limit', '512M');

    $stats = ['new' => 0, 'moved' => 0, 'updated' => 0, 'deleted' => 0];

    // =====================================================
    // 0. START SCAN
    // =====================================================
    $stmt = $pdo->prepare("
        INSERT INTO scan_history (scan_started_at, status)
        VALUES (CURRENT_TIMESTAMP, 'running')
        RETURNING id
    ");
    $stmt->execute();
    $scanId = $stmt->fetchColumn();

    try {

        // =====================================================
        // 1. LOAD SCAN PATHS
        // =====================================================
        $paths = $pdo->query("SELECT id, path FROM scan_paths")->fetchAll(PDO::FETCH_ASSOC);

        if (!$paths) {
            throw new Exception('Нет путей для сканирования');
        }

        $pathIds = array_column($paths, 'id');
        $placeholders = implode(',', array_fill(0, count($pathIds), '?'));

        // =====================================================
        // 1.5 RESET temp_found (КЛЮЧЕВО!)
        // =====================================================
        $pdo->prepare("
            UPDATE files
            SET temp_found = false
            WHERE scan_path_id IN ($placeholders)
        ")->execute($pathIds);

        // =====================================================
        // 2. LOAD FILES FROM DB (SNAPSHOT)
        // =====================================================
        $stmt = $pdo->prepare("
            SELECT *
            FROM files
            WHERE scan_path_id IN ($placeholders)
            AND file_status != 'deleted'
        ");
        $stmt->execute($pathIds);

        $dbByPath = [];
        $dbByNameSize = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $dbByPath[$row['file_path']] = $row;
            $key = $row['file_name'] . '|' . $row['file_size'];
            $dbByNameSize[$key] = $row;
        }

        // =====================================================
        // 3. FILE SYSTEM SNAPSHOT
        // =====================================================
        $fsFiles = [];
        $ignoredExtensions = ['reapeaks'];

        foreach ($paths as $rootData) {

            $root = normalizeWindowsPath($rootData['path']);
            $scanPathId = $rootData['id'];

            if (empty($root) || !is_dir($root)) {
                continue;
            }

            $dirIt = new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS);
            $it = new RecursiveIteratorIterator($dirIt, RecursiveIteratorIterator::LEAVES_ONLY);

            foreach ($it as $info) {

                if (!$info->isFile() || !$info->isReadable()) {
                    continue;
                }

                $path = str_replace('\\', '/', $info->getPathname());
                $name = $info->getFilename();
                $size = $info->getSize();
                $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));

                if (in_array($ext, $ignoredExtensions, true)) {
                    continue;
                }

                $fsFiles[] = [
                    'scan_path_id' => $scanPathId,
                    'path' => $path,
                    'name' => $name,
                    'size' => $size,
                    'ext'  => $ext
                ];
            }
        }

        // =====================================================
        // 4. COMPARE SNAPSHOTS
        // =====================================================
        $seenDb   = [];
        $toInsert = [];
        $toUpdate = [];
        $toMove   = [];
        $changes  = [];

        foreach ($fsFiles as $file) {

            $path = $file['path'];

            // === 1. ФАЙЛ НАЙДЕН ПО ТОМУ ЖЕ ПУТИ ===
            if (isset($dbByPath[$path])) {

                $dbFile = $dbByPath[$path];
                $seenDb[$dbFile['id']] = true;

                // размер изменился
                if ((int)$dbFile['file_size'] !== (int)$file['size']) {

                    $toUpdate[] = [
                        'id'     => $dbFile['id'],
                        'size'   => $file['size'],
                        'status' => 'updated'
                    ];

                    $changes[] = [
                        'file_id'  => $dbFile['id'],
                        'type'     => 'updated',
                        'old_path' => $path,
                        'new_path' => $path,
                        'details'  => json_encode([
                            'old_size' => $dbFile['file_size'],
                            'new_size' => $file['size']
                        ])
                    ];

                    $stats['updated']++;
                }
                // просто возвращаем в active
                elseif ($dbFile['file_status'] !== 'active') {

                    $toUpdate[] = [
                        'id'     => $dbFile['id'],
                        'size'   => $file['size'],
                        'status' => 'active'
                    ];
                }

                continue;
            }

            // === 2. ПЕРЕМЕЩЕН ===
            $key = $file['name'] . '|' . $file['size'];

            if (isset($dbByNameSize[$key])) {

                $dbFile = $dbByNameSize[$key];
                $seenDb[$dbFile['id']] = true;

                $toMove[] = [
                    'id'           => $dbFile['id'],
                    'path'         => $file['path'],
                    'scan_path_id' => $file['scan_path_id'],
                    'status'       => 'moved'
                ];

                $changes[] = [
                    'file_id'  => $dbFile['id'],
                    'type'     => 'moved',
                    'old_path' => $dbFile['file_path'],
                    'new_path' => $file['path'],
                    'details'  => null
                ];

                $stats['moved']++;
                continue;
            }

            // === 3. НОВЫЙ ФАЙЛ ===
            $toInsert[] = $file;
            $stats['new']++;
        }

        // =====================================================
        // 5. DELETED FILES
        // =====================================================
        $toDelete = [];

        foreach ($dbByPath as $dbFile) {
            if (
                !isset($seenDb[$dbFile['id']]) &&
                $dbFile['file_status'] !== 'deleted'
            ) {


                $toDelete[] = $dbFile['id'];

                $changes[] = [
                    'file_id'  => $dbFile['id'],
                    'type'     => 'deleted',
                    'old_path' => $dbFile['file_path'],
                    'new_path' => null,
                    'details'  => null
                ];
            }
        }

        $stats['deleted'] = count($toDelete);

        // =====================================================
        // 6. APPLY CHANGES
        // =====================================================
        $pdo->beginTransaction();

        // NEW
        foreach ($toInsert as $f) {
            $pdo->prepare("
                INSERT INTO files
                (scan_path_id, file_name, file_path, file_extension, file_size, file_status, temp_found)
                VALUES (?, ?, ?, ?, ?, 'new', true)
            ")->execute([
                $f['scan_path_id'], $f['name'], $f['path'], $f['ext'], $f['size']
            ]);
        }

        // UPDATED / ACTIVE
        foreach ($toUpdate as $u) {
            $pdo->prepare("
                UPDATE files
                SET file_size = ?, file_status = ?, temp_found = true, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ")->execute([$u['size'], $u['status'], $u['id']]);
        }

        // MOVED
        foreach ($toMove as $m) {
            $pdo->prepare("
                UPDATE files
                SET file_path = ?, scan_path_id = ?, file_status = ?, temp_found = true, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ")->execute([$m['path'], $m['scan_path_id'], $m['status'], $m['id']]);
        }

        // DELETED
        if ($toDelete) {
            $ph = implode(',', array_fill(0, count($toDelete), '?'));
            $pdo->prepare("
                UPDATE files
                SET file_status = 'deleted', updated_at = CURRENT_TIMESTAMP
                WHERE id IN ($ph)
            ")->execute($toDelete);
        }

        // LOGS
        foreach ($changes as $c) {
            $pdo->prepare("
                INSERT INTO file_changes
                (scan_history_id, file_id, change_type, old_path, new_path, details)
                VALUES (?, ?, ?, ?, ?, ?)
            ")->execute([
                $scanId,
                $c['file_id'],
                $c['type'],
                $c['old_path'],
                $c['new_path'],
                $c['details']
            ]);
        }

        $pdo->commit();

        // =====================================================
        // 7. FINISH SCAN
        // =====================================================
        $pdo->prepare("
            UPDATE scan_history
            SET scan_finished_at = CURRENT_TIMESTAMP,
                files_added = ?,
                files_updated = ?,
                files_moved = ?,
                files_deleted = ?,
                status = 'success'
            WHERE id = ?
        ")->execute([
            $stats['new'],
            $stats['updated'],
            $stats['moved'],
            $stats['deleted'],
            $scanId
        ]);

        $pdo->exec("UPDATE scan_paths SET last_scanned_at = CURRENT_TIMESTAMP");

        $_SESSION['last_scan_time'] = date("H:i:s");

        return ['status' => 'success', 'stats' => $stats, 'scan_id' => $scanId];

    } catch (Exception $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $pdo->prepare("
            UPDATE scan_history
            SET status = 'error', error_message = ?, scan_finished_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ")->execute([$e->getMessage(), $scanId]);

        return ['status' => 'error', 'message' => $e->getMessage()];
    }
}

function normalizeWindowsPath($path) {
    $path = preg_replace('/[\x00-\x1F\x7F\xA0]/u', '', $path);
    $path = trim($path);
    $path = str_replace('/', '\\', $path);
    return $path;
}

?>