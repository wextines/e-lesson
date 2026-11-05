<?php
require_once __DIR__ . '/../authorization.php';
require_once __DIR__ . '/../../php/config.php';
require_once __DIR__ . '/../../php/student_service.php';
require_once __DIR__ . '/../../php/student_importer.php';
require_once __DIR__ . '/../includes/lang.php';

$tid = Tenant::getId();
$action = $_GET['action'] ?? '';

// ── Template download (no HTML wrapper) ──────────────────────────────
if ($action === 'download_template') {
    $csv = StudentImporter::generateTemplateCsv();
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="students_template.csv"');
    header('Content-Length: ' . strlen($csv));
    echo $csv;
    exit;
}

// ── Credentials handout download for the batch just imported ────────
if ($action === 'download_credentials') {
    $created = $_SESSION['import_credentials'] ?? [];
    if (empty($created)) {
        header('Location: /admin/students/import.php');
        exit;
    }
    $csv = StudentImporter::generateCredentialsCsv($created);
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="students_logins_' . date('Y-m-d') . '.csv"');
    header('Content-Length: ' . strlen($csv));
    echo $csv;
    exit;
}

$pageTitle = 'Импорт учеников';

$step = 'upload';
$uploadError = '';
$previewRows = [];
$validCount = 0;
$invalidCount = 0;
$importSummary = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $formAction = $_POST['form_action'] ?? '';

    if ($formAction === 'preview') {
        // A fresh upload invalidates any leftover credentials handout from a previous batch.
        unset($_SESSION['import_credentials']);

        if (empty($_FILES['import_file']['name']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            $uploadError = 'Пожалуйста, выберите файл .csv или .txt.';
        } else {
            $ext = strtolower(pathinfo($_FILES['import_file']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['csv', 'txt'], true)) {
                $uploadError = 'Поддерживаются только файлы .csv и .txt.';
            } elseif ($_FILES['import_file']['size'] > 2 * 1024 * 1024) {
                $uploadError = 'Файл слишком большой (максимум 2 МБ).';
            } else {
                try {
                    $rows = StudentImporter::parseCsv($_FILES['import_file']['tmp_name']);
                } catch (Throwable $e) {
                    $rows = null;
                    $uploadError = 'Не удалось прочитать файл: ' . $e->getMessage();
                }

                if ($rows !== null) {
                    if (empty($rows)) {
                        $uploadError = 'Файл пуст или не удалось распознать колонки (Имя, Фамилия, Телефон, Класс, Email).';
                    } elseif (count($rows) > 3000) {
                        $uploadError = 'Слишком много строк (' . count($rows) . '). Разделите файл на части до 3000 строк.';
                    } else {
                        $validated = [];
                        foreach ($rows as $row) {
                            $v = StudentImporter::validateRow($pdo, $tid, $row);
                            $validated[] = $row + ['_valid' => $v['valid'], '_reason' => $v['reason']];
                        }
                        $_SESSION['import_rows'] = $rows;
                        $step = 'preview';
                        $previewRows = $validated;
                        $validCount = count(array_filter($previewRows, fn($r) => $r['_valid']));
                        $invalidCount = count($previewRows) - $validCount;
                    }
                }
            }
        }
    } elseif ($formAction === 'confirm_import') {
        $rows = $_SESSION['import_rows'] ?? null;
        if (!$rows) {
            $uploadError = 'Сессия импорта устарела. Загрузите файл ещё раз.';
        } else {
            $importSummary = StudentImporter::importRows($pdo, $tid, $rows);
            unset($_SESSION['import_rows']);
            if (!empty($importSummary['created'])) {
                $_SESSION['import_credentials'] = $importSummary['created'];
            }
            $step = 'done';
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="mx-auto flex max-w-4xl flex-col gap-6">

    <!-- Page Header -->
    <header class="flex items-center gap-3">
        <a href="index.php" class="flex size-9 items-center justify-center rounded-lg text-slate-400 no-underline transition-colors hover:bg-slate-100 hover:text-slate-700" title="Назад к списку учеников">
            <i data-lucide="arrow-left" class="size-5"></i>
        </a>
        <h1 class="text-2xl sm:text-3xl font-bold text-slate-900 tracking-tight">Импорт учеников из Excel/CSV</h1>
    </header>

    <?php if ($step === 'upload'): ?>

        <!-- Block 1: Upload -->
        <div class="rounded-2xl border border-slate-200/80 bg-white p-6 shadow-[0_2px_8px_-2px_rgba(15,23,42,0.08),0_1px_4px_-1px_rgba(15,23,42,0.06)]">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-4">
                <h2 class="text-lg font-bold text-slate-900 m-0">Загрузка файла</h2>
                <a href="?action=download_template" class="inline-flex items-center gap-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2 text-sm font-medium transition-colors no-underline">
                    <i data-lucide="download" class="size-4"></i>
                    Скачать образец таблицы (.csv)
                </a>
            </div>

            <?php if ($uploadError): ?>
                <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800"><?= htmlspecialchars($uploadError) ?></div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data" class="m-0">
                <?= csrf_field() ?>
                <input type="hidden" name="form_action" value="preview">

                <label id="dropZone" for="import_file" class="flex flex-col items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center cursor-pointer transition-colors hover:border-blue-400 hover:bg-blue-50/40">
                    <i data-lucide="upload-cloud" class="size-8 text-slate-400"></i>
                    <p class="text-sm font-medium text-slate-700 m-0">Перетащите файл сюда или нажмите, чтобы выбрать</p>
                    <p class="text-xs text-slate-400 m-0">Поддерживаются .csv и .txt, до 2 МБ</p>
                    <span id="fileNameLabel" class="hidden text-xs font-semibold text-blue-600"></span>
                </label>
                <input type="file" id="import_file" name="import_file" accept=".csv,.txt,text/csv,text/plain" class="hidden" required>

                <div class="flex justify-end mt-4">
                    <button type="submit" style="border:none;" class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition-opacity hover:opacity-90 cursor-pointer">
                        Проверить файл
                    </button>
                </div>
            </form>
        </div>

        <script>
        (function () {
            var dropZone = document.getElementById('dropZone');
            var input = document.getElementById('import_file');
            var label = document.getElementById('fileNameLabel');
            if (!dropZone || !input) return;

            function showName(name) {
                if (!name) return;
                label.textContent = name;
                label.classList.remove('hidden');
            }

            input.addEventListener('change', function () {
                if (input.files && input.files[0]) showName(input.files[0].name);
            });

            ['dragover', 'dragleave', 'drop'].forEach(function (evt) {
                dropZone.addEventListener(evt, function (e) { e.preventDefault(); e.stopPropagation(); });
            });
            dropZone.addEventListener('dragover', function () {
                dropZone.classList.add('border-blue-400', 'bg-blue-50/40');
            });
            dropZone.addEventListener('dragleave', function () {
                dropZone.classList.remove('border-blue-400', 'bg-blue-50/40');
            });
            dropZone.addEventListener('drop', function (e) {
                dropZone.classList.remove('border-blue-400', 'bg-blue-50/40');
                if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0]) {
                    input.files = e.dataTransfer.files;
                    showName(e.dataTransfer.files[0].name);
                }
            });
        })();
        </script>

    <?php elseif ($step === 'preview'): ?>

        <!-- Block 2: Preview -->
        <div class="rounded-2xl border border-slate-200/80 bg-white p-6 shadow-[0_2px_8px_-2px_rgba(15,23,42,0.08),0_1px_4px_-1px_rgba(15,23,42,0.06)]">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-2">
                <h2 class="text-lg font-bold text-slate-900 m-0">Предпросмотр импорта</h2>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center rounded-md bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700"><?= $validCount ?> готово</span>
                    <?php if ($invalidCount > 0): ?>
                        <span class="inline-flex items-center rounded-md bg-red-100 px-2.5 py-1 text-xs font-semibold text-red-700"><?= $invalidCount ?> с ошибками</span>
                    <?php endif; ?>
                </div>
            </div>
            <p class="text-sm text-slate-500 mb-4">
                Показаны первые 10 из <?= count($previewRows) ?> строк файла. Строки с ошибками не будут импортированы.
            </p>

            <div class="overflow-x-auto rounded-xl border border-slate-200">
                <table class="w-full border-collapse text-left text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-400">
                            <th class="px-4 py-3">Имя</th>
                            <th class="px-4 py-3">Фамилия</th>
                            <th class="px-4 py-3">Телефон</th>
                            <th class="px-4 py-3">Класс</th>
                            <th class="px-4 py-3">Email</th>
                            <th class="px-4 py-3">Статус</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($previewRows, 0, 10) as $r): ?>
                            <tr class="border-b border-slate-100 last:border-0">
                                <td class="px-4 py-3 text-slate-900"><?= htmlspecialchars($r['first_name'] ?? '') ?></td>
                                <td class="px-4 py-3 text-slate-900"><?= htmlspecialchars($r['last_name'] ?? '') ?></td>
                                <td class="px-4 py-3 text-slate-500"><?= ($r['phone'] ?? '') !== '' ? htmlspecialchars($r['phone']) : '—' ?></td>
                                <td class="px-4 py-3 text-slate-500"><?= ($r['class'] ?? '') !== '' ? htmlspecialchars($r['class']) : '—' ?></td>
                                <td class="px-4 py-3 text-slate-500"><?= ($r['email'] ?? '') !== '' ? htmlspecialchars($r['email']) : 'будет создан автоматически' ?></td>
                                <td class="px-4 py-3">
                                    <?php if ($r['_valid']): ?>
                                        <span class="inline-flex items-center rounded-md bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">Готов</span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center rounded-md bg-red-100 px-2.5 py-1 text-xs font-semibold text-red-700" title="<?= htmlspecialchars($r['_reason'] ?? '') ?>">Ошибка</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($invalidCount > 0): ?>
                <p class="text-xs text-slate-400 mt-3">Наведите на бейдж «Ошибка», чтобы увидеть причину.</p>
            <?php endif; ?>

            <div class="flex items-center justify-between mt-5">
                <a href="import.php" class="text-sm font-medium text-slate-500 no-underline hover:text-slate-800">&larr; Загрузить другой файл</a>
                <form method="post" class="m-0">
                    <?= csrf_field() ?>
                    <input type="hidden" name="form_action" value="confirm_import">
                    <button type="submit" style="border:none;" <?= $validCount === 0 ? 'disabled' : '' ?> class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-medium text-white shadow-sm transition-opacity hover:opacity-90 cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed">
                        Импортировать учеников (<?= $validCount ?>)
                    </button>
                </form>
            </div>
        </div>

    <?php elseif ($step === 'done'): ?>

        <!-- Block 3: Summary -->
        <div class="rounded-2xl border border-slate-200/80 bg-white p-6 shadow-[0_2px_8px_-2px_rgba(15,23,42,0.08),0_1px_4px_-1px_rgba(15,23,42,0.06)]">
            <div class="mb-5 flex items-center gap-3">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
                    <i data-lucide="check-circle-2" class="size-5"></i>
                </div>
                <div>
                    <h2 class="text-lg font-bold text-slate-900 m-0">Импорт завершён</h2>
                    <p class="text-sm text-slate-500 m-0">
                        Создано: <span class="font-semibold text-emerald-700"><?= $importSummary['created_count'] ?></span>.
                        Пропущено: <span class="font-semibold <?= $importSummary['skipped_count'] > 0 ? 'text-red-700' : 'text-slate-500' ?>"><?= $importSummary['skipped_count'] ?></span>.
                    </p>
                </div>
            </div>

            <?php if (!empty($importSummary['created'])): ?>
                <a href="?action=download_credentials" class="mb-5 inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-medium text-white no-underline shadow-sm transition-opacity hover:opacity-90">
                    📥 Скачать логины и пароли (.csv)
                </a>
            <?php endif; ?>

            <?php if (!empty($importSummary['skipped'])): ?>
                <h3 class="mt-2 mb-2 text-sm font-semibold text-slate-700">Пропущенные строки</h3>
                <div class="overflow-x-auto rounded-xl border border-slate-200">
                    <table class="w-full border-collapse text-left text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-400">
                                <th class="px-4 py-3">Строка</th>
                                <th class="px-4 py-3">Имя</th>
                                <th class="px-4 py-3">Причина</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($importSummary['skipped'] as $s): ?>
                                <tr class="border-b border-slate-100 last:border-0">
                                    <td class="px-4 py-3 font-mono text-slate-400">#<?= (int)$s['row'] ?></td>
                                    <td class="px-4 py-3 text-slate-900"><?= htmlspecialchars($s['name'] ?: '—') ?></td>
                                    <td class="px-4 py-3 text-red-700"><?= htmlspecialchars($s['reason']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <div class="mt-5 flex items-center gap-4">
                <a href="import.php" class="text-sm font-medium text-blue-600 no-underline hover:underline">Импортировать ещё один файл &rarr;</a>
                <a href="index.php" class="text-sm font-medium text-slate-500 no-underline hover:text-slate-800">К списку учеников</a>
            </div>
        </div>

    <?php endif; ?>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
