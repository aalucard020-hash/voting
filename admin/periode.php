<?php
require_once __DIR__ . '/../includes/app.php';
requireAdmin();

$editId = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT);
$editing = null;
if ($editId && $editId > 0) {
    $stmt = $conn->prepare('SELECT * FROM periode_voting WHERE id = ?');
    $stmt->bind_param('i', $editId);
    $stmt->execute();
    $editing = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$periods = $conn->query(
    'SELECT p.*,
            (SELECT COUNT(*) FROM kontestan k WHERE k.periode_id = p.id) AS jumlah_kontestan,
            (SELECT COUNT(*) FROM voting v WHERE v.periode_id = p.id) AS jumlah_suara
     FROM periode_voting p ORDER BY p.id DESC'
)->fetch_all(MYSQLI_ASSOC);

renderHeader('Periode Voting', 'admin', '..');
?>
<section class="page-hero admin-hero"><div class="container"><span class="section-label">ADMINISTRATOR</span><h1 class="section-title text-white mb-2">Periode <span>voting.</span></h1><p class="text-white-50 mb-0">Hanya periode berstatus aktif dan berada dalam rentang tanggal yang membuka voting.</p></div></section>
<section class="page-section"><div class="container"><div class="row g-4">
    <div class="col-lg-5"><div class="admin-form-card">
        <h2 class="h4"><?= $editing ? 'Ubah periode' : 'Buat periode' ?></h2>
        <form action="../proses/admin.php" method="post">
            <?= csrfField() ?><input type="hidden" name="action" value="period_save"><input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
            <div class="mb-3"><label class="form-label" for="nama_periode">Nama periode</label><input class="form-control" id="nama_periode" name="nama_periode" maxlength="150" value="<?= h($editing['nama_periode'] ?? '') ?>" placeholder="Pemilihan Kakang Senduk 2026" required></div>
            <div class="mb-3"><label class="form-label" for="tanggal_mulai">Waktu mulai</label><input class="form-control" id="tanggal_mulai" name="tanggal_mulai" type="datetime-local" value="<?= !empty($editing['tanggal_mulai']) ? h(date('Y-m-d\TH:i', strtotime($editing['tanggal_mulai']))) : '' ?>" required></div>
            <div class="mb-3"><label class="form-label" for="tanggal_selesai">Waktu selesai</label><input class="form-control" id="tanggal_selesai" name="tanggal_selesai" type="datetime-local" value="<?= !empty($editing['tanggal_selesai']) ? h(date('Y-m-d\TH:i', strtotime($editing['tanggal_selesai']))) : '' ?>" required></div>
            <div class="mb-4"><label class="form-label" for="status">Status</label><select class="form-select" id="status" name="status"><?php foreach (['Persiapan', 'Aktif', 'Selesai', 'Nonaktif'] as $status): ?><option value="<?= h($status) ?>" <?= ($editing['status'] ?? 'Persiapan') === $status ? 'selected' : '' ?>><?= h($status) ?></option><?php endforeach; ?></select><small class="form-text">Mengaktifkan periode akan menonaktifkan periode lain.</small></div>
            <button class="btn btn-gold" type="submit">Simpan periode</button><?php if ($editing): ?> <a class="btn btn-outline-secondary" href="periode.php">Batal</a><?php endif; ?>
        </form>
    </div></div>
    <div class="col-lg-7"><h2 class="section-title h3 mb-3">Semua periode</h2><div class="admin-table-wrap table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Periode</th><th>Jadwal</th><th>Status</th><th>Data</th><th>Aksi</th></tr></thead><tbody>
        <?php if (!$periods): ?><tr><td colspan="5" class="text-center text-muted py-4">Belum ada periode voting.</td></tr><?php endif; ?>
        <?php foreach ($periods as $period): ?><tr><td class="fw-semibold"><?= h($period['nama_periode']) ?></td><td><?= h(date('d M Y H:i', strtotime($period['tanggal_mulai']))) ?><small class="d-block text-muted">s.d. <?= h(date('d M Y H:i', strtotime($period['tanggal_selesai']))) ?></small></td><td><span class="status-badge"><?= h($period['status']) ?></span></td><td><?= (int) $period['jumlah_kontestan'] ?> kontestan<br><?= (int) $period['jumlah_suara'] ?> suara</td><td><div class="d-flex gap-2"><a class="btn btn-sm btn-outline-maroon" href="periode.php?edit=<?= (int) $period['id'] ?>">Edit</a><form action="../proses/admin.php" method="post" onsubmit="return confirm('Hapus periode ini? Periode yang masih memiliki kontestan atau suara tidak dapat dihapus.');"><?= csrfField() ?><input type="hidden" name="action" value="period_delete"><input type="hidden" name="id" value="<?= (int) $period['id'] ?>"><button class="btn btn-sm btn-outline-danger" type="submit">Hapus</button></form></div></td></tr><?php endforeach; ?>
    </tbody></table></div></div>
</div></div></section>
<?php renderFooter('..'); ?>
