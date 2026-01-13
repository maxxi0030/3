<?php
header('Content-Type: application/json');

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
?>