<?php
require_once __DIR__ . '/../authorization.php';
require_once __DIR__ . '/../../php/config.php';
require_once __DIR__ . '/../includes/lang.php';

$tid = Tenant::getId();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_book_id'])) {
    verify_csrf();
    $book_id = (int)$_POST['delete_book_id'];

    try {
        $stmt = $pdo->prepare("SELECT preview_path, book_path FROM books WHERE book_id = ? AND tenant_id = ?");
        $stmt->execute([$book_id, $tid]);
        $book = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($book) {
            if (!empty($book['preview_path']) && file_exists($_SERVER['DOCUMENT_ROOT'] . $book['preview_path'])) {
                unlink($_SERVER['DOCUMENT_ROOT'] . $book['preview_path']);
            }
            if (!empty($book['book_path']) && file_exists($_SERVER['DOCUMENT_ROOT'] . $book['book_path'])) {
                unlink($_SERVER['DOCUMENT_ROOT'] . $book['book_path']);
            }

            $stmt = $pdo->prepare("DELETE FROM books WHERE book_id = ? AND tenant_id = ?");
            $stmt->execute([$book_id, $tid]);

            header("Location: index.php?deleted=1");
            exit;
        }
    } catch (PDOException $e) {
        error_log("[AdminBooks] Error deleting book: " . $e->getMessage());
        $error = __('error_occurred');
    }
}

// Class filtering
$filterClass = trim($_GET['class'] ?? 'all');
$validClasses = ['1-2', '3-4', '5-6', '7-8', '9-11'];

$where = "WHERE tenant_id = ?";
$params = [$tid];

if ($filterClass !== 'all' && in_array($filterClass, $validClasses)) {
    $where .= " AND class = ?";
    $params[] = $filterClass;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM books {$where} ORDER BY created_at DESC");
    $stmt->execute($params);
    $books = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Total books count
    $totalCountStmt = $pdo->prepare("SELECT COUNT(*) FROM books WHERE tenant_id = ?");
    $totalCountStmt->execute([$tid]);
    $totalBooksCount = (int)$totalCountStmt->fetchColumn();
} catch (PDOException $e) {
    error_log("[AdminBooks] Error fetching books: " . $e->getMessage());
    $books = [];
    $totalBooksCount = 0;
}

$pageTitle = __('books_title');
include __DIR__ . '/../includes/header.php';
?>

<div class="mx-auto flex max-w-6xl flex-col gap-6">

    <!-- Header Section -->
    <header class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-3xl font-bold tracking-tight text-slate-900 m-0">
                    <?= htmlspecialchars(__('books_title')) ?>
                </h1>
                <span class="inline-flex min-w-7 items-center justify-center rounded-full bg-blue-50 px-2.5 py-0.5 text-sm font-semibold text-blue-700">
                    <?= $totalBooksCount ?>
                </span>
            </div>
            <p class="mt-1.5 text-sm text-slate-500 m-0">
                <?= htmlspecialchars(__('books_subtitle')) ?>
            </p>
        </div>

        <div>
            <a href="add_book.php" style="border: none;" class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition-opacity hover:opacity-90 no-underline cursor-pointer">
                <i data-lucide="plus" class="size-4"></i>
                <?= htmlspecialchars(__('add_book_btn')) ?>
            </a>
        </div>
    </header>

    <!-- Flash message -->
    <?php if (isset($_GET['deleted']) && $_GET['deleted'] == 1): ?>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 shadow-sm">
            <?= htmlspecialchars(__('book_deleted_msg')) ?>
        </div>
    <?php endif; ?>

    <!-- Class Filter Pills -->
    <div class="flex flex-wrap gap-2">
        <a href="index.php" class="rounded-xl px-4 py-2 text-sm font-medium transition-colors no-underline <?= $filterClass === 'all' ? 'bg-blue-600 text-white shadow-sm' : 'border border-slate-200 bg-white text-slate-600 hover:text-slate-900 shadow-sm' ?>" style="<?= $filterClass === 'all' ? 'background-color:#2563eb; color:#fff;' : 'border:1px solid #e2e8f0; background-color:#fff;' ?>">
            <?= htmlspecialchars(__('all_classes')) ?>
        </a>
        <?php foreach ($validClasses as $c): ?>
            <a href="index.php?class=<?= urlencode($c) ?>" class="rounded-xl px-4 py-2 text-sm font-medium transition-colors no-underline <?= $filterClass === $c ? 'bg-blue-600 text-white shadow-sm' : 'border border-slate-200 bg-white text-slate-600 hover:text-slate-900 shadow-sm' ?>" style="<?= $filterClass === $c ? 'background-color:#2563eb; color:#fff;' : 'border:1px solid #e2e8f0; background-color:#fff;' ?>">
                <?= $c ?> <?= mb_strtolower(__('class_label') ?: 'класс') ?>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Books Card Grid -->
    <?php if (empty($books)): ?>
        <div class="rounded-2xl border border-slate-200/80 bg-white p-12 text-center shadow-[0_2px_8px_-2px_rgba(15,23,42,0.08),0_1px_4px_-1px_rgba(15,23,42,0.06)]">
            <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-slate-100 text-slate-400 mb-3">
                <i data-lucide="library" class="size-6"></i>
            </div>
            <p class="font-semibold text-slate-900 text-base m-0"><?= htmlspecialchars(__('no_books_found')) ?></p>
            <p class="mt-1 text-xs text-slate-400 m-0">Попробуйте выбрать другой класс или добавьте новую книгу.</p>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
            <?php foreach ($books as $book): ?>
                <?php 
                    $previewSrc = !empty($book['preview_path']) ? '/' . ltrim($book['preview_path'], '/') : '/file.php?type=book_preview&id=' . $book['book_id'];
                ?>
                <article class="group flex flex-col overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-[0_2px_8px_-2px_rgba(15,23,42,0.08),0_1px_4px_-1px_rgba(15,23,42,0.06)] transition-all hover:shadow-[0_4px_16px_-4px_rgba(15,23,42,0.12),0_2px_6px_-2px_rgba(15,23,42,0.08)]">
                    <!-- Image Container -->
                    <div class="relative aspect-[16/10] w-full overflow-hidden bg-slate-100">
                        <img src="<?= htmlspecialchars($previewSrc) ?>" alt="<?= htmlspecialchars($book['title']) ?>" class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-[1.03]" onerror="this.onerror=null; this.src='/file.php?type=book_preview&id=<?= $book['book_id'] ?>';">
                        <!-- Class Badge in top left -->
                        <div class="absolute left-3.5 top-3.5">
                            <span class="inline-flex items-center rounded-lg bg-blue-50/95 px-2.5 py-1 text-xs font-bold text-blue-700 shadow-sm backdrop-blur-sm border border-blue-200/50">
                                <?= htmlspecialchars($book['class']) ?> класс
                            </span>
                        </div>
                    </div>

                    <!-- Card Content -->
                    <div class="flex flex-1 flex-col p-5">
                        <h3 class="font-bold text-slate-900 text-base leading-snug m-0"><?= htmlspecialchars($book['title']) ?></h3>
                        <p class="mt-1.5 text-xs text-slate-500 line-clamp-2 m-0 flex-1 leading-relaxed"><?= htmlspecialchars($book['description'] ?? '') ?></p>
                        <p class="mt-3 text-[11px] text-slate-400 m-0"><?= htmlspecialchars(__('added_label')) ?> <?= date('d.m.Y', strtotime($book['created_at'])) ?></p>

                        <!-- Actions Bar -->
                        <div class="mt-4 flex items-center gap-2 border-t border-slate-100 pt-4">
                            <!-- Main Action: Read -->
                            <a href="/file.php?type=book&id=<?= $book['book_id'] ?>" target="_blank" style="border: none;" class="flex-1 inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-2 text-xs font-medium text-white shadow-sm hover:opacity-90 transition-opacity no-underline cursor-pointer">
                                <i data-lucide="book-open" class="size-4"></i>
                                <?= htmlspecialchars(__('read_btn')) ?>
                            </a>

                            <!-- Secondary: Edit -->
                            <a href="edit_book.php?book_id=<?= $book['book_id'] ?>" title="<?= htmlspecialchars(__('edit')) ?>" style="border: 1px solid #e2e8f0; background: #ffffff;" class="inline-flex size-8 shrink-0 items-center justify-center rounded-xl text-slate-600 shadow-sm hover:bg-slate-50 hover:text-slate-900 transition-colors no-underline">
                                <i data-lucide="pencil" class="size-3.5"></i>
                            </a>

                            <!-- Secondary: Delete -->
                            <form method="post" class="inline m-0" onsubmit="return confirm('<?= htmlspecialchars(__('delete_book_confirm')) ?>');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="delete_book_id" value="<?= $book['book_id'] ?>">
                                <button type="submit" title="<?= htmlspecialchars(__('delete_btn')) ?>" style="border: 1px solid #e2e8f0; background: #ffffff;" class="inline-flex size-8 shrink-0 items-center justify-center rounded-xl text-slate-400 shadow-sm hover:border-red-200 hover:bg-red-50 hover:text-red-600 transition-colors cursor-pointer outline-none">
                                    <i data-lucide="trash-2" class="size-3.5"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
