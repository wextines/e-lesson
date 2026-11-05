<?php
declare(strict_types=1);

/**
 * Public Tenant Landing API
 * Returns public branding data and active courses for the current resolved tenant.
 */

// Set secure headers immediately
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

try {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    require_once __DIR__ . '/../php/config.php';

    // The tenant is resolved automatically in config.php via Tenant::resolveFromRequest()
    $tenantId = Tenant::getId();
    $tenantData = Tenant::get();

    if (!$tenantData || empty($tenantData['is_active'])) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error'   => 'Educational center not found or inactive.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Query only active courses belonging to the current tenant
    $stmt = $pdo->prepare("
        SELECT 
            course_id,
            subject,
            title,
            description,
            price,
            classLevel
        FROM courses 
        WHERE tenant_id = ? AND is_active = 1
        ORDER BY course_id ASC
    ");
    $stmt->execute([$tenantId]);
    $rawCourses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format safe course payload
    $courses = array_map(function (array $c): array {
        return [
            'course_id'   => (int)$c['course_id'],
            'subject'     => $c['subject'] ?? '',
            'title'       => $c['title'] ?? '',
            'description' => $c['description'] ?? '',
            'price'       => (int)$c['price'],
            'classLevel'  => $c['classLevel'] ?? ''
        ];
    }, $rawCourses);

    // Assemble safe public response
    $response = [
        'success' => true,
        'tenant'  => [
            'tenant_id'        => (int)$tenantData['tenant_id'],
            'name'             => (string)($tenantData['name'] ?? 'e-Lesson'),
            'slug'             => (string)($tenantData['slug'] ?? ''),
            'logo'             => Tenant::getLogoUrl(),
            'primary_color'    => (string)($tenantData['primary_color'] ?? '#0a192f'),
            'secondary_color'  => (string)($tenantData['secondary_color'] ?? '#172a45'),
            'accent_color'     => (string)($tenantData['accent_color'] ?? '#64ffda'),
            'email'            => (string)($tenantData['email'] ?? ''),
            'phone'            => (string)($tenantData['phone'] ?? ''),
            'default_language' => (string)($tenantData['default_language'] ?? 'ru')
        ],
        'courses' => $courses
    ];

    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;

} catch (Throwable $e) {
    error_log('[API tenant-landing] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Internal server error. Please try again later.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
