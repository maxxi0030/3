<?php

function runIncrementalScan($pathsFile, $dataFile) {

    // Увеличиваем время выполнения, если папок много
    set_time_limit(500);

    // джсон файлы
    $saved_paths = file_exists($pathsFile) ? json_decode(file_get_contents($pathsFile), true) : [];
    $current_data = file_exists($dataFile) ? json_decode(file_get_contents($dataFile), true) : [];
    
    $stats = ['new' => 0, 'moved' => 0, 'deleted' => 0];

    // индексируем старые данные для быстрого поиска по пути  
    $db_by_path = [];
    foreach ($current_data as $index => $file) {
        $db_by_path[$file['path']] = $index;
        $current_data[$index]['found'] = false; // По умолчанию считаем, что файл пропал
    }

    // создаем счетчик ID для новых файлов так как у нас нет БД настоящей
    $id_counter = 1;
    if (count($current_data) > 0) {
        $ids = array_column($current_data, 'id');
        if (!empty($ids)) {
            $id_counter = max($ids) + 1;
        }
    }

    // 1 собираем всё, что реально есть на дисках сейчас

    // проходим по каждой папке в админке которые
    foreach ($saved_paths as $root) {
        if (!is_dir($root)) continue;

        try {
            // RecursiveIteratorIterator наш встроенный пхп инструмент для рекурсивного обхода папок
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CATCH_GET_CHILD // Игнорируем ошибки доступа
            );

            foreach ($iterator as $info) {
                // Пропускаем файлы, к которым нет доступа
                if (!$info->isReadable()) continue;
                
                $path = str_replace('\\', '/', $info->getPathname());
                
                if (isset($db_by_path[$path])) {
                    // файл на месте
                    $idx = $db_by_path[$path];
                    $current_data[$idx]['found'] = true;
                    $current_data[$idx]['status'] = 'exists';
                    $current_data[$idx]['status_text'] = 'Существует';
                    // Обновляем размер на случай, если файл изменился
                    $current_data[$idx]['bytes'] = $info->getSize();
                    $current_data[$idx]['size'] = round($info->getSize() / 1024 / 1024, 2) . ' MB';
                } else {
                    // Это либо абсолютно новый, либо перемещенный
                    $bytes = $info->getSize();
                    $current_data[] = [
                        'id' => $id_counter++,
                        'name' => $info->getFilename(),
                        'path' => $path,
                        'size' => round($bytes / 1024 / 1024, 2) . ' MB',
                        'bytes' => $bytes, // Сохраним байты для сравнения при перемещении
                        'status' => 'pending_new',
                        'status_text' => 'Обработка',
                        'date' => date("Y-m-d H:i"),
                        'found' => true,
                        'old_status' => null
                    ];
                }
            }
        } catch (Exception $e) {
            // Логируем ошибку, но продолжаем работу
            error_log("Ошибка при сканировании $root: " . $e->getMessage());
            continue;
        }
    }

    
    // Создаём индекс pending_new файлов для быстрого поиска
    $pending_files = [];
    foreach ($current_data as $j => $file) {
        if ($file['status'] === 'pending_new') {
            // Ключ: имя + размер для уникальности
            $key = $file['name'] . '|' . $file['bytes'];
            if (!isset($pending_files[$key])) {
                $pending_files[$key] = [];
            }
            $pending_files[$key][] = $j;
        }
    }
    
    foreach ($current_data as $i => $file) {
        if (!$file['found'] && $file['status'] !== 'pending_new') {
            $moved = false;
            $key = $file['name'] . '|' . $file['bytes'];
            
            // Ищем среди новых файлов тот, который совпадает по размеру и имени
            if (isset($pending_files[$key]) && !empty($pending_files[$key])) {
                $j = array_shift($pending_files[$key]); // Берём первый подходящий
                
                $current_data[$i]['old_path'] = $current_data[$i]['path'];
                $current_data[$i]['path'] = $current_data[$j]['path'];
                $current_data[$i]['status'] = 'moved';
                $current_data[$i]['status_text'] = 'Перемещен';
                $current_data[$i]['found'] = true;
                $current_data[$i]['size'] = $current_data[$j]['size']; // Обновляем size
                
                unset($current_data[$j]); // удаляем временную "новую" запись
                $stats['moved']++;
                $moved = true;
            }

            if (!$moved) {
                $current_data[$i]['status'] = 'deleted';
                $current_data[$i]['status_text'] = 'Удален';
                $stats['deleted']++;
            }
        }
    }
    
    // финализируем тех кто остался в статусе pending_new
    foreach ($current_data as $key => $file) {
        if ($file['status'] === 'pending_new') {
            $current_data[$key]['status'] = 'new';
            $current_data[$key]['status_text'] = 'Новый';
            $stats['new']++;
        }
        unset($current_data[$key]['old_status']);
    }

    // Перед сохранением переиндексируем массив
    $current_data = array_values($current_data);
    

    file_put_contents($dataFile, json_encode($current_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    return "Скан завершен! Новых: {$stats['new']}, Перемещено: {$stats['moved']}, Удалено: {$stats['deleted']}";

}
?>