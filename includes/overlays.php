<?php
// ДЛЯ ДЖСОН
// Поиск данных конкретного файла, если передан info_id
// $current_file = null;
// if ($show_info && isset($files)) {
//     foreach ($files as $f) {
//         if ($f['id'] == $show_info) {
//             $current_file = $f;
//             break;
//         }
//     }
// }
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
            <span id="infoIcon" class="material-icons-round file-preview-icon">insert_drive_file</span>
        </div>

        <div class="file-detail-section">
            <h3>Детали</h3>
            <div class="file-detail-row">
                <span class="file-detail-label">Имя:</span>
                <span class="file-detail-value" id="infoName"></span>
            </div>
            <div class="file-detail-row">
                <span class="file-detail-label">Размер:</span>
                <span class="file-detail-value" id="infoSize"></span>
            </div>
            <div class="file-detail-row">
                <span class="file-detail-label">Статус:</span>
                <span id="infoStatus" class="file-detail-value badge"></span>
            </div>
            <div class="file-detail-row">
                <span class="file-detail-label">Дата создания:</span>
                <span id="infoCreated" class="file-detail-value badge"></span>
            </div>
            <div class="file-detail-row">
                <span class="file-detail-label">Дата изменения:</span>
                <span class="file-detail-value" id="infoUpdated"></span>
            </div>

        </div>

                <!-- Секция метаданных видео -->
        <div id="videoMetadataSection" class="file-detail-section video-metadata-section" style="display: none;">
            <h3>Метаданные видео</h3>
            
            <!-- Состояние загрузки -->
            <div id="videoMetadataLoading" class="video-metadata-loading">
                <span class="material-icons-round rotating">refresh</span>
                <span>Загрузка метаданных...</span>
            </div>

            <!-- Ошибка загрузки -->
            <div id="videoMetadataError" class="video-metadata-error" style="display: none;">
                <span class="material-icons-round">error_outline</span>
                <span>Не удалось загрузить метаданные</span>
            </div>

            <!-- Метаданные -->
            <div id="videoMetadataContent" style="display: none;">
                <div class="file-detail-row">
                    <span class="file-detail-label">Длительность:</span>
                    <span class="file-detail-value" id="videoDuration">—</span>
                </div>
                <div class="file-detail-row">
                    <span class="file-detail-label">Разрешение:</span>
                    <span class="file-detail-value" id="videoResolution">—</span>
                </div>
                <div class="file-detail-row">
                    <span class="file-detail-label">Соотношение сторон:</span>
                    <span class="file-detail-value" id="videoAspectRatio">—</span>
                </div>
                <div class="file-detail-row">
                    <span class="file-detail-label">FPS:</span>
                    <span class="file-detail-value" id="videoFps">—</span>
                </div>
                <div class="file-detail-row">
                    <span class="file-detail-label">Битрейт:</span>
                    <span class="file-detail-value" id="videoBitrate">—</span>
                </div>
                <div class="file-detail-row">
                    <span class="file-detail-label">Видео кодек:</span>
                    <span class="file-detail-value" id="videoCodecVideo">—</span>
                </div>
                <div class="file-detail-row">
                    <span class="file-detail-label">Аудио кодек:</span>
                    <span class="file-detail-value" id="videoCodecAudio">—</span>
                </div>
                <div class="file-detail-row">
                    <span class="file-detail-label">Аудио каналы:</span>
                    <span class="file-detail-value" id="videoAudioChannels">—</span>
                </div>
                <div class="file-detail-row">
                    <span class="file-detail-label">Язык:</span>
                    <span class="file-detail-value" id="videoLanguage">—</span>
                </div>
                <div class="file-detail-row">
                    <span class="file-detail-label">Субтитры:</span>
                    <span class="file-detail-value" id="videoSubtitles">—</span>
                </div>
            </div>
        </div>

        <div id="movedSection" class="file-detail-section file-moved-section">
            <h3>История перемещения</h3>
            <div class="file-path-label">ОТКУДА:</div>
            <code id="infoOldPath" class="file-path-code"></code>
            <div class="file-path-label">КУДА:</div>
            <code id="infoNewPath" class="file-path-code-new"></code>
        </div>

        <div class="file-detail-section">
            <h3>Путь</h3>
            <div class="file-path-container">
                <code id="infoFullPath" class="file-full-path"></code>
                <button onclick="copyPath()" class="copy-path-btn" title="Копировать путь">
                    <span class="material-icons-round">content_copy</span>
                </button>
            </div>
        </div>

        <div class="file-actions-panel">
            <button id="openFolderBtn" class="btn-primary open-folder-btn">
                <span class="material-icons-round">folder</span> Перейти в папку
            </button>
        </div>
    </div>
</aside>





<!-- Модальное окно привязки клиента к файлу -->
<aside class="file-info-panel hidden" id="clientAssignModal" style="max-width:420px;">
    <div class="file-info-header">
        <h2>Привязать клиента</h2>
        <button onclick="closeClientAssign()" class="close-panel-btn">
            <span class="material-icons-round">close</span>
        </button>
    </div>

    <div class="file-info-content">
        <div class="file-detail-section">
            <div class="file-detail-row">
                <label class="file-detail-label">Клиент</label>
                <select id="clientSelect" style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid var(--border); font-size:14px;"></select>
            </div>
        </div>

        <div class="file-actions-panel" style="margin-top:12px; display:flex; gap:8px; justify-content:flex-end;">
            <button id="clientAssignCancel" class="btn-secondary" onclick="closeClientAssign()">Отмена</button>
            <button id="clientAssignSave" class="btn-primary">Сохранить</button>
        </div>
    </div>
</aside>