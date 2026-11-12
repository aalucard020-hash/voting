<?php
require_once __DIR__ . '/includes/app.php';

$activePeriod = activeVotingPeriod($conn);
$period = $activePeriod;
if (!$period) {
    $period = $conn->query('SELECT * FROM periode_voting ORDER BY id DESC LIMIT 1')->fetch_assoc() ?: null;
}

$totalContestants = 0;
$totalVoters = (int) $conn->query("SELECT COUNT(*) FROM users WHERE role = 'pemilih' AND status = 'Aktif'")->fetch_row()[0];
$totalVotes = 0;
$featured = [];
if ($period) {
    $periodId = (int) $period['id'];
    $stmt = $conn->prepare("SELECT COUNT(*) FROM kontestan WHERE periode_id = ? AND status = 'Aktif'");
    $stmt->bind_param('i', $periodId);
    $stmt->execute();
    $totalContestants = (int) $stmt->get_result()->fetch_row()[0];
    $stmt->close();

    $stmt = $conn->prepare('SELECT COUNT(*) FROM voting WHERE periode_id = ?');
    $stmt->bind_param('i', $periodId);
    $stmt->execute();
    $totalVotes = (int) $stmt->get_result()->fetch_row()[0];
    $stmt->close();

    $stmt = $conn->prepare(
        "SELECT k.id, k.nama, k.kategori, k.nomor_peserta, k.foto,
                (SELECT COUNT(*) FROM voting v WHERE v.kontestan_id = k.id) AS jumlah_suara
         FROM kontestan k
         WHERE k.periode_id = ? AND k.status = 'Aktif'
         ORDER BY jumlah_suara DESC, k.kategori, k.nomor_peserta LIMIT 3"
    );
    $stmt->bind_param('i', $periodId);
    $stmt->execute();
    $featured = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

renderHeader('Beranda', 'home');
?>
<section class="hero-section">
    <div class="hero-overlay"></div>
    <div class="container hero-content">
        <span class="hero-label">PEMILIHAN DUTA WISATA DAERAH</span>
        <h1>KAKANG SENDUK <span>PONOROGO</span></h1>
        <p>Merayakan budaya, memperkenalkan potensi daerah, dan melahirkan generasi muda yang menjadi wajah kebanggaan Ponorogo.</p>
        <div class="hero-buttons"><a href="pages/kontestan.php" class="btn btn-gold btn-lg">Kenali Kontestan</a><a href="pages/klasemen.php" class="btn btn-outline-light btn-lg">Lihat Klasemen</a></div>
        <div class="hero-status mt-4"><span class="status-dot <?= $activePeriod ? '' : 'inactive' ?>"></span><?= $activePeriod ? 'Voting sedang berlangsung' : 'Voting belum dibuka' ?><?php if ($activePeriod): ?> · <?= h($activePeriod['nama_periode']) ?><?php endif; ?></div>
    </div>
    <div class="hero-bottom"><div class="container"><div class="row g-3">
        <div class="col-4"><div class="hero-stat"><h3><?= number_format($totalContestants) ?></h3><span>Kontestan aktif</span></div></div>
        <div class="col-4"><div class="hero-stat"><h3><?= number_format($totalVoters) ?></h3><span>Pemilih terdaftar</span></div></div>
        <div class="col-4"><div class="hero-stat"><h3><?= number_format($totalVotes) ?></h3><span>Suara tercatat</span></div></div>
    </div></div></div>
</section>
<section class="about-section"><div class="container"><div class="row align-items-center g-5">
    <div class="col-lg-6"><span class="section-label">TENTANG PEMILIHAN</span><h2 class="section-title">Lebih dari sekadar <span>sebuah gelar.</span></h2><p class="section-description">Kakang Senduk Ponorogo merupakan representasi generasi muda yang diharapkan mampu mengenalkan kekayaan budaya, pariwisata, dan potensi daerah kepada masyarakat luas.</p><p class="section-description">Kenali gagasan dan profil setiap peserta, lalu dukung pilihan Anda melalui proses voting yang aman dan transparan.</p><a href="pages/kontestan.php" class="text-link">Kenali para kontestan →</a></div>
    <div class="col-lg-6"><div class="about-visual"><div class="about-placeholder"><span>REOG PONOROGO</span><h3>Budaya yang Menyatukan</h3><p>Warisan, identitas, dan kebanggaan daerah.</p></div></div></div>
</div></div></section>
<?php if ($featured): ?>
    <section class="page-section"><div class="container"><div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end mb-4"><div><span class="section-label">PERIODE TERKINI</span><h2 class="section-title mb-0"><?= h($period['nama_periode']) ?></h2></div><a href="pages/klasemen.php?periode=<?= (int) $period['id'] ?>" class="text-link">Lihat klasemen →</a></div>
        <div class="row g-4"><?php foreach ($featured as $candidate): ?><div class="col-sm-6 col-lg-4"><article class="candidate-card h-100"><a class="candidate-image" href="pages/detail-kontestan.php?id=<?= (int) $candidate['id'] ?>"><?php if ($candidate['foto']): ?><img src="uploads/kontestan/<?= rawurlencode($candidate['foto']) ?>" alt="Foto <?= h($candidate['nama']) ?>" loading="lazy"><?php else: ?><span><?= h(mb_strtoupper(mb_substr($candidate['nama'], 0, 1))) ?></span><?php endif; ?><span class="candidate-number"><?= h($candidate['nomor_peserta']) ?></span></a><div class="p-4"><span class="candidate-category"><?= h($candidate['kategori']) ?></span><h3 class="h4 mt-2"><?= h($candidate['nama']) ?></h3><p class="text-muted mb-0"><?= (int) $candidate['jumlah_suara'] ?> suara</p></div></article></div><?php endforeach; ?></div>
    </div></section>
<?php endif; ?>
<section class="voting-section"><div class="container text-center"><span class="section-label">SUARA ANDA BERARTI</span><h2 class="section-title">Jadilah bagian dari <span>pemilihan ini.</span></h2><p class="section-description mx-auto">Satu akun dapat memilih satu Kakang dan satu Senduk pada periode pemilihan yang aktif.</p><a href="pages/voting.php" class="btn btn-gold btn-lg mt-3">Buka halaman voting</a></div></section>
<?php renderFooter(); ?>
