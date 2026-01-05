<?php
// Поиск данных конкретного файла, если передан info_id
$current_file = null;
if ($show_info && isset($files)) {
    foreach ($files as $f) {
        if ($f['id'] == $show_info) {
            $current_file = $f;
            break;
        }
    }
}
?>

<aside class="file-info-panel <?= $info_panel_class ?>" id="fileInfoPanel">
    <div class="file-info-header">
        <h2>Информация о файле</h2>
        <a href="?page=<?= $page ?>" class="close-panel-btn">
            <span class="material-icons-round">close</span>
        </a>
    </div>
    
    <div class="file-info-content">
        <?php if ($current_file): ?>
            <div class="file-preview">
                <span class="material-icons-round" style="font-size: 64px; color: var(--accent);">
                    <?= $current_file['status'] === 'exists' ? 'insert_drive_file' : 'report_problem' ?>
                </span>
            </div>

            <div class="file-detail-section">
                <h3>Детали</h3>
                <div class="file-detail-row">
                    <span class="file-detail-label">Имя:</span>
                    <span class="file-detail-value"><?= $current_file['name'] ?></span>
                </div>
                <div class="file-detail-row">
                    <span class="file-detail-label">Размер:</span>
                    <span class="file-detail-value"><?= $current_file['size'] ?></span>
                </div>
                <div class="file-detail-row">
                    <span class="file-detail-label">Статус:</span>
                    <span class="file-detail-value badge <?= $current_file['status'] ?>">
                        <?= $current_file['status_text'] ?>
                    </span>
                </div>
            </div>

            <div class="file-detail-section">
                <h3>Путь</h3>
                <code style="display: block; background: #f5f5f5; padding: 10px; border-radius: 8px; font-size: 12px; word-break: break-all;">
                    <?= $current_file['path'] ?>
                </code>
            </div>

            <div class="file-actions-panel">
                <button class="btn-primary" style="width: 100%;">
                    <span class="material-icons-round">tr</span>перейти в папку с файлом
                </button>
            </div>
        <?php else: ?>
            <div class="placeholder-content">
                <span class="material-icons-round">info</span>
                <p>Файл не найден или не выбран</p>
            </div>
        <?php endif; ?>
    </div>
</aside>
