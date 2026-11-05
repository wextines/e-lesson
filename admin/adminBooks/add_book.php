<?php
require_once __DIR__ . '/../authorization.php';
require_once __DIR__ . '/../../php/config.php';
require_once __DIR__ . '/../../php/tenant_file.php';
require_once __DIR__ . '/../includes/lang.php';

$tid = Tenant::getId();
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $class = trim($_POST['class'] ?? '');

    $preview_path = '';
    $book_path = '';

    try {
        $upload_errors = [];

        if (isset($_FILES['preview']) && $_FILES['preview']['error'] === UPLOAD_ERR_OK) {
            $previewResult = TenantFile::handleUpload(
                $_FILES['preview'],
                $tid,
                'books'
            );
            if ($previewResult) {
                $preview_path = '/' . $previewResult['relativePath'];
            } else {
                $upload_errors[] = "Ошибка загрузки превью";
            }
        } else {
            $upload_errors[] = "Ошибка загрузки превью";
        }

        if (isset($_FILES['book']) && $_FILES['book']['error'] === UPLOAD_ERR_OK) {
            $bookResult = TenantFile::handleUpload(
                $_FILES['book'],
                $tid,
                'books'
            );
            if ($bookResult) {
                $book_path = '/' . $bookResult['relativePath'];
            } else {
                $upload_errors[] = "Книга должна быть в формате PDF";
            }
        } else {
            $upload_errors[] = "Ошибка загрузки файла книги";
        }

        if (!empty($upload_errors)) {
            throw new Exception(implode("<br>", $upload_errors));
        }

        $stmt = $pdo->prepare("INSERT INTO books (tenant_id, title, description, class, preview_path, book_path) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $tid,
            $title,
            $description,
            $class,
            $preview_path,
            $book_path
        ]);

        header("Location: index.php?success=1");
        exit;
    } catch (Exception $e) {
        error_log("add_book error: " . $e->getMessage());
        $error_message = $e->getMessage() ?: "Произошла ошибка. Попробуйте позже.";
    }
}

$pageTitle = __('add_book_btn');
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
            Добавить новую книгу
        </h1>
    </header>

    <?php if (!empty($error_message)): ?>
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800 shadow-sm">
            <?= $error_message ?>
        </div>
    <?php endif; ?>

    <div class="rounded-2xl border border-slate-200/80 bg-white p-6 shadow-[0_2px_8px_-2px_rgba(15,23,42,0.08),0_1px_4px_-1px_rgba(15,23,42,0.06)]">
        <form action="" method="post" enctype="multipart/form-data" class="flex flex-col gap-5">
            <?= csrf_field() ?>
            <div>
                <label for="title" class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1.5">Название книги *</label>
                <input type="text" id="title" name="title" required class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 focus:border-blue-500 focus:outline-none" style="border: 1px solid #e2e8f0;">
            </div>

            <div>
                <label for="description" class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1.5">Описание</label>
                <textarea id="description" name="description" rows="3" class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 focus:border-blue-500 focus:outline-none" style="border: 1px solid #e2e8f0;"></textarea>
            </div>

            <div>
                <label for="class" class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1.5">Класс *</label>
                <select name="class" id="class" required class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 focus:border-blue-500 focus:outline-none cursor-pointer" style="border: 1px solid #e2e8f0;">
                    <option value="1-2">1-2</option>
                    <option value="3-4">3-4</option>
                    <option value="5-6">5-6</option>
                    <option value="7-8">7-8</option>
                    <option value="9-11">9-11</option>
                </select>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="preview" class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1.5">Превью (JPG/PNG) *</label>
                    <input type="file" id="preview" name="preview" accept="image/jpeg,image/png" required class="w-full text-xs">
                    <p class="mt-1 text-[11px] text-slate-400">Рекомендуемый размер: 600x400 пикселей</p>
                </div>

                <div>
                    <label for="book" class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1.5">Файл книги (PDF) *</label>
                    <input type="file" id="book" name="book" accept=".pdf" required class="w-full text-xs">
                </div>
            </div>

            <div class="flex items-center gap-3 mt-4 pt-4 border-t border-slate-100">
                <button type="submit" style="border: none;" class="inline-flex items-center rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-medium text-white shadow-sm hover:opacity-90 transition-opacity cursor-pointer">
                    Добавить книгу
                </button>
                <a href="index.php" style="border: 1px solid #e2e8f0; background: #ffffff;" class="inline-flex items-center rounded-xl px-5 py-2.5 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50 transition-colors no-underline">
                    Отмена
                </a>
            </div>
        </form>
    </div>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
