<?php
require_once __DIR__ . '/../includes/app.php';

$periodId = filter_var($_GET['periode'] ?? null, FILTER_VALIDATE_INT);
$periods = $conn->query('SELECT id, nama_periode, status FROM periode_voting ORDER BY id DESC')->fetch_all(MYSQLI_ASSOC);

if (!$periodId && $periods) {
    foreach ($periods as $period) {
        if ($period['status'] === 'Aktif') {
            $periodId = (int) $period['id'];
            break;
        }
    }
    $periodId = $periodId ?: (int) $periods[0]['id'];
}

$contestants = [];
$selectedPeriod = null;
if ($periodId) {
    $periodQuery = $conn->prepare('SELECT * FROM periode_voting WHERE id = ?');
    $periodQuery->bind_param('i', $periodId);
    $periodQuery->execute();
    $selectedPeriod = $periodQuery->get_result()->fetch_assoc();
    $periodQuery->close();

    if ($selectedPeriod) {
        $stmt = $conn->prepare(
            "SELECT k.*, COALESCE(v.jumlah_suara, 0) AS jumlah_suara
             FROM kontestan k
             LEFT JOIN (
                SELECT kontestan_id, COUNT(*) AS jumlah_suara
                FROM voting
                GROUP BY kontestan_id
             ) v ON v.kontestan_id = k.id
             WHERE k.periode_id = ? AND k.status = 'Aktif'
             ORDER BY FIELD(k.kategori, 'Kakang', 'Senduk'), k.nomor_peserta"
        );
        $stmt->bind_param('i', $periodId);
        $stmt->execute();
        $contestants = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

renderHeader('Kontestan', 'kontestan', '..');
?>
<section class="page-hero">
    <div class="container">
        <span class="section-label">CALON DUTA WISATA PONOROGO</span>
        <h1 class="section-title text-white mb-2">Kenali para <span>kontestan.</span></h1>
        <p class="text-white-50 mb-0">Temukan gagasan dan profil setiap Kakang dan Senduk.</p>
    </div>
</section>
<section class="page-section">
    <div class="container">
        <?php if ($periods): ?>
            <form method="get" class="row align-items-end g-3 mb-4">
                <div class="col-sm-8 col-md-5">
                    <label class="form-label" for="periode">Periode pemilihan</label>
                    <select class="form-select" id="periode" name="periode" onchange="this.form.submit()">
                        <?php foreach ($periods as $period): ?>
                            <option value="<?= (int) $period['id'] ?>" <?= (int) $period['id'] === (int) $periodId ? 'selected' : '' ?>>
                                <?= h($period['nama_periode']) ?> · <?= h($period['status']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        <?php endif; ?>

        <?php if (!$selectedPeriod): ?>
            <div class="empty-state"><h2>Belum ada periode pemilihan</h2><p>Periode dan profil kontestan akan ditampilkan setelah disiapkan administrator.</p></div>
        <?php elseif (!$contestants): ?>
            <div class="empty-state"><h2>Kontestan belum tersedia</h2><p>Belum ada kontestan aktif pada periode <?= h($selectedPeriod['nama_periode']) ?>.</p></div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($contestants as $contestant): ?>
                    <div class="col-sm-6 col-lg-4">
                        <article class="candidate-card h-100">
                            <a class="candidate-image" href="detail-kontestan.php?id=<?= (int) $contestant['id'] ?>">
                                <?php if ($contestant['foto']): ?>
                                    <img src="../uploads/kontestan/<?= rawurlencode($contestant['foto']) ?>" alt="Foto <?= h($contestant['nama']) ?>" loading="lazy">
                                <?php else: ?>
                                    <span><?= h(mb_strtoupper(mb_substr($contestant['nama'], 0, 1))) ?></span>
                                <?php endif; ?>
                                <span class="candidate-number"><?= h($contestant['nomor_peserta']) ?></span>
                            </a>
                            <div class="p-4">
                                <span class="candidate-category"><?= h($contestant['kategori']) ?></span>
                                <h2 class="h4 mt-2"><?= h($contestant['nama']) ?></h2>
                                <p class="text-muted"><?= h($contestant['asal_instansi'] ?: 'Kontestan Kakang Senduk Ponorogo') ?></p>
                                <a class="text-link" href="detail-kontestan.php?id=<?= (int) $contestant['id'] ?>">Lihat profil →</a>
                            </div>
                        </article>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php renderFooter('..'); ?>
