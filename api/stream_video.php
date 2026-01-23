<?php
// api/stream_video.php
header('Content-Type: application/json');
require_once __DIR__ . '/../db/db_connect.php';

if (!isset($_GET['file_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No file_id provided']);
    exit;
}

$fileId = (int)$_GET['file_id'];

// Получаем путь к файлу из БД
$stmt = $pdo->prepare("SELECT file_path, file_name FROM files WHERE id = ?");
$stmt->execute([$fileId]);
$file = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$file) {
    http_response_code(404);
    echo json_encode(['error' => 'File not found']);
    exit;
}

$filePath = $file['file_path'];

// Проверяем существование файла
if (!file_exists($filePath)) {
    http_response_code(404);
    echo json_encode(['error' => 'File does not exist on disk']);
    exit;
}

// Определяем MIME-тип по расширению
$extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
$mimeTypes = [
    'mp4' => 'video/mp4',
    'webm' => 'video/webm',
    'ogg' => 'video/ogg',
    'avi' => 'video/x-msvideo',
    'mkv' => 'video/x-matroska',
    'mov' => 'video/quicktime',
    'wmv' => 'video/x-ms-wmv',
    'flv' => 'video/x-flv',
    'm4v' => 'video/x-m4v',
    'mpeg' => 'video/mpeg',
    'mpg' => 'video/mpeg',
];

$mimeType = $mimeTypes[$extension] ?? 'application/octet-stream';

// Получаем размер файла
$fileSize = filesize($filePath);
$start = 0;
$end = $fileSize - 1;

// Поддержка Range запросов (для перемотки видео)
if (isset($_SERVER['HTTP_RANGE'])) {
    $range = $_SERVER['HTTP_RANGE'];
    $range = str_replace('bytes=', '', $range);
    $range = explode('-', $range);
    $start = intval($range[0]);
    
    if (isset($range[1]) && $range[1] !== '') {
        $end = intval($range[1]);
    }
    
    header('HTTP/1.1 206 Partial Content');
    header('Content-Range: bytes ' . $start . '-' . $end . '/' . $fileSize);
} else {
    header('HTTP/1.1 200 OK');
}

// Устанавливаем заголовки
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . ($end - $start + 1));
header('Accept-Ranges: bytes');
header('Content-Disposition: inline; filename="' . basename($filePath) . '"');

// Открываем файл и отдаем нужный кусок
$fp = fopen($filePath, 'rb');
fseek($fp, $start);

$buffer = 1024 * 8; // 8KB chunks
$bytesLeft = $end - $start + 1;

while ($bytesLeft > 0 && !feof($fp)) {
    $bytesToRead = min($buffer, $bytesLeft);
    echo fread($fp, $bytesToRead);
    $bytesLeft -= $bytesToRead;
    flush();
}

fclose($fp);
exit;
?>