<?php
require $_SERVER['DOCUMENT_ROOT'].'/db.php';

/* ---------- Добавление клиента ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_client'])) {

    $name  = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);

    if ($name !== '') {
        $check = $db->prepare("
            SELECT 1 FROM clients WHERE LOWER(name)=LOWER(:name)
        ");
        $check->execute([':name' => $name]);

        if (!$check->fetch()) {
            $stmt = $db->prepare("
                INSERT INTO clients (name, phone, email)
                VALUES (:name, :phone, :email)
            ");
            $stmt->execute([
                ':name'  => $name,
                ':phone' => $phone,
                ':email' => $email
            ]);
        }
    }
}

/* ---------- Список клиентов ---------- */
$clients = $db->query("
    SELECT c.*,
           COUNT(f.id) AS files_count
    FROM clients c
    LEFT JOIN files f ON f.client_id = c.id
    GROUP BY c.id
    ORDER BY c.name
")->fetchAll(PDO::FETCH_ASSOC);
?>

<header class="top-bar">
    <h1>Клиенты</h1>
    <div class="stats">
        <span>Всего: <b><?= count($clients) ?></b></span>
    </div>
</header>

<div class="card">

    <form method="POST" class="client-add-form">
        <input type="text" name="name" placeholder="Название клиента" required>
        <input type="text" name="phone" placeholder="Телефон">
        <input type="email" name="email" placeholder="Email">
        <button type="submit" name="add_client">Добавить</button>
    </form>

    <table class="clients-table">
        <thead>
            <tr>
                <th>Название</th>
                <th>Телефон</th>
                <th>Email</th>
                <th>Файлов</th>
                <th></th>
            </tr>
        </thead>
        <tbody>

        <?php foreach ($clients as $client): ?>
            <tr>
                <td>
                    <input type="text"
                           value="<?= htmlspecialchars($client['name']) ?>"
                           data-client-name="<?= $client['id'] ?>">
                </td>
                <td><?= htmlspecialchars($client['phone']) ?></td>
                <td><?= htmlspecialchars($client['email']) ?></td>
                <td><?= $client['files_count'] ?></td>
                <td>
                    <button class="btn-icon"
                            data-delete-client="<?= $client['id'] ?>">
                        <span class="material-icons-round">delete</span>
                    </button>
                </td>
            </tr>
        <?php endforeach; ?>

        </tbody>
    </table>
</div>

<script>
document.querySelectorAll('[data-client-name]').forEach(input => {
    input.addEventListener('blur', () => {
        fetch('/ajax/clients.php', {
            method: 'POST',
            body: new URLSearchParams({
                action: 'rename',
                id: input.dataset.clientName,
                name: input.value
            })
        });
    });
});

document.querySelectorAll('[data-delete-client]').forEach(btn => {
    btn.addEventListener('click', () => {
        if (!confirm('Удалить клиента?')) return;

        fetch('/ajax/clients.php', {
            method: 'POST',
            body: new URLSearchParams({
                action: 'delete',
                id: btn.dataset.deleteClient
            })
        }).then(() => location.reload());
    });
});
</script>
