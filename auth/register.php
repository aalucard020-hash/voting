<?php
require_once __DIR__ . '/../includes/app.php';

if (sudahLogin()) {
    redirectTo('../pages/voting.php');
}

$pesan = '';
$tipePesan = 'danger';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $nama = trim($_POST['nama'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $noHp = trim($_POST['no_hp'] ?? '');
    $password = $_POST['password'] ?? '';
    $konfirmasi = $_POST['konfirmasi_password'] ?? '';

    if (
        $nama === '' ||
        mb_strlen($nama) > 100 ||
        !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        strlen($email) > 150 ||
        $noHp === '' ||
        strlen($noHp) > 20
    ) {
        $pesan = 'Periksa kembali nama, email, dan nomor HP Anda.';
    } elseif (strlen($password) < 8) {
        $pesan = 'Password harus terdiri dari minimal 8 karakter.';
    } elseif ($password !== $konfirmasi) {
        $pesan = 'Konfirmasi password tidak cocok.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare(
            "INSERT INTO users (nama, email, no_hp, password, role, status)
             VALUES (?, ?, ?, ?, 'pemilih', 'Aktif')"
        );
        $stmt->bind_param('ssss', $nama, $email, $noHp, $hash);

        try {
            $stmt->execute();
            $pesan = 'Registrasi berhasil. Silakan masuk untuk memberikan suara.';
            $tipePesan = 'success';
        } catch (mysqli_sql_exception $exception) {
            if ((int) $exception->getCode() === 1062) {
                $pesan = 'Email tersebut sudah terdaftar.';
            } else {
                error_log('Voter registration failed: ' . $exception->getMessage());
                $pesan = 'Registrasi belum berhasil. Silakan coba kembali.';
            }
        } finally {
            $stmt->close();
        }
    }
}

renderHeader('Daftar Pemilih', '', '..');
?>
<section class="auth-section">
    <div class="auth-card mx-auto">
        <div class="text-center mb-4">
            <div class="brand-icon mx-auto mb-3">KS</div>
            <span class="section-label">MENJADI BAGIAN DARI PEMILIHAN</span>
            <h1 class="h2">Daftar akun pemilih</h1>
            <p class="text-muted">Akun digunakan untuk memilih satu Kakang dan satu Senduk.</p>
        </div>
        <div class="alert alert-<?= h($tipePesan) ?> <?= $pesan === '' ? 'd-none' : '' ?>"><?= h($pesan) ?></div>
        <form method="post">
            <?= csrfField() ?>
            <div class="mb-3"><label class="form-label" for="nama">Nama lengkap</label><input class="form-control" id="nama" name="nama" maxlength="100" autocomplete="name" value="<?= h($_POST['nama'] ?? '') ?>" required></div>
            <div class="mb-3"><label class="form-label" for="email">Email</label><input class="form-control" id="email" name="email" type="email" maxlength="150" autocomplete="email" value="<?= h($_POST['email'] ?? '') ?>" required></div>
            <div class="mb-3"><label class="form-label" for="no_hp">Nomor HP</label><input class="form-control" id="no_hp" name="no_hp" type="tel" maxlength="20" autocomplete="tel" value="<?= h($_POST['no_hp'] ?? '') ?>" required></div>
            <div class="mb-3"><label class="form-label" for="password">Password</label><input class="form-control" id="password" name="password" type="password" minlength="8" autocomplete="new-password" required></div>
            <div class="mb-4"><label class="form-label" for="konfirmasi_password">Konfirmasi password</label><input class="form-control" id="konfirmasi_password" name="konfirmasi_password" type="password" minlength="8" autocomplete="new-password" required></div>
            <button class="btn btn-gold w-100" type="submit">Daftar sekarang</button>
        </form>
        <p class="text-center mt-4 mb-0">Sudah punya akun? <a href="login.php">Masuk di sini</a></p>
    </div>
</section>
<?php renderFooter('..'); ?>
