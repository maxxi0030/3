document.getElementById('toggleSidebar').addEventListener('click', () => {
    document.getElementById('sidebar').classList.toggle('collapsed');
});



// сейчас эта кнопка обрабытывается пхп
// document.getElementById('closePanelBtn')?.addEventListener('click', () => {
//     document.getElementById('fileInfoPanel').classList.add('hidden');
// });


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