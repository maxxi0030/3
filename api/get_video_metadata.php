<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../db/db_connect.php';

if (!isset($_GET['file_id'])) {
    echo json_encode(['success' => false, 'error' => 'No file_id provided']);
    exit;
}

$fileId = (int)$_GET['file_id'];

// 1. Проверяем, есть ли уже метаданные в БД
$stmt = $pdo->prepare("SELECT * FROM video_metadata WHERE file_id = ?");
$stmt->execute([$fileId]);
$existingMetadata = $stmt->fetch(PDO::FETCH_ASSOC);

if ($existingMetadata) {
    // Если метаданные уже есть, возвращаем их
    echo json_encode([
        'success' => true,
        'metadata' => [
            'duration' => $existingMetadata['duration'],
            'resolution' => $existingMetadata['resolution'],
            'width' => $existingMetadata['width'],
            'height' => $existingMetadata['height'],
            'aspect_ratio' => $existingMetadata['aspect_ratio'],
            'fps' => $existingMetadata['fps'],
            'bitrate' => $existingMetadata['bitrate'],
            'codec_video' => $existingMetadata['codec_video'],
            'codec_audio' => $existingMetadata['codec_audio'],
            'audio_channels' => $existingMetadata['audio_channels'],
            'language' => $existingMetadata['language'],
            'subtitles' => (bool)$existingMetadata['subtitles']
        ]
    ]);
    exit;
}

// 2. Если нет, получаем путь к файлу из БД
$stmt = $pdo->prepare("SELECT file_path FROM files WHERE id = ?");
$stmt->execute([$fileId]);
$file = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$file) {
    echo json_encode(['success' => false, 'error' => 'File not found']);
    exit;
}

$filePath = $file['file_path'];

// Проверяем существование файла
if (!file_exists($filePath)) {
    echo json_encode(['success' => false, 'error' => 'File does not exist on disk']);
    exit;
}

// 3. Команда для ffprobe (извлечение метаданных в JSON формате)
$cmd = escapeshellcmd('ffprobe -v error -show_format -show_streams -of json "' . $filePath . '"');

try {
    $output = shell_exec($cmd);
    
    if ($output === null) {
        // Сохраняем пустые метаданные
        $insertStmt = $pdo->prepare("
            INSERT INTO video_metadata 
            (file_id, duration, resolution, fps, bitrate, codec_video, codec_audio, audio_channels, language, subtitles)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $insertStmt->execute([
            $fileId, 0, '—', null, null, '—', '—', null, '—', false
        ]);
        
        echo json_encode([
            'success' => true,
            'metadata' => [
                'duration' => 0,
                'resolution' => '—',
                'fps' => null,
                'bitrate' => null,
                'codec_video' => '—',
                'codec_audio' => '—',
                'audio_channels' => null,
                'language' => '—',
                'subtitles' => false
            ]
        ]);
        exit;
    }

    $probeData = json_decode($output, true);
    
    // Инициализируем метаданные
    $metadata = [
        'duration' => 0,
        'resolution' => '—',
        'width' => null,
        'height' => null,
        'aspect_ratio' => '—',
        'fps' => null,
        'bitrate' => null,
        'codec_video' => '—',
        'codec_audio' => '—',
        'audio_channels' => null,
        'language' => '—',
        'subtitles' => false
    ];

    // Если есть информация о формате
    if (isset($probeData['format'])) {
        $format = $probeData['format'];
        
        // Длительность
        if (isset($format['duration'])) {
            $metadata['duration'] = (int)round((float)$format['duration']);
        }
        
        // Битрейт
        if (isset($format['bit_rate'])) {
            $metadata['bitrate'] = (int)$format['bit_rate'];
        }
    }

    // Парсим потоки (streams)
    $videoStream = null;
    $audioStream = null;
    
    if (isset($probeData['streams']) && is_array($probeData['streams'])) {
        foreach ($probeData['streams'] as $stream) {
            // Видео поток
            if ($stream['codec_type'] === 'video' && $videoStream === null) {
                $videoStream = $stream;
            }
            // Аудио поток
            if ($stream['codec_type'] === 'audio' && $audioStream === null) {
                $audioStream = $stream;
            }
        }
    }

    // Парсим видео информацию
    if ($videoStream) {
        // Разрешение
        if (isset($videoStream['width']) && isset($videoStream['height'])) {
            $metadata['width'] = (int)$videoStream['width'];
            $metadata['height'] = (int)$videoStream['height'];
            $metadata['resolution'] = "{$videoStream['width']}×{$videoStream['height']}";
        }
        
        // Соотношение сторон (aspect ratio)
        if (isset($videoStream['display_aspect_ratio'])) {
            $metadata['aspect_ratio'] = $videoStream['display_aspect_ratio'];
        } elseif (isset($videoStream['width']) && isset($videoStream['height'])) {
            $gcd = function($a, $b) {
                while ($b != 0) {
                    $temp = $b;
                    $b = $a % $b;
                    $a = $temp;
                }
                return $a;
            };
            $divisor = $gcd((int)$videoStream['width'], (int)$videoStream['height']);
            $aspectW = (int)$videoStream['width'] / $divisor;
            $aspectH = (int)$videoStream['height'] / $divisor;
            $metadata['aspect_ratio'] = "{$aspectW}:{$aspectH}";
        }
        
        // FPS
        if (isset($videoStream['r_frame_rate'])) {
            $parts = explode('/', $videoStream['r_frame_rate']);
            if (count($parts) === 2) {
                $fps = (float)$parts[0] / (float)$parts[1];
                $metadata['fps'] = round($fps, 2);
            }
        } elseif (isset($videoStream['avg_frame_rate'])) {
            $parts = explode('/', $videoStream['avg_frame_rate']);
            if (count($parts) === 2) {
                $fps = (float)$parts[0] / (float)$parts[1];
                $metadata['fps'] = round($fps, 2);
            }
        }
        
        // Видео кодек
        if (isset($videoStream['codec_name'])) {
            $metadata['codec_video'] = strtoupper($videoStream['codec_name']);
        }
    }

    // Парсим аудио информацию
    if ($audioStream) {
        // Аудио кодек
        if (isset($audioStream['codec_name'])) {
            $metadata['codec_audio'] = strtoupper($audioStream['codec_name']);
        }
        
        // Количество аудиоканалов
        if (isset($audioStream['channels'])) {
            $metadata['audio_channels'] = (int)$audioStream['channels'];
        }
        
        // Язык
        if (isset($audioStream['tags']['language'])) {
            $metadata['language'] = $audioStream['tags']['language'];
        }
    }

    // Проверка наличия субтитров
    if (isset($probeData['streams']) && is_array($probeData['streams'])) {
        foreach ($probeData['streams'] as $stream) {
            if ($stream['codec_type'] === 'subtitle') {
                $metadata['subtitles'] = true;
                break;
            }
        }
    }

    // 4. Сохраняем метаданные в БД
    $insertStmt = $pdo->prepare("
        INSERT INTO video_metadata 
        (file_id, duration, width, height, resolution, fps, bitrate, codec_video, codec_audio, audio_channels, aspect_ratio, language, subtitles)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $insertStmt->execute([
        $fileId,
        $metadata['duration'],
        $metadata['width'],
        $metadata['height'],
        $metadata['resolution'],
        $metadata['fps'],
        $metadata['bitrate'],
        $metadata['codec_video'],
        $metadata['codec_audio'],
        $metadata['audio_channels'],
        $metadata['aspect_ratio'],
        $metadata['language'],
        $metadata['subtitles'] ? 1 : 0
    ]);

    echo json_encode([
        'success' => true,
        'metadata' => $metadata
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

exit;
?>