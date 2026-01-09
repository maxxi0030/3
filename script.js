// боковое меню
document.getElementById('toggleSidebar').addEventListener('click', () => {
    document.getElementById('sidebar').classList.toggle('collapsed');
});



// сейчас эта кнопка обрабытывается пхп
// document.getElementById('closePanelBtn')?.addEventListener('click', () => {
//     document.getElementById('fileInfoPanel').classList.add('hidden');
// });



// функция для открытися папки с файлом
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
    if (file.status === 'moved' && file.old_path) {
        movedSection.style.display = 'block';
        document.getElementById('infoOldPath').textContent = file.old_path;
        document.getElementById('infoNewPath').textContent = file.path;
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
    document.getElementById('fileInfoPanel').classList.add('hidden');
    
    // Убираем выделение со всех строк
    document.querySelectorAll('.file-table tr').forEach(row => {
        row.classList.remove('active-row');
    });
    
}











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



// Функция только для изменения внешнего вида кнопок
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

    fetch('ajax_scan.php', { method: 'POST' })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
           
            // Обновляем локальную метку времени, чтобы ЭТОМУ пользователю 
    // не вылезло уведомление о его же собственном скане
        if (data.sync_time) {
            lastKnownSyncTime = data.sync_time;
        }
            // 2. Выводим уведомление
            if (container) {
                const statsText = `Новых: ${data.stats.new} | Обновлено: ${data.stats.updated} | Перемещено: ${data.stats.moved} | Удалено: ${data.stats.deleted}`;
                container.innerHTML = `
                    <div class="info-alert" style="padding: 12px; background: #EEF2FF; color: var(--accent); border-radius: 12px; margin-bottom: 24px; border: 1px solid var(--border); animation: fadeIn 0.3s ease;">
                        <span class="material-icons-round" style="vertical-align: middle; font-size: 18px;">info</span>
                        <b>${data.message}</b><br>${statsText}
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
    fetch('ajax_scan.php?check_status=1')
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










// Переменная, которая хранит время последнего скана, известное ЭТОЙ странице
// При загрузке страницы берем его из PHP
let lastKnownSyncTime = "<?= file_exists('last_scan_sync.txt') ? file_get_contents('last_scan_sync.txt') : '' ?>";

function checkGlobalUpdate() {
    // Спрашиваем статус, передав параметр для синхронизации
    fetch('ajax_scan.php?check_status=1&get_sync=1')
    .then(r => r.json())
    .then(data => {
        // Если на сервере время скана новее, чем то, что мы запомнили при загрузке
        if (data.global_sync && data.global_sync !== lastKnownSyncTime) {
            showUpdateNotify();
        }
    })
    .catch(err => console.log("Ошибка проверки обновлений"));
}

function showUpdateNotify() {
    // Если плашка уже висит — ничего не делаем
    if (document.getElementById('sync-notify')) return;

    const notify = document.createElement('div');
    notify.id = 'sync-notify';
    notify.innerHTML = `
        <div onclick="location.reload()" style="
            position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%);
            background: #4F46E5; color: white; padding: 14px 28px;
            border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.3);
            z-index: 9999; cursor: pointer; display: flex; align-items: center;
            gap: 12px; font-weight: 500; animation: slideUp 0.4s ease-out;
        ">
            <span class="material-icons-round" style="animation: spin 2s infinite linear;">refresh</span>
            Данные обновились. Нажмите, чтобы увидеть изменения
        </div>
    `;
    document.body.appendChild(notify);
}

// Запускаем проверку каждые 30 секунд
setInterval(checkGlobalUpdate, 10000);