<?php
declare(strict_types=1);

/**
 * e-Lesson Question Service
 *
 * Centralized service for CRUD operations, continuous resequencing,
 * multi-tenant isolation, and secure file management for test questions.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/tenant_file.php';

class QuestionService
{
    /**
     * Resequence questions for a given test so sort_order is strictly 1, 2, 3... N without gaps.
     */
    public static function resequence(PDO $pdo, string $type, int $testId): void
    {
        $table = $type === 'daily' ? 'daily_questions' : 'weekly_questions';
        $testCol = $type === 'daily' ? 'daily_test_id' : 'weekly_test_id';
        $idCol = $type === 'daily' ? 'daily_question_id' : 'weekly_question_id';

        $stmt = $pdo->prepare("SELECT {$idCol} FROM {$table} WHERE {$testCol} = ? ORDER BY sort_order ASC, {$idCol} ASC");
        $stmt->execute([$testId]);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $order = 1;
        $upd = $pdo->prepare("UPDATE {$table} SET sort_order = ? WHERE {$idCol} = ?");
        foreach ($ids as $id) {
            $upd->execute([$order, $id]);
            $order++;
        }
    }

    /**
     * Get next sort_order for a test.
     */
    public static function getNextSortOrder(PDO $pdo, string $type, int $testId): int
    {
        $table = $type === 'daily' ? 'daily_questions' : 'weekly_questions';
        $testCol = $type === 'daily' ? 'daily_test_id' : 'weekly_test_id';

        $stmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) FROM {$table} WHERE {$testCol} = ?");
        $stmt->execute([$testId]);
        return ((int)$stmt->fetchColumn()) + 1;
    }

    /**
     * Fetch questions for a test in strictly deterministic order.
     */
    public static function getQuestions(PDO $pdo, string $type, int $testId): array
    {
        $table = $type === 'daily' ? 'daily_questions' : 'weekly_questions';
        $testCol = $type === 'daily' ? 'daily_test_id' : 'weekly_test_id';
        $idCol = $type === 'daily' ? 'daily_question_id' : 'weekly_question_id';

        $stmt = $pdo->prepare("SELECT {$idCol}, sort_order, question_text, question_img_url, correct_answer FROM {$table} WHERE {$testCol} = ? ORDER BY sort_order ASC, {$idCol} ASC");
        $stmt->execute([$testId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Add a question with strict tenant validation and duplicate number check.
     */
    public static function addQuestion(
        PDO $pdo,
        int $tenantId,
        string $type,
        int $testId,
        int $courseId,
        ?string $text,
        ?array $file,
        string $correctAnswer,
        ?int $customOrder = null
    ): array {
        $testTable = $type === 'daily' ? 'daily_tests' : 'weekly_tests';
        $table = $type === 'daily' ? 'daily_questions' : 'weekly_questions';
        $testCol = $type === 'daily' ? 'daily_test_id' : 'weekly_test_id';

        // Verify test ownership by tenant
        $stmtTest = $pdo->prepare("SELECT course_id FROM {$testTable} WHERE {$testCol} = ? AND tenant_id = ?");
        $stmtTest->execute([$testId, $tenantId]);
        $testRow = $stmtTest->fetch(PDO::FETCH_ASSOC);
        if (!$testRow) {
            return ['success' => false, 'error' => 'Тест не найден или доступ запрещен'];
        }
        $actualCourseId = (int)$testRow['course_id'];

        $text = trim($text ?? '');
        $correctAnswer = strtoupper(trim($correctAnswer));

        if (!in_array($correctAnswer, ['A', 'B', 'C', 'D', 'E'])) {
            return ['success' => false, 'error' => 'Неверный правильный ответ (A-E)'];
        }

        $hasFile = $file && isset($file['error']) && $file['error'] === UPLOAD_ERR_OK && !empty($file['tmp_name']);

        if (empty($text) && !$hasFile) {
            return ['success' => false, 'error' => 'Необходимо указать текст вопроса или прикрепить изображение'];
        }

        // Check if custom number already exists in this test
        if ($customOrder !== null && $customOrder > 0) {
            $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE {$testCol} = ? AND sort_order = ?");
            $stmtCheck->execute([$testId, $customOrder]);
            if ((int)$stmtCheck->fetchColumn() > 0) {
                return [
                    'success' => false,
                    'error' => "Вопрос №{$customOrder} уже существует. Отредактируйте существующий вопрос или выберите другой номер."
                ];
            }
            $sortOrder = $customOrder;
        } else {
            $sortOrder = self::getNextSortOrder($pdo, $type, $testId);
        }

        $imgRelPath = null;
        if ($hasFile) {
            $ur = TenantFile::handleUpload($file, $tenantId, 'courses', $actualCourseId, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp']);
            if (!$ur) {
                return ['success' => false, 'error' => 'Ошибка при загрузке изображения. Проверьте формат файла (JPG, PNG, WEBP).'];
            }
            $imgRelPath = $ur['relativePath'];
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO {$table} ({$testCol}, sort_order, question_text, question_img_url, correct_answer) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$testId, $sortOrder, !empty($text) ? $text : null, $imgRelPath, $correctAnswer]);
            $newId = (int)$pdo->lastInsertId();

            self::resequence($pdo, $type, $testId);

            return ['success' => true, 'question_id' => $newId];
        } catch (PDOException $e) {
            if ($imgRelPath) {
                TenantFile::deleteFile($tenantId, $imgRelPath);
            }
            error_log("[QuestionService::addQuestion] DB error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Ошибка базы данных при сохранении вопроса'];
        }
    }

    /**
     * Update a question with strict tenant validation.
     */
    public static function updateQuestion(
        PDO $pdo,
        int $tenantId,
        string $type,
        int $testId,
        int $courseId,
        int $questionId,
        ?string $text,
        ?array $file,
        bool $removeImage,
        string $correctAnswer
    ): array {
        $testTable = $type === 'daily' ? 'daily_tests' : 'weekly_tests';
        $table = $type === 'daily' ? 'daily_questions' : 'weekly_questions';
        $testCol = $type === 'daily' ? 'daily_test_id' : 'weekly_test_id';
        $idCol = $type === 'daily' ? 'daily_question_id' : 'weekly_question_id';

        // Verify test ownership by tenant
        $stmtTest = $pdo->prepare("SELECT course_id FROM {$testTable} WHERE {$testCol} = ? AND tenant_id = ?");
        $stmtTest->execute([$testId, $tenantId]);
        $testRow = $stmtTest->fetch(PDO::FETCH_ASSOC);
        if (!$testRow) {
            return ['success' => false, 'error' => 'Тест не найден или доступ запрещен'];
        }
        $actualCourseId = (int)$testRow['course_id'];

        $text = trim($text ?? '');
        $correctAnswer = strtoupper(trim($correctAnswer));

        if (!in_array($correctAnswer, ['A', 'B', 'C', 'D', 'E'])) {
            return ['success' => false, 'error' => 'Неверный правильный ответ (A-E)'];
        }

        // Verify question exists for this test
        $stmt = $pdo->prepare("SELECT question_img_url FROM {$table} WHERE {$idCol} = ? AND {$testCol} = ?");
        $stmt->execute([$questionId, $testId]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$current) {
            return ['success' => false, 'error' => 'Вопрос не найден'];
        }

        $oldImg = $current['question_img_url'];
        $newImg = $oldImg;

        $hasNewFile = $file && isset($file['error']) && $file['error'] === UPLOAD_ERR_OK && !empty($file['tmp_name']);

        if ($hasNewFile) {
            $ur = TenantFile::handleUpload($file, $tenantId, 'courses', $actualCourseId, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp']);
            if (!$ur) {
                return ['success' => false, 'error' => 'Ошибка при загрузке изображения. Проверьте формат файла (JPG, PNG, WEBP).'];
            }
            $newImg = $ur['relativePath'];
            // Only delete old file after new upload is saved
            if (!empty($oldImg) && $oldImg !== $newImg) {
                TenantFile::deleteFile($tenantId, $oldImg);
            }
        } elseif ($removeImage) {
            if (!empty($oldImg)) {
                TenantFile::deleteFile($tenantId, $oldImg);
            }
            $newImg = null;
        }

        if (empty($text) && empty($newImg)) {
            return ['success' => false, 'error' => 'Вопрос не может быть пустым. Укажите текст или прикрепите изображение.'];
        }

        try {
            $upd = $pdo->prepare("UPDATE {$table} SET question_text = ?, question_img_url = ?, correct_answer = ? WHERE {$idCol} = ? AND {$testCol} = ?");
            $upd->execute([!empty($text) ? $text : null, $newImg, $correctAnswer, $questionId, $testId]);

            self::resequence($pdo, $type, $testId);

            return ['success' => true];
        } catch (PDOException $e) {
            error_log("[QuestionService::updateQuestion] DB error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Ошибка базы данных при обновлении вопроса'];
        }
    }

    /**
     * Delete a question with strict tenant validation.
     */
    public static function deleteQuestion(
        PDO $pdo,
        int $tenantId,
        string $type,
        int $testId,
        int $questionId
    ): array {
        $testTable = $type === 'daily' ? 'daily_tests' : 'weekly_tests';
        $table = $type === 'daily' ? 'daily_questions' : 'weekly_questions';
        $testCol = $type === 'daily' ? 'daily_test_id' : 'weekly_test_id';
        $idCol = $type === 'daily' ? 'daily_question_id' : 'weekly_question_id';

        // Verify test ownership by tenant
        $stmtTest = $pdo->prepare("SELECT course_id FROM {$testTable} WHERE {$testCol} = ? AND tenant_id = ?");
        $stmtTest->execute([$testId, $tenantId]);
        $testRow = $stmtTest->fetch(PDO::FETCH_ASSOC);
        if (!$testRow) {
            return ['success' => false, 'error' => 'Тест не найден или доступ запрещен'];
        }

        $stmt = $pdo->prepare("SELECT question_img_url FROM {$table} WHERE {$idCol} = ? AND {$testCol} = ?");
        $stmt->execute([$questionId, $testId]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$current) {
            return ['success' => false, 'error' => 'Вопрос не найден'];
        }

        try {
            $pdo->prepare("DELETE FROM {$table} WHERE {$idCol} = ? AND {$testCol} = ?")->execute([$questionId, $testId]);

            if (!empty($current['question_img_url'])) {
                TenantFile::deleteFile($tenantId, $current['question_img_url']);
            }

            self::resequence($pdo, $type, $testId);

            return ['success' => true];
        } catch (PDOException $e) {
            error_log("[QuestionService::deleteQuestion] DB error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Ошибка при удалении вопроса'];
        }
    }
}
