<?php
require_once __DIR__ . '/../authorization.php';
require_once __DIR__ . '/../../php/config.php';

header('Location: /admin/courses/');
exit;
$tid = Tenant::getId();

// Fetch all courses with stats
$r = $pdo->prepare("
    SELECT c.*,
           (SELECT COUNT(*) FROM course_subs cs WHERE cs.course_id = c.course_id AND cs.tenant_id = c.tenant_id) AS total_subs,
           (SELECT COUNT(*) FROM course_subs cs WHERE cs.course_id = c.course_id AND cs.tenant_id = c.tenant_id AND cs.access_expires_at > NOW()) AS active_subs,
           (SELECT COALESCE(SUM(cs.price),0) FROM course_subs cs WHERE cs.course_id = c.course_id AND cs.tenant_id = c.tenant_id) AS total_revenue,
           (SELECT COUNT(*) FROM materials m WHERE m.course_id = c.course_id AND m.tenant_id = c.tenant_id) AS material_count
    FROM courses c
    WHERE c.tenant_id = ?
    ORDER BY total_subs DESC
");
$r->execute([$tid]);
$courses = $r->fetchAll(PDO::FETCH_ASSOC);

// Test analytics per course
$r = $pdo->prepare("
    SELECT c.course_id, c.title,
           COUNT(DISTINCT tr.slug) AS unique_students,
           COUNT(tr.result_id) AS total_attempts,
           ROUND(AVG(CAST(tr.score AS DECIMAL(5,2))),1) AS avg_score,
           MIN(CAST(tr.score AS DECIMAL(5,2))) AS min_score,
           MAX(CAST(tr.score AS DECIMAL(5,2))) AS max_score
    FROM courses c
    LEFT JOIN daily_tests dt ON dt.course_id = c.course_id AND dt.tenant_id = c.tenant_id
    LEFT JOIN weekly_tests wt ON wt.course_id = c.course_id AND wt.tenant_id = c.tenant_id
    LEFT JOIN test_results tr ON tr.tenant_id = c.tenant_id
        AND ((tr.test_type = 'daily' AND tr.test_id = dt.daily_test_id)
          OR (tr.test_type = 'weekly' AND tr.test_id = wt.weekly_test_id))
    WHERE c.tenant_id = ?
    GROUP BY c.course_id, c.title
    ORDER BY total_attempts DESC
");
$r->execute([$tid]);
$testAnalytics = $r->fetchAll(PDO::FETCH_ASSOC);
$testMap = [];
foreach ($testAnalytics as $ta) $testMap[$ta['course_id']] = $ta;

include __DIR__ . '/../includes/header.php';
?>

<h1 class="page-title">Аналитика курсов</h1>

<div class="card">
    <h2>Обзор курсов (<?= count($courses) ?>)</h2>
    <?php if (empty($courses)): ?>
        <p style="color:#6b7280;text-align:center;padding:20px">Нет курсов</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Курс</th>
                    <th>Предмет</th>
                    <th>Класс</th>
                    <th>Цена</th>
                    <th>Подписок</th>
                    <th>Активных</th>
                    <th>Доход</th>
                    <th>Материалов</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($courses as $c): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($c['title']) ?></strong></td>
                    <td><?= htmlspecialchars($c['subject']) ?></td>
                    <td><?= htmlspecialchars($c['classLevel']) ?></td>
                    <td><?= number_format($c['price'], 0, '.', ' ') ?></td>
                    <td><?= $c['total_subs'] ?></td>
                    <td><span class="badge badge-green"><?= $c['active_subs'] ?></span></td>
                    <td><span class="badge badge-blue"><?= number_format($c['total_revenue'], 0, '.', ' ') ?></span></td>
                    <td><?= $c['material_count'] ?></td>
                    <td><a href="/admin/tests/index_tests.php?course_id=<?= $c['course_id'] ?>" class="btn btn-primary btn-sm">Тесты</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php if (!empty($testMap)): ?>
<div class="card" style="margin-top:20px">
    <h2>Результаты тестов по курсам</h2>
    <table>
        <thead>
            <tr>
                <th>Курс</th>
                <th>Учеников</th>
                <th>Попыток</th>
                <th>Средний балл</th>
                <th>Мин</th>
                <th>Макс</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($testMap as $ta): ?>
            <tr>
                <td><?= htmlspecialchars($ta['title']) ?></td>
                <td><?= $ta['unique_students'] ?></td>
                <td><?= $ta['total_attempts'] ?></td>
                <td><span class="badge badge-blue"><?= $ta['avg_score'] ?? '—' ?></span></td>
                <td><?= $ta['min_score'] ?? '—' ?></td>
                <td><?= $ta['max_score'] ?? '—' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
