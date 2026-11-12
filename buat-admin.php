<?php
require_once __DIR__ . '/includes/app.php';

$adminExists = (int) $conn->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetch_row()[0] > 0;
$message = '';
$messageType = 'danger';

if (!$adminExists && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $name = trim($_POST['nama'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = trim($_POST['no_hp'] ?? '');
    $password = $_POST['password'] ?? '';

    if (
        $name === '' ||
        mb_strlen($name) > 100 ||
        !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        strlen($email) > 150 ||
        $phone === '' ||
        strlen($phone) > 20 ||
        strlen($password) < 8
    ) {
        $message = 'Periksa kembali nama, email, nomor HP, dan password (minimal 8 karakter).';
    } else {
        try {
            $conn->begin_transaction();
            $check = $conn->query(
                "SELECT id FROM users WHERE role = 'admin' LIMIT 1 FOR UPDATE"
            );
            if ($check->num_rows > 0) {
                $conn->rollback();
                $adminExists = true;
                $message = 'Akun administrator sudah tersedia.';
                $messageType = 'info';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare(
                    "INSERT INTO users (nama, email, no_hp, password, role, status)
                     VALUES (?, ?, ?, ?, 'admin', 'Aktif')"
                );
                $stmt->bind_param('ssss', $name, $email, $phone, $hash);
                $stmt->execute();
                $stmt->close();
                $conn->commit();
                $adminExists = true;
                $message = 'Akun administrator berhasil dibuat. Silakan masuk.';
                $messageType = 'success';
            }
        } catch (mysqli_sql_exception $exception) {
            $conn->rollback();
            error_log('Administrator setup failed: ' . $exception->getMessage());
            $message = (int) $exception->getCode() === 1062
                ? 'Email tersebut sudah terdaftar.'
                : 'Akun administrator gagal dibuat. Periksa log PHP untuk detail kesalahan.';
        }
    }
}

renderHeader('Buat Administrator');
?>
<section class="auth-section">
    <div class="auth-card mx-auto">
        <div class="text-center mb-4"><div class="brand-icon mx-auto mb-3">KS</div><span class="section-label">PENYIAPAN APLIKASI</span><h1 class="h2">Administrator</h1></div>
        <?php if ($message !== ''): ?><div class="alert alert-<?= h($messageType) ?>"><?= h($message) ?></div><?php endif; ?>
        <?php if (!$adminExists): ?>
            <p class="text-muted">Formulir ini hanya tersedia sebelum akun administrator pertama dibuat.</p>
            <form method="post">
                <?= csrfField() ?>
                <div class="mb-3"><label class="form-label" for="nama">Nama lengkap</label><input class="form-control" id="nama" name="nama" maxlength="100" required></div>
                <div class="mb-3"><label class="form-label" for="email">Email</label><input class="form-control" id="email" name="email" type="email" maxlength="150" required></div>
                <div class="mb-3"><label class="form-label" for="no_hp">Nomor HP</label><input class="form-control" id="no_hp" name="no_hp" maxlength="20" required></div>
                <div class="mb-4"><label class="form-label" for="password">Password (minimal 8 karakter)</label><input class="form-control" id="password" name="password" type="password" minlength="8" required></div>
                <button class="btn btn-gold w-100" type="submit">Buat administrator</button>
            </form>
        <?php else: ?>
            <p class="text-muted">Administrator sudah tersedia. Masuk untuk mengelola aplikasi.</p>
            <a class="btn btn-gold w-100" href="auth/login.php">Ke halaman masuk</a>
        <?php endif; ?>
    </div>
</section>
<?php renderFooter(); ?>
