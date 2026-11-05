<?php
declare(strict_types=1);

/**
 * e-Lesson Protected File Delivery
 *
 * Serves private files with authentication, tenant isolation, and access control.
 *
 * Usage:
 *   /file.php?type=material&id=123
 *   /file.php?type=book_preview&id=456
 *   /file.php?type=book&id=456
 *   /file.php?type=question&id=789
 *   /file.php?type=receipt&id=101
 *   /file.php?type=resource&id=202
 *   /file.php?type=preview&id=123
 */

session_start();

require_once __DIR__ . '/php/config.php';
require_once __DIR__ . '/login/Login_functions.php';
require_once __DIR__ . '/php/tenant_file.php';
require_once __DIR__ . '/php/access_service.php';

/**
 * Send a JSON error response and exit.
 */
function sendError(int $code, string $message): void
{
    http_response_code($code);
    header('Content-Type: application/json');
    header('X-Content-Type-Options: nosniff');
    echo json_encode(['error' => $message]);
    exit;
}

/**
 * Serve a file from disk with proper headers.
 */
function serveFile(string $filePath, string $contentType, ?string $downloadName = null): void
{
    if (!file_exists($filePath)) {
        sendError(404, 'File not found');
    }

    // Release session lock before streaming file so parallel Range requests never block
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    clearstatcache(true, $filePath);
    $size = filesize($filePath);

    header('Content-Type: ' . $contentType);
    header('Accept-Ranges: bytes');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, max-age=3600');

    if ($downloadName) {
        header('Content-Disposition: attachment; filename="' . $downloadName . '"');
    }

    // Support HTTP Range requests (crucial for video playback and seeking)
    if (isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=\s*(\d+)-(\d*)/i', $_SERVER['HTTP_RANGE'], $matches)) {
        $start = (int)$matches[1];
        $end = ($matches[2] !== '') ? (int)$matches[2] : $size - 1;

        if ($start > $end || $start >= $size) {
            http_response_code(416); // Range Not Satisfiable
            header("Content-Range: bytes */$size");
            exit;
        }

        if ($end >= $size) {
            $end = $size - 1;
        }

        $length = $end - $start + 1;
        http_response_code(206);
        header("Content-Range: bytes $start-$end/$size");
        header("Content-Length: " . $length);

        $fp = fopen($filePath, 'rb');
        if ($fp) {
            fseek($fp, $start);
            $bufferSize = 65536;
            $remaining = $length;
            while ($remaining > 0 && !feof($fp)) {
                $read = min($bufferSize, $remaining);
                $chunk = fread($fp, $read);
                if ($chunk === false) break;
                echo $chunk;
                flush();
                $remaining -= strlen($chunk);
                if (connection_aborted()) break;
            }
            fclose($fp);
        }
        exit;
    }

    header('Content-Length: ' . $size);
    readfile($filePath);
    exit;
}

// ─── Determine user and role ───────────────────────────────────

$userId = null;
$role = null;

if (isLoggedIn()) {
    $userId = getSessionUserId();
    $role = 'student';
}

if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    $role = isset($_SESSION['admin_role']) ? $_SESSION['admin_role'] : 'center_admin';
    $userId = (int)($_SESSION['admin_id'] ?? 0);
}

if ($userId === null || $userId <= 0) {
    sendError(401, 'Authentication required');
}

// ─── Verify tenant is active ──────────────────────────────────
$tenant = Tenant::get();
if (!$tenant) {
    sendError(403, 'Tenant is inactive');
}

// ─── Parse request ─────────────────────────────────────────────

$type = $_GET['type'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$type || $id <= 0) {
    sendError(400, 'Invalid parameters');
}

$allowedTypes = ['material', 'preview', 'book_preview', 'book', 'question', 'receipt', 'resource'];
if (!in_array($type, $allowedTypes)) {
    sendError(400, 'Unknown file type');
}

$tenantId = Tenant::getId();

// ─── Resolve and serve ─────────────────────────────────────────

switch ($type) {

    // ── Material file (video/document) ──
    case 'material':
        if ($role === 'super_admin') {
            $stmt = $pdo->prepare("SELECT material_id, file_path, course_id, tenant_id FROM materials WHERE material_id = ?");
            $stmt->execute([$id]);
        } else {
            $stmt = $pdo->prepare("SELECT material_id, file_path, course_id, tenant_id FROM materials WHERE material_id = ? AND tenant_id = ?");
            $stmt->execute([$id, $tenantId]);
        }
        $material = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$material) {
            sendError(404, 'Material not found');
        }

        // Student access: must be enrolled in the course with active access or free course for class
        if ($role === 'student') {
            $isEnrolled = AccessService::hasActiveAccess($pdo, $tenantId, $userId, (int)$material['course_id']);

            if (!$isEnrolled) {
                // Check if free course for student's class
                $stmtFree = $pdo->prepare("SELECT c.price, c.classLevel, u.class 
                    FROM courses c 
                    JOIN users u ON u.user_id = ? AND u.tenant_id = c.tenant_id
                    WHERE c.course_id = ? AND c.tenant_id = ?");
                $stmtFree->execute([$userId, $material['course_id'], $tenantId]);
                $freeCourse = $stmtFree->fetch(PDO::FETCH_ASSOC);
                if ($freeCourse && (int)$freeCourse['price'] === 0 && $freeCourse['classLevel'] === $freeCourse['class']) {
                    $isEnrolled = true;
                }
            }

            if (!$isEnrolled) {
                sendError(403, 'Access denied');
            }
        }

        $matTenantId = (int)$material['tenant_id'];
        $resolved = TenantFile::resolveFile($matTenantId, $material['file_path']);
        if ($resolved === false) {
            sendError(404, 'File not found on disk');
        }

        $mimeType = TenantFile::getMimeType($resolved);
        serveFile($resolved, $mimeType);
        break;

    // ── Material preview (thumbnail) ──
    case 'preview':
        if ($role === 'super_admin') {
            $stmt = $pdo->prepare("SELECT material_id, thumbnail_path, course_id, tenant_id FROM materials WHERE material_id = ?");
            $stmt->execute([$id]);
        } else {
            $stmt = $pdo->prepare("SELECT material_id, thumbnail_path, course_id, tenant_id FROM materials WHERE material_id = ? AND tenant_id = ?");
            $stmt->execute([$id, $tenantId]);
        }
        $material = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$material || empty($material['thumbnail_path'])) {
            sendError(404, 'Preview not found');
        }

        // Student access: must be enrolled with active access or free course for class
        if ($role === 'student') {
            $isEnrolled = AccessService::hasActiveAccess($pdo, $tenantId, $userId, (int)$material['course_id']);

            if (!$isEnrolled) {
                // Check if free course for student's class
                $stmtFree = $pdo->prepare("SELECT c.price, c.classLevel, u.class 
                    FROM courses c 
                    JOIN users u ON u.user_id = ? AND u.tenant_id = c.tenant_id
                    WHERE c.course_id = ? AND c.tenant_id = ?");
                $stmtFree->execute([$userId, $material['course_id'], $tenantId]);
                $freeCourse = $stmtFree->fetch(PDO::FETCH_ASSOC);
                if ($freeCourse && (int)$freeCourse['price'] === 0 && $freeCourse['classLevel'] === $freeCourse['class']) {
                    $isEnrolled = true;
                }
            }

            if (!$isEnrolled) {
                sendError(403, 'Access denied');
            }
        }

        $matTenantId = (int)$material['tenant_id'];
        $resolved = TenantFile::resolveFile($matTenantId, $material['thumbnail_path']);
        if ($resolved === false) {
            sendError(404, 'File not found on disk');
        }

        $mimeType = TenantFile::getMimeType($resolved);
        serveFile($resolved, $mimeType);
        break;

    // ── Book preview (thumbnail image) ──
    case 'book_preview':
        $stmt = $pdo->prepare("SELECT book_id, preview_path FROM books WHERE book_id = ? AND tenant_id = ?");
        $stmt->execute([$id, $tenantId]);
        $book = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$book || empty($book['preview_path'])) {
            sendError(404, 'Book preview not found');
        }

        // Book previews are public within the tenant
        $resolved = TenantFile::resolveFile($tenantId, $book['preview_path']);
        if ($resolved === false) {
            sendError(404, 'File not found on disk');
        }

        $mimeType = TenantFile::getMimeType($resolved);
        serveFile($resolved, $mimeType);
        break;

    // ── Book file (PDF) ──
    case 'book':
        $stmt = $pdo->prepare("SELECT book_id, book_path, title FROM books WHERE book_id = ? AND tenant_id = ?");
        $stmt->execute([$id, $tenantId]);
        $book = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$book || empty($book['book_path'])) {
            sendError(404, 'Book not found');
        }

        // Books are accessible to all authenticated users of this tenant
        $resolved = TenantFile::resolveFile($tenantId, $book['book_path']);
        if ($resolved === false) {
            sendError(404, 'File not found on disk');
        }

        $mimeType = TenantFile::getMimeType($resolved);
        $downloadName = ($book['title'] ?: 'book') . '.pdf';
        serveFile($resolved, $mimeType, $downloadName);
        break;

    // ── Question image ──
    case 'question':
        $found = false;
        $imgPath = '';
        $courseId = 0;

        // Check daily_questions
        $stmt = $pdo->prepare("SELECT dq.question_img_url, dt.course_id
            FROM daily_questions dq
            JOIN daily_tests dt ON dq.daily_test_id = dt.daily_test_id
            WHERE dq.daily_question_id = ? AND dt.tenant_id = ?");
        $stmt->execute([$id, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $found = true;
            $imgPath = $row['question_img_url'];
            $courseId = $row['course_id'];
        } else {
            // Check weekly_questions
            $stmt = $pdo->prepare("SELECT wq.question_img_url, wt.course_id
                FROM weekly_questions wq
                JOIN weekly_tests wt ON wq.weekly_test_id = wt.weekly_test_id
                WHERE wq.weekly_question_id = ? AND wt.tenant_id = ?");
            $stmt->execute([$id, $tenantId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                $found = true;
                $imgPath = $row['question_img_url'];
                $courseId = $row['course_id'];
            }
        }

        if (!$found) {
            sendError(404, 'Question not found');
        }

        // Student access: must be enrolled with active access
        if ($role === 'student' && $courseId > 0) {
            if (!AccessService::hasActiveAccess($pdo, $tenantId, $userId, $courseId)) {
                sendError(403, 'Access denied');
            }
        }

        // Handle both new tenant storage paths and legacy fallback paths
        $resolved = TenantFile::resolveFile($tenantId, $imgPath);
        if ($resolved === false) {
            sendError(404, 'File not found on disk');
        }

        $mimeType = TenantFile::getMimeType($resolved);
        serveFile($resolved, $mimeType);
        break;

    // ── Receipt ──
    case 'receipt':
        if ($role === 'super_admin') {
            $stmt = $pdo->prepare("SELECT p.receipt_path, p.user_id, p.tenant_id
                FROM payments p
                WHERE p.payment_id = ?");
            $stmt->execute([$id]);
        } else {
            $stmt = $pdo->prepare("SELECT p.receipt_path, p.user_id, p.tenant_id
                FROM payments p
                WHERE p.payment_id = ? AND p.tenant_id = ?");
            $stmt->execute([$id, $tenantId]);
        }
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$payment || empty($payment['receipt_path'])) {
            sendError(404, 'Receipt not found');
        }

        // Access: owner, center_admin, or super_admin
        if ($role === 'student') {
            if ((int)$payment['user_id'] !== $userId) {
                sendError(403, 'Access denied');
            }
        } elseif ($role === 'center_admin') {
            // Already filtered by tenant_id above
        } elseif ($role !== 'super_admin') {
            sendError(403, 'Access denied');
        }

        $receiptTenantId = (int)$payment['tenant_id'];
        $resolved = TenantFile::resolveFile($receiptTenantId, $payment['receipt_path']);
        if ($resolved === false) {
            sendError(404, 'File not found on disk');
        }

        $mimeType = TenantFile::getMimeType($resolved);
        serveFile($resolved, $mimeType);
        break;

    // ── Other resource ──
    case 'resource':
        $stmt = $pdo->prepare("SELECT resource_id, resource_path FROM other_resources WHERE resource_id = ? AND tenant_id = ?");
        $stmt->execute([$id, $tenantId]);
        $resource = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$resource || empty($resource['resource_path'])) {
            sendError(404, 'Resource not found');
        }

        // Resources are admin-only
        if ($role === 'student') {
            sendError(403, 'Access denied');
        }

        $resolved = TenantFile::resolveFile($tenantId, $resource['resource_path']);
        if ($resolved === false) {
            sendError(404, 'File not found on disk');
        }

        $mimeType = TenantFile::getMimeType($resolved);
        serveFile($resolved, $mimeType);
        break;
}

sendError(400, 'Unknown file type');
