<?php
// Проверяем, что пришел именно POST запрос
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['path'])) {
    $path = $_POST['path'];
    
    // Преобразуем слэши для Windows
    $winPath = str_replace('/', '\\', $path);

    if (file_exists($winPath)) {
        // Выполняем команду открытия с выделением файла
        shell_exec('explorer.exe /select,"' . $winPath . '"');
        echo json_encode(['status' => 'success']);
    } else {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Файл не найден']);
    }
    exit;
}

// // В начало файла dashboard2.php добавить:
// if (isset($_GET['action']) && $_GET['action'] === 'open_folder') {
//     $path = $_GET['path'] ?? '';
    
//     if (file_exists($path)) {
//         $dir = dirname($path); // Получаем директорию
        
//         if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
//             // Windows
//             exec('explorer /select,"' . str_replace('/', '\\', $path) . '"');
//         } else {
//             // Linux/Mac
//             exec('xdg-open "' . $dir . '"'); // Linux
//             // exec('open "' . $dir . '"'); // macOS
//         }
        
//         echo json_encode(['success' => true]);
//     } else {
//         echo json_encode(['success' => false, 'message' => 'Файл не найден']);
//     }
//     exit;
// }


?>