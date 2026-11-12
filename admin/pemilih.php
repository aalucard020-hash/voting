<?php
require_once __DIR__ . '/../includes/app.php';
requireAdmin();

$search = trim($_GET['q'] ?? '');
if ($search !== '') {
    $pattern = '%' . $search . '%';
    $stmt = $conn->prepare(
        "SELECT u.id, u.nama, u.email, u.no_hp, u.status, u.created_at,
                (SELECT COUNT(*) FROM voting v WHERE v.user_id = u.id) AS jumlah_suara
         FROM users u WHERE u.role = 'pemilih'
           AND (u.nama LIKE ? OR u.email LIKE ? OR u.no_hp LIKE ?)
         ORDER BY u.created_at DESC LIMIT 500"
    );
    $stmt->bind_param('sss', $pattern, $pattern, $pattern);
    $stmt->execute();
    $voters = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $voters = $conn->query(
        "SELECT u.id, u.nama, u.email, u.no_hp, u.status, u.created_at,
                (SELECT COUNT(*) FROM voting v WHERE v.user_id = u.id) AS jumlah_suara
         FROM users u WHERE u.role = 'pemilih'
         ORDER BY u.created_at DESC LIMIT 500"
    )->fetch_all(MYSQLI_ASSOC);
}

renderHeader('Manajemen Pemilih', 'admin', '..');
?>
<section class="page-hero admin-hero"><div class="container"><span class="section-label">ADMINISTRATOR</span><h1 class="section-title text-white mb-2">Manajemen <span>pemilih.</span></h1><p class="text-white-50 mb-0">Tinjau pendaftaran dan kendalikan status akses akun pemilih.</p></div></section>
<section class="page-section"><div class="container">
    <form method="get" class="row g-2 mb-4"><div class="col-md-5"><label class="form-label" for="q">Cari nama, email, atau nomor HP</label><input class="form-control" id="q" name="q" value="<?= h($search) ?>" placeholder="Ketik kata kunci"></div><div class="col-auto align-self-end"><button class="btn btn-outline-maroon" type="submit">Cari</button></div></form>
    <div class="admin-table-wrap table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Nama</th><th>Email</th><th>Nomor HP</th><th>Terdaftar</th><th>Suara</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
        <?php if (!$voters): ?><tr><td colspan="7" class="text-center text-muted py-4">Tidak ada akun pemilih ditemukan.</td></tr><?php endif; ?>
        <?php foreach ($voters as $voter): ?><tr><td class="fw-semibold"><?= h($voter['nama']) ?></td><td><?= h($voter['email']) ?></td><td><?= h($voter['no_hp']) ?></td><td><?= h(date('d M Y', strtotime($voter['created_at']))) ?></td><td><?= (int) $voter['jumlah_suara'] ?></td><td><span class="status-badge"><?= h($voter['status']) ?></span></td><td><form action="../proses/admin.php" method="post"><?= csrfField() ?><input type="hidden" name="action" value="voter_status"><input type="hidden" name="id" value="<?= (int) $voter['id'] ?>"><input type="hidden" name="status" value="<?= $voter['status'] === 'Aktif' ? 'Nonaktif' : 'Aktif' ?>"><button class="btn btn-sm <?= $voter['status'] === 'Aktif' ? 'btn-outline-danger' : 'btn-outline-success' ?>" type="submit"><?= $voter['status'] === 'Aktif' ? 'Nonaktifkan' : 'Aktifkan' ?></button></form></td></tr><?php endforeach; ?>
    </tbody></table></div><p class="form-text mt-2">Maksimal 500 akun ditampilkan per pencarian.</p>
</div></section>
<?php renderFooter('..'); ?>
