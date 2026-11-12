<?php
require_once __DIR__ . '/../includes/app.php';
requireAdmin();

$periods = $conn->query('SELECT id, nama_periode FROM periode_voting ORDER BY id DESC')->fetch_all(MYSQLI_ASSOC);
$periodId = filter_var($_GET['periode'] ?? null, FILTER_VALIDATE_INT);
if (!$periodId && $periods) {
    $periodId = (int) $periods[0]['id'];
}

$period = null;
$results = [];
if ($periodId) {
    $stmt = $conn->prepare('SELECT * FROM periode_voting WHERE id = ?');
    $stmt->bind_param('i', $periodId);
    $stmt->execute();
    $period = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($period) {
        $stmt = $conn->prepare(
            "SELECT k.id, k.nama, k.kategori, k.nomor_peserta, k.foto, COUNT(v.id) AS jumlah_suara
             FROM kontestan k
             LEFT JOIN voting v ON v.kontestan_id = k.id
             WHERE k.periode_id = ?
               AND (k.status = 'Aktif' OR EXISTS (
                    SELECT 1 FROM voting v2 WHERE v2.kontestan_id = k.id
               ))
             GROUP BY k.id, k.nama, k.kategori, k.nomor_peserta, k.foto
             ORDER BY k.kategori, jumlah_suara DESC, k.nomor_peserta"
        );
        $stmt->bind_param('i', $periodId);
        $stmt->execute();
        $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

$chartData = ['Kakang' => [], 'Senduk' => []];
foreach ($results as $result) {
    $chartData[$result['kategori']][] = ['name' => $result['nama'], 'votes' => (int) $result['jumlah_suara']];
}

renderHeader('Hasil Voting', 'admin', '..');
?>
<section class="page-hero admin-hero"><div class="container"><span class="section-label">ADMINISTRATOR</span><h1 class="section-title text-white mb-2">Hasil dan <span>grafik.</span></h1><p class="text-white-50 mb-0">Rekap jumlah suara berdasarkan data yang tersimpan.</p></div></section>
<section class="page-section"><div class="container">
    <?php if ($periods): ?><form method="get" class="row mb-4"><div class="col-md-5"><label class="form-label" for="periode">Periode</label><select class="form-select" id="periode" name="periode" onchange="this.form.submit()"><?php foreach ($periods as $item): ?><option value="<?= (int) $item['id'] ?>" <?= (int) $item['id'] === (int) $periodId ? 'selected' : '' ?>><?= h($item['nama_periode']) ?></option><?php endforeach; ?></select></div></form><?php endif; ?>
    <?php if (!$period): ?><div class="empty-state"><h2>Belum ada data periode</h2><p>Buat periode pemilihan untuk memulai.</p></div><?php elseif (!$results): ?><div class="empty-state"><h2>Kontestan belum tersedia</h2><p>Tambahkan kontestan sebelum memantau hasil voting.</p></div><?php else: ?>
        <h2 class="section-title h3 mb-4"><?= h($period['nama_periode']) ?></h2>
        <div class="row g-4 mb-5"><?php foreach (['Kakang', 'Senduk'] as $category): ?><div class="col-lg-6"><div class="chart-card"><h3 class="h5"><?= h($category) ?></h3><canvas id="chart-<?= strtolower($category) ?>"></canvas></div></div><?php endforeach; ?></div>
        <div class="admin-table-wrap table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Kontestan</th><th>Kategori</th><th>Nomor</th><th>Suara</th></tr></thead><tbody><?php foreach ($results as $result): ?><tr><td><span class="leaderboard-candidate"><span class="leaderboard-avatar"><?php if ($result['foto']): ?><img src="../uploads/kontestan/<?= rawurlencode($result['foto']) ?>" alt="Foto <?= h($result['nama']) ?>" loading="lazy"><?php else: ?><span><?= h(mb_strtoupper(mb_substr($result['nama'], 0, 1))) ?></span><?php endif; ?></span><strong><?= h($result['nama']) ?></strong></span></td><td><?= h($result['kategori']) ?></td><td><?= h($result['nomor_peserta']) ?></td><td class="fw-bold"><?= (int) $result['jumlah_suara'] ?></td></tr><?php endforeach; ?></tbody></table></div>
    <?php endif; ?>
</div></section>
<?php if ($period && $results): ?><script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script><script>
const chartResults = <?= json_encode($chartData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
for (const category of ['Kakang', 'Senduk']) {
    const canvas = document.getElementById(`chart-${category.toLowerCase()}`);
    new Chart(canvas, { type: 'bar', data: { labels: chartResults[category].map(item => item.name), datasets: [{ label: 'Jumlah suara', data: chartResults[category].map(item => item.votes), backgroundColor: '#9a294c', borderRadius: 6 }] }, options: { indexAxis: 'y', responsive: true, plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, ticks: { precision: 0 } } } } });
}
</script><?php endif; ?>
<?php renderFooter('..'); ?>
