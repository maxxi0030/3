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



<aside class="file-info-panel hidden" id="fileInfoPanel">
    <div class="file-info-header">
        <h2>Информация о файле</h2>
        <button onclick="closeFileInfo()" class="close-panel-btn">
            <span class="material-icons-round">close</span>
        </button>
    </div>
    
    <div class="file-info-content" id="infoContent">
        <div class="file-preview">
            <span id="infoIcon" class="material-icons-round" style="font-size: 64px; color: var(--accent);">insert_drive_file</span>
        </div>

        <div class="file-detail-section">
            <h3>Детали</h3>
            <div class="file-detail-row"><span class="file-detail-label">Имя:</span> <span class="file-detail-value" id="infoName"></span></div>
            <div class="file-detail-row"><span class="file-detail-label">Размер:</span> <span class="file-detail-value" id="infoSize"></span></div>
            <div class="file-detail-row"><span class="file-detail-label">Статус:</span> <span id="infoStatus" class="file-detail-value badge"></span></div>
        </div>

        <div id="movedSection" class="file-detail-section" style="display: none; background: #FFF3E0; border-radius: 12px; padding: 15px; border: 1px dashed #FFB74D;">
            <h3 style="color: #E65100; margin-bottom: 12px;">История перемещения</h3>
            <div style="font-size: 11px; color: #666;">ОТКУДА:</div>
            <code id="infoOldPath" style="display: block; word-break: break-all; margin-bottom: 10px;"></code>
            <div style="font-size: 11px; color: #666;">КУДА:</div>
            <code id="infoNewPath" style="display: block; word-break: break-all; font-weight: bold;"></code>
        </div>

        <div class="file-detail-section">
            <h3>Путь</h3>
            <code id="infoFullPath" style="display: block; background: #f5f5f5; padding: 10px; border-radius: 8px; font-size: 12px; word-break: break-all;"></code>
        </div>

        <div class="file-actions-panel">
            <button id="openFolderBtn" class="btn-primary" style="width: 100%;">
                <span class="material-icons-round">folder</span> Перейти в папку
            </button>
        </div>
    </div>
</aside>
