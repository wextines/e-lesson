<?php
require_once __DIR__ . '/../../php/config.php';

$tenantId = 3;

$check = $pdo->prepare("SELECT name FROM tenants WHERE tenant_id = ?");
$check->execute([$tenantId]);
if ($check->fetchColumn() !== 'Demo Academy') {
    die("ABORT: Tenant 3 is not Demo Academy\n");
}

$pdo->beginTransaction();
try {
    // 1. Обновление названий и метаданных курсов
    $pdo->prepare("
        UPDATE courses SET
            subject = 'English',
            title = '10-11 sinf Ingliz tili: IELTS Intensive',
            classLevel = '10-11',
            price = 500000,
            subscription_days = 30
        WHERE tenant_id = ? AND course_id = 101
    ")->execute([$tenantId]);

    $pdo->prepare("
        UPDATE courses SET
            subject = 'Matematika',
            title = '10-11 sinf Matematika: DTM & Milliy sertifikat',
            classLevel = '10-11',
            price = 450000,
            subscription_days = 30
        WHERE tenant_id = ? AND course_id = 105
    ")->execute([$tenantId]);

    // Обновляем класс системной Гюльнозы 2108
    $pdo->prepare("UPDATE users SET class = '10' WHERE tenant_id = ? AND user_id = 2108")->execute([$tenantId]);

    // 2. Список новых студентов (31 продолжающий + 2 новичка)
    // Формат: [first_name, last_name, phone, class, cohort]
    // cohort: 'eng' (только англ), 'math' (только мат), 'both' (оба курса), 'new_eng', 'new_math'
    $studentsData = [
        // Когорта A: Только Английский (12)
        ['Jasur', 'Karimov', '+998901112233', '11', 'eng'],
        ['Madina', 'Usmonova', '+998912223344', '10', 'eng'],
        ['Bobur', 'Mirzayev', '+998933334455', '11', 'eng'],
        ['Nigora', 'Aliyeva', '+998974445566', '10', 'eng'],
        ['Shaxzod', 'Qodirov', '+998995556677', '11', 'eng'],
        ['Dilnoza', 'Xasanova', '+998906667788', '10', 'eng'],
        ['Farrux', 'Toirov', '+998917778899', '11', 'eng'],
        ['Kamola', 'Raximova', '+998938889900', '10', 'eng'],
        ['Ulugbek', 'Normatov', '+998979990011', '11', 'eng'],
        ['Malika', 'Yusupova', '+998990001122', '10', 'eng'],
        ['Javoxir', 'Ergashev', '+998901234501', '11', 'eng'], // Должник англ (4 дня)
        ['Shahnoza', 'Sobirova', '+998912345602', '10', 'eng'], // Должник англ (5 дней)
        // Когорта B: Только Математика (9 новых + Гюльноза = 10)
        ['Otabek', 'Abdubakiyev', '+998933456703', '11', 'math'],
        ['Azizbek', 'Sultonov', '+998974567804', '10', 'math'],
        ['Sevara', 'Karimova', '+998995678905', '11', 'math'],
        ['Bekzod', 'Rustamov', '+998906789006', '10', 'math'],
        ['Nodira', 'Umarova', '+998917890107', '11', 'math'],
        ['Temur', 'Ismoilov', '+998938901208', '10', 'math'],
        ['Zilola', 'Davronova', '+998979012309', '11', 'math'], // Должник мат (4 дня)
        ['Sanjar', 'Murodov', '+998990123410', '10', 'math'],   // Должник мат (5 дней)
        ['Mohira', 'Saidova', '+998901234511', '11', 'math'],   // Должник мат (4 дня)
        // Когорта C: ПЕРЕСЕЧЕНИЕ — Английский И Математика (10 студентов)
        ['Sardor', 'Rahimov', '+998912345612', '11', 'both'],   // Англ оплачен, Математика просрочена (4 дня)!
        ['Rayhona', 'Zokirova', '+998933456713', '10', 'both'],
        ['Doniyor', 'Shokirov', '+998974567814', '11', 'both'],
        ['Nilufar', 'Qosimova', '+998995678915', '10', 'both'],
        ['Anvar', 'Tursunov', '+998906789016', '11', 'both'],
        ['Feruza', 'Mahmudova', '+998917890117', '10', 'both'],
        ['Jahongir', 'Bozorov', '+998938901218', '11', 'both'],
        ['Gulchehra', 'Odilova', '+998979012319', '10', 'both'],
        ['Rustam', 'Xoliqov', '+998990123420', '11', 'both'],
        ['Ziyoda', 'Vohidova', '+998901234521', '10', 'both'],
        // Когорта D: Новички сентября (2)
        ['Asadbek', 'Nurmatov', '+998912345622', '10', 'new_eng'],
        ['Shodiya', 'Boboyeva', '+998933456723', '11', 'new_math'],
    ];

    // NOTE: the users table has no `role` column (only the separate `admins` table
    // does) — dropped it from this INSERT, it would have aborted the whole run.
    $userStmt = $pdo->prepare("
        INSERT INTO users (tenant_id, first_name, last_name, phone, class, email, password, is_active, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)
    ");
    $subStmt = $pdo->prepare("
        INSERT INTO course_subs (tenant_id, user_id, course_id, price, status, purchase_date, next_payment_date, access_expires_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $txStmt = $pdo->prepare("
        INSERT INTO transactions (tenant_id, user_id, course_sub_id, amount, type, payment_date, payment_method, notes, created_by, created_at)
        VALUES (?, ?, ?, ?, 'payment', ?, ?, ?, 'demoadmin', ?)
    ");

    $defaultHash = password_hash('Study2026', PASSWORD_DEFAULT);

    // Добавляем августовскую и сентябрьскую транзакции для Гюльнозы 2108 (Курс 105)
    // Находим ее существующую подписку
    $s = $pdo->prepare("SELECT subscription_id FROM course_subs WHERE tenant_id = ? AND user_id = 2108 AND course_id = 105");
    $s->execute([$tenantId]);
    $gSubId = (int)$s->fetchColumn();

    // Август для Гюльнозы
    $txStmt->execute([$tenantId, 2108, $gSubId, 450000, '2026-08-02', 'cash', 'Оплата за 1-й месяц (август)', '2026-08-02 10:15:00']);
    // Сентябрь для Гюльнозы
    $txStmt->execute([$tenantId, 2108, $gSubId, 450000, '2026-09-02', 'cash', 'Продление на 2-й месяц (сентябрь)', '2026-09-02 11:20:00']);
    $pdo->prepare("UPDATE course_subs SET purchase_date = '2026-08-02 10:15:00', next_payment_date = '2026-10-02', access_expires_at = '2026-10-02' WHERE subscription_id = ?")->execute([$gSubId]);

    $methodCycle = ['cash', 'cash', 'card', 'cash', 'transfer'];
    $mIdx = 0;

    foreach ($studentsData as $idx => $st) {
        [$fn, $ln, $phone, $cls, $cohort] = $st;
        $email = strtolower($fn . '.' . $ln . ($idx + 10) . '@demo.uz');
        $regDate = in_array($cohort, ['new_eng', 'new_math']) ? '2026-09-08 11:00:00' : '2026-08-01 09:00:00';

        $userStmt->execute([$tenantId, $fn, $ln, $phone, $cls, $email, $defaultHash, $regDate]);
        $uid = (int)$pdo->lastInsertId();

        // Обработка курсов в зависимости от когорты
        $coursesToEnroll = [];
        if ($cohort === 'eng' || $cohort === 'new_eng') {
            $coursesToEnroll[] = 101;
        } elseif ($cohort === 'math' || $cohort === 'new_math') {
            $coursesToEnroll[] = 105;
        } elseif ($cohort === 'both') {
            $coursesToEnroll = [101, 105];
        }

        foreach ($coursesToEnroll as $cId) {
            $cPrice = ($cId === 101) ? 500000 : 450000;
            $cName = ($cId === 101) ? 'IELTS Intensive' : 'Математика DTM';

            // 1. Новички сентября (pending)
            if (in_array($cohort, ['new_eng', 'new_math'])) {
                $subStmt->execute([$tenantId, $uid, $cId, $cPrice, 'pending', '2026-09-08 11:00:00', null, null]);
                continue;
            }

            // 2. Определение статуса сентября: должник или оплатил?
            $isDebtor = false;
            $overdueDays = 4;
            if ($fn === 'Javoxir' && $cId === 101)   { $isDebtor = true; $overdueDays = 4; }
            if ($fn === 'Shahnoza' && $cId === 101)  { $isDebtor = true; $overdueDays = 5; }
            if ($fn === 'Zilola' && $cId === 105)    { $isDebtor = true; $overdueDays = 4; }
            if ($fn === 'Sanjar' && $cId === 105)    { $isDebtor = true; $overdueDays = 5; }
            if ($fn === 'Mohira' && $cId === 105)    { $isDebtor = true; $overdueDays = 4; }
            if ($fn === 'Sardor' && $cId === 105)    { $isDebtor = true; $overdueDays = 4; } // Sardor просрочил только математику!

            $augPayDay = 1 + ($idx % 5); // 1..5 августа
            $augDateStr = sprintf('2026-08-%02d', $augPayDay);

            if ($isDebtor) {
                // Должник: оплатил август, но НЕ оплатил сентябрь
                $nextDueDay = 10 - $overdueDays; // 5 или 6 сентября
                $nextDateStr = sprintf('2026-09-%02d', $nextDueDay);
                $subStmt->execute([$tenantId, $uid, $cId, $cPrice, 'active', $augDateStr . ' 10:00:00', $nextDateStr, $nextDateStr]);
                $subId = (int)$pdo->lastInsertId();

                // Транзакция только за август
                $m = $methodCycle[$mIdx++ % count($methodCycle)];
                $txStmt->execute([$tenantId, $uid, $subId, $cPrice, $augDateStr, $m, "Оплата за август ({$cName})", $augDateStr . ' 10:00:00']);
            } else {
                // Оплатил и август, и сентябрь вовремя
                $sepPayDay = 1 + ($idx % 8); // 1..8 сентября
                $sepDateStr = sprintf('2026-09-%02d', $sepPayDay);
                $octDateStr = sprintf('2026-10-%02d', $sepPayDay);
                $subStmt->execute([$tenantId, $uid, $cId, $cPrice, 'active', $augDateStr . ' 10:00:00', $octDateStr, $octDateStr]);
                $subId = (int)$pdo->lastInsertId();

                // 1. Транзакция за август
                $m1 = $methodCycle[$mIdx++ % count($methodCycle)];
                $txStmt->execute([$tenantId, $uid, $subId, $cPrice, $augDateStr, $m1, "Оплата за август ({$cName})", $augDateStr . ' 10:00:00']);
                // 2. Транзакция за сентябрь
                $m2 = $methodCycle[$mIdx++ % count($methodCycle)];
                $txStmt->execute([$tenantId, $uid, $subId, $cPrice, $sepDateStr, $m2, "Продление на сентябрь ({$cName})", $sepDateStr . ' 14:30:00']);
            }
        }
    }

    $pdo->commit();
    echo "SEED SUCCESS: 2 courses, 34 students, realistic 2-month ledger seeded.\n";
} catch (Exception $e) {
    $pdo->rollBack();
    die("SEED FAILED: " . $e->getMessage() . "\n");
}
