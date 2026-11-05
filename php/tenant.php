<?php
declare(strict_types=1);

/**
 * e-Lesson Tenant Helper
 *
 * Resolves tenant from HTTP_HOST (subdomain or custom domain),
 * then falls back to session, then to default tenant #1.
 */

class Tenant
{
    private static ?array $currentTenant = null;
    private static ?int $currentTenantId = null;
    private static bool $resolvedFromHost = false;

    /**
     * Get the current tenant ID.
     * Priority: static cache > host resolution > session > default (1).
     * Note: does NOT call get() to avoid circular dependency.
     */
    public static function getId(): int
    {
        if (self::$currentTenantId !== null) {
            return self::$currentTenantId;
        }

        // Priority 1: from session (set during auth or explicitly)
        if (isset($_SESSION['tenant_id']) && is_numeric($_SESSION['tenant_id'])) {
            self::$currentTenantId = (int) $_SESSION['tenant_id'];
            return self::$currentTenantId;
        }

        // Priority 2: fallback to default tenant #1
        self::$currentTenantId = 1;
        return self::$currentTenantId;
    }

    /**
     * Resolve tenant from the current HTTP request hostname.
     * Must be called after PDO is available.
     * Resolution order:
     *   1. Custom domain match (tenants.domain = HTTP_HOST)
     *   2. Subdomain match ({slug}.e-lesson.uz → tenants.slug)
     *   3. Fallback to default tenant #1
     *
     * @return int The resolved tenant_id
     */
    public static function resolveFromRequest(): int
    {
        if (self::$resolvedFromHost) {
            return self::getId();
        }

        self::$resolvedFromHost = true;

        $tenantId = self::resolveFromHost();

        if ($tenantId > 0) {
            self::$currentTenantId = $tenantId;
            // Sync to session for downstream code that reads $_SESSION['tenant_id']
            $_SESSION['tenant_id'] = $tenantId;
        }

        return self::getId();
    }

    /**
     * Resolve tenant_id from HTTP_HOST.
     * Returns tenant_id or 0 if no match.
     */
    private static function resolveFromHost(): int
    {
        global $pdo;
        if (!$pdo) {
            return 0;
        }

        $hostname = self::getHostname();
        if ($hostname === '') {
            return 0;
        }

        // Local development — no host-based resolution, use default tenant
        if ($hostname === 'localhost' || $hostname === '127.0.0.1' || $hostname === '::1') {
            return 0;
        }

        try {
            // 1. Check custom domain match
            $stmt = $pdo->prepare("SELECT tenant_id FROM tenants WHERE domain = ? AND is_active = 1");
            $stmt->execute([$hostname]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return (int) $row['tenant_id'];
            }

            // 2. Check subdomain match: {slug}.e-lesson.uz
            $baseDomain = 'e-lesson.uz';
            if (str_ends_with($hostname, '.' . $baseDomain)) {
                $slug = substr($hostname, 0, -(strlen($baseDomain) + 1));
                if ($slug !== '' && preg_match('/^[a-z0-9\-]+$/', $slug)) {
                    $stmt = $pdo->prepare("SELECT tenant_id FROM tenants WHERE slug = ? AND is_active = 1");
                    $stmt->execute([$slug]);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($row) {
                        return (int) $row['tenant_id'];
                    }
                }
            }
        } catch (PDOException $e) {
            error_log('[TENANT] Host resolution error: ' . $e->getMessage());
        }

        return 0;
    }

    /**
     * Extract hostname from HTTP request, stripping port.
     */
    private static function getHostname(): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if ($host === '') {
            $host = $_SERVER['SERVER_NAME'] ?? '';
        }
        // Strip port if present (e.g. "localhost:8080" → "localhost")
        if (str_contains($host, ':')) {
            $host = explode(':', $host, 2)[0];
        }
        return strtolower(trim($host));
    }

    /**
     * Get the current tenant data from the database.
     */
    public static function get(): ?array
    {
        if (self::$currentTenant !== null) {
            return self::$currentTenant;
        }

        global $pdo;
        if (!$pdo) {
            return null;
        }

        $tenantId = self::getId();

        try {
            $stmt = $pdo->prepare("SELECT * FROM tenants WHERE tenant_id = ? AND is_active = 1");
            $stmt->execute([$tenantId]);
            self::$currentTenant = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            return self::$currentTenant;
        } catch (PDOException $e) {
            error_log('[TENANT] Failed to load tenant: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get a config value for the current tenant.
     * Falls back to default if key not found.
     */
    public static function getConfig(string $key, string $default = ''): string
    {
        global $pdo;
        if (!$pdo) {
            return $default;
        }

        $tenantId = self::getId();

        try {
            $stmt = $pdo->prepare("SELECT config_value FROM tenant_config WHERE tenant_id = ? AND config_key = ?");
            $stmt->execute([$tenantId, $key]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? ($row['config_value'] ?? $default) : $default;
        } catch (PDOException $e) {
            return $default;
        }
    }

    /**
     * Set a config value for the current tenant, creating the key if absent.
     * Scoped to self::getId() — a config key can never be written into another tenant.
     */
    public static function setConfig(string $key, string $value): bool
    {
        global $pdo;
        if (!$pdo) {
            return false;
        }

        try {
            $stmt = $pdo->prepare(
                "INSERT INTO tenant_config (tenant_id, config_key, config_value)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)"
            );
            return $stmt->execute([self::getId(), $key, $value]);
        } catch (PDOException $e) {
            error_log('[Tenant] setConfig() failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get tenant name.
     */
    public static function getName(): string
    {
        $tenant = self::get();
        return $tenant ? ($tenant['name'] ?? 'e-Lesson') : 'e-Lesson';
    }

    /**
     * Get tenant secondary color.
     */
    public static function getSecondaryColor(): string
    {
        $tenant = self::get();
        return $tenant ? ($tenant['secondary_color'] ?? '#172a45') : '#172a45';
    }

    /**
     * Get tenant accent color.
     */
    public static function getAccentColor(): string
    {
        $tenant = self::get();
        return $tenant ? ($tenant['accent_color'] ?? '#64ffda') : '#64ffda';
    }

    /**
     * Get tenant primary color.
     */
    public static function getPrimaryColor(): string
    {
        $tenant = self::get();
        return $tenant ? ($tenant['primary_color'] ?? '#0a192f') : '#0a192f';
    }

    /**
     * Get tenant logo path.
     */
    public static function getLogoPath(): ?string
    {
        $tenant = self::get();
        return $tenant ? ($tenant['logo_path'] ?? null) : null;
    }

    /**
     * Get tenant email.
     */
    public static function getEmail(): string
    {
        $tenant = self::get();
        return $tenant ? ($tenant['email'] ?? '') : '';
    }

    /**
     * Get tenant phone.
     */
    public static function getPhone(): string
    {
        $tenant = self::get();
        return $tenant ? ($tenant['phone'] ?? '') : '';
    }

    /**
     * Get tenant timezone.
     */
    public static function getTimezone(): string
    {
        $tenant = self::get();
        return $tenant ? ($tenant['timezone'] ?? 'Asia/Tashkent') : 'Asia/Tashkent';
    }

    /**
     * Get tenant default language.
     */
    public static function getDefaultLanguage(): string
    {
        $tenant = self::get();
        return $tenant ? ($tenant['default_language'] ?? 'uz') : 'uz';
    }

    /**
     * Get the logo URL or null if no logo.
     */
    public static function getLogoUrl(): ?string
    {
        $path = self::getLogoPath();
        if ($path && file_exists(__DIR__ . '/..' . $path)) {
            return $path;
        }
        return null;
    }

    /**
     * Get the base storage directory for the current tenant.
     */
    public static function getStorageDir(): string
    {
        return __DIR__ . '/../storage/tenants/' . self::getId();
    }

    /**
     * Get the storage base directory for a specific tenant ID.
     */
    public static function getStorageDirFor(int $tenantId): string
    {
        return __DIR__ . '/../storage/tenants/' . $tenantId;
    }

    /**
     * Generate CSS variables string for current tenant branding.
     * Used to inject into <style> blocks.
     */
    public static function getBrandingCSS(): string
    {
        $primary = self::getPrimaryColor();
        $secondary = self::getSecondaryColor();
        $accent = self::getAccentColor();
        return "--primary-color: {$primary}; --primary-color-blur: {$primary}a8; --secondary-color: {$secondary}; --accent-color: {$accent}; --accent-color-hover: {$accent}e6;";
    }

    /**
     * Set the current tenant (for testing or forced context).
     */
    public static function set(int $tenantId): void
    {
        self::$currentTenantId = $tenantId;
        self::$currentTenant = null;
    }

    /**
     * Reset tenant context (for testing).
     */
    public static function reset(): void
    {
        self::$currentTenantId = null;
        self::$currentTenant = null;
    }
}
