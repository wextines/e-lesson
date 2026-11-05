<?php
declare(strict_types=1);

/**
 * e-Lesson Student Importer
 *
 * Bulk-creates student accounts from a CSV/TXT roster uploaded by an admin
 * (admin/students/import.php). Every account is created through
 * StudentService::create() — same validation, same tenant scoping, same
 * "no side effects beyond the users row" guarantee as the single-student
 * add form. This class only adds: file parsing, a template to download,
 * and default password/email generation for rows that omit them.
 */

require_once __DIR__ . '/student_service.php';

class StudentImporter
{
    /** Header aliases (RU/UZ), lowercased -> canonical StudentService input field. */
    private const HEADER_MAP = [
        'имя' => 'first_name', 'ism' => 'first_name', 'ismi' => 'first_name',
        'фамилия' => 'last_name', 'familiya' => 'last_name', 'familiyasi' => 'last_name',
        'телефон' => 'phone', 'telefon' => 'phone', 'tel' => 'phone',
        'класс' => 'class', 'sinf' => 'class',
        'email' => 'email', 'e-mail' => 'email', 'почта' => 'email',
    ];

    /**
     * Parse an uploaded CSV/TXT roster into structured rows.
     *
     * Auto-detects the delimiter (, or ;) from the header line, strips a
     * UTF-8 BOM if present, and maps recognized RU/UZ column headers to
     * canonical field names. Unrecognized columns are ignored. Blank lines
     * are skipped.
     *
     * @return array<int, array<string,string>> Rows keyed by canonical field name.
     */
    public static function parseCsv(string $filePath): array
    {
        if (!is_readable($filePath)) {
            throw new RuntimeException('Файл недоступен для чтения.');
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new RuntimeException('Не удалось прочитать файл.');
        }

        // Strip UTF-8 BOM, if present.
        if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
            $content = substr($content, 3);
        }

        $lines = preg_split('/\r\n|\r|\n/', $content);
        $lines = array_values(array_filter($lines, fn($l) => trim((string)$l) !== ''));
        if (empty($lines)) {
            return [];
        }

        // Delimiter auto-detection: whichever of , or ; appears more often in the header row.
        $headerLine = $lines[0];
        $delimiter = substr_count($headerLine, ';') > substr_count($headerLine, ',') ? ';' : ',';

        $headerFields = str_getcsv($headerLine, $delimiter);
        $columns = [];
        foreach ($headerFields as $i => $h) {
            $key = mb_strtolower(trim((string)$h), 'UTF-8');
            $columns[$i] = self::HEADER_MAP[$key] ?? null;
        }

        $rows = [];
        for ($i = 1, $n = count($lines); $i < $n; $i++) {
            $fields = str_getcsv($lines[$i], $delimiter);
            $row = [];
            foreach ($columns as $idx => $field) {
                if ($field === null) {
                    continue;
                }
                $row[$field] = trim((string)($fields[$idx] ?? ''));
            }
            if (empty(array_filter($row, fn($v) => $v !== ''))) {
                continue; // fully blank row
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /** A ready-to-fill CSV template with example rows, semicolon-delimited for Excel. */
    public static function generateTemplateCsv(): string
    {
        $sample = [
            ['Имя', 'Фамилия', 'Телефон', 'Класс', 'Email'],
            ['Aziz', 'Karimov', '+998901234567', '5', 'aziz.karimov@example.com'],
            ['Malika', 'Yusupova', '+998907654321', '9', ''],
        ];
        return self::rowsToCsvString($sample);
    }

    /** The credentials handout for a just-completed import: name, email, temp password. */
    public static function generateCredentialsCsv(array $created): string
    {
        $sample = [['Имя', 'Фамилия', 'Email', 'Временный пароль']];
        foreach ($created as $c) {
            $sample[] = [$c['first_name'], $c['last_name'], $c['email'], $c['password']];
        }
        return self::rowsToCsvString($sample);
    }

    private static function rowsToCsvString(array $rows): string
    {
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF"); // BOM so Excel opens Cyrillic/Uzbek text correctly
        foreach ($rows as $r) {
            fputcsv($out, $r, ';');
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);
        return $csv;
    }

    /**
     * Read-only check used by the import preview: would this row succeed if
     * imported right now? Mirrors StudentService::create()'s validation
     * rules without creating anything, so the preview badge is never a lie.
     * An empty email is valid here — importRows() auto-generates one.
     */
    public static function validateRow(PDO $pdo, int $tenantId, array $row): array
    {
        $firstName = trim($row['first_name'] ?? '');
        $lastName = trim($row['last_name'] ?? '');
        $class = trim($row['class'] ?? '');
        $email = trim($row['email'] ?? '');

        if ($firstName === '' || $lastName === '') {
            return ['valid' => false, 'reason' => 'Не указано имя или фамилия.'];
        }
        if (!in_array($class, StudentService::CLASS_LEVELS, true)) {
            return ['valid' => false, 'reason' => 'Неверный класс (допустимо 1-11).'];
        }
        if ($email !== '') {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return ['valid' => false, 'reason' => 'Некорректный email.'];
            }
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND tenant_id = ?");
            $stmt->execute([$email, $tenantId]);
            if ((int)$stmt->fetchColumn() > 0) {
                return ['valid' => false, 'reason' => 'Такой email уже существует.'];
            }
        }
        return ['valid' => true, 'reason' => null];
    }

    /**
     * Create one student account per row via StudentService::create().
     * Rows are processed sequentially (not batched) so email-uniqueness
     * checks — both the explicit one here and StudentService's own — always
     * see every account created earlier in the same run.
     *
     * @return array{created: array, skipped: array, created_count: int, skipped_count: int}
     */
    public static function importRows(PDO $pdo, int $tenantId, array $rows): array
    {
        $created = [];
        $skipped = [];

        foreach ($rows as $i => $row) {
            $rowNum = $i + 2; // header is row 1
            $firstName = trim($row['first_name'] ?? '');
            $lastName = trim($row['last_name'] ?? '');
            $phone = trim($row['phone'] ?? '');
            $class = trim($row['class'] ?? '');
            $email = trim($row['email'] ?? '');
            $password = trim($row['password'] ?? '');

            $displayName = trim("{$firstName} {$lastName}");

            if ($firstName === '' || $lastName === '') {
                $skipped[] = ['row' => $rowNum, 'name' => $displayName, 'reason' => 'Не указано имя или фамилия.'];
                continue;
            }

            if ($password === '') {
                $password = 'Study' . date('Y');
            }
            if ($email === '') {
                $email = self::generateUniqueEmail($pdo, $tenantId, $firstName, $lastName);
            }

            $result = StudentService::create($pdo, $tenantId, [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'phone' => $phone,
                'class' => $class,
                'password' => $password,
            ]);

            if ($result['success']) {
                $created[] = [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $email,
                    'password' => $password,
                ];
            } else {
                $reason = implode(' ', array_values($result['errors'] ?? []));
                $skipped[] = ['row' => $rowNum, 'name' => $displayName, 'reason' => $reason !== '' ? $reason : 'Неизвестная ошибка.'];
            }
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
            'created_count' => count($created),
            'skipped_count' => count($skipped),
        ];
    }

    /** translit(Имя+Фамилия) + random 3-digit suffix + @student.uz, retried until unique in-tenant. */
    private static function generateUniqueEmail(PDO $pdo, int $tenantId, string $firstName, string $lastName): string
    {
        $base = preg_replace('/[^a-z0-9]+/', '', transliterate($firstName . $lastName));
        if ($base === '' || $base === null) {
            $base = 'student';
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND tenant_id = ?");
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $email = $base . random_int(100, 999) . '@student.uz';
            $stmt->execute([$email, $tenantId]);
            if ((int)$stmt->fetchColumn() === 0) {
                return $email;
            }
        }
        // Practically unreachable (20 collisions in a row), but never loop forever.
        return $base . uniqid() . '@student.uz';
    }
}
