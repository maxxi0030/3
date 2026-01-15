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
// ФУНКЦИИ ДЛЯ ПАНЕЛИ С ИНФО О ФАЙЛЕ
// ====================================================================

// функция для показа панели с инфой о файле без перезагрузки страницы
function showFileInfo(buttonElement, file) {
    // убираем подсветку у всех строк
    document.querySelectorAll('.file-table tr').forEach(row => {
        row.classList.remove('active-row');
    });

    // находим строку и красим
    const row = buttonElement.closest('tr'); 
    if (row) {
        row.classList.add('active-row');
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
    
    // 1. Проверяем наличие old_path (это самый верный признак)
    // 2. Используем опциональный статус, если он есть в объекте
    const isMoved = file.old_path || file.status === 'moved';

    if (isMoved) {
        movedSection.style.display = 'block';
        
        // Заполняем пути
        const oldPathElem = document.getElementById('infoOldPath');
        const newPathElem = document.getElementById('infoNewPath');
        
        if (oldPathElem) oldPathElem.textContent = file.old_path || 'Неизвестно';
        if (newPathElem) newPathElem.textContent = file.path || file.new_path;
        
        console.log("Отображаем историю перемещения для файла:", file.id);
    } else {
        movedSection.style.display = 'none';
    }

    document.getElementById('openFolderBtn').onclick = function() {
        openInExplorer(file.path); 
    };

    panel.classList.remove('hidden');

    // проверка видео ли это 
    if (isVideoFile(file.name)) {
        loadVideoMetadata(file.id);
    } else {
        // Скрываем секцию метаданных для не-видео файлов
        document.getElementById('videoMetadataSection').style.display = 'none';
    }

    panel.classList.remove('hidden');
}


// при закрытии панели
function closeFileInfo() {
    document.getElementById('fileInfoPanel').classList.add('hidden');
    
    // Убираем выделение со всех строк
    document.querySelectorAll('.file-table tr').forEach(row => {
        row.classList.remove('active-row');
    });
    
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

            // 2. Вызываем твою оригинальную функцию, которую ты скинул
            showFileInfo(buttonElement, fileForDisplay);
            
            // 3. Заполняем даты (которых не было в старой функции)
            const createdDate = data.created_at ? data.created_at.split('.')[0] : '-';
            document.getElementById('infoCreated').textContent = createdDate;

            // Если есть дата изменения
            if (data.updated_at) {
                const updatedDate = data.updated_at.split('.')[0];
                document.getElementById('infoUpdated').textContent = updatedDate;
            }
        });
}

// кнопка копировать путь в карточке с ифой о файле
function copyPath() {
    const pathText = document.getElementById('infoFullPath').innerText;
    navigator.clipboard.writeText(pathText).then(() => {
        // Можно добавить визуальный эффект, например, сменить иконку на галочку
    });
}








// ====================================================================
// ФУНКЦИИ ДЛЯ КНОПКИ СКАНИРОВАНИЯ
// ====================================================================

// светофор для кнопок скана
// function startScan(btn) {
//     // Находим все кнопки сканирования на странице (и на дашборде, и в сайдбаре если есть)
//     const allScanBtns = document.querySelectorAll('[onclick="startScan(this)"]');
    
//     // Блокируем кнопки (Светофор - Красный)
//     allScanBtns.forEach(b => {
//         b.disabled = true;
//         b.style.opacity = "0.6";
//         b.querySelector('.btn-text').textContent = "Сканирование...";
//         b.querySelector('.material-icons-round').classList.add('fa-spin'); // если есть анимация вращения
//     });

//     // Отправляем запрос на сервер
//     fetch('bc/ajax_scan.php', { method: 'POST' })
//     .then(response => response.json())
//     .then(data => {
//         if (data.status === 'success') {
//             // Выводим результат (можно через alert или красивое уведомление)
//             alert("Готово! Новых: " + data.stats.new);
            
//             // Обновляем время последнего скана на всех кнопках
//             document.querySelectorAll('#lastTime').forEach(t => t.textContent = data.last_time);
//         } else {
//             alert("Ошибка: " + data.message);
//         }
//     })
//     .catch(error => console.error('Error:', error))
//     .finally(() => {
//         // Разблокируем кнопки (Светофор - Зеленый)
//         allScanBtns.forEach(b => {
//             b.disabled = false;
//             b.style.opacity = "1";
//             b.querySelector('.btn-text').textContent = "Сканировать";
//         });
//     });
// }

// // При загрузке страницы проверяем, не идет ли сейчас скан (на случай, если пользователь обновил страницу)
// window.onload = function() {
//     fetch('bc/ajax_scan.php?check_status=1')
//     .then(r => r.json())
//     .then(data => {
//         if (data.scanning) {
//             // Если скан идет, имитируем нажатие для визуальной блокировки
//             const btn = document.querySelector('[onclick="startScan(this)"]');
//             if (btn) startScan(btn); 
//         }
//     });
// }



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
            document.querySelectorAll('#lastTime').forEach(t => t.textContent = data.last_time);
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
        if (data.scanning) {
            setScanState(true);
            // Запускаем опрос статуса, чтобы кнопка разблокировалась сама, когда скан кончится
            const checkInterval = setInterval(() => {
                fetch('ajax_scan.php?check_status=1')
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








// СВЕТОФОР

// Переменная, которая хранит время последнего скана, известное ЭТОЙ странице
// При загрузке страницы берем его из PHP
// let lastKnownSyncTime = "<?= file_exists('last_scan_sync.txt') ? file_get_contents('last_scan_sync.txt') : '' ?>";
// let notificationShown = false; // Флаг для контроля показа

// function checkGlobalUpdate() {
//     fetch('ajax_scan.php?check_status=1&get_sync=1')
//     .then(r => r.json())
//     .then(data => {
//         // Если на сервере время скана новее
//         if (data.global_sync && data.global_sync !== lastKnownSyncTime) {
//             showUpdateNotify();
//             // ✅ ИСПРАВЛЕНИЕ: Обновляем время, чтобы не показывать уведомление снова
//             lastKnownSyncTime = data.global_sync;
//             notificationShown = true; // Показываем только один раз до перезагрузки
//         }
//     })
//     .catch(err => console.log("Ошибка проверки обновлений:", err));
// }

// function showUpdateNotify() {
//     // Если плашка уже висит — ничего не делаем
//     if (document.getElementById('sync-notify')) return;

//     const notify = document.createElement('div');
//     notify.id = 'sync-notify';
//     notify.innerHTML = `
//         <div onclick="location.reload()" style="
//             position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%);
//             background: #4F46E5; color: white; padding: 14px 28px;
//             border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.3);
//             z-index: 9999; cursor: pointer; display: flex; align-items: center;
//             gap: 12px; font-weight: 500; animation: slideUp 0.4s ease-out;
//         ">
//             <span class="material-icons-round" style="animation: spin 2s infinite linear;">refresh</span>
//             Данные обновились. Нажмите, чтобы увидеть изменения
//         </div>
//     `;
//     document.body.appendChild(notify);
// }

// // Добавьте CSS для анимации
// const style = document.createElement('style');
// style.textContent = `
//     @keyframes slideUp {
//         from { transform: translate(-50%, 100px); opacity: 0; }
//         to { transform: translate(-50%, 0); opacity: 1; }
//     }
//     @keyframes spin {
//         from { transform: rotate(0deg); }
//         to { transform: rotate(360deg); }
//     }
// `;
// document.head.appendChild(style);

// // Запускаем проверку каждые 10 секунд
// setInterval(checkGlobalUpdate, 10000);












// ====================================================================
// ФУНКЦИИ ДЛЯ МЕТАДАННЫХ ВИДЕО 
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


// первод в удобный формат времени из секунд
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


// битрейт показывает нормално
function formatBitrate(bitrate) {
    if (!bitrate) return '—';
    const kbps = Math.round(bitrate / 1000);
    if (kbps > 1000) {
        return `${(kbps / 1000).toFixed(2)} Mbps`;
    }
    return `${kbps} kbps`;
}


// аудио каналы
function formatAudioChannels(channels) {
    if (!channels) return '—';
    const channelMap = {
        1: 'Моно',
        2: 'Стерео',
        6: '5.1',
        8: '7.1'
    };
    return channelMap[channels] || `${channels} каналов`;
}

function formatBytes(bytes, decimals = 2) {
    if (!bytes || bytes === 0) return '0 Bytes';
    const k = 1024;
    const dm = decimals < 0 ? 0 : decimals;
    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
}

// Загрузка метаданных видео
function loadVideoMetadata(fileId) {
    const section = document.getElementById('videoMetadataSection');
    const loadingEl = document.getElementById('videoMetadataLoading');
    const errorEl = document.getElementById('videoMetadataError');
    const contentEl = document.getElementById('videoMetadataContent');
    
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
