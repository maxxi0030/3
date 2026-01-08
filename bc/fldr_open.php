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


?>