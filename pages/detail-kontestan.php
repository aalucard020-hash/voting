<?php
require_once __DIR__ . '/../includes/app.php';

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$contestant = null;
if ($id && $id > 0) {
    $stmt = $conn->prepare(
        "SELECT k.*, p.nama_periode,
                (SELECT COUNT(*) FROM voting v WHERE v.kontestan_id = k.id) AS jumlah_suara
         FROM kontestan k
         JOIN periode_voting p ON p.id = k.periode_id
         WHERE k.id = ? AND k.status = 'Aktif'
         LIMIT 1"
    );
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $contestant = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$contestant) {
    http_response_code(404);
}

renderHeader($contestant ? $contestant['nama'] : 'Kontestan tidak ditemukan', 'kontestan', '..');
?>
<section class="page-section">
    <div class="container">
        <?php if (!$contestant): ?>
            <div class="empty-state"><h1>Kontestan tidak ditemukan</h1><p>Profil tidak tersedia atau sudah dinonaktifkan.</p><a class="btn btn-gold" href="kontestan.php">Kembali ke kontestan</a></div>
        <?php else: ?>
            <a href="kontestan.php?periode=<?= (int) $contestant['periode_id'] ?>" class="text-link">← Kembali ke daftar kontestan</a>
            <div class="row g-5 mt-2">
                <div class="col-lg-5">
                    <div class="candidate-profile-image">
                        <?php if ($contestant['foto']): ?>
                            <img src="../uploads/kontestan/<?= rawurlencode($contestant['foto']) ?>" alt="Foto <?= h($contestant['nama']) ?>">
                        <?php else: ?>
                            <span><?= h(mb_strtoupper(mb_substr($contestant['nama'], 0, 1))) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-lg-7">
                    <span class="section-label"><?= h($contestant['kategori']) ?> · NOMOR <?= h($contestant['nomor_peserta']) ?></span>
                    <h1 class="section-title"><?= h($contestant['nama']) ?></h1>
                    <p class="lead text-muted"><?= h($contestant['asal_instansi'] ?: 'Kontestan Kakang Senduk Ponorogo') ?></p>
                    <p class="text-muted">Periode: <?= h($contestant['nama_periode']) ?></p>
                    <?php if ($contestant['tempat_lahir'] || $contestant['tanggal_lahir']): ?>
                        <p><strong>Tempat, tanggal lahir:</strong> <?= h(trim(($contestant['tempat_lahir'] ?? '') . ', ' . ($contestant['tanggal_lahir'] ?? ''), ', ')) ?></p>
                    <?php endif; ?>
                    <?php foreach (['pendidikan' => 'Pendidikan', 'pekerjaan' => 'Pekerjaan', 'alamat' => 'Domisili'] as $field => $label): ?>
                        <?php if ($contestant[$field]): ?><p><strong><?= h($label) ?>:</strong> <?= h($contestant[$field]) ?></p><?php endif; ?>
                    <?php endforeach; ?>
                    <?php foreach (['visi' => 'Visi', 'misi' => 'Misi', 'deskripsi' => 'Tentang kontestan'] as $field => $label): ?>
                        <?php if ($contestant[$field]): ?><section class="profile-copy"><h2><?= h($label) ?></h2><p><?= nl2br(h($contestant[$field])) ?></p></section><?php endif; ?>
                    <?php endforeach; ?>
                    <a href="klasemen.php?periode=<?= (int) $contestant['periode_id'] ?>" class="btn btn-outline-maroon mt-3">Lihat klasemen</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php renderFooter('..'); ?>
