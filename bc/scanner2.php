<?php
function runIncrementalScan($pathsFile, $dataFile) {

    // увеличиваем время выполнения, если папок много. но это работает только пока нету настоящей бд - потом надо будет тут менять логику
    set_time_limit(600);
    ini_set('memory_limit', '512M');


    // ЗАГРУЗКА СПИСКА ПУТЕЙ
    if (file_exists($pathsFile)) {
        $content = file_get_contents($pathsFile);
        $decoded_paths = json_decode($content, true);

        // проверяем что джсон валидный
        if (is_array($decoded_paths)) {
            $saved_paths = $decoded_paths;
        }
    } else {
        return "путь не найден";
    }

    // ЗАГРУЗКА ТЕКУЩЕЙ БАЗЫ ФАЙЛОВ

    $current_db = []; // по умолчанию база пустая

    if (file_exists($dataFile)) {
        $content = file_get_contents($dataFile);
        $decoded_data = json_decode($content, true);

        // Если файл битый или пустой, json_decode вернет null
        if (is_array($decoded_data)) {
            $current_db = $decoded_data;
        }
    }


    // ИНДЕКСАЦИЯ - типо подготовка для быстрого поиска

    $pathIndex = []; // это для определения индекса в массиве
    $maxId = 0; // это для поиска последнего айди


    foreach ($current_db as $key => $file) { 

    // Нормализуем путь сразу. Меняем обратные слеши на прямые.
        // Это важно, чтобы C:\Folder\File.txt и C:/Folder/File.txt считались одним и тем же.

        $normPath = str_replace('\\', "/", $file['path']);

        $pathIndex[$normPath] = $key; // записываем в индекс

        // изначально флаг будет фоунд будет фолс и если сканер найдет файлы то он поставит тру 
        $current_db[$key]['found'] = false;


        // сбрасываем статус на обычный чтобы пересчитать его заново - но щас мы ее закоментили чтобы можно было норм считать удаленные после скана
        // $current_db[$key]['status'] = 'exists';

        
        // добвялем айди +1 к новым файлам
        if (isset($file['id'])) {
            if ($file['id'] > $maxId) {
                $maxId = $file['id'];
            }
        }
    }

    $nextId = $maxId + 1; // следующий айди для новых файлов

    // для статистики
    $stats = [
        'new' => 0, 
        'moved' => 0, 
        'deleted' => 0, 
        'updated' => 0
    ];




    // СКАНИРОВАНИЕ
    
    $pending_new = []; // временный массив для новых файлов чтобы потом искать перемещения

    // проходим по каждому корню который указан в админке
    foreach ($saved_paths as $root) {

        // проверяем есть ли директория и доступна ли она
        if (file_exists($root)) {
            if (is_dir($root)) {

                try {
                    // Создаем итератор. SKIP_DOTS убирает точки . и ..
                    $dir_iterator = new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS);


                    // RecursiveDirectoryIteratoрр позволяет нам чекать все папки
                    // CATCH_GET_CHILD нужен, чтобы скрипт не падал, если в какую-то папку нельзя зайти

                    $iterator = new RecursiveIteratorIterator(
                        $dir_iterator, 
                        RecursiveIteratorIterator::CATCH_GET_CHILD
                    );

                    foreach ($iterator as $info) {

                        // если файл нельзя прочитать - из за прав доступа например - то просто пропускаем его 
                        if ($info -> isReadable()) {

                            // нормалиуем путь
                            $full_path = $info -> getPathname();
                            $path = str_replace('\\', '/', $full_path);

                            //проверяем индексирован ли уже этот путь
                            if (isset($pathIndex[$path])) {

                                $idx = $pathIndex[$path]; // если файл уже есть в базе

                                //помечаем что файл найден живым
                                $current_db[$idx]['found'] = true;

                                $current_db[$idx]['status'] = 'exists';
                                $current_db[$idx]['status_text'] = 'Существует';

                                // проверка изменения размера - актуальнось данных
                                $real_bytes = $info -> getSize();

                                if ($current_db[$idx]['bytes'] !== $real_bytes) {
                                    // Файл был изменен или перезаписан под тем же именем
                                    $current_db[$idx]['bytes'] = $real_bytes;

                                    // Пересчитываем человекочитаемый размер (GB для больших файлов)
                                    if ($real_bytes >= 1073741824) { // 1 GB в байтах
                                        $current_db[$idx]['size'] = round($real_bytes / 1073741824, 2) . ' GB';
                                    } else {
                                        $current_db[$idx]['size'] = round($real_bytes / 1048576, 2) . ' MB';
                                    }
                                    
                                    $current_db[$idx]['date'] = date("Y-m-d H:i"); // Обновляем дату
                                    $stats['updated'] = $stats['updated'] + 1;
                                }

                                


                            } else {
                                // если файла нет в базе (то это новый или перемещенный)
                                $real_bytes = $info -> getSize();
                                
                                // Собираем данные во временный массив
                                $new_item = [
                                    'id' => $nextId,
                                    'name' => $info->getFilename(),
                                    'path' => $path,
                                    'bytes' => $real_bytes,
                                    'status' => 'pending_new', // Статус-заглушка для Части 3
                                    'status_text' => 'Обработка...',
                                    'date' => date("Y-m-d H:i"),
                                    'found' => true
                                ];

                                // Считаем размер для новой записи
                                if ($real_bytes >= 1073741824) {
                                    $new_item['size'] = round($real_bytes / 1073741824, 2) . ' GB';
                                } else {
                                    $new_item['size'] = round($real_bytes / 1048576, 2) . ' MB';
                                }

                                $pending_new[] = $new_item;

                                // для след потенциального нового файла
                                $nextId = $nextId + 1;

                            }
                        }
                    }
                } catch (Exception $e) {
                    // Ошибка может возникнуть, если корень сканирования недоступен
                    error_log("Критическая ошибка сканирования: " . $e->getMessage());
                }

            }
        }
    }

    // АНАЛИЗ ПЕРЕМЕЩЕНИЙ и ОБНовление статусов

    // создаем массив с потерянными файлами
    $lost_files_map = [];

    foreach ($current_db as $index => $file) {

        // нас интересует только те кто был в базе но не найден физичнски по старому пути
        if ($current_db[$index]['found'] === false) {

            // формируем уникальный ключ для поиска пары (имя + размер)
            $key = $file['name'] . '|' . $file['bytes'];

            // Добавляем индекс в карту. Мы используем массив [], так как может быть 
            // несколько разных файлов с одинаковым именем и размером.
            if (!isset($lost_files_map[$key])) {
                $lost_files_map[$key] = [];
            }
            $lost_files_map[$key][] = $index;
        }
    }

    // сверим потенциально новые с потерянными
    $final_new_entries = []; // Сюда пойдут только те, кто реально новый

    foreach ($pending_new as $new_item) {
        $key = $new_item['name'] . '|' . $new_item['bytes'];
    
        // есть ли такой ключ среди пропавших файлов
        if (isset($lost_files_map[$key])) {
            // если в массиве к этому ключу еще остались индексы
            if (count($lost_files_map[$key]) > 0) {

                // --- СЦЕНАРИЙ: ПЕРЕМЕЩЕНИЕ ---
                
                // Извлекаем первый подходящий индекс старого файла
                // array_shift удаляет элемент из карты, чтобы один старый файл 
                // не "приклеился" к двум новым.

                if(!empty($lost_files_map[$key])) {

                    // извлекаем индекс один раз
                    $old_db_index = array_shift($lost_files_map[$key]);

                    // проверяем что индекс валидный
                    if ($old_db_index !== null) {
                        $current_db[$old_db_index]['old_path'] = $current_db[$old_db_index]['path']; // Старый путь для истории            

                        $current_db[$old_db_index]['path'] = $new_item['path']; // Новый путь
                        $current_db[$old_db_index]['date'] = $new_item['date']; // Дата обнаружения
                        $current_db[$old_db_index]['found'] = true; // Теперь он снова "существует"
                        $current_db[$old_db_index]['status'] = 'moved';
                        $current_db[$old_db_index]['status_text'] = 'Перемещен';
                        
                        $stats['moved']++;

                        // Важно: мы не добавляем этот файл в финальный список новых, 
                        // так как мы просто "оживили" старую запись.
                        continue;
                    }
                }
            
        
            }
        }

        // файл реально новый
        $new_item['status'] = 'new';
        $new_item['status_text'] = 'Новый';
        $final_new_entries[] = $new_item;
        $stats['new'] = $stats['new'] + 1;

    }

    // добавляем реально новые файлы в основной массив
    foreach ($final_new_entries as $row) {
        $current_db[] = $row;
    }
    

    // финализируем статусов

    // Все, кто в $current_db так и остался с found = false — это трупы.
    foreach ($current_db as $index => $file) {

        if ($current_db[$index]['found'] === false) {

            // запоминаем какой статус был у файла до этого сканирования
            $previous_status = $file['status'] ?? 'exists';

            // проверяем а должны ли мы вообще видеть этот файл
            $is_root_active = false;
            foreach ($saved_paths as $root) {
                // если путь файла начинается с одного из активных корней сканирования
                if (strpos($file['path'], $root) === 0) {
                    $is_root_active = true;
                    break;
                }
            }



            if ($is_root_active) {
                $current_db[$index]['status'] = 'deleted';
                $current_db[$index]['status_text'] = 'Удален';
                // $stats['deleted'] = $stats['deleted'] + 1; это нам посчитает сколько всего файлов со статусом удален

                if ($previous_status !== 'deleted') {
                    // учитываем в статистике только если файл не был уже помечен как удаленный
                    $stats['deleted']++;
                }
            } else {

                // корневая папка этого файла отключена из админки (удалена)
                $current_db[$index]['status'] = 'source_off';
                $current_db[$index]['status_text'] = 'Источник отключен';
            }
        }
    }



    // СОХРАНЕНИЕ РЕЗУЛЬТАТОВ


    // так как щас работаем с джсон то превращаем наш массив в джсон строку

    // JSON_UNESCAPED_UNICODE — чтобы кириллица в путях была читаемой
    // JSON_PRETTY_PRINT — чтобы файл можно было открыть и прочитать глазами
    $jsonData = json_encode($current_db, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    // проверяем не пустой ли джсон (типо защита от сбоя памяти)
    if ($jsonData !== false) {

        $tempFile = $dataFile . '.tmp'; // создаем имя для временно файла

        $result = file_put_contents($tempFile, $jsonData); // пишем данные сначала во временный файл

        if ($result !== false) {

            // Атомарная операция: заменяем старый файл новым.
            // Если запись в .tmp прошла успешно, функция rename просто 
            // мгновенно подменит файлы на уровне файловой системы

            if (rename($tempFile, $dataFile)) {
                $saveStatus = "Данные успешно сохранены.";
            } else {
                $saveStatus = "Ошибка: не удалось заменить основной файл данных.";
            }
        } else {
            $saveStatus = "Ошибка: не удалось записать временный файл (возможно, нет места на диске).";
        }
    } else {
        $saveStatus = "Ошибка: не удалось закодировать данные в JSON.";
    }



    // === ВЫВОД РЕЗУЛЬТАТА ===
    return [
        'status' => 'success',
        'message' => $saveStatus,
        'stats' => $stats
    ];

}

?>