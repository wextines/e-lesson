<?php
function transliterate($text)
{
    $map = [
        'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D', 'Е' => 'E',
        'Ё' => 'Yo', 'Ж' => 'Zh', 'З' => 'Z', 'И' => 'I', 'Й' => 'Y', 'К' => 'K',
        'Л' => 'L', 'М' => 'M', 'Н' => 'N', 'О' => 'O', 'П' => 'P', 'Р' => 'R',
        'С' => 'S', 'Т' => 'T', 'У' => 'U', 'Ф' => 'F', 'Х' => 'X', 'Ц' => 'Ts',
        'Ч' => 'Ch', 'Ш' => 'Sh', 'Щ' => 'Sch', 'Ъ' => '', 'Ы' => 'Y', 'Ь' => '',
        'Э' => 'E', 'Ю' => 'Yu', 'Я' => 'Ya',
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e',
        'ё' => 'yo', 'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k',
        'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r',
        'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'x', 'ц' => 'ts',
        'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch', 'ъ' => '', 'ы' => 'y', 'ь' => '',
        'э' => 'e', 'ю' => 'yu', 'я' => 'ya'
    ];
    return strtolower(strtr($text, $map));
}

function generateSlug($pdo, $first_name, $last_name)
{
    $transliterated = transliterate($first_name . '-' . $last_name);
    $slug_base = preg_replace('/[^a-z0-9\-]+/', '-', $transliterated);
    $slug = trim($slug_base, '-');

    $unique_slug = $slug;
    $counter = 1;

    while (true) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE slug = ?");
        $stmt->execute([$unique_slug]);
        $count = $stmt->fetchColumn();

        if ($count == 0) {
            break;
        }

        $unique_slug = $slug . '-' . $counter;
        $counter++;
    }

    return $unique_slug;
}

function formatToUzPhone($input)
{
    $digits = preg_replace('/\D/', '', $input);
    return '+998' . $digits;
}

/**
 * Initialize PHP session with secure settings.
 */
function initSession()
{
    if (session_status() === PHP_SESSION_NONE) {
        $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                    || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);

        session_set_cookie_params([
            'lifetime' => 60 * 60 * 24 * 7, // 7 days
            'path' => '/',
            'httponly' => true,
            'secure' => $isSecure,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
    if (isset($_SESSION['tenant_id']) && is_numeric($_SESSION['tenant_id']) && class_exists('Tenant')) {
        Tenant::set((int)$_SESSION['tenant_id']);
    }
}

/**
 * Log in a user: store user data in session.
 */
function loginUser($userData)
{
    initSession();
    $_SESSION['user_id'] = $userData['user_id'];
    $_SESSION['first_name'] = $userData['first_name'];
    $_SESSION['last_name'] = $userData['last_name'];
    $_SESSION['slug'] = $userData['slug'];
    $_SESSION['email'] = $userData['email'];
    $_SESSION['phone'] = $userData['phone'] ?? '';
    $_SESSION['points'] = $userData['points'] ?? 0;
    $tId = !empty($userData['tenant_id']) ? (int)$userData['tenant_id'] : Tenant::getId();
    $_SESSION['tenant_id'] = $tId;
    if (class_exists('Tenant')) {
        Tenant::set($tId);
    }
}

/**
 * Check if user is logged in via session.
 */
function isLoggedIn()
{
    initSession();
    return isset($_SESSION['user_id']);
}

/**
 * Get current user data from session.
 */
function getCurrentUser()
{
    if (!isLoggedIn()) return null;
    return [
        'user_id' => $_SESSION['user_id'],
        'first_name' => $_SESSION['first_name'] ?? '',
        'last_name' => $_SESSION['last_name'] ?? '',
        'slug' => $_SESSION['slug'] ?? '',
        'email' => $_SESSION['email'] ?? '',
        'phone' => $_SESSION['phone'] ?? '',
        'points' => $_SESSION['points'] ?? 0,
    ];
}

/**
 * Log out: destroy session.
 */
function logoutUser()
{
    initSession();
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}

/**
 * Generate and return a CSRF token.
 */
if (!function_exists('csrfToken')) {
    function csrfToken()
    {
        initSession();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

/**
 * Validate a CSRF token from POST data.
 */
if (!function_exists('validateCsrfToken')) {
    function validateCsrfToken()
    {
        initSession();
        $token = $_POST['csrf_token'] ?? '';
        if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            http_response_code(403);
            die('CSRF token mismatch');
        }
    }
}

/**
 * Get the current user's ID from session, or 0 if not logged in.
 */
function getSessionUserId()
{
    initSession();
    return (int)($_SESSION['user_id'] ?? 0);
}

/**
 * Get the current user's slug from session, or empty string.
 */
function getSessionSlug()
{
    initSession();
    return $_SESSION['slug'] ?? '';
}
