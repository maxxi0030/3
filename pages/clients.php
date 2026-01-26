<?php

/* ---------- ОБРАБОТКА POST-ЗАПРОСОВ ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    switch ($_POST['action']) {
        
        case 'add_client':
            $name  = trim($_POST['name']);
            $phone = trim($_POST['phone']);
            $email = trim($_POST['email']);

            if ($name !== '') {
                $check = $pdo->prepare("
                    SELECT 1 FROM clients WHERE LOWER(name)=LOWER(:name)
                ");
                $check->execute([':name' => $name]);

                if (!$check->fetch()) {
                    $stmt = $pdo->prepare("
                        INSERT INTO clients (name, phone, email)
                        VALUES (:name, :phone, :email)
                    ");
                    $stmt->execute([
                        ':name'  => $name,
                        ':phone' => $phone,
                        ':email' => $email
                    ]);
                    $message = "Клиент добавлен.";
                }
            }
            break;

        case 'update_client':
            $stmt = $pdo->prepare("
                UPDATE clients SET
                    name = :name,
                    phone = :phone,
                    email = :email,
                    updated_at = NOW()
                WHERE id = :id
            ");

            $stmt->execute([
                ':id'    => (int)$_POST['id'],
                ':name'  => trim($_POST['name']),
                ':phone' => trim($_POST['phone']),
                ':email' => trim($_POST['email'])
            ]);
            $message = "Клиент обновлен.";
            break;

        case 'delete_client':
            $id_to_delete = (int)$_POST['id'];

            $stmt = $pdo->prepare("DELETE FROM clients WHERE id = ?");
            $stmt->execute([$id_to_delete]);
            $message = "Клиент удален.";
            break;
    }

    // Редирект после POST (Post-Redirect-Get паттерн)
    // header('Location: ' . $_SERVER['PHP_SELF']);
    // exit;
}


/* ---------- Список клиентов ---------- */
$stmt = $pdo->query("
    SELECT c.*,
        COUNT(f.id) AS files_count
    FROM clients c
    LEFT JOIN files f ON f.client_id = c.id
    GROUP BY c.id
    ORDER BY c.name
");

$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>


<header class="top-bar">
    <div class="top-bar-left">
        <h1>Клиенты</h1>
        <div class="subtitle">
            <span>Всего: <b><?= count($clients) ?></b></span>
        </div>
    </div>
    
    <button class="add-client-trigger">
        <span class="material-icons-round">add</span>
        Добавить
    </button>
</header>

<div class="card">
    <!-- Форма добавления -->
    <form method="POST" class="client-add-form">
        <div class="client-add-form-inner">
            <input type="hidden" name="action" value="add_client">
            <input type="text" name="name" placeholder="Название клиента" required>
            <input type="text" name="phone" placeholder="Телефон">
            <input type="email" name="email" placeholder="Email">
            <button type="submit">Добавить</button>
        </div>
    </form>

    <?php if (empty($clients)): ?>
        <!-- Пустое состояние -->
        <div class="empty-state">
            <div class="empty-state-icon">
                <span class="material-icons-round">people_outline</span>
            </div>
            <h3>Нет клиентов</h3>
            <p>Добавьте первого клиента, чтобы начать работу</p>
        </div>
    <?php else: ?>
        <table class="clients-table">
            <thead>
                <tr>
                    <th>Название</th>
                    <th>Телефон</th>
                    <th>Email</th>
                    <th>Файлов</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($clients as $client): ?>
                <tr data-client-id="<?= $client['id'] ?>">
                    <td contenteditable="false" data-field="name"><?= htmlspecialchars($client['name']) ?></td>
                    <td contenteditable="false" data-field="phone"><?= htmlspecialchars($client['phone']) ?></td>
                    <td contenteditable="false" data-field="email"><?= htmlspecialchars($client['email']) ?></td>
                    <td data-field="files"><?= $client['files_count'] ?></td>
                    <td>
                        <button class="btn-icon edit-btn" data-id="<?= $client['id'] ?>">
                            <span class="material-icons-round">edit</span>
                        </button>
                        <button class="btn-icon save-btn" data-id="<?= $client['id'] ?>" style="display:none">
                            <span class="material-icons-round">save</span>
                        </button>
                        
                        <form method="POST" style="display:inline" onsubmit="return confirm('Удалить клиента?')">
                            <input type="hidden" name="action" value="delete_client">
                            <input type="hidden" name="id" value="<?= $client['id'] ?>">
                            <button type="submit" class="btn-icon">
                                <span class="material-icons-round">delete</span>
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>






