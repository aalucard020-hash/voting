
<?php
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

// Memeriksa apakah pengguna sudah login
function sudahLogin(): bool
{
    return isset($_SESSION['user_id']);
}

// Melindungi halaman yang memerlukan login
function wajibLogin(): void
{
    if (!sudahLogin()) {
        header("Location: ../auth/login.php");
        exit;
    }
}

// Melindungi halaman khusus admin
function wajibAdmin(): void
{
    wajibLogin();

    if (($_SESSION['role'] ?? '') !== 'admin') {
        header("Location: ../index.php");
        exit;
    }
}

// Melindungi halaman khusus pemilih
function wajibPemilih(): void
{
    wajibLogin();

    if (($_SESSION['role'] ?? '') !== 'pemilih') {
        header("Location: ../admin/dashboard.php");
        exit;
    }
}
?>
