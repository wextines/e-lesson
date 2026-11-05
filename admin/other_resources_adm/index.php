<?php
require_once '../authorization.php';
require_once '../../php/config.php';
require_once '../../php/tenant_file.php';

function translateResourceType($type)
{
    $translations = [
        'project_results' => 'Natijalar',
        'more_info' => 'Ko\'proq ma\'lumot'
    ];
    return $translations[$type] ?? $type;
}

$resource_type = isset($_GET['type']) && in_array($_GET['type'], ['project_results', 'more_info'])
    ? $_GET['type']
    : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_resource_id']) && !empty($_POST['delete_resource_id'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        die('CSRF token mismatch');
    }

    $resource_id = (int)$_POST['delete_resource_id'];

    try {
        $stmt = $pdo->prepare("SELECT resource_path FROM other_resources WHERE resource_id = ? AND tenant_id = ?");
        $stmt->execute([$resource_id, Tenant::getId()]);
        $resource = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($resource) {
            TenantFile::deleteFile(Tenant::getId(), $resource['resource_path']);

            $stmt = $pdo->prepare("DELETE FROM other_resources WHERE resource_id = ? AND tenant_id = ?");
            $stmt->execute([$resource_id, Tenant::getId()]);

            header("Location: index.php?type=" . urlencode($resource_type) . "&deleted=1");
            exit;
        }
    } catch (PDOException $e) {
        error_log("[OTHER_RESOURCES] Delete error: " . $e->getMessage());
        die("Ошибка при удалении ресурса.");
    }
}

try {
    if ($resource_type) {
        $stmt = $pdo->prepare("SELECT * FROM other_resources WHERE resource_type = ? AND tenant_id = ? ORDER BY created_at DESC");
        $stmt->execute([$resource_type, Tenant::getId()]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM other_resources WHERE tenant_id = ? ORDER BY created_at DESC");
        $stmt->execute([Tenant::getId()]);
    }
    $resources = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("[OtherResources] Error fetching resources: " . $e->getMessage());
    die("Произошла ошибка. Попробуйте позже.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['resource_file']) && $resource_type) {
    try {
        $stmt = $pdo->prepare("SELECT resource_id, resource_path FROM other_resources WHERE resource_type = ? AND tenant_id = ?");
        $stmt->execute([$resource_type, Tenant::getId()]);
        $old_resource = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($old_resource) {
            TenantFile::deleteFile(Tenant::getId(), $old_resource['resource_path']);
            $stmt = $pdo->prepare("DELETE FROM other_resources WHERE resource_id = ? AND tenant_id = ?");
            $stmt->execute([$old_resource['resource_id'], Tenant::getId()]);
        }
    } catch (PDOException $e) {
        error_log("[OtherResources] Error deleting old file: " . $e->getMessage());
        die("Произошла ошибка. Попробуйте позже.");
    }

    $uploadResult = TenantFile::handleUpload(
        $_FILES['resource_file'],
        Tenant::getId(),
        'resources'
    );

    if ($uploadResult) {
        try {
            $stmt = $pdo->prepare("INSERT INTO other_resources (tenant_id, resource_type, resource_path) VALUES (?, ?, ?)");
            $stmt->execute([Tenant::getId(), $resource_type, $uploadResult['relativePath']]);

            header("Location: index.php?type=" . urlencode($resource_type) . "&added=1");
            exit;
        } catch (PDOException $e) {
            TenantFile::deleteFile(Tenant::getId(), $uploadResult['relativePath']);
            error_log("[OtherResources] Error saving to database: " . $e->getMessage());
            die("Произошла ошибка. Попробуйте позже.");
        }
    } else {
        die("Ошибка при загрузке файла");
    }
}

$pageTitle = $resource_type ? translateResourceType($resource_type) : 'Ресурсы';
include __DIR__ . '/../includes/header.php';
?>

<h1 class="page-title"><?= $resource_type ? translateResourceType($resource_type) : 'Все ресурсы' ?></h1>
<a href="../" class="back-link">← Назад</a>

<div class="filter-bar">
    <a href="index.php" class="btn <?= !$resource_type ? 'btn-primary' : 'btn-outline' ?> btn-sm">Все</a>
    <a href="index.php?type=project_results" class="btn <?= $resource_type === 'project_results' ? 'btn-primary' : 'btn-outline' ?> btn-sm">Natijalar</a>
    <a href="index.php?type=more_info" class="btn <?= $resource_type === 'more_info' ? 'btn-primary' : 'btn-outline' ?> btn-sm">Ko'proq ma'lumot</a>
</div>

<?php if (isset($_GET['deleted']) && $_GET['deleted'] == 1): ?>
    <div class="msg msg-ok">Ресурс успешно удален!</div>
<?php endif; ?>

<?php if (isset($_GET['added']) && $_GET['added'] == 1): ?>
    <div class="msg msg-ok">Ресурс успешно добавлен!</div>
<?php endif; ?>

<?php if ($resource_type): ?>
    <div class="card">
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="resource_file">Добавить новый файл:</label>
                <input type="file" name="resource_file" id="resource_file" required>
            </div>
            <button type="submit" class="btn btn-primary">Загрузить</button>
        </form>
    </div>
<?php endif; ?>

<?php if (empty($resources)): ?>
    <div class="card" style="text-align:center;padding:50px 20px">
        <h2 style="color:var(--gray-500)">Ресурсы не найдены</h2>
        <p style="color:var(--gray-400)"><?= $resource_type ? 'Выберите файл для загрузки' : 'Выберите тип ресурсов для просмотра' ?></p>
    </div>
<?php else: ?>
    <div class="kpi-grid" style="grid-template-columns:repeat(auto-fill,minmax(280px,1fr))">
        <?php foreach ($resources as $resource): ?>
            <div class="card" style="margin-bottom:0">
                <span class="badge badge-blue" style="text-transform:capitalize;margin-bottom:10px"><?= translateResourceType($resource['resource_type']) ?></span>
                <h3 style="font-size:1.1rem;font-weight:600;margin:0 0 10px;color:var(--gray-900);word-break:break-all"><?= htmlspecialchars(basename($resource['resource_path'])) ?></h3>

                <div style="font-size:12px;color:var(--gray-400);margin:10px 0">
                    <small>Добавлено: <?= date('d.m.Y H:i', strtotime($resource['created_at'])) ?></small>
                </div>

                <div class="btn-group" style="margin-top:14px">
                    <a href="/file.php?type=resource&id=<?= $resource['resource_id'] ?>" class="btn btn-primary btn-sm" target="_blank">Открыть</a>
                    <form method="post" style="display:inline" onsubmit="return confirm('Вы уверены, что хотите удалить этот ресурс?');">
                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                        <input type="hidden" name="delete_resource_id" value="<?= $resource['resource_id'] ?>">
                        <button type="submit" class="btn btn-danger btn-sm">Удалить</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
