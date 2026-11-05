<?php
require_once 'authorization.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    verify_csrf();
    $leadId = (int)($_POST['lead_id'] ?? 0);
    $newStatus = (string)($_POST['status'] ?? '');
    if (in_array($newStatus, ['new', 'contacted', 'archived'], true)) {
        $upd = $pdo->prepare("UPDATE platform_leads SET status = ? WHERE lead_id = ?");
        $upd->execute([$newStatus, $leadId]);
    }
    header('Location: leads.php');
    exit;
}

$leads = $pdo->query("SELECT * FROM platform_leads ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

function h($str): string
{
    return htmlspecialchars((string)($str ?? ''), ENT_QUOTES, 'UTF-8');
}

$statusLabels = [
    'new' => ['label' => 'Новая', 'class' => 'badge-new'],
    'contacted' => ['label' => 'Связались', 'class' => 'badge-contacted'],
    'archived' => ['label' => 'В архиве', 'class' => 'badge-archived'],
];
?>
<!DOCTYPE html>
<html lang="ru" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Заявки с сайта — Super Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="icon" href="/img/homepage/logo.png">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #0a192f;
            --secondary: #112240;
            --card: #1e293b;
            --accent: #64ffda;
            --text: #f8fafc;
            --text-sec: #94a3b8;
            --border: #334155;
            --success: #22c55e;
            --warning: #f59e0b;
            --danger: #ef4444;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--primary);
            color: var(--text);
            min-height: 100vh;
        }
        .header {
            background: var(--secondary);
            padding: 20px 30px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        .header h1 {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 1.4rem;
            color: var(--accent);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .back-link {
            color: var(--text-sec);
            text-decoration: none;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .back-link:hover { color: var(--accent); }
        .container { max-width: 1200px; margin: 0 auto; padding: 24px; }
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
        }
        table { width: 100%; border-collapse: collapse; }
        thead th {
            text-align: left;
            padding: 14px 18px;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--text-sec);
            border-bottom: 1px solid var(--border);
        }
        tbody td {
            padding: 16px 18px;
            border-bottom: 1px solid var(--border);
            font-size: 0.88rem;
            vertical-align: top;
        }
        tbody tr:last-child td { border-bottom: none; }
        .lead-name { font-weight: 700; }
        .lead-phone { color: var(--accent); font-weight: 600; white-space: nowrap; }
        .lead-message { color: var(--text-sec); max-width: 320px; }
        .lead-date { color: var(--text-sec); font-size: 0.8rem; white-space: nowrap; }
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 0.72rem;
            font-weight: 700;
        }
        .badge-new { background: rgba(100, 255, 218, 0.14); color: var(--accent); }
        .badge-contacted { background: rgba(34, 197, 94, 0.14); color: var(--success); }
        .badge-archived { background: rgba(148, 163, 184, 0.14); color: var(--text-sec); }
        .status-form { display: flex; gap: 6px; flex-wrap: wrap; }
        .status-btn {
            border: 1px solid var(--border);
            background: transparent;
            color: var(--text-sec);
            font-size: 0.72rem;
            font-weight: 600;
            padding: 5px 9px;
            border-radius: 7px;
            cursor: pointer;
        }
        .status-btn:hover { border-color: var(--accent); color: var(--accent); }
        .empty-state {
            padding: 60px 24px;
            text-align: center;
            color: var(--text-sec);
        }
        @media (max-width: 760px) {
            .container { padding: 16px; }
            table, thead, tbody, th, td, tr { display: block; }
            thead { display: none; }
            tbody tr {
                border-bottom: 1px solid var(--border);
                padding: 14px 0;
            }
            tbody td {
                border-bottom: none;
                padding: 4px 18px;
            }
            .lead-message { max-width: none; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><i class="fas fa-inbox"></i> Заявки с сайта</h1>
        <a href="index.php" class="back-link"><i class="fas fa-arrow-left"></i> К панели</a>
    </div>

    <div class="container">
        <div class="card">
            <?php if (empty($leads)): ?>
                <div class="empty-state">Заявок пока нет.</div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Имя / Центр</th>
                            <th>Телефон</th>
                            <th>Комментарий</th>
                            <th>Статус</th>
                            <th>Дата</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($leads as $lead): ?>
                            <?php $st = $statusLabels[$lead['status']] ?? $statusLabels['new']; ?>
                            <tr>
                                <td>
                                    <div class="lead-name"><?= h($lead['name']) ?></div>
                                    <?php if (!empty($lead['center_name'])): ?>
                                        <div class="lead-message"><?= h($lead['center_name']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="lead-phone"><?= h($lead['phone']) ?></td>
                                <td class="lead-message"><?= $lead['message'] ? h($lead['message']) : '—' ?></td>
                                <td><span class="badge <?= $st['class'] ?>"><?= $st['label'] ?></span></td>
                                <td class="lead-date"><?= date('d.m.Y H:i', strtotime($lead['created_at'])) ?></td>
                                <td>
                                    <form method="post" class="status-form">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="lead_id" value="<?= (int)$lead['lead_id'] ?>">
                                        <?php if ($lead['status'] !== 'contacted'): ?>
                                            <button type="submit" name="status" value="contacted" class="status-btn">Связались</button>
                                        <?php endif; ?>
                                        <?php if ($lead['status'] !== 'archived'): ?>
                                            <button type="submit" name="status" value="archived" class="status-btn">В архив</button>
                                        <?php endif; ?>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
