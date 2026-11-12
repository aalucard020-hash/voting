<?php
require_once __DIR__ . '/../includes/app.php';

if (sudahLogin()) {
    redirectTo(($_SESSION['role'] ?? '') === 'admin' ? '../admin/dashboard.php' : '../pages/voting.php');
}

$pesan = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $pesan = 'Email atau password salah.';
    } else {
        $stmt = $conn->prepare(
            'SELECT id, nama, email, password, role, status
             FROM users WHERE email = ? LIMIT 1'
        );
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (
            $user &&
            ($user['status'] ?? 'Aktif') === 'Aktif' &&
            password_verify($password, $user['password'])
        ) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['nama'] = $user['nama'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            redirectTo($user['role'] === 'admin' ? '../admin/dashboard.php' : '../pages/voting.php');
        }

        $pesan = 'Email, password, atau status akun tidak valid.';
    }
}

renderHeader('Masuk', '', '..');
?>
<section class="auth-section">
    <div class="auth-card mx-auto">
        <div class="text-center mb-4">
            <div class="brand-icon mx-auto mb-3">KS</div>
            <span class="section-label">SELAMAT DATANG KEMBALI</span>
            <h1 class="h2">Masuk ke akun Anda</h1>
            <p class="text-muted">Gunakan akun pemilih atau administrator.</p>
        </div>
        <?php if ($pesan !== ''): ?><div class="alert alert-danger"><?= h($pesan) ?></div><?php endif; ?>
        <form method="post">
            <?= csrfField() ?>
            <div class="mb-3">
                <label class="form-label" for="email">Email</label>
                <input class="form-control" id="email" name="email" type="email" maxlength="150" autocomplete="email" value="<?= h($_POST['email'] ?? '') ?>" required>
            </div>
            <div class="mb-4">
                <label class="form-label" for="password">Password</label>
                <input class="form-control" id="password" name="password" type="password" autocomplete="current-password" required>
            </div>
            <button class="btn btn-gold w-100" type="submit">Masuk</button>
        </form>
        <p class="text-center mt-4 mb-0">Belum punya akun? <a href="register.php">Daftar sebagai pemilih</a></p>
    </div>
</section>
<?php renderFooter('..'); ?>
