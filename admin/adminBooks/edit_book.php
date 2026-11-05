<?php
require_once __DIR__ . '/../authorization.php';
require_once __DIR__ . '/../../php/config.php';
require_once __DIR__ . '/../../php/tenant_file.php';
require_once __DIR__ . '/../includes/lang.php';

$tid = Tenant::getId();
$book_id = isset($_GET['book_id']) ? (int)$_GET['book_id'] : 0;
$error_message = '';

try {
    $stmt = $pdo->prepare("SELECT * FROM books WHERE book_id = ? AND tenant_id = ?");
    $stmt->execute([$book_id, $tid]);
    $book = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$book) {
        header('Location: index.php');
        exit;
    }
} catch (PDOException $e) {
    error_log("[AdminBooks] Error fetching book data: " . $e->getMessage());
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $class = trim($_POST['class'] ?? '');
    
    try {
        if (isset($_FILES['preview']) && $_FILES['preview']['error'] === UPLOAD_ERR_OK) {
            if (!empty($book['preview_path'])) {
                TenantFile::deleteFile($tid, ltrim($book['preview_path'], '/'));
            }
            
            $previewResult = TenantFile::handleUpload(
                $_FILES['preview'],
                $tid,
                'books'
            );
            if ($previewResult) {
                $preview_path = '/' . $previewResult['relativePath'];
            }
        } else {
            $preview_path = $book['preview_path'];
        }
        
        if (isset($_FILES['book']) && $_FILES['book']['error'] === UPLOAD_ERR_OK) {
            if (!empty($book['book_path'])) {
                TenantFile::deleteFile($tid, ltrim($book['book_path'], '/'));
            }
            
            $bookResult = TenantFile::handleUpload(
                $_FILES['book'],
                $tid,
                'books'
            );
            if ($bookResult) {
                $book_path = '/' . $bookResult['relativePath'];
            }
        } else {
            $book_path = $book['book_path'];
        }
        
        $stmt = $pdo->prepare("UPDATE books SET title = :title, description = :description, class = :class, preview_path = :preview_path, book_path = :book_path WHERE book_id = :book_id AND tenant_id = :tenant_id");
        $stmt->execute([
            ':title' => $title,
            ':description' => $description,
            ':class' => $class,
            ':preview_path' => $preview_path,
            ':book_path' => $book_path,
            ':book_id' => $book_id,
            ':tenant_id' => $tid
        ]);
        
        header("Location: index.php?updated=1");
        exit;
    } catch (PDOException $e) {
        error_log("edit_book error: " . $e->getMessage());
        $error_message = "Произошла ошибка. Попробуйте позже.";
    }
}

$pageTitle = 'Редактировать книгу';
include __DIR__ . '/../includes/header.php';
?>

<div class="mx-auto flex max-w-4xl flex-col gap-6">

    <div>
        <a href="index.php" class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 no-underline transition-colors hover:text-slate-900">
            <i data-lucide="arrow-left" class="size-4"></i>
            Назад к списку
        </a>
    </div>

    <header>
        <h1 class="text-3xl font-bold tracking-tight text-slate-900 m-0">
            Редактировать книгу
        </h1>
    </header>

    <?php if (!empty($error_message)): ?>
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800 shadow-sm">
            <?= htmlspecialchars($error_message) ?>
        </div>
    <?php endif; ?>

    <div class="rounded-2xl border border-slate-200/80 bg-white p-6 shadow-[0_2px_8px_-2px_rgba(15,23,42,0.08),0_1px_4px_-1px_rgba(15,23,42,0.06)]">
        <form action="" method="post" enctype="multipart/form-data" class="flex flex-col gap-5">
            <?= csrf_field() ?>
            <div>
                <label for="title" class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1.5">Название книги *</label>
                <input type="text" id="title" name="title" value="<?= htmlspecialchars($book['title']) ?>" required class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 focus:border-blue-500 focus:outline-none" style="border: 1px solid #e2e8f0;">
            </div>

            <div>
                <label for="description" class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1.5">Описание</label>
                <textarea id="description" name="description" rows="3" class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 focus:border-blue-500 focus:outline-none" style="border: 1px solid #e2e8f0;"><?= htmlspecialchars($book['description']) ?></textarea>
            </div>

            <div>
                <label for="class" class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1.5">Класс *</label>
                <select name="class" id="class" required class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 focus:border-blue-500 focus:outline-none cursor-pointer" style="border: 1px solid #e2e8f0;">
                    <?php foreach (['1-2', '3-4', '5-6', '7-8', '9-11'] as $c): ?>
                        <option value="<?= $c ?>" <?= $book['class'] === $c ? 'selected' : '' ?>><?= $c ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="preview" class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1.5">Превью (оставьте пустым, чтобы сохранить)</label>
                    <input type="file" id="preview" name="preview" accept="image/jpeg,image/png" class="w-full text-xs">
                    <?php if (!empty($book['preview_path'])): ?>
                        <div class="mt-2 flex items-center gap-2">
                            <img src="/file.php?type=book_preview&id=<?= $book['book_id'] ?>" alt="Превью" class="size-10 rounded-lg object-cover border border-slate-200">
                            <span class="text-xs text-slate-400">Текущее превью</span>
                        </div>
                    <?php endif; ?>
                </div>

                <div>
                    <label for="book" class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1.5">Файл книги PDF (оставьте пустым, чтобы сохранить)</label>
                    <input type="file" id="book" name="book" accept=".pdf" class="w-full text-xs">
                    <?php if (!empty($book['book_path'])): ?>
                        <div class="mt-2">
                            <a href="/file.php?type=book&id=<?= $book['book_id'] ?>" target="_blank" class="text-xs text-blue-600 hover:underline">Просмотреть текущий PDF</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="flex items-center gap-3 mt-4 pt-4 border-t border-slate-100">
                <button type="submit" style="border: none;" class="inline-flex items-center rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-medium text-white shadow-sm hover:opacity-90 transition-opacity cursor-pointer">
                    Сохранить изменения
                </button>
                <a href="index.php" style="border: 1px solid #e2e8f0; background: #ffffff;" class="inline-flex items-center rounded-xl px-5 py-2.5 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50 transition-colors no-underline">
                    Отмена
                </a>
            </div>
        </form>
    </div>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
