<?php
require_once __DIR__ . '/../includes/app.php';

$periods = $conn->query('SELECT id, nama_periode, status FROM periode_voting ORDER BY id DESC')->fetch_all(MYSQLI_ASSOC);
$periodId = filter_var($_GET['periode'] ?? null, FILTER_VALIDATE_INT);
if (!$periodId && $periods) {
    $periodId = (int) $periods[0]['id'];
}

$selectedPeriod = null;
$results = [];
if ($periodId) {
    $stmt = $conn->prepare('SELECT * FROM periode_voting WHERE id = ?');
    $stmt->bind_param('i', $periodId);
    $stmt->execute();
    $selectedPeriod = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($selectedPeriod) {
        $stmt = $conn->prepare(
            "SELECT k.id, k.nama, k.kategori, k.nomor_peserta, k.foto, k.asal_instansi,
                    COUNT(v.id) AS jumlah_suara
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
    $chartData[$result['kategori']][] = [
        'name' => $result['nama'],
        'votes' => (int) $result['jumlah_suara'],
    ];
}

renderHeader('Klasemen', 'klasemen', '..');
?>
<section class="page-hero">
    <div class="container"><span class="section-label">HASIL PEMILIHAN</span><h1 class="section-title text-white mb-2">Klasemen <span>suara.</span></h1><p class="text-white-50 mb-0">Perolehan suara diperbarui langsung berdasarkan suara yang tercatat.</p></div>
</section>
<section class="page-section">
    <div class="container">
        <?php if ($periods): ?>
            <form method="get" class="row mb-4">
                <div class="col-sm-8 col-md-5"><label class="form-label" for="periode">Periode pemilihan</label><select class="form-select" id="periode" name="periode" onchange="this.form.submit()"><?php foreach ($periods as $period): ?><option value="<?= (int) $period['id'] ?>" <?= (int) $period['id'] === (int) $periodId ? 'selected' : '' ?>><?= h($period['nama_periode']) ?></option><?php endforeach; ?></select></div>
            </form>
        <?php endif; ?>
        <?php if (!$selectedPeriod): ?>
            <div class="empty-state"><h2>Belum ada hasil</h2><p>Hasil akan muncul setelah periode pemilihan dibuat.</p></div>
        <?php elseif (!$results): ?>
            <div class="empty-state"><h2>Kontestan belum tersedia</h2><p>Belum ada data kontestan pada periode ini.</p></div>
        <?php else: ?>
            <div class="row g-4 mb-5">
                <?php foreach (['Kakang', 'Senduk'] as $category): ?>
                    <div class="col-lg-6"><div class="chart-card"><h2 class="h4"><?= h($category) ?></h2><canvas id="chart-<?= strtolower($category) ?>" aria-label="Grafik hasil kategori <?= h($category) ?>"></canvas></div></div>
                <?php endforeach; ?>
            </div>
            <?php foreach (['Kakang', 'Senduk'] as $category): ?>
                <section class="mb-5">
                    <h2 class="section-title h3"><?= h($category) ?></h2>
                    <div class="table-responsive admin-table-wrap">
                        <table class="table align-middle mb-0"><thead><tr><th>Peringkat</th><th>Kontestan</th><th>Nomor</th><th class="text-end">Suara</th></tr></thead><tbody>
                            <?php $rank = 0; foreach ($results as $result): if ($result['kategori'] !== $category) continue; $rank++; ?>
                                <tr>
                                    <td><span class="rank-pill"><?= $rank ?></span></td>
                                    <td>
                                        <a class="leaderboard-candidate" href="detail-kontestan.php?id=<?= (int) $result['id'] ?>">
                                            <span class="leaderboard-avatar">
                                                <?php if ($result['foto']): ?>
                                                    <img src="../uploads/kontestan/<?= rawurlencode($result['foto']) ?>" alt="Foto <?= h($result['nama']) ?>" loading="lazy">
                                                <?php else: ?>
                                                    <span><?= h(mb_strtoupper(mb_substr($result['nama'], 0, 1))) ?></span>
                                                <?php endif; ?>
                                            </span>
                                            <span><strong><?= h($result['nama']) ?></strong><small><?= h($result['asal_instansi'] ?: 'Kontestan ' . $category) ?></small></span>
                                        </a>
                                    </td>
                                    <td><?= h($result['nomor_peserta']) ?></td>
                                    <td class="text-end"><span class="leaderboard-vote-count"><?= number_format((int) $result['jumlah_suara']) ?><small>suara</small></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody></table>
                    </div>
                </section>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>
<?php if ($selectedPeriod && $results): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <script>
        const chartResults = <?= json_encode($chartData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        for (const category of ['Kakang', 'Senduk']) {
            const canvas = document.getElementById(`chart-${category.toLowerCase()}`);
            if (!canvas) continue;
            new Chart(canvas, {
                type: 'bar',
                data: {
                    labels: chartResults[category].map(item => item.name),
                    datasets: [{ label: 'Jumlah suara', data: chartResults[category].map(item => item.votes), backgroundColor: '#9a294c', borderRadius: 6 }]
                },
                options: { indexAxis: 'y', responsive: true, plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, ticks: { precision: 0 } } } }
            });
        }
    </script>
<?php endif; ?>
<?php renderFooter('..'); ?>
