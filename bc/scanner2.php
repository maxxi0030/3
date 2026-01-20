<?php

// function runIncrementalScan($pathsFile, $dataFile) {

//     // увеличиваем время выполнения, если папок много. но это работает только пока нету настоящей бд - потом надо будет тут менять логику
//     set_time_limit(600);
//     ini_set('memory_limit', '512M');


//     // ЗАГРУЗКА СПИСКА ПУТЕЙ
//     if (file_exists($pathsFile)) {
//         $content = file_get_contents($pathsFile);
//         $decoded_paths = json_decode($content, true);

//         // проверяем что джсон валидный
//         if (is_array($decoded_paths)) {
//             $saved_paths = $decoded_paths;
//         }
//     } else {
//         return "путь не найден";
//     }

//     // ЗАГРУЗКА ТЕКУЩЕЙ БАЗЫ ФАЙЛОВ

//     $current_db = []; // по умолчанию база пустая

//     if (file_exists($dataFile)) {
//         $content = file_get_contents($dataFile);
//         $decoded_data = json_decode($content, true);

//         // Если файл битый или пустой, json_decode вернет null
//         if (is_array($decoded_data)) {
//             $current_db = $decoded_data;
//         }
//     }


//     // ИНДЕКСАЦИЯ - типо подготовка для быстрого поиска

//     $pathIndex = []; // это для определения индекса в массиве
//     $maxId = 0; // это для поиска последнего айди


//     foreach ($current_db as $key => $file) { 

//     // Нормализуем путь сразу. Меняем обратные слеши на прямые.
//         // Это важно, чтобы C:\Folder\File.txt и C:/Folder/File.txt считались одним и тем же.

//         $normPath = str_replace('\\', "/", $file['path']);

//         $pathIndex[$normPath] = $key; // записываем в индекс

//         // изначально флаг будет фоунд будет фолс и если сканер найдет файлы то он поставит тру 
//         $current_db[$key]['found'] = false;


//         // сбрасываем статус на обычный чтобы пересчитать его заново - но щас мы ее закоментили чтобы можно было норм считать удаленные после скана
//         // $current_db[$key]['status'] = 'exists';

        
//         // добвялем айди +1 к новым файлам
//         if (isset($file['id'])) {
//             if ($file['id'] > $maxId) {
//                 $maxId = $file['id'];
//             }
//         }
//     }

//     $nextId = $maxId + 1; // следующий айди для новых файлов

//     // для статистики
//     $stats = [
//         'new' => 0, 
//         'moved' => 0, 
//         'deleted' => 0, 
//         'updated' => 0
//     ];




//     // СКАНИРОВАНИЕ
    
//     $pending_new = []; // временный массив для новых файлов чтобы потом искать перемещения

//     // проходим по каждому корню который указан в админке
//     foreach ($saved_paths as $root) {

//         // проверяем есть ли директория и доступна ли она
//         if (file_exists($root)) {
//             if (is_dir($root)) {

//                 try {
//                     // Создаем итератор. SKIP_DOTS убирает точки . и ..
//                     $dir_iterator = new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS);


//                     // RecursiveDirectoryIteratoрр позволяет нам чекать все папки
//                     // CATCH_GET_CHILD нужен, чтобы скрипт не падал, если в какую-то папку нельзя зайти

//                     $iterator = new RecursiveIteratorIterator(
//                         $dir_iterator, 
//                         RecursiveIteratorIterator::CATCH_GET_CHILD
//                     );

//                     foreach ($iterator as $info) {

//                         // если файл нельзя прочитать - из за прав доступа например - то просто пропускаем его 
//                         if ($info -> isReadable()) {

//                             // нормалиуем путь
//                             $full_path = $info -> getPathname();
//                             $path = str_replace('\\', '/', $full_path);

//                             //проверяем индексирован ли уже этот путь
//                             if (isset($pathIndex[$path])) {

//                                 $idx = $pathIndex[$path]; // если файл уже есть в базе

//                                 //помечаем что файл найден живым
//                                 $current_db[$idx]['found'] = true;

//                                 $current_db[$idx]['status'] = 'exists';
//                                 $current_db[$idx]['status_text'] = 'Существует';

//                                 // проверка изменения размера - актуальнось данных
//                                 $real_bytes = $info -> getSize();

//                                 if ($current_db[$idx]['bytes'] !== $real_bytes) {
//                                     // Файл был изменен или перезаписан под тем же именем
//                                     $current_db[$idx]['bytes'] = $real_bytes;

//                                     // Пересчитываем человекочитаемый размер (GB для больших файлов)
//                                     if ($real_bytes >= 1073741824) { // 1 GB в байтах
//                                         $current_db[$idx]['size'] = round($real_bytes / 1073741824, 2) . ' GB';
//                                     } else {
//                                         $current_db[$idx]['size'] = round($real_bytes / 1048576, 2) . ' MB';
//                                     }
                                    
//                                     $current_db[$idx]['date'] = date("Y-m-d H:i"); // Обновляем дату
//                                     $stats['updated'] = $stats['updated'] + 1;
//                                 }

                                


//                             } else {
//                                 // если файла нет в базе (то это новый или перемещенный)
//                                 $real_bytes = $info -> getSize();
                                
//                                 // Собираем данные во временный массив
//                                 $new_item = [
//                                     'id' => $nextId,
//                                     'name' => $info->getFilename(),
//                                     'path' => $path,
//                                     'bytes' => $real_bytes,
//                                     'status' => 'pending_new', // Статус-заглушка для Части 3
//                                     'status_text' => 'Обработка...',
//                                     'date' => date("Y-m-d H:i"),
//                                     'found' => true
//                                 ];

//                                 // Считаем размер для новой записи
//                                 if ($real_bytes >= 1073741824) {
//                                     $new_item['size'] = round($real_bytes / 1073741824, 2) . ' GB';
//                                 } else {
//                                     $new_item['size'] = round($real_bytes / 1048576, 2) . ' MB';
//                                 }

//                                 $pending_new[] = $new_item;

//                                 // для след потенциального нового файла
//                                 $nextId = $nextId + 1;

//                             }
//                         }
//                     }
//                 } catch (Exception $e) {
//                     // Ошибка может возникнуть, если корень сканирования недоступен
//                     error_log("Критическая ошибка сканирования: " . $e->getMessage());
//                 }

//             }
//         }
//     }

//     // АНАЛИЗ ПЕРЕМЕЩЕНИЙ и ОБНовление статусов

//     // создаем массив с потерянными файлами
//     $lost_files_map = [];

//     foreach ($current_db as $index => $file) {

//         // нас интересует только те кто был в базе но не найден физичнски по старому пути
//         if ($current_db[$index]['found'] === false) {

//             // формируем уникальный ключ для поиска пары (имя + размер)
//             $key = $file['name'] . '|' . $file['bytes'];

//             // Добавляем индекс в карту. Мы используем массив [], так как может быть 
//             // несколько разных файлов с одинаковым именем и размером.
//             if (!isset($lost_files_map[$key])) {
//                 $lost_files_map[$key] = [];
//             }
//             $lost_files_map[$key][] = $index;
//         }
//     }

//     // сверим потенциально новые с потерянными
//     $final_new_entries = []; // Сюда пойдут только те, кто реально новый

//     foreach ($pending_new as $new_item) {
//         $key = $new_item['name'] . '|' . $new_item['bytes'];
    
//         // есть ли такой ключ среди пропавших файлов
//         if (isset($lost_files_map[$key])) {
//             // если в массиве к этому ключу еще остались индексы
//             if (count($lost_files_map[$key]) > 0) {

//                 // --- СЦЕНАРИЙ: ПЕРЕМЕЩЕНИЕ ---
                
//                 // Извлекаем первый подходящий индекс старого файла
//                 // array_shift удаляет элемент из карты, чтобы один старый файл 
//                 // не "приклеился" к двум новым.

//                 if(!empty($lost_files_map[$key])) {

//                     // извлекаем индекс один раз
//                     $old_db_index = array_shift($lost_files_map[$key]);

//                     // проверяем что индекс валидный
//                     if ($old_db_index !== null) {
//                         $current_db[$old_db_index]['old_path'] = $current_db[$old_db_index]['path']; // Старый путь для истории            

//                         $current_db[$old_db_index]['path'] = $new_item['path']; // Новый путь
//                         $current_db[$old_db_index]['date'] = $new_item['date']; // Дата обнаружения
//                         $current_db[$old_db_index]['found'] = true; // Теперь он снова "существует"
//                         $current_db[$old_db_index]['status'] = 'moved';
//                         $current_db[$old_db_index]['status_text'] = 'Перемещен';
                        
//                         $stats['moved']++;

//                         // Важно: мы не добавляем этот файл в финальный список новых, 
//                         // так как мы просто "оживили" старую запись.
//                         continue;
//                     }
//                 }
            
        
//             }
//         }

//         // файл реально новый
//         $new_item['status'] = 'new';
//         $new_item['status_text'] = 'Новый';
//         $final_new_entries[] = $new_item;
//         $stats['new'] = $stats['new'] + 1;

//     }

//     // добавляем реально новые файлы в основной массив
//     foreach ($final_new_entries as $row) {
//         $current_db[] = $row;
//     }
    

//     // финализируем статусов

//     // Все, кто в $current_db так и остался с found = false — это трупы.
//     foreach ($current_db as $index => $file) {

//         if ($current_db[$index]['found'] === false) {

//             // запоминаем какой статус был у файла до этого сканирования
//             $previous_status = $file['status'] ?? 'exists';

//             // проверяем а должны ли мы вообще видеть этот файл
//             $is_root_active = false;
//             foreach ($saved_paths as $root) {
//                 // если путь файла начинается с одного из активных корней сканирования
//                 if (strpos($file['path'], $root) === 0) {
//                     $is_root_active = true;
//                     break;
//                 }
//             }



//             if ($is_root_active) {
//                 $current_db[$index]['status'] = 'deleted';
//                 $current_db[$index]['status_text'] = 'Удален';
//                 // $stats['deleted'] = $stats['deleted'] + 1; это нам посчитает сколько всего файлов со статусом удален

//                 if ($previous_status !== 'deleted') {
//                     // учитываем в статистике только если файл не был уже помечен как удаленный
//                     $stats['deleted']++;
//                 }
//             } else {

//                 // корневая папка этого файла отключена из админки (удалена)
//                 $current_db[$index]['status'] = 'source_off';
//                 $current_db[$index]['status_text'] = 'Источник отключен';
//             }
//         }
//     }



//     // СОХРАНЕНИЕ РЕЗУЛЬТАТОВ


//     // так как щас работаем с джсон то превращаем наш массив в джсон строку

//     // JSON_UNESCAPED_UNICODE — чтобы кириллица в путях была читаемой
//     // JSON_PRETTY_PRINT — чтобы файл можно было открыть и прочитать глазами
//     $jsonData = json_encode($current_db, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

//     // проверяем не пустой ли джсон (типо защита от сбоя памяти)
//     if ($jsonData !== false) {

//         $tempFile = $dataFile . '.tmp'; // создаем имя для временно файла

//         $result = file_put_contents($tempFile, $jsonData); // пишем данные сначала во временный файл

//         if ($result !== false) {

//             // Атомарная операция: заменяем старый файл новым.
//             // Если запись в .tmp прошла успешно, функция rename просто 
//             // мгновенно подменит файлы на уровне файловой системы

//             if (rename($tempFile, $dataFile)) {
//                 $saveStatus = "Данные успешно сохранены.";
//             } else {
//                 $saveStatus = "Ошибка: не удалось заменить основной файл данных.";
//             }
//         } else {
//             $saveStatus = "Ошибка: не удалось записать временный файл (возможно, нет места на диске).";
//         }
//     } else {
//         $saveStatus = "Ошибка: не удалось закодировать данные в JSON.";
//     }



//     // === ВЫВОД РЕЗУЛЬТАТА ===
//     return [
//         'status' => 'success',
//         'message' => $saveStatus,
//         'stats' => $stats
//     ];

// }









// function runIncrementalScan($pdo) {

//     // увеличиваем время выполнения, если папок много. но это работает только пока нету настоящей бд - потом надо будет тут менять логику
//     set_time_limit(1200);
//     ini_set('memory_limit', '512M');

//     $stats = ['new' => 0, 'moved' => 0, 'deleted' => 0, 'updated' => 0];

// // 1. ПОЛУЧАЕМ ПУТИ ДЛЯ СКАНИРОВАНИЯ
//     $stmt = $pdo->query("SELECT path FROM scan_paths");
//     $roots = $stmt->fetchAll(PDO::FETCH_COLUMN);

//     if (empty($roots)) {
//         return ["status" => "error", "message" => "Добавьте пути в админке"];
//     }

//     // Помечаем все текущие файлы как 'not_found' перед началом скана
//     // Это аналог $file['found'] = false в вашем коде
//     $pdo->exec("UPDATE files SET temp_found = false");



// // 2. СКАНИРОВАНИЕ
//     foreach ($roots as $root) {
//         if (!is_dir($root)) continue;

//         try {
//             $dir_iterator = new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS);
//             $iterator = new RecursiveIteratorIterator($dir_iterator, RecursiveIteratorIterator::CATCH_GET_CHILD);

//             foreach ($iterator as $info) {
//                 if (!$info->isReadable() || $info->isDir()) continue;

//                 $path = str_replace('\\', '/', $info->getPathname());
//                 $name = $info->getFilename();
//                 $size = $info->getSize();

//                 // ПРОВЕРКА: Есть ли файл по этому пути?
//                 $checkStmt = $pdo->prepare("SELECT id, file_size FROM files WHERE file_path = ?");
//                 $checkStmt->execute([$path]);
//                 $existing = $checkStmt->fetch();

//                 if ($existing) {
//                     // --- ФАЙЛ УЖЕ ЕСТЬ ---
//                     $updateFields = ["temp_found = true", "file_status = 'active'"];
                    
//                     if ($existing['file_size'] != $size) {
//                         $updateFields[] = "file_size = " . (int)$size;
//                         $updateFields[] = "file_status = 'updated'";
//                         $updateFields[] = "created_at = NOW()"; // Обновим дату, если размер изменился
//                         $stats['updated']++;
//                     }

//                     $sql = "UPDATE files SET " . implode(", ", $updateFields) . " WHERE id = ?";
//                     $pdo->prepare($sql)->execute([$existing['id']]);

//                 } else {
//                     // 1. Пытаемся найти этот файл в базе среди тех, кого мы ЕЩЕ НЕ НАШЛИ
//                     // (т.е. он либо был удален раньше, либо перемещен сейчас)
//                     $moveStmt = $pdo->prepare("
//                         SELECT id, file_path FROM files 
//                         WHERE file_name = ? 
//                         AND file_size = ? 
//                         AND temp_found = false 
//                         LIMIT 1
//                     ");
//                     $moveStmt->execute([$name, (int)$size]);
//                     $lostFile = $moveStmt->fetch();

//                     if ($lostFile) {
//                     // 1. Сохраняем старый путь в переменную ДО обновления
//                     // Если в массиве нет 'file_path', попробуйте 'path' (зависит от вашего SELECT)
//                     $oldPath = isset($lostFile['file_path']) ? $lostFile['file_path'] : 'Unknown';

//                     // 2. Обновляем файл
//                     $updMove = $pdo->prepare("
//                         UPDATE files SET 
//                             file_path = ?, 
//                             file_status = 'moved', 
//                             temp_found = true,
//                             updated_at = NOW() 
//                         WHERE id = ?
//                     ");
//                     $updMove->execute([$path, $lostFile['id']]);

//                     // 3. ЗАПИСЫВАЕМ В ИСТОРИЮ (file_changes)
//                     $logMove = $pdo->prepare("
//                         INSERT INTO file_changes (scan_history_id, file_id, change_type, old_path, new_path)
//                         VALUES (?, ?, 'moved', ?, ?)
//                     ");
                    
//                     // Используем сохраненный $oldPath
//                     $logMove->execute([
//                         $currentScanId ?? null, 
//                         $lostFile['id'], 
//                         $oldPath, 
//                         $path
//                     ]);

//                     $stats['moved']++;
//                 } else {
//                         // Вообще нет совпадений - это реально новая запись
//                         $insStmt = $pdo->prepare("
//                             INSERT INTO files (file_name, file_path, file_size, file_status, temp_found)
//                             VALUES (?, ?, ?, 'new', true)
//                         ");
//                         $insStmt->execute([$name, $path, $size]);
//                         $stats['new']++;
//                     }
//                 }
//             }
//         } catch (Exception $e) {
//             error_log("Ошибка пути $root: " . $e->getMessage());
//         }
//     }

//     // 3. ФИНАЛИЗАЦИЯ (Удаленные файлы)
//     // Все, кто не был найден в этом скане и чей корень активен — помечаем как 'deleted'
// // 1. Считаем, сколько файлов мы пометим как удаленные (для статистики)
//     $stmtDel = $pdo->prepare("
//         SELECT COUNT(*) FROM files 
//         WHERE temp_found = false AND file_status != 'deleted'
//     ");
//     $stmtDel->execute();
//     $stats['deleted'] = $stmtDel->fetchColumn();

//     // 2. Массово обновляем статус для всех пропавших файлов
//     $pdo->exec("
//         UPDATE files 
//         SET file_status = 'deleted' 
//         WHERE temp_found = false
//     ");

//     // 3. (Опционально) Помечаем те файлы, чьи пути вообще были удалены из админки
//     // Если scan_path_id больше не существует в таблице scan_paths
//     $pdo->exec("
//         UPDATE files 
//         SET file_status = 'source_off' 
//         WHERE scan_path_id NOT IN (SELECT id FROM scan_paths)
//     ");

//     // Сохраняем время скана в сессию (как у вас было)
//     $_SESSION['last_scan_time'] = date("H:i:s");

//     return [
//         'status' => 'success',
//         'message' => 'Сканирование завершено',
//         'stats' => $stats
//     ];
// }







function runIncrementalScan($pdo) {
    // Увеличиваем время выполнения, если папок много
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
        // 1. ПОЛУЧАЕМ ПУТИ ДЛЯ СКАНИРОВАНИЯ
        // ============================================
        $stmt = $pdo->query("SELECT id, path FROM scan_paths");
        $roots = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($roots)) {
            // Обновляем статус скана как ошибка
            $pdo->prepare("UPDATE scan_history SET status = 'error', error_message = ?, scan_finished_at = CURRENT_TIMESTAMP WHERE id = ?")
                ->execute(['Добавьте пути в админке', $currentScanId]);
            
            return ["status" => "error", "message" => "Добавьте пути в админке"];
        }

        // ============================================
        // СБРОС ФЛАГОВ ПЕРЕД СКАНИРОВАНИЕМ
        // ============================================
        // Получаем ID всех активных путей
        $activePathIds = array_column($roots, 'id');
        $placeholders = implode(',', array_fill(0, count($activePathIds), '?'));
        
        // Помечаем файлы из активных путей И файлы с отключенными источниками
        $resetStmt = $pdo->prepare("
            UPDATE files 
            SET temp_found = false 
            WHERE scan_path_id IN ($placeholders) 
            OR scan_path_id IS NULL 
            OR file_status = 'source_off'
        ");
        $resetStmt->execute($activePathIds);

        // ============================================
        // 2. СКАНИРОВАНИЕ (Фаза 1: Сбор данных)
        // ============================================
        
        // Временный массив для потенциально новых файлов
        $pending_new = [];
        
        foreach ($roots as $rootData) {
            $root = $rootData['path'];
            $scanPathId = $rootData['id'];



            $root = rtrim($root, '/\\');


            if (!is_dir($root) && !file_exists($root)) {
                error_log("Путь не существует или недоступен: $root");
                continue;
            }

            // if (!is_dir($root)) {
            //     error_log("Путь не существует или недоступен: $root");
            //     continue;
            // }

            try {
                $dir_iterator = new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS);
                $iterator = new RecursiveIteratorIterator($dir_iterator, RecursiveIteratorIterator::LEAVES_ONLY, RecursiveIteratorIterator::CATCH_GET_CHILD);

                foreach ($iterator as $info) {
                    // if (!$info->isReadable() || $info->isDir()) continue;
                    if (!$info->isReadable()) continue;

                    $path = str_replace('\\', '/', $info->getPathname());
                    $name = $info->getFilename();
                    $size = $info->getSize();
                    
                    // Получаем расширение файла
                    $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

                    // ПРОВЕРКА: Есть ли файл по этому пути?
                    $checkStmt = $pdo->prepare("SELECT id, file_size, scan_path_id, file_status FROM files WHERE file_path = ?");
                    $checkStmt->execute([$path]);
                    $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

                    if ($existing) {
                        // --- ФАЙЛ УЖЕ ЕСТЬ ПО ЭТОМУ ПУТИ ---
                        $updateFields = ["temp_found = true"];
                        $params = [];
                        
                        // Проверяем изменился ли размер
                        if ($existing['file_size'] != $size) {
                            $updateFields[] = "file_size = ?";
                            $updateFields[] = "file_status = 'updated'";
                            $updateFields[] = "updated_at = CURRENT_TIMESTAMP";
                            $params[] = $size;
                            
                            // Записываем в историю изменений
                            $logUpdate = $pdo->prepare("
                                INSERT INTO file_changes (scan_history_id, file_id, change_type, old_path, new_path, details)
                                VALUES (?, ?, 'updated', ?, ?, ?::jsonb)
                            ");
                            $logUpdate->execute([
                                $currentScanId,
                                $existing['id'],
                                $path,
                                $path,
                                json_encode(['old_size' => $existing['file_size'], 'new_size' => $size])
                            ]);
                            
                            $stats['updated']++;
                        } else {
                            // Размер не изменился
                            // Возвращаем в 'active' все статусы, КРОМЕ 'active'
                            if ($existing['file_status'] !== 'active') {
                                $updateFields[] = "file_status = 'active'";
                                $updateFields[] = "updated_at = CURRENT_TIMESTAMP";
                            }
                        }

                        $params[] = $existing['id'];
                        $sql = "UPDATE files SET " . implode(", ", $updateFields) . " WHERE id = ?";
                        $pdo->prepare($sql)->execute($params);

                    } else {
                        // --- ФАЙЛА НЕТ ПО ЭТОМУ ПУТИ ---
                        // Складываем во временный массив для последующего анализа
                        $pending_new[] = [
                            'scan_path_id' => $scanPathId,
                            'name' => $name,
                            'path' => $path,
                            'extension' => $extension,
                            'size' => $size
                        ];
                    }
                }
            } catch (Exception $e) {
                error_log("Ошибка при сканировании пути $root: " . $e->getMessage());
                continue;
            }
        }

        // ============================================
        // 2.5 АНАЛИЗ ПЕРЕМЕЩЕНИЙ (Фаза 2: После сканирования)
        // ============================================
        
        foreach ($pending_new as $item) {
            // Ищем потерянный файл с таким же именем и размером
            $moveStmt = $pdo->prepare("
                SELECT id, file_path, scan_path_id, file_status FROM files 
                WHERE file_name = ? 
                AND file_size = ? 
                AND (temp_found = false OR scan_path_id IS NULL OR file_status = 'source_off')
                LIMIT 1
            ");
            $moveStmt->execute([$item['name'], $item['size']]);
            $lostFile = $moveStmt->fetch(PDO::FETCH_ASSOC);

            if ($lostFile) {
                // --- ФАЙЛ ПЕРЕМЕСТИЛСЯ ИЛИ ИСТОЧНИК БЫЛ ВОССТАНОВЛЕН ---
                $oldPath = $lostFile['file_path'];
                $oldStatus = $lostFile['file_status'];

                // Определяем новый статус
                if ($oldStatus === 'source_off' || $lostFile['scan_path_id'] === null) {
                    // Источник был отключен, теперь включён обратно
                    $newStatus = 'active';
                    $changeType = 'restored';
                } else {
                    // Обычное перемещение
                    $newStatus = 'moved';
                    $changeType = 'moved';
                }

                // Обновляем файл
                $updMove = $pdo->prepare("
                    UPDATE files SET 
                        file_path = ?,
                        scan_path_id = ?,
                        file_status = ?,
                        temp_found = true,
                        updated_at = CURRENT_TIMESTAMP 
                    WHERE id = ?
                ");
                $updMove->execute([$item['path'], $item['scan_path_id'], $newStatus, $lostFile['id']]);

                // Записываем в историю
                $logMove = $pdo->prepare("
                    INSERT INTO file_changes (scan_history_id, file_id, change_type, old_path, new_path)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $logMove->execute([
                    $currentScanId,
                    $lostFile['id'],
                    $changeType,
                    $oldPath,
                    $item['path']
                ]);

                if ($changeType === 'moved') {
                    $stats['moved']++;
                }
                
            } else {
                // --- НОВЫЙ ФАЙЛ ---
                $insStmt = $pdo->prepare("
                    INSERT INTO files (scan_path_id, file_name, file_path, file_extension, file_size, file_status, temp_found)
                    VALUES (?, ?, ?, ?, ?, 'new', true)
                    RETURNING id
                ");
                $insStmt->execute([
                    $item['scan_path_id'], 
                    $item['name'], 
                    $item['path'], 
                    $item['extension'], 
                    $item['size']
                ]);
                $newFileId = $insStmt->fetchColumn();
                
                // Записываем в историю
                // $logNew = $pdo->prepare("
                //     INSERT INTO file_changes (scan_history_id, file_id, change_type, new_path)
                //     VALUES (?, ?, 'added', ?)
                // ");
                // $logNew->execute([$currentScanId, $newFileId, $item['path']]);
                
                $stats['new']++;
            }
        }

        // ============================================
        // 3. ФИНАЛИЗАЦИЯ (Удаленные файлы)
        // ============================================
        
        // Получаем список удалённых файлов из АКТИВНЫХ путей
        $stmtDeleted = $pdo->prepare("
            SELECT id, file_path FROM files 
            WHERE temp_found = false 

            AND file_status NOT IN ('deleted', 'source_off')
            AND scan_path_id IN ($placeholders)
        ");
        $stmtDeleted->execute($activePathIds);
        $deletedFiles = $stmtDeleted->fetchAll(PDO::FETCH_ASSOC);
        
        $stats['deleted'] = count($deletedFiles);

        // Логируем каждый удалённый файл
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

        // Массово обновляем статус для всех пропавших файлов из активных путей
        $updateDeletedStmt = $pdo->prepare("
            UPDATE files 
            SET file_status = 'deleted', updated_at = CURRENT_TIMESTAMP
            WHERE temp_found = false 
            AND file_status != 'deleted'
            AND scan_path_id IN ($placeholders)
        ");
        $updateDeletedStmt->execute($activePathIds);

        // Помечаем файлы, чьи пути были удалены из админки
        // НО только если они ещё не были помечены как source_off
        $pdo->exec("
            UPDATE files 
            SET file_status = 'source_off', updated_at = CURRENT_TIMESTAMP
            WHERE file_status != 'source_off'
            AND (scan_path_id IS NULL OR scan_path_id NOT IN (SELECT id FROM scan_paths))
        ");

        // ============================================
        // 4. ОБНОВЛЯЕМ ЗАПИСЬ О СКАНИРОВАНИИ
        // ============================================
        $updateScanStmt = $pdo->prepare("
            UPDATE scan_history SET 
                scan_finished_at = CURRENT_TIMESTAMP,
                files_found = (SELECT COUNT(*) FROM files WHERE temp_found = true),
                files_added = ?,
                files_updated = ?,
                files_deleted = ?,
                files_moved = ?,
                status = 'success'
            WHERE id = ?

        ");


        // $roots = $rootData['path']; прикол в том что у нас может быть несколько айди - хз как это вписать в бд
        // $scanPathId = $rootData['id'];
        // scan_path_id = ?,

        $updateScanStmt->execute([
            // $scanPathId,
            $stats['new'],
            $stats['updated'],
            $stats['deleted'],
            $stats['moved'],
            $currentScanId
        ]);


        // тут ласт скан в скан патче таблице обновим время
        $stmt2 = $pdo->prepare("UPDATE scan_paths SET last_scanned_at = CURRENT_TIMESTAMP");
        $stmt2->execute();


        // Сохраняем время скана в сессию
        $_SESSION['last_scan_time'] = date("H:i:s");

        return [
            'status' => 'success',
            'message' => 'Сканирование завершено',
            'stats' => $stats,
            'scan_id' => $currentScanId
        ];

    } catch (Exception $e) {
        // В случае ошибки обновляем статус скана
        $pdo->prepare("
            UPDATE scan_history SET 
                status = 'error', 
                error_message = ?,
                scan_finished_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ")->execute([$e->getMessage(), $currentScanId]);
        
        return [
            'status' => 'error',
            'message' => 'Ошибка при сканировании: ' . $e->getMessage()
        ];
    }
}

?>