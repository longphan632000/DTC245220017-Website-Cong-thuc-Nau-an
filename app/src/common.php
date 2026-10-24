<?php
// PHP/PDO thuần, dùng chung cho trang đọc và trang quản trị.
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
session_set_cookie_params(['httponly' => true, 'secure' => $https, 'samesite' => 'Lax', 'path' => '/']);
session_start();
header('Cache-Control: no-store');

function h($value) { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function text($key) { return trim(is_string($_POST[$key] ?? null) ? $_POST[$key] : ''); }
function chars($value) { return preg_match_all('/./us', $value); }
function fail($status, $message) {
    http_response_code($status);
    header('Content-Type: text/html; charset=UTF-8');
    exit(h($message));
}
function csrf_check() {
    if (!hash_equals($_SESSION['csrf'], text('csrf'))) fail(403, 'Biểu mẫu không hợp lệ. Vui lòng tải lại trang.');
}
function redirect_to($path, $message = '') {
    if ($message !== '') $_SESSION['flash'] = $message;
    header('Location: ' . $path, true, 303);
    exit;
}

$host = getenv('DB_HOST') ?: 'mysql';
$db = getenv('DB_NAME') ?: 'recipe_db';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", getenv('DB_USER') ?: 'recipe_user', getenv('DB_PASSWORD') ?: '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $e) {
    error_log('Kết nối MySQL thất bại: ' . $e->getMessage());
    fail(503, 'Chưa kết nối được cơ sở dữ liệu. Vui lòng thử lại sau.');
}

if (!isset($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));
$admin = null;
if (isset($_SESSION['admin_id'])) {
    if (time() - ($_SESSION['admin_last_seen'] ?? 0) > 1800) {
        unset($_SESSION['admin_id'], $_SESSION['admin_last_seen']);
    } else {
        // Quyền lấy từ DB, không lấy role/id do biểu mẫu gửi lên.
        $stmt = $pdo->prepare("SELECT id, username, fullname FROM users WHERE id=? AND role='admin'");
        $stmt->execute([$_SESSION['admin_id']]);
        $admin = $stmt->fetch() ?: null;
        if ($admin) $_SESSION['admin_last_seen'] = time();
        else unset($_SESSION['admin_id'], $_SESSION['admin_last_seen']);
    }
}
$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);
