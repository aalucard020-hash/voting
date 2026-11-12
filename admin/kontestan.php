<?php
require_once __DIR__ . '/../includes/app.php';
requireAdmin();

$editId = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT);
$editing = null;
if ($editId && $editId > 0) {
    $stmt = $conn->prepare('SELECT * FROM kontestan WHERE id = ?');
    $stmt->bind_param('i', $editId);
    $stmt->execute();
    $editing = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$periods = $conn->query('SELECT id, nama_periode FROM periode_voting ORDER BY id DESC')->fetch_all(MYSQLI_ASSOC);
$contestants = $conn->query(
    'SELECT k.*, p.nama_periode,
            (SELECT COUNT(*) FROM voting v WHERE v.kontestan_id = k.id) AS jumlah_suara
     FROM kontestan k JOIN periode_voting p ON p.id = k.periode_id
     ORDER BY p.id DESC, k.kategori, k.nomor_peserta'
)->fetch_all(MYSQLI_ASSOC);

renderHeader('Kelola Kontestan', 'admin', '..');
?>
<section class="page-hero admin-hero"><div class="container"><span class="section-label">ADMINISTRATOR</span><h1 class="section-title text-white mb-2">Kelola <span>kontestan.</span></h1><p class="text-white-50 mb-0">Profil kontestan dan foto disimpan per periode pemilihan.</p></div></section>
<section class="page-section"><div class="container">
    <div class="row g-4">
        <div class="col-lg-5">
            <div class="admin-form-card">
                <h2 class="h4"><?= $editing ? 'Perbarui kontestan' : 'Tambah kontestan' ?></h2>
                <?php if (!$periods): ?><div class="alert alert-warning">Buat periode pemilihan terlebih dahulu.</div><?php else: ?>
                    <form action="../proses/admin.php" method="post" enctype="multipart/form-data">
                        <?= csrfField() ?><input type="hidden" name="action" value="contestant_save"><input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
                        <div class="mb-3"><label class="form-label" for="periode_id">Periode</label><select class="form-select" id="periode_id" name="periode_id" required><?php foreach ($periods as $period): ?><option value="<?= (int) $period['id'] ?>" <?= (int) ($editing['periode_id'] ?? 0) === (int) $period['id'] ? 'selected' : '' ?>><?= h($period['nama_periode']) ?></option><?php endforeach; ?></select></div>
                        <div class="row g-3 mb-3"><div class="col-5"><label class="form-label" for="nomor_peserta">Nomor peserta</label><input class="form-control" id="nomor_peserta" name="nomor_peserta" maxlength="20" value="<?= h($editing['nomor_peserta'] ?? '') ?>" required></div><div class="col-7"><label class="form-label" for="kategori">Kategori</label><select class="form-select" id="kategori" name="kategori" required><option value="Kakang" <?= ($editing['kategori'] ?? '') === 'Kakang' ? 'selected' : '' ?>>Kakang</option><option value="Senduk" <?= ($editing['kategori'] ?? '') === 'Senduk' ? 'selected' : '' ?>>Senduk</option></select></div></div>
                        <div class="mb-3"><label class="form-label" for="nama">Nama lengkap</label><input class="form-control" id="nama" name="nama" maxlength="100" value="<?= h($editing['nama'] ?? '') ?>" required></div>
                        <div class="mb-3"><label class="form-label" for="asal_instansi">Asal instansi</label><input class="form-control" id="asal_instansi" name="asal_instansi" maxlength="150" value="<?= h($editing['asal_instansi'] ?? '') ?>"></div>
                        <div class="mb-3"><label class="form-label" for="foto">Foto (JPG, PNG, WebP; maks. 3 MB)</label><input class="form-control" id="foto" name="foto" type="file" accept="image/jpeg,image/png,image/webp"><?php if (!empty($editing['foto'])): ?><small class="text-muted">Foto saat ini: <?= h($editing['foto']) ?></small><?php endif; ?></div>
                        <div class="row g-3 mb-3"><div class="col-sm-6"><label class="form-label" for="tempat_lahir">Tempat lahir</label><input class="form-control" id="tempat_lahir" name="tempat_lahir" maxlength="100" value="<?= h($editing['tempat_lahir'] ?? '') ?>"></div><div class="col-sm-6"><label class="form-label" for="tanggal_lahir">Tanggal lahir</label><input class="form-control" id="tanggal_lahir" name="tanggal_lahir" type="date" value="<?= h($editing['tanggal_lahir'] ?? '') ?>"></div></div>
                        <div class="mb-3"><label class="form-label" for="alamat">Domisili</label><input class="form-control" id="alamat" name="alamat" maxlength="255" value="<?= h($editing['alamat'] ?? '') ?>"></div>
                        <div class="row g-3 mb-3"><div class="col-sm-6"><label class="form-label" for="pendidikan">Pendidikan</label><input class="form-control" id="pendidikan" name="pendidikan" maxlength="150" value="<?= h($editing['pendidikan'] ?? '') ?>"></div><div class="col-sm-6"><label class="form-label" for="pekerjaan">Pekerjaan</label><input class="form-control" id="pekerjaan" name="pekerjaan" maxlength="100" value="<?= h($editing['pekerjaan'] ?? '') ?>"></div></div>
                        <div class="mb-3"><label class="form-label" for="visi">Visi</label><textarea class="form-control" id="visi" name="visi" rows="2"><?= h($editing['visi'] ?? '') ?></textarea></div>
                        <div class="mb-3"><label class="form-label" for="misi">Misi</label><textarea class="form-control" id="misi" name="misi" rows="2"><?= h($editing['misi'] ?? '') ?></textarea></div>
                        <div class="mb-3"><label class="form-label" for="deskripsi">Deskripsi</label><textarea class="form-control" id="deskripsi" name="deskripsi" rows="3"><?= h($editing['deskripsi'] ?? '') ?></textarea></div>
                        <div class="mb-4"><label class="form-label" for="status">Status</label><select class="form-select" id="status" name="status"><option value="Aktif" <?= ($editing['status'] ?? 'Aktif') === 'Aktif' ? 'selected' : '' ?>>Aktif</option><option value="Nonaktif" <?= ($editing['status'] ?? '') === 'Nonaktif' ? 'selected' : '' ?>>Nonaktif</option></select></div>
                        <button class="btn btn-gold" type="submit">Simpan kontestan</button><?php if ($editing): ?> <a class="btn btn-outline-secondary" href="kontestan.php">Batal</a><?php endif; ?>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="d-flex justify-content-between align-items-center mb-3"><h2 class="section-title h3 mb-0">Daftar kontestan</h2><span class="status-badge"><?= count($contestants) ?> total</span></div>
            <div class="admin-table-wrap table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Nama</th><th>Kategori / periode</th><th>Status</th><th>Suara</th><th>Aksi</th></tr></thead><tbody>
                <?php if (!$contestants): ?><tr><td colspan="5" class="text-center text-muted py-4">Belum ada kontestan.</td></tr><?php endif; ?>
                <?php foreach ($contestants as $contestant): ?><tr><td><strong><?= h($contestant['nama']) ?></strong><small class="d-block text-muted">No. <?= h($contestant['nomor_peserta']) ?></small></td><td><?= h($contestant['kategori']) ?><small class="d-block text-muted"><?= h($contestant['nama_periode']) ?></small></td><td><?= h($contestant['status']) ?></td><td><?= (int) $contestant['jumlah_suara'] ?></td><td><div class="d-flex gap-2"><a class="btn btn-sm btn-outline-maroon" href="kontestan.php?edit=<?= (int) $contestant['id'] ?>">Edit</a><form action="../proses/admin.php" method="post" onsubmit="return confirm('Hapus kontestan ini? Data yang sudah menerima suara tidak dapat dihapus.');"><?= csrfField() ?><input type="hidden" name="action" value="contestant_delete"><input type="hidden" name="id" value="<?= (int) $contestant['id'] ?>"><button class="btn btn-sm btn-outline-danger" type="submit">Hapus</button></form></div></td></tr><?php endforeach; ?>
            </tbody></table></div>
        </div>
    </div>
</div></section>
<?php renderFooter('..'); ?>
