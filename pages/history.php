<header class="top-bar">
    <h1>История изменений</h1>
</header>

<div class="history-container">
    <?php
    $history_data = file_exists('history.json') ? json_decode(file_get_contents('history.json'), true) : [];
    $history_data = array_reverse($history_data); // Свежие события сверху
    
    if (empty($history_data)): ?>
        <div class="empty-state">
            <span class="material-icons-round">history</span>
            <p>Событий пока нет..</p>
        </div>
    <?php else: ?>
        <div class="timeline">
            <?php foreach ($history_data as $event): 
                $icon = 'add_circle';
                $class = 'event-added';
                if ($event['action'] === 'deleted') { $icon = 'delete'; $class = 'event-deleted'; }
                if ($event['action'] === 'moved') { $icon = 'input'; $class = 'event-moved'; }
            ?>
                <div class="timeline-item <?= $class ?>">
                    <div class="timeline-icon">
                        <span class="material-icons-round"><?= $icon ?></span>
                    </div>
                    <div class="timeline-content">
                        <div class="timeline-header">
                            <strong><?= htmlspecialchars($event['filename']) ?></strong>
                            <span class="timeline-time"><?= $event['date'] ?></span>
                        </div>
                        <p class="timeline-path"><?= htmlspecialchars($event['path']) ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>