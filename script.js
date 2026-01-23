// ====================================================================
// SIDEBAR (показываем или скрываем боковое меню)
// ====================================================================
document.getElementById('toggleSidebar').addEventListener('click', () => {
    document.getElementById('sidebar').classList.toggle('collapsed');
});



// сейчас эта кнопка обрабытывается пхп
// document.getElementById('closePanelBtn')?.addEventListener('click', () => {
//     document.getElementById('fileInfoPanel').classList.add('hidden');
// });



// ====================================================================
// ФУНКЦИИ ДЛЯ КНОПКИ "ОТКРЫТЬ В ЭКСПЛОРЕРЕ"
// ====================================================================
function openInExplorer(filePath) {
    // Создаем объект формы для POST запроса
    const formData = new FormData();
    formData.append('path', filePath);

    // Отправляем запрос через fetch
    fetch('bc/fldr_open.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            return response.json().then(err => { throw err; });
        }
        console.log('Папка открыта успешно');
    })
    .catch(error => {
        console.error('Ошибка:', error.message || 'Не удалось открыть папку');
        alert('Ошибка: ' + (error.message || 'Файл не найден'));
    });
}




// ====================================================================
// ФУНКЦИИ ДЛЯ КНОПКИ ДОБАВИТЬ КЛИЕНТА
// ====================================================================


document.addEventListener('DOMContentLoaded', function() {
    const addButton = document.querySelector('.add-client-trigger');
    const addForm = document.querySelector('.client-add-form');
    
    if (addButton && addForm) {
        addButton.addEventListener('click', function() {
            addForm.classList.toggle('open');
            addButton.classList.toggle('active');
        });
    }
    
    // Редактирование клиентов
    document.querySelectorAll('.edit-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const row = this.closest('tr');
            const clientId = this.dataset.id;
            const editableCells = row.querySelectorAll('[contenteditable]');
            const saveBtn = row.querySelector('.save-btn');
            
            editableCells.forEach(cell => {
                cell.setAttribute('contenteditable', 'true');
                cell.focus();
            });
            
            this.style.display = 'none';
            saveBtn.style.display = 'inline-flex';
        });
    });
    
    // Сохранение изменений
    document.querySelectorAll('.save-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const row = this.closest('tr');
            const clientId = this.dataset.id;
            const editBtn = row.querySelector('.edit-btn');
            
            const data = {
                action: 'update_client',
                id: clientId,
                name: row.querySelector('[data-field="name"]').textContent,
                phone: row.querySelector('[data-field="phone"]').textContent,
                email: row.querySelector('[data-field="email"]').textContent
            };
            
            // Отправка данных через AJAX
            const formData = new FormData();
            Object.keys(data).forEach(key => formData.append(key, data[key]));
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            }).then(() => {
                row.querySelectorAll('[contenteditable]').forEach(cell => {
                    cell.setAttribute('contenteditable', 'false');
                });
                
                this.style.display = 'none';
                editBtn.style.display = 'inline-flex';
            });
        });
    });
});




















function escapeHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function addClient(buttonElement, fileId) {

    document.querySelectorAll('.file-table tr').forEach(row => {
        row.classList.remove('active-row');
    });

    const row = buttonElement.closest('tr'); 
    if (row) {
        row.classList.add('active-row');
    }

    const modal = document.getElementById('clientAssignModal');
    const select = document.getElementById('clientSelect');
    const saveBtn = document.getElementById('clientAssignSave');
    if (!modal || !select || !saveBtn) return;

    modal.dataset.fileId = fileId;
    // select.innerHTML = '<option>Загрузка...</option>';
    modal.classList.remove('hidden');

    // Load clients
    fetch('api/ajax_clients.php?action=list')
        .then(r => r.json())
        .then(data => {
            if (data && data.success) {
                let html = '<option value="">(Не привязывать)</option>';
                html += data.clients.map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('');
                select.innerHTML = html;

                // try to preselect current file client
                fetch('api/get_file_info.php?id=' + fileId)
                    .then(r => r.json())
                    .then(fi => {
                        if (fi && fi.client_id) select.value = fi.client_id;
                    }).catch(() => {});
            } else {
                select.innerHTML = '<option value="">Ошибка загрузки</option>';
            }
        }).catch(err => {
            console.error('load clients error', err);
            select.innerHTML = '<option value="">Ошибка</option>';
        });

    // attach save handler
    saveBtn.onclick = function() {
        const clientId = select.value;
        const form = new FormData();
        form.append('action', 'assign');
        form.append('file_id', fileId);
        form.append('client_id', clientId);

        saveBtn.disabled = true;
        fetch('api/ajax_clients.php', { method: 'POST', body: form })
            .then(r => r.json())
            .then(resp => {
                saveBtn.disabled = false;
                if (resp && resp.success) {
                    closeClientAssign();
                    // refresh to show updated relation; can be changed to update row in-place
                    location.reload();
                } else {
                    alert('Ошибка: ' + (resp.message || 'Не удалось сохранить'));
                }
            }).catch(err => {
                saveBtn.disabled = false;
                console.error(err);
                alert('Ошибка запроса');
            });
    };
}

function closeClientAssign() {
    document.querySelectorAll('.file-table tr').forEach(row => {
        row.classList.remove('active-row');
    });

    const modal = document.getElementById('clientAssignModal');
    if (!modal) return;
    modal.classList.add('hidden');
    modal.dataset.fileId = '';
    const saveBtn = document.getElementById('clientAssignSave');
    if (saveBtn) saveBtn.onclick = null;
}









// ====================================================================
// ФУНКЦИИ ДЛЯ МЕТАДАННЫХ ВИДЕО И ВОСПРОИЗВЕДЕНИЯ
// ====================================================================

// список расширений для видео
const VIDEO_EXTENSIONS = [
    '.mp4', '.avi', '.mkv', '.mov', '.webm', '.flv', '.wmv', '.m4v', 
    '.mpeg', '.mpg', '.3gp', '.ogv', '.m2ts', '.mts', '.vob', '.divx',
    '.xvid', '.rm', '.rmvb', '.asf', '.qt', '.ts'
];

// проверка, является ли файл видео по расширению
function isVideoFile(filename) {
    const ext = filename.toLowerCase().substring(filename.lastIndexOf('.'));
    return VIDEO_EXTENSIONS.includes(ext);
}

// Переменные для воспроизведения
let currentVideoPath = null;
let currentFileType = null;

// Загрузка метаданных видео
function loadVideoMetadata(fileId, filePath) {
    const section = document.getElementById('videoMetadataSection');
    const loadingEl = document.getElementById('videoMetadataLoading');
    const errorEl = document.getElementById('videoMetadataError');
    const contentEl = document.getElementById('videoMetadataContent');

    // ВАЖНО: используем ID для стриминга через PHP, а не прямой путь!
    currentVideoPath = `api/stream_video.php?file_id=${fileId}`;
    currentFileType = 'video/mp4';
    
    // Сбрасываем превью перед загрузкой
    const thumbnail = document.getElementById('infoThumbnail');
    const video = document.getElementById('infoVideo');
    const icon = document.getElementById('infoIcon');
    const preview = document.getElementById('filePreview');
    
    // Останавливаем и скрываем видео
    video.pause();
    video.src = '';
    video.style.display = 'none';
    preview.classList.remove('playing');
    
    thumbnail.style.display = 'none';
    icon.style.display = 'block';
    icon.textContent = 'play_circle'; // Иконка воспроизведения для видео
    
    // Показываем секцию и состояние загрузки
    section.style.display = 'block';
    loadingEl.style.display = 'flex';
    errorEl.style.display = 'none';
    contentEl.style.display = 'none';
    
    // AJAX запрос к PHP
    fetch(`api/get_video_metadata.php?file_id=${fileId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const m = data.metadata;

                // Показываем thumbnail если есть
                if (data.metadata.thumbnail) {
                    thumbnail.src = data.metadata.thumbnail;
                    thumbnail.style.display = 'block';
                    icon.style.display = 'none';
                }
                
                // Заполняем все поля
                document.getElementById('videoDuration').textContent = formatDuration(m.duration);
                document.getElementById('videoResolution').textContent = m.resolution || (m.width && m.height ? `${m.width}×${m.height}` : '—');
                document.getElementById('videoAspectRatio').textContent = m.aspect_ratio || '—';
                document.getElementById('videoFps').textContent = m.fps ? `${m.fps} fps` : '—';
                document.getElementById('videoBitrate').textContent = formatBitrate(m.bitrate);
                document.getElementById('videoCodecVideo').textContent = m.codec_video || '—';
                document.getElementById('videoCodecAudio').textContent = m.codec_audio || '—';
                document.getElementById('videoAudioChannels').textContent = formatAudioChannels(m.audio_channels);
                document.getElementById('videoLanguage').textContent = m.language || '—';
                document.getElementById('videoSubtitles').textContent = m.subtitles ? 'Да' : 'Нет';
                
                // Показываем контент, скрываем загрузку
                loadingEl.style.display = 'none';
                contentEl.style.display = 'block';
            } else {
                throw new Error(data.error || 'Unknown error');
            }
        })
        .catch(error => {
            console.error('Error loading video metadata:', error);
            loadingEl.style.display = 'none';
            errorEl.style.display = 'flex';
        });
}

// Переменная для хранения статуса файла
let currentFileStatus = null;

// Обработчик клика на превью (только один раз!)
document.addEventListener('DOMContentLoaded', function() {
    const filePreview = document.getElementById('filePreview');
    
    if (filePreview) {
        filePreview.addEventListener('click', function() {
            // Блокируем для deleted файлов
            if (currentFileStatus === 'deleted') {
                return;
            }
            
            if (currentFileType && currentFileType.startsWith('video/')) {
                const video = document.getElementById('infoVideo');
                const icon = document.getElementById('infoIcon');
                const thumbnail = document.getElementById('infoThumbnail');
                
                // Если видео уже проигрывается - ничего не делаем
                if (video.style.display === 'block') {
                    return;
                }
                
                // Скрываем иконку/thumbnail и показываем видео
                icon.style.display = 'none';
                thumbnail.style.display = 'none';
                video.style.display = 'block';
                video.src = currentVideoPath;
                
                // Добавляем класс для отключения hover эффекта
                filePreview.classList.add('playing');
                
                // Автоматически начинаем воспроизведение
                video.play().catch(e => {
                    console.error('Ошибка воспроизведения:', e);
                    alert('Не удалось воспроизвести видео. Проверьте формат файла.');
                });
                
                // Обработка ошибок
                video.onerror = function() {
                    // alert('Не удалось загрузить видео. Возможно, формат не поддерживается браузером.');
                    video.style.display = 'none';
                    icon.style.display = 'block';
                    icon.textContent = 'videocam_off';
                    filePreview.classList.remove('playing');
                };
                
                // Когда видео закончилось - показываем обратно thumbnail
                video.onended = function() {
                    video.style.display = 'none';
                    if (thumbnail.src) {
                        thumbnail.style.display = 'block';
                    } else {
                        icon.style.display = 'block';
                        icon.textContent = 'play_circle';
                    }
                    filePreview.classList.remove('playing');
                };
            }
        });
    }
});



// Вспомогательные функции форматирования
function formatDuration(seconds) {
    if (!seconds) return '—';
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const secs = seconds % 60;
    
    if (hours > 0) {
        return `${hours}:${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
    }
    return `${minutes}:${String(secs).padStart(2, '0')}`;
}

function formatBitrate(bitrate) {
    if (!bitrate) return '—';
    const kbps = Math.round(bitrate / 1000);
    if (kbps > 1000) {
        return `${(kbps / 1000).toFixed(2)} Mbps`;
    }
    return `${kbps} kbps`;
}

function formatAudioChannels(channels) {
    if (!channels) return '—';
    const channelMap = {
        1: 'Моно (1)',
        2: 'Стерео (2)',
        6: '5.1 (6)',
        8: '7.1 (8)'
    };
    return channelMap[channels] || `${channels} каналов`;
}


// Добавьте эту функцию в начало файла, где у вас другие вспомогательные функции

function formatBytes(bytes, decimals = 2) {
    if (!bytes || bytes === 0) return '0 Bytes';
    const k = 1024;
    const dm = decimals < 0 ? 0 : decimals;
    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
}















// ====================================================================
// ФУНКЦИИ ДЛЯ ПАНЕЛИ С ИНФО О ФАЙЛЕ
// ====================================================================

function showFileInfo(buttonElement, file) {
    // убираем подсветку у всех строк
    document.querySelectorAll('.file-table tr').forEach(row => {
        row.classList.remove('active-row');
    });

    const row = buttonElement.closest('tr'); 
    if (row) {
        row.classList.add('active-row');
    }

    // Сохраняем статус файла
    currentFileStatus = file.status;

    // Проверяем тип файла
    const filename = file.name;
    const preview = document.getElementById('filePreview');
        
    if (isVideoFile(filename)) {
        // Для deleted файлов убираем курсор и блокируем
        if (file.status === 'deleted') {
            preview.style.cursor = 'default';
            preview.classList.add('disabled');
        } else {
            preview.style.cursor = 'pointer';
            preview.classList.remove('disabled');
        }
        
        // ВАЖНО: передаем file.path для воспроизведения!
        loadVideoMetadata(file.id, file.path);
    } else {
        // Это не видео - скрываем превью
        const video = document.getElementById('infoVideo');
        video.pause();
        video.src = '';
        video.style.display = 'none';
        
        document.getElementById('infoThumbnail').style.display = 'none';
        document.getElementById('infoIcon').style.display = 'block';
        document.getElementById('videoMetadataSection').style.display = 'none';
        document.getElementById('filePreview').classList.remove('playing');
        
        // Сбрасываем переменные воспроизведения
        currentVideoPath = null;
        currentFileType = null;
    }

    // заполняем панель данными
    const panel = document.getElementById('fileInfoPanel');
    
    document.getElementById('infoName').textContent = file.name;
    document.getElementById('infoSize').textContent = file.size || '-';
    document.getElementById('infoFullPath').textContent = file.path;
    
    const statusBadge = document.getElementById('infoStatus');
    statusBadge.textContent = file.status_text || '???';
    statusBadge.className = 'file-detail-value badge ' + (file.status || 'unknown');

    const icon = document.getElementById('infoIcon');
    icon.textContent = (file.status === 'exists') ? 'insert_drive_file' : 'report_problem';

    const movedSection = document.getElementById('movedSection');
    const isMoved = file.old_path || file.status === 'moved';

    if (isMoved) {
        movedSection.style.display = 'block';
        
        const oldPathElem = document.getElementById('infoOldPath');
        const newPathElem = document.getElementById('infoNewPath');
        
        if (oldPathElem) {
            oldPathElem.innerHTML = highlightPathDifference(file.old_path, file.path || file.new_path, true);
        }
        
        if (newPathElem) {
            newPathElem.innerHTML = highlightPathDifference(file.old_path, file.path || file.new_path, false);
        }
    } else {
        movedSection.style.display = 'none';
    }

    document.getElementById('openFolderBtn').onclick = function() {
        openInExplorer(file.path); 
    };

    panel.classList.remove('hidden');
}

// при закрытии панели
function closeFileInfo() {
    const panel = document.getElementById('fileInfoPanel');
    panel.classList.add('hidden');
    
    // Останавливаем видео при закрытии
    const video = document.getElementById('infoVideo');
    if (video) {
        video.pause();
        video.src = '';
        video.style.display = 'none';
    }
    
    // Сбрасываем состояние превью
    const preview = document.getElementById('filePreview');
    if (preview) {
        preview.classList.remove('playing');
    }
    
    // Убираем выделение со всех строк
    document.querySelectorAll('.file-table tr').forEach(row => {
        row.classList.remove('active-row');
    });
    
    // Сбрасываем переменные
    currentVideoPath = null;
    currentFileType = null;
}



// Новая функция-посредник
function loadFileAndShowInfo(buttonElement, fileId) {
    // 1. Идем на сервер за данными конкретного файла
    fetch('api/get_file_info.php?id=' + fileId)
        .then(response => response.json())
        .then(data => {
            // Переделываем названия полей из БД под те, что ждет твоя функция
            const fileForDisplay = {
                id: data.id,
                name: data.file_name,
                size: formatBytes(data.file_size),
                path: data.file_path,
                status: data.file_status,
                status_text: data.file_status, // можно сделать маппинг на русский
                old_path: data.old_path // если добавишь такую колонку
            };

            // 2. Вызываем твою оригинальную функцию
            showFileInfo(buttonElement, fileForDisplay);
            
            // 3. Заполняем ОРИГИНАЛЬНЫЕ даты файла (из файловой системы)
            document.getElementById('infoFileCreated').textContent = 
                data.file_created_at ? formatDateTime(data.file_created_at) : '-';
            
            document.getElementById('infoFileModified').textContent = 
                data.file_modified_at ? formatDateTime(data.file_modified_at) : '-';

            // 4. Заполняем даты работы с БД
            document.getElementById('infoDbCreated').textContent = 
                data.created_at ? formatDateTime(data.created_at) : '-';
            
            document.getElementById('infoDbUpdated').textContent = 
                data.updated_at ? formatDateTime(data.updated_at) : '-';
        });
}

// Вспомогательная функция для форматирования даты
function formatDateTime(dateString) {
    if (!dateString) return '-';
    
    // Убираем миллисекунды если есть
    const cleanDate = dateString.split('.')[0];
    
    // Парсим дату
    const date = new Date(cleanDate);
    
    // Форматируем как "23.01.2026 14:30"
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    
    return `${day}.${month}.${year} ${hours}:${minutes}`;
}

// кнопка копировать путь в карточке с ифой о файле
function copyPath() {
    const pathText = document.getElementById('infoFullPath').innerText;
    navigator.clipboard.writeText(pathText).then(() => {
        // Можно добавить визуальный эффект, например, сменить иконку на галочку
    });
}





// Функция для выделения различий в путях
function highlightPathDifference(oldPath, newPath, isOldPath = false) {
    if (!oldPath || !newPath) {
        return newPath || oldPath || '';
    }
    
    // Нормализуем пути
    oldPath = oldPath.replace(/\\/g, '/');
    newPath = newPath.replace(/\\/g, '/');
    
    // Разбиваем пути на части
    const oldParts = oldPath.split('/');
    const newParts = newPath.split('/');
    
    let result = '';
    const highlightClass = isOldPath ? 'path-highlight-old' : 'path-highlight-new';
    
    // Для старого пути используем oldParts, для нового - newParts
    const partsToUse = isOldPath ? oldParts : newParts;
    const partsToCompare = isOldPath ? newParts : oldParts;
    
    for (let i = 0; i < partsToUse.length; i++) {
        // Если части не совпадают, выделяем
        if (!partsToCompare[i] || partsToCompare[i] !== partsToUse[i]) {
            result += `<span class="${highlightClass}">${escapeHtml(partsToUse[i])}</span>`;
        } else {
            result += escapeHtml(partsToUse[i]);
        }
        
        // Добавляем разделитель, если это не последний элемент
        if (i < partsToUse.length - 1) {
            result += '/';
        }
    }
    
    return result;
}

// Вспомогательная функция для экранирования HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}







// ====================================================================
// ФУНКЦИИ ДЛЯ КНОПКИ СКАНИРОВАНИЯ
// ====================================================================


// Функция только для изменения внешнего вида кнопок скана 
function setScanState(isLoading) {
    const allScanBtns = document.querySelectorAll('[onclick="startScan(this)"]');
    allScanBtns.forEach(b => {
        const btnText = b.querySelector('.btn-text');
        const btnIcon = b.querySelector('.material-icons-round');
        
        if (isLoading) {
            b.disabled = true;
            b.style.opacity = "0.6";
            if (btnText) btnText.textContent = "Сканирование...";
            if (btnIcon) btnIcon.style.animation = "spin 2s infinite linear"; 
        } else {
            b.disabled = false;
            b.style.opacity = "1";
            if (btnText) btnText.textContent = "Сканировать";
            if (btnIcon) btnIcon.style.animation = "none";
        }
    });
}




function startScan(btn) {
    setScanState(true);
    
    // Чистим старое уведомление
    const container = document.getElementById('ajax-message-container');
    if (container) container.innerHTML = '';

    fetch('api/ajax_scan.php', { method: 'POST' })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
           
            // Обновляем локальную метку времени, чтобы ЭТОМУ пользователю 
    // не вылезло уведомление о его же собственном скане
        // if (data.sync_time) {
        //     lastKnownSyncTime = data.sync_time;
        // }
        if (data.global_sync) { 
            lastKnownSyncTime = data.global_sync; 
        }
            // 2. Выводим уведомление
            if (container) {
                const statsText = `Новых: ${data.stats.new} | Обновлено: ${data.stats.updated} | Перемещено: ${data.stats.moved} | Удалено: ${data.stats.deleted}`;
                container.innerHTML = `
                    <div class="info-alert" style="padding: 12px; background: #EEF2FF; color: var(--accent); border-radius: 12px; margin-bottom: 24px; border: 1px solid var(--border); animation: fadeIn 0.3s ease;">
                        <span class="material-icons-round" style="vertical-align: middle; font-size: 18px;">info</span>
                        <b>Result</b><br>${statsText}
                    </div>
                `;
            }

            // 3. Обновляем время на всех кнопках
            document.querySelectorAll('#lastTime').forEach(t => t.textContent = data.last_scan_time);
        } else {
            alert("Ошибка: " + data.message);
        }
    })
    .catch(error => console.error('Error:', error))
    .finally(() => setScanState(false));
}



// Проверка при загрузке
window.addEventListener('load', function() {
    fetch('api/ajax_scan.php?check_status=1')
    .then(r => r.json())
    .then(data => {
        // Обновляем время под кнопкой при загрузке страницы
        if (data.last_scan_time) {
            document.querySelectorAll('#lastTime').forEach(t => t.textContent = data.last_scan_time);
        }
        
        if (data.scanning) {
            setScanState(true);
            // Запускаем опрос статуса, чтобы кнопка разблокировалась сама, когда скан кончится
            const checkInterval = setInterval(() => {
                fetch('api/ajax_scan.php?check_status=1')
                .then(r => r.json())
                .then(d => {
                    if (!d.scanning) {
                        setScanState(false);
                        clearInterval(checkInterval);
                        // Опционально: перезагрузить, чтобы увидеть данные
                        location.reload();
                    }
                });
            }, 3000); // проверяем каждые 3 секунды
        }
    });
});








// УВЕДОМЛЕНИЕ

let lastSeenScanTime = null;
let checkInterval = null;


function checkForUpdates() {
    fetch(`api/ajax_scan.php?check_status`)
        .then(response => response.json())
        .then(data => {
            const currentScanTime = data.last_scan_time;

            // Если это первый запуск после загрузки страницы
            if (lastSeenScanTime === null) {
                lastSeenScanTime = currentScanTime;
                return;
            }
            
            // Если время изменилось - значит был новый скан
            if (currentScanTime && currentScanTime !== lastSeenScanTime) {
                
                // Проверяем, не запускал ли я сам этот скан
                if (localStorage.getItem('i_started_scan') === 'true') {
                    localStorage.removeItem('i_started_scan');
                    lastSeenScanTime = currentScanTime;
                    return; // Не показываем уведомление
                }
                
                // Получаем детали последнего скана из БД
                fetchScanDetails(currentScanTime);
                
                // Обновляем сохранённое время
                lastSeenScanTime = currentScanTime;
            }
        })
        .catch(error => console.error('Ошибка проверки обновлений:', error));
}

function fetchScanDetails(scanTime) {
    // Получаем детали последнего успешного скана
    fetch(`api/get-scan-details.php`)
        .then(response => response.json())
        .then(data => {
            // Проверяем, что были реальные изменения
            const hasRealChanges = data.files_added > 0 || data.files_updated > 0 || 
                                   data.files_deleted > 0 || data.files_moved > 0;
            
            if (hasRealChanges) {
                showUpdateNotify(data);
            }
        })
        .catch(error => console.error('Ошибка получения деталей скана:', error));
}


function showUpdateNotify(scan) {
    if (document.getElementById('sync-notify')) return;

    if (checkInterval) {
        clearInterval(checkInterval);
        checkInterval = null;
    }

    let changesList = '';
    if (scan.files_added > 0) changesList += `Добавлено: ${scan.files_added}<br>`;
    if (scan.files_updated > 0) changesList += `Обновлено: ${scan.files_updated}<br>`;
    if (scan.files_deleted > 0) changesList += `Удалено: ${scan.files_deleted}<br>`;
    if (scan.files_moved > 0) changesList += `Перемещено: ${scan.files_moved}<br>`;

    const notify = document.createElement('div');
    notify.id = 'sync-notify';
    notify.innerHTML = `
        <div>
            <button class="close-notify" onclick="document.getElementById('sync-notify').remove(); event.stopPropagation();">
                <span class="material-icons-round">close</span>
            </button>
            <div onclick="location.reload()" style="cursor: pointer;">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <span class="material-icons-round" style="animation: spin 2s infinite linear;">refresh</span>
                    <strong>Новый скан завершён (${lastSeenScanTime})</strong>
                </div>
                <div style="font-size: 14px; opacity: 0.95; line-height: 1.6;">
                    ${changesList}
                </div>
                <div style="font-size: 13px; opacity: 0.8; margin-top: 4px;">
                    Нажмите, чтобы обновить страницу
                </div>
            </div>
        </div>
    `;
    document.body.appendChild(notify);
}



// Запускаем проверку каждые н секунд
checkInterval = setInterval(checkForUpdates, 90000);
// checkInterval = setInterval(checkForUpdates, 7000);


// Первая проверка сразу при загрузке (установит начальное значение)
checkForUpdates();






// ====================================================================
// ФУНКЦИИ ДЛЯ АВТОСАМБИТА ФИЛЬТРОВ НА ДЭШЕ
// ====================================================================

document.addEventListener('DOMContentLoaded', () => {
    const filtersForm = document.querySelector('.filters-form');
    
    if (filtersForm) {
        // пполучаем все селекты в форме фильтров
        const selects = filtersForm.querySelectorAll('select');
        
        // добавляем слушатель на каждый селект
        selects.forEach(select => {
            select.addEventListener('change', () => {
                filtersForm.submit();
            });
        });
    }
});













// ====================================================================
// ФУНКЦИИ ДЛЯ УПРАВЛЕНИЯ КЛИЕНТАМИ
// ====================================================================

document.addEventListener('DOMContentLoaded', function() {
    
    // Редактирование клиента
    document.querySelectorAll('.edit-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            const row = this.closest('tr');
            const cells = row.querySelectorAll('td[contenteditable]');
            
            cells.forEach(cell => {
                cell.contentEditable = 'true';
                cell.classList.add('editing');
            });
            
            this.style.display = 'none';
            row.querySelector('.save-btn').style.display = 'inline-block';
        });
    });
    
    // Сохранение изменений
    document.querySelectorAll('.save-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            const row = this.closest('tr');
            const cells = row.querySelectorAll('td[contenteditable]');
            
            const formData = new FormData();
            formData.append('action', 'update_client');
            formData.append('id', id);
            
            cells.forEach(cell => {
                const field = cell.dataset.field;
                const value = cell.textContent.trim();
                formData.append(field, value);
                
                cell.contentEditable = 'false';
                cell.classList.remove('editing');
            });
            
            // Отправка AJAX запроса
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (response.ok) {
                    this.style.display = 'none';
                    row.querySelector('.edit-btn').style.display = 'inline-block';
                }
            })
            .catch(error => {
                console.error('Ошибка:', error);
                alert('Ошибка при сохранении');
            });
        });
    });
});




// ====================================================================
// ФУНКЦИИ ДЛЯ ПАГИНАЦИИ "ЗАГРУЗИТЬ ЕЩЕ"
// ====================================================================

// Переменная для отслеживания текущей страницы пагинации
let currentLoadMorePage = 1;




// Функция для загрузки дополнительных файлов через AJAX
function loadMoreFiles() {
    const btn = document.getElementById('loadMoreBtn');
    
    // Защита: если кнопка уже загружает, не отправляем повторный запрос
    if (btn.disabled) {
        return;
    }
    
    // Блокируем кнопку и меняем текст
    btn.disabled = true;
    btn.classList.add('loading');
    btn.querySelector('.btn-text').textContent = 'Загрузка...';
    
    // Собираем параметры фильтрации из текущих GET параметров
    const params = new URLSearchParams(window.location.search);
    params.set('page', currentLoadMorePage + 1); // Увеличиваем номер страницы
    
    // Отправляем запрос на сервер
    fetch('api/load_more_files.php?' + params.toString())
        .then(response => {
            if (!response.ok) {
                throw new Error('Ошибка сервера: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            if (data.status !== 'success') {
                throw new Error(data.message || 'Неизвестная ошибка');
            }
            
            renderLoadedFiles(data.data);
            
            // Увеличиваем счетчик страницы
            currentLoadMorePage = data.page;
            
            // Проверяем, есть ли еще записи
            if (!data.hasMore) {
                // Скрываем кнопку и показываем сообщение
                btn.textContent = 'Данных больше нет';
                btn.disabled = true;
                btn.classList.remove('loading');
                btn.style.visibility = 'hidden';
            } else {
                // Кнопка остается активной для следующей загрузки
                btn.disabled = false;
                btn.classList.remove('loading');
                btn.querySelector('.btn-text').textContent = 'Загрузить еще';
            }
        })
        .catch(error => {
            console.error('Ошибка при загрузке файлов:', error);
            btn.disabled = false;
            btn.classList.remove('loading');
            btn.querySelector('.btn-text').textContent = 'Загрузить еще';
            alert('Ошибка при загрузке: ' + error.message);
        });
}




// Функция для рендеринга загруженных файлов в таблицу
// Функция для рендеринга загруженных файлов в таблицу
function renderLoadedFiles(files) {
    const tbody = document.querySelector('.file-table tbody');
    
    if (!tbody) {
        console.error('Таблица не найдена');
        return;
    }
    
    // Если это первая загрузка и была пустая таблица, очищаем её
    if (currentLoadMorePage === 1) {
        const emptyRow = tbody.querySelector('tr td[colspan]');
        if (emptyRow) {
            emptyRow.closest('tr').remove();
        }
    }
    
    // Карта статусов (как в PHP)
    const statusMap = {
        'new': 'Новый',
        'active': 'Ок',
        'deleted': 'Удален',
        'moved': 'Перемещен',
        'updated': 'Обновлен',
        'source_off': 'Источник отключен'
    };
    
    const classMap = {
        'active': 'exists',   // PHP использует 'exists' для active
        'new': 'new',
        'deleted': 'deleted',
        'moved': 'moved',
        'updated': 'updated',
        'source_off': 'source_off'
    };
    
    files.forEach(file => {
        const tr = document.createElement('tr');
        if (file.status === 'deleted') {
            tr.classList.add('row-deleted');
        }
        
        const statusText = statusMap[file.status] || file.status;
        const statusClass = classMap[file.status] || 'source_off';
        
        // Генерируем HTML точно как в PHP
        tr.innerHTML = `
            <td class="cell-name">
                <div class="file-icon">
                    <span class="material-icons-round">description</span>
                </div>
                ${escapeHtml(file.name)}
            </td>
            <td class="client_name">${file.client_name ? escapeHtml(file.client_name) : ''}</td>
            <td class="cell-size hide-mobile">${file.size}</td>
            <td>
                <span class="badge ${statusClass}">${statusText}</span>
            </td>
            <td class="cell-date hide-mobile">${file.date}</td>
            <td>
                <div class="action-group">
                    <button class="btn-icon" onclick="loadFileAndShowInfo(this, ${file.id})" title="Информация">
                        <span class="material-icons-round">info</span>
                    </button>
                    <button class="btn-icon" onclick="addClient(this, ${file.id})" title="Добавить клиента">
                        <span class="material-icons-round">people</span>
                    </button>
                    <button class="btn-icon" title="Открыть папку" onclick="openInExplorer('${escapeHtml(file.path).replace(/'/g, "\\'")}')">
                        <span class="material-icons-round">folder</span>
                    </button>
                </div>
            </td>
        `;
        
        tbody.appendChild(tr);
    });
}

// Вспомогательная функция для экранирования HTML
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
// Инициализация пагинации при загрузке страницы
document.addEventListener('DOMContentLoaded', () => {
    const loadMoreBtn = document.getElementById('loadMoreBtn');
    if (loadMoreBtn) {
        loadMoreBtn.addEventListener('click', loadMoreFiles);
    }
});







// ====================================================================
// ФУНКЦИИ ДЛЯ ИСТОРИИ
// ====================================================================
    function toggleFilters() {
        const panel = document.getElementById('filtersPanel');
        panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
    }
    
    function toggleDetails(id) {
        const details = document.getElementById('details-' + id);
        const isVisible = details.style.display !== 'none';
        details.style.display = isVisible ? 'none' : 'block';
    }


































