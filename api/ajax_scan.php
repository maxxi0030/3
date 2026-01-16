<?php
// тут сделаем обработку кнопки сканировать

// испольщуем сессию чтобы пометить что скан запущен
// session_start();

// require_once 'bc/scanner2.php';
// $paths_file = 'paths.json';
// $data_file = 'data.json';

// header('Content-Type: application/json');

// if (isset($_GET['check_status'])) {
//     $syncFile = '../last_scan_sync.txt';
//     $globalSync = file_exists($syncFile) ? file_get_contents($syncFile) : '';
    
//     echo json_encode([
//         'scanning' => isset($_SESSION['is_scanning']),
//         'global_sync' => $globalSync // Отдаем время последнего скана в мире
//     ]);
//     exit;
// }

// //запуск скана
// if ($_SERVER['REQUEST_METHOD'] === 'POST') {

//     // ставим блок
//     $_SESSION['is_scanning'] = true;
//     session_write_close();

//     $result = runIncrementalScan($paths_file, $data_file);

//     session_start();
//     unset($_SESSION['is_scanning']);

//     // сохраняем время ласт скана
//     $currentTime = date('H:i:s');
//     $currentDate = date('Y-m-d H:i:s');
//     $_SESSION['last_scan_time'] = $currentTime;
//     file_put_contents('../last_scan_sync.txt', $currentDate);

//     if (is_array($result)) {
//         // $all_data = json_decode(file_get_contents($data_file), true) ?: [];
        
//         // $total_stats = [
//         //     'total' => count($all_data),
//         //     'deleted' => 0,
//         //     'moved' => 0,
//         //     'new' => 0
//         // ];


//         // // КЛАДЕМ В СЕССИЮ
//         // $_SESSION['total_stats'] = $total_stats;

//         // echo json_encode([
//         //     'status' => 'success',
//         //     'message' => $result['message'],
//         //     'stats' => $result['stats'],
//         //     'total_stats' => $total_stats,
//         //     'sync_time' => $currentDate,
//         //     'last_time' => $currentTime
//         // ]);
//         // ... внутри блока if (is_array($result)) ...

//         $s = $result['stats'];
//         // Считаем общее количество изменений
//         $totalChanges = $s['new'] + $s['updated'] + $s['moved'] + $s['deleted'];

//         $currentTime = date('H:i:s');
//         $currentDate = date('Y-m-d H:i:s');

//         $_SESSION['last_scan_time'] = $currentTime;

//         // ЗАПИСЫВАЕМ В ФАЙЛ ТОЛЬКО ЕСЛИ ЕСТЬ ИЗМЕНЕНИЯ
//         if ($totalChanges > 0) {
//             file_put_contents('../last_scan_sync.txt', $currentDate);
//         }

//         // Отправляем ответ
//         echo json_encode([
//             'status' => 'success',
//             'last_time' => $currentTime,
//             'global_sync' => (file_exists('../last_scan_sync.txt') ? file_get_contents('../last_scan_sync.txt') : $currentDate),
//             'total_stats' => $total_stats,
//             'stats' => $result['stats']
//         ]);


//     } else {
//         echo json_encode(['status' => 'error', 'message' => $result]);
//     }

//     exit;

// }




session_start();

// 1. Подключаем базу данных (там создается переменная $pdo)
require_once __DIR__ . '/../db/db_connect.php';
require_once __DIR__ . '/../bc/scanner2.php';
date_default_timezone_set('Europe/Riga');

header('Content-Type: application/json');

// Проверка статуса (оставляем как есть, это удобно для фронтенда)
if (isset($_GET['check_status'])) {
    // Берем дату завершения последнего УСПЕШНОГО сканирования из БД
    $sql = "SELECT scan_finished_at FROM scan_history 
            WHERE status = 'success' 
            ORDER BY scan_finished_at DESC LIMIT 1";
    $lastScanTime = $pdo->query($sql)->fetchColumn();
    
    // Форматируем время: из "2026-01-16 13:27:16.936187" в "01-16  13:27:16"
    if ($lastScanTime) {
        $lastScanTime = date('m/d  H:i:s', strtotime($lastScanTime));
    }
    
    echo json_encode([
        'scanning' => isset($_SESSION['is_scanning']),
        // 'global_sync' => $lastScanTime,
        'last_scan_time' => $lastScanTime
    ]);
    exit;
}

// ЗАПУСК СКАНА
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $_SESSION['is_scanning'] = true;
    session_write_close();

    // 2. ВЫЗЫВАЕМ СКАННЕР, передавая ему $pdo
    $result = runIncrementalScan($pdo);

    session_start();
    unset($_SESSION['is_scanning']);

    if (is_array($result) && $result['status'] === 'success') {
        
        $s = $result['stats'];
        // $totalChanges = $s['new'] + $s['updated'] + $s['moved'] + $s['deleted'];  на будщее можно и это заюзать

        $currentTime = date('m-d  H:i:s');

        $_SESSION['last_scan_time'] = $currentTime;

        // 3. Считаем общее кол-во файлов в базе быстрым запросом
        $totalFiles = $pdo->query("SELECT COUNT(*) FROM files")->fetchColumn();

        $total_stats = [
            'total' => $totalFiles,
            'new' => $s['new'],
            'moved' => $s['moved'],
            'deleted' => $s['deleted']
        ];
        
        echo json_encode([
            'status' => 'success',
            'last_scan_time' => $currentTime,
            // 'global_sync' => $currentTime, 
            'total_stats' => $total_stats,
            'stats' => $s
        ]);

    } else {
        echo json_encode([
            'status' => 'error', 
            'message' => is_array($result) ? $result['message'] : $result
        ]);
    }
    exit;
}

?>