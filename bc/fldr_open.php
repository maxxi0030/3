<?php
// Проверяем, что пришел именно POST запрос
// if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['path'])) {
//     $path = $_POST['path'];
    
//     // Преобразуем слэши для Windows
//     $winPath = str_replace('/', '\\', $path);

//     if (file_exists($winPath)) {
//         // Выполняем команду открытия с выделением файла
//         shell_exec('explorer.exe /select,"' . $winPath . '"');
//         echo json_encode(['status' => 'success']);
//     } else {
//         http_response_code(404);
//         echo json_encode(['status' => 'error', 'message' => 'Файл не найден']);
//     }
//     exit;
// }





if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['path'])) {
    $path = $_POST['path'];
    
    // 1. Проверяем, существует ли файл/папка на самом деле
    if (file_exists($path)) {
        
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Windows: преобразуем слеши и используем /select для подсветки файла
            $winPath = str_replace('/', '\\', $path);
            // Используем pclose + popen, чтобы PHP не "зависал" в ожидании закрытия окна проводника
            pclose(popen("start explorer.exe /select,\"$winPath\"", "r"));
        } else {
            // Linux/Mac: просто открываем папку (выделение файла там работает сложнее)
            $dir = is_dir($path) ? $path : dirname($path);
            exec('xdg-open "' . $dir . '" > /dev/null 2>&1 &'); 
        }

        echo json_encode(['status' => 'success']);
    } else {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Путь не найден: ' . $path]);
    }
    exit;
}


?>