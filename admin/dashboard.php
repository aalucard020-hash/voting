
<?php
require_once __DIR__ . '/../includes/app.php';
requireAdmin();

$stats = [];
foreach ([
    'pemilih' => "SELECT COUNT(*) FROM users WHERE role = 'pemilih'",
    'kontestan' => "SELECT COUNT(*) FROM kontestan WHERE status = 'Aktif'",
    'suara' => 'SELECT COUNT(*) FROM voting',
    'periode' => "SELECT COUNT(*) FROM periode_voting WHERE status = 'Aktif'",
] as $key => $query) {
    $stats[$key] = (int) $conn->query($query)->fetch_row()[0];
}

$periods = $conn->query(
    'SELECT p.id, p.nama_periode, p.status, p.tanggal_mulai, p.tanggal_selesai,
            (SELECT COUNT(*) FROM kontestan k WHERE k.periode_id = p.id) AS jumlah_kontestan,
            (SELECT COUNT(*) FROM voting v WHERE v.periode_id = p.id) AS jumlah_suara
     FROM periode_voting p ORDER BY p.id DESC LIMIT 5'
)->fetch_all(MYSQLI_ASSOC);

renderHeader('Dashboard Administrator', 'admin', '..');
?>
<section class="page-hero admin-hero">
    <div class="container"><span class="section-label">PUSAT PENGELOLAAN</span><h1 class="section-title text-white mb-2">Dashboard <span>administrator.</span></h1><p class="text-white-50 mb-0">Selamat datang, <?= h($_SESSION['nama'] ?? 'Administrator') ?>.</p></div>
</section>
<section class="page-section">
    <div class="container">
        <div class="row g-3 mb-5">
            <?php foreach (['pemilih' => 'Akun pemilih', 'kontestan' => 'Kontestan aktif', 'suara' => 'Suara masuk', 'periode' => 'Periode aktif'] as $key => $label): ?>
                <div class="col-6 col-xl-3"><div class="stat-card"><span><?= h($label) ?></span><strong><?= number_format($stats[$key]) ?></strong></div></div>
            <?php endforeach; ?>
        </div>
        <div class="row g-4">
            <?php foreach ([
                ['kontestan.php', 'Kelola kontestan', 'Tambah profil, unggah foto, atau perbarui data kontestan.'],
                ['pemilih.php', 'Akun pemilih', 'Lihat akun terdaftar dan atur status akses pemilih.'],
                ['periode.php', 'Periode voting', 'Atur jadwal pemilihan dan periode yang sedang aktif.'],
                ['hasil.php', 'Hasil dan grafik', 'Pantau klasemen suara menurut kategori dan periode.'],
            ] as [$path, $title, $description]): ?>
                <div class="col-md-6"><a class="admin-link-card" href="<?= h($path) ?>"><span class="section-label">PENGELOLAAN</span><h2 class="h4"><?= h($title) ?></h2><p><?= h($description) ?></p><span class="text-link">Buka halaman →</span></a></div>
            <?php endforeach; ?>
        </div>
        <div class="mt-5">
            <div class="d-flex justify-content-between align-items-center mb-3"><h2 class="section-title h3 mb-0">Periode terbaru</h2><a href="periode.php" class="text-link">Kelola periode</a></div>
            <div class="admin-table-wrap table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Periode</th><th>Status</th><th>Kontestan</th><th>Suara</th><th>Jadwal</th></tr></thead><tbody>
                <?php if (!$periods): ?><tr><td colspan="5" class="text-center text-muted py-4">Belum ada periode voting.</td></tr><?php endif; ?>
                <?php foreach ($periods as $period): ?><tr><td class="fw-semibold"><?= h($period['nama_periode']) ?></td><td><span class="status-badge"><?= h($period['status']) ?></span></td><td><?= (int) $period['jumlah_kontestan'] ?></td><td><?= (int) $period['jumlah_suara'] ?></td><td><?= h(date('d M Y', strtotime($period['tanggal_mulai']))) ?> – <?= h(date('d M Y', strtotime($period['tanggal_selesai']))) ?></td></tr><?php endforeach; ?>
            </tbody></table></div>
        </div>
    </div>
</section>
<?php renderFooter('..'); ?>
