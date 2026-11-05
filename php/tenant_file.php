<?php
declare(strict_types=1);

/**
 * e-Lesson Tenant File Helper
 *
 * Provides tenant-specific file path construction, validation, and upload handling.
 * All file paths are resolved relative to storage/tenants/{tenant_id}/.
 */

class TenantFile
{
    private const STORAGE_ROOT = __DIR__ . '/../storage/tenants';

    /**
     * Get the base storage directory for the given tenant.
     */
    public static function getBaseDir(int $tenantId): string
    {
        return self::STORAGE_ROOT . '/' . $tenantId;
    }

    /**
     * Get the base directory for the current tenant from session.
     */
    public static function getCurrentBaseDir(): string
    {
        return self::getBaseDir(Tenant::getId());
    }

    /**
     * Build a full filesystem path for a tenant file.
     *
     * @param int    $tenantId
     * @param string $subpath  Relative path within tenant storage (e.g. "courses/15/resources/file.mp4")
     * @return string          Absolute filesystem path
     */
    public static function getFullPath(int $tenantId, string $subpath): string
    {
        $subpath = self::normalizePath($subpath);
        return self::getBaseDir($tenantId) . '/' . $subpath;
    }

    /**
     * Build a web-relative path for a tenant file.
     * Returns something like "/file.php?type=material&id=123" for use in HTML.
     */
    public static function getFileUrl(string $type, int $id): string
    {
        return '/file.php?type=' . urlencode($type) . '&id=' . $id;
    }

    /**
     * Build a tenant-specific storage path for a new upload.
     *
     * @param int    $tenantId
     * @param string $category  "courses", "books", "receipts", "resources"
     * @param string $filename  The filename to use
     * @param int    $courseId  Optional course ID for course-related files
     * @return string           Relative path within tenant storage (for DB)
     */
    public static function buildPath(int $tenantId, string $category, string $filename, int $courseId = 0): string
    {
        $filename = self::sanitizeFilename($filename);

        switch ($category) {
            case 'courses':
                if ($courseId <= 0) {
                    throw new InvalidArgumentException("courseId required for courses category");
                }
                return "courses/{$courseId}/{$filename}";

            case 'books':
                return "books/{$filename}";

            case 'receipts':
                return "receipts/{$filename}";

            case 'resources':
                return "resources/{$filename}";

            case 'logo':
                return "logo/{$filename}";

            default:
                throw new InvalidArgumentException("Unknown category: {$category}");
        }
    }

    /**
     * Validate and sanitize a filename.
     * Removes path traversal, null bytes, and dangerous characters.
     */
    public static function sanitizeFilename(string $filename): string
    {
        // Remove directory components
        $filename = basename($filename);

        // Remove null bytes
        $filename = str_replace(chr(0), '', $filename);

        // Replace potentially dangerous characters
        $filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $filename);

        // Ensure it's not empty
        if (empty($filename) || $filename === '.' || $filename === '..') {
            $filename = 'file_' . bin2hex(random_bytes(4));
        }

        return $filename;
    }

    /**
     * Normalize a path to prevent traversal.
     * Removes ../, //, leading slashes, etc.
     */
    public static function normalizePath(string $path): string
    {
        // Remove null bytes
        $path = str_replace(chr(0), '', $path);

        // Remove leading slashes
        $path = ltrim($path, '/');

        // Normalize separators
        $path = str_replace('\\', '/', $path);

        // Remove double slashes
        while (strpos($path, '//') !== false) {
            $path = str_replace('//', '/', $path);
        }

        // Resolve .. components
        $parts = [];
        foreach (explode('/', $path) as $part) {
            if ($part === '..' || $part === '.') {
                continue;
            }
            $parts[] = $part;
        }

        return implode('/', $parts);
    }

    /**
     * Validate that a resolved path stays within the tenant directory or safe allowed storage.
     * Returns true if safe and exists, false otherwise.
     */
    public static function validatePath(int $tenantId, string $subpath): bool
    {
        return self::resolveFile($tenantId, $subpath) !== false;
    }

    /**
     * Validate and move an uploaded file to tenant-specific storage.
     *
     * @param array  $file       $_FILES entry
     * @param int    $tenantId
     * @param string $category
     * @param int    $courseId
     * @param array  $allowedExtensions
     * @return array|null       ['fullPath' => ..., 'relativePath' => ...] or null on failure
     */
    public static function handleUpload(
        array $file,
        int $tenantId,
        string $category,
        int $courseId = 0,
        array $allowedExtensions = []
    ): ?array {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            error_log("[TenantFile] Upload error: {$file['error']}");
            return null;
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!empty($allowedExtensions) && !in_array($ext, $allowedExtensions)) {
            error_log("[TenantFile] Extension not allowed: {$ext}");
            return null;
        }

        // MIME type validation
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);

        $blockedMimes = ['application/x-php', 'application/x-httpd-php', 'text/x-script.php'];
        if (in_array($mimeType, $blockedMimes)) {
            error_log("[TenantFile] Blocked MIME type: {$mimeType}");
            return null;
        }

        $unique = uniqid('f_', true) . '.' . $ext;
        $relativePath = self::buildPath($tenantId, $category, $unique, $courseId);
        $fullPath = self::getFullPath($tenantId, $relativePath);

        // Create directory if needed
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0777, true)) {
                error_log("[TenantFile] Failed to create directory: {$dir}");
                return null;
            }
            chmod($dir, 0777);
        }

        $moved = false;
        if (is_uploaded_file($file['tmp_name'])) {
            $moved = move_uploaded_file($file['tmp_name'], $fullPath);
        } else if (file_exists($file['tmp_name'])) {
            $moved = copy($file['tmp_name'], $fullPath);
        }

        if (!$moved) {
            error_log("[TenantFile] Failed to move/copy uploaded file to: {$fullPath}");
            return null;
        }

        @chmod($fullPath, 0666);

        return [
            'fullPath' => $fullPath,
            'relativePath' => $relativePath,
        ];
    }

    /**
     * Validate that a file belongs to the given tenant and resolve its full path.
     * Supports tenant-isolated storage, common path prefixes, course directory variations,
     * and safe legacy document root fallbacks without allowing directory traversal.
     * Returns the real absolute path if valid, false otherwise.
     */
    public static function resolveFile(int $tenantId, string $relativePath): string|false
    {
        if (empty($relativePath)) {
            return false;
        }

        $baseDir = realpath(self::getBaseDir($tenantId));
        if ($baseDir === false) {
            return false;
        }

        // Clean and normalize path
        $cleanPath = self::normalizePath($relativePath);

        // Strip redundant tenant storage prefixes if already included in path
        $prefixesToStrip = [
            "storage/tenants/{$tenantId}/",
            "uploads/tenants/{$tenantId}/",
            "storage/tenants/",
            "uploads/tenants/",
        ];
        foreach ($prefixesToStrip as $prefix) {
            if (strpos($cleanPath, $prefix) === 0) {
                $cleanPath = substr($cleanPath, strlen($prefix));
                break;
            }
        }

        // 1. Direct resolution in tenant storage
        $fullPath = self::getFullPath($tenantId, $cleanPath);
        if (file_exists($fullPath)) {
            $realPath = realpath($fullPath);
            if ($realPath !== false && strpos($realPath, $baseDir) === 0) {
                return $realPath;
            }
        }

        // 2. Course directory resolution variations (e.g. uploads/courses/course_101/lesson_1.mp4 -> courses/101/lesson_1.mp4)
        if (preg_match('#(?:uploads/)?courses/(?:course_)?(\d+)/(.+)$#', $relativePath, $m)) {
            $cId = (int)$m[1];
            $fname = self::sanitizeFilename($m[2]);

            // Try storage/tenants/{tenantId}/courses/{courseId}/{filename}
            $candidate = self::getBaseDir($tenantId) . "/courses/{$cId}/{$fname}";
            if (file_exists($candidate)) {
                $realCandidate = realpath($candidate);
                if ($realCandidate !== false && strpos($realCandidate, $baseDir) === 0) {
                    return $realCandidate;
                }
            }

            // Try without underscores (e.g. lesson_1.mp4 -> lesson1.mp4)
            $altName = str_replace('_', '', $fname);
            if ($altName !== $fname) {
                $candidateAlt = self::getBaseDir($tenantId) . "/courses/{$cId}/{$altName}";
                if (file_exists($candidateAlt)) {
                    $realCandidateAlt = realpath($candidateAlt);
                    if ($realCandidateAlt !== false && strpos($realCandidateAlt, $baseDir) === 0) {
                        return $realCandidateAlt;
                    }
                }
            }
        }

        // 3. Legacy project root assets fallback (e.g. /materials/..., /books/...)
        $docRoot = realpath(__DIR__ . '/..');
        if ($docRoot !== false) {
            $legacyPath = $docRoot . '/' . ltrim($relativePath, '/');
            if (file_exists($legacyPath)) {
                $realLegacy = realpath($legacyPath);
                if ($realLegacy !== false && strpos($realLegacy, $docRoot) === 0) {
                    return $realLegacy;
                }
            }
        }

        return false;
    }

    /**
     * Check if a tenant or legacy file exists on disk.
     */
    public static function fileExists(int $tenantId, ?string $relativePath): bool
    {
        if (empty($relativePath)) {
            return false;
        }
        return self::resolveFile($tenantId, $relativePath) !== false;
    }

    /**
     * Delete a file from tenant storage.
     */
    public static function deleteFile(int $tenantId, string $relativePath): bool
    {
        $resolved = self::resolveFile($tenantId, $relativePath);
        if ($resolved === false) {
            return false;
        }
        return unlink($resolved);
    }

    /**
     * Get the MIME type of a file.
     */
    public static function getMimeType(string $filePath): string
    {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        return $finfo->file($filePath);
    }

    /**
     * Get the file extension for a MIME type (fallback to 'bin').
     */
    public static function getExtensionFromMime(string $mimeType): string
    {
        $map = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'video/quicktime' => 'mov',
            'text/plain' => 'txt',
            'application/zip' => 'zip',
        ];
        return $map[$mimeType] ?? 'bin';
    }
}
