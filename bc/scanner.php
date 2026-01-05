<?php
function runIncrementalScan($pathsFile, $dataFile) {
    $saved_paths = file_exists($pathsFile) ? json_decode(file_get_contents($pathsFile), true) : [];
    $current_data = file_exists($dataFile) ? json_decode(file_get_contents($dataFile), true) : [];
    
    // Индексируем старые данные для быстрого поиска по пути
    $db_by_path = [];
    foreach ($current_data as $index => $file) {
        $db_by_path[$file['path']] = $index;
        $current_data[$index]['found'] = false; // Метка "на месте"
    }

    $id_counter = count($current_data) > 0 ? max(array_column($current_data, 'id')) + 1 : 1;

    // 1. Собираем всё, что реально есть на дисках сейчас
    foreach ($saved_paths as $root) {
        if (!is_dir($root)) continue;
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS));

        foreach ($iterator as $info) {
            $path = str_replace('\\', '/', $info->getPathname());
            
            if (isset($db_by_path[$path])) {
                // Файл на месте
                $idx = $db_by_path[$path];
                $current_data[$idx]['found'] = true;
                $current_data[$idx]['status'] = 'exists';
                $current_data[$idx]['status_text'] = 'Существует';
            } else {
                // Это либо абсолютно новый, либо перемещенный
                $current_data[] = [
                    'id' => $id_counter++,
                    'name' => $info->getFilename(),
                    'path' => $path,
                    'size' => round($info->getSize() / 1024 / 1024, 2) . ' MB',
                    'bytes' => $info->getSize(), // Сохраним байты для сравнения при перемещении
                    'status' => 'new',
                    'status_text' => 'Новый',
                    'date' => date("Y-m-d H:i"),
                    'found' => true
                ];
            }
        }
    }

    // 2. Логика MOVE и DELETE
    foreach ($current_data as $i => $file) {
        if (!$file['found']) {
            // Файла нет по адресу. Попробуем найти среди новых такой же (имя + размер)
            $moved = false;
            foreach ($current_data as $j => $new_file) {
                if ($new_file['status'] === 'new' && $new_file['name'] === $file['name'] && $new_file['bytes'] === ($file['bytes'] ?? 0)) {
                    // Нашли! Обновляем старую запись новыми данными пути
                    $current_data[$i]['path'] = $new_file['path'];
                    $current_data[$i]['status'] = 'moved';
                    $current_data[$i]['status_text'] = 'Перемещен';
                    $current_data[$i]['found'] = true;
                    
                    // Удаляем временную "новую" запись
                    unset($current_data[$j]);
                    $moved = true;
                    break;
                }
            }
            if (!$moved) {
                $current_data[$i]['status'] = 'deleted';
                $current_data[$i]['status_text'] = 'Удален';
            }
        }
        unset($current_data[$i]['found']); // Чистим метку
    }

    file_put_contents($dataFile, json_encode(array_values($current_data), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    return "Сканирование завершено!";
}