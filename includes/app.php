<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/koneksi.php';

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrfToken()) . '">';
}

function verifyCsrf(): void
{
    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token) ||
        empty($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        http_response_code(403);
        exit('Permintaan tidak valid. Muat ulang halaman, lalu coba lagi.');
    }
}

function setFlash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function takeFlash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);

    return is_array($flash) ? $flash : null;
}

function redirectTo(string $location): never
{
    header('Location: ' . $location);
    exit;
}

function activeVotingPeriod(mysqli $conn): ?array
{
    $result = $conn->query(
        "SELECT * FROM periode_voting
         WHERE status = 'Aktif'
           AND NOW() BETWEEN tanggal_mulai AND tanggal_selesai
         ORDER BY id DESC
         LIMIT 1"
    );

    $period = $result->fetch_assoc();

    return $period ?: null;
}

function requireVoter(): void
{
    wajibLogin();

    if (($_SESSION['role'] ?? '') !== 'pemilih') {
        redirectTo('../admin/dashboard.php');
    }
}

function requireAdmin(): void
{
    wajibAdmin();
}

function renderHeader(string $title, string $active = '', string $root = ''): void
{
    $flash = takeFlash();
    $isAdmin = ($_SESSION['role'] ?? '') === 'admin';
    $prefix = $root === '' ? '.' : $root;
    $home = $prefix . '/index.php';
    $candidates = $prefix . '/pages/kontestan.php';
    $results = $prefix . '/pages/klasemen.php';
    $voting = $prefix . '/pages/voting.php';
    $admin = $prefix . '/admin/dashboard.php';
    $login = $prefix . '/auth/login.php';
    $register = $prefix . '/auth/register.php';
    $logout = $prefix . '/auth/logout.php';
    $css = $prefix . '/assets/css/style.css';
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= h($title) ?> | Kakang Senduk Ponorogo</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="<?= h($css) ?>">
    </head>
    <body>
    <nav class="navbar navbar-expand-lg navbar-dark custom-navbar">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="<?= h($home) ?>">
                <span class="brand-icon">KS</span>
                <span><strong>KAKANG SENDUK</strong><small class="d-block">PONOROGO</small></span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMenu" aria-label="Buka navigasi">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarMenu">
                <div class="navbar-nav mx-auto">
                    <a class="nav-link <?= $active === 'home' ? 'active' : '' ?>" href="<?= h($home) ?>">Beranda</a>
                    <a class="nav-link <?= $active === 'kontestan' ? 'active' : '' ?>" href="<?= h($candidates) ?>">Kontestan</a>
                    <a class="nav-link <?= $active === 'klasemen' ? 'active' : '' ?>" href="<?= h($results) ?>">Klasemen</a>
                    <?php if ($isAdmin): ?>
                        <a class="nav-link <?= $active === 'admin' ? 'active' : '' ?>" href="<?= h($admin) ?>">Admin</a>
                    <?php elseif (!empty($_SESSION['user_id'])): ?>
                        <a class="nav-link <?= $active === 'voting' ? 'active' : '' ?>" href="<?= h($voting) ?>">Voting</a>
                    <?php endif; ?>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <?php if (!empty($_SESSION['user_id'])): ?>
                        <span class="text-white-50 small"><?= h($_SESSION['nama'] ?? '') ?></span>
                        <form method="post" action="<?= h($logout) ?>" class="m-0">
                            <?= csrfField() ?>
                            <button class="btn btn-outline-light btn-sm" type="submit">Keluar</button>
                        </form>
                    <?php else: ?>
                        <a class="btn btn-outline-light btn-sm" href="<?= h($login) ?>">Masuk</a>
                        <a class="btn btn-gold btn-sm" href="<?= h($register) ?>">Daftar</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>
    <main class="min-vh-75">
        <?php if ($flash): ?>
            <div class="container pt-4">
                <div class="alert alert-<?= h($flash['type'] ?? 'info') ?> alert-dismissible fade show" role="alert">
                    <?= h($flash['message'] ?? '') ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
                </div>
            </div>
        <?php endif; ?>
    <?php
}

function renderFooter(string $root = ''): void
{
    $prefix = $root === '' ? '.' : $root;
    ?>
    </main>
    <footer class="footer mt-auto">
        <div class="container d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
            <div><strong>KAKANG SENDUK PONOROGO</strong><p class="mb-0">Sistem Informasi Pemilihan Duta Wisata Daerah.</p></div>
            <div class="footer-copy">© <?= date('Y') ?> Pemilihan Kakang Senduk Ponorogo.</div>
        </div>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= h($prefix . '/assets/js/script.js') ?>"></script>
    </body>
    </html>
    <?php
}
