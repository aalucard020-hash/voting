<?php
require_once __DIR__ . '/../includes/app.php';
requireVoter();

$accountId = (int) $_SESSION['user_id'];
$account = $conn->prepare('SELECT status FROM users WHERE id = ?');
$account->bind_param('i', $accountId);
$account->execute();
$accountStatus = $account->get_result()->fetch_column();
$account->close();

if ($accountStatus !== 'Aktif') {
    $_SESSION = [];
    session_destroy();
    redirectTo('../auth/login.php');
}

$period = activeVotingPeriod($conn);
$message = '';
$castVotes = [];
$candidates = ['Kakang' => [], 'Senduk' => []];

if ($period) {
    $periodId = (int) $period['id'];
    $stmt = $conn->prepare(
        'SELECT v.kategori, v.kontestan_id, k.nama, k.nomor_peserta, k.foto
         FROM voting v
         JOIN kontestan k ON k.id = v.kontestan_id
         WHERE v.user_id = ? AND v.periode_id = ?'
    );
    $stmt->bind_param('ii', $accountId, $periodId);
    $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $vote) {
        $castVotes[$vote['kategori']] = $vote;
    }
    $stmt->close();

    $stmt = $conn->prepare(
        "SELECT id, nama, nomor_peserta, kategori, foto, asal_instansi
         FROM kontestan WHERE periode_id = ? AND status = 'Aktif'
         ORDER BY kategori, nomor_peserta"
    );
    $stmt->bind_param('i', $periodId);
    $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $candidate) {
        $candidates[$candidate['kategori']][] = $candidate;
    }
    $stmt->close();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verifyCsrf();

        $submittedSelections = $_POST['kontestan'] ?? [];
        if (!is_array($submittedSelections)) {
            $submittedSelections = [];
        }

        $selection = [];
        foreach (['Kakang', 'Senduk'] as $category) {
            if (!array_key_exists($category, $submittedSelections)) {
                continue;
            }

            if (isset($castVotes[$category])) {
                $message = 'Suara kategori ' . $category . ' Anda sudah tercatat dan tidak dapat diubah.';
                break;
            }

            $candidateId = filter_var(
                $submittedSelections[$category],
                FILTER_VALIDATE_INT
            );
            $availableCandidateIds = array_column($candidates[$category], 'id');
            if (
                !$candidateId ||
                !in_array($candidateId, array_map('intval', $availableCandidateIds), true)
            ) {
                $message = 'Pilihan ' . $category . ' tidak valid. Muat ulang halaman, lalu coba lagi.';
                break;
            }

            $selection[$category] = $candidateId;
        }

        if ($message === '' && !$selection) {
            $message = 'Pilih setidaknya satu kontestan yang masih tersedia.';
        }

        if ($message === '') {
            try {
                $conn->begin_transaction();
                foreach ($selection as $category => $candidateId) {
                    $stmt = $conn->prepare(
                        'INSERT INTO voting (user_id, periode_id, kontestan_id, kategori)
                         VALUES (?, ?, ?, ?)'
                    );
                    $stmt->bind_param('iiis', $accountId, $periodId, $candidateId, $category);
                    $stmt->execute();
                    $stmt->close();
                }
                $conn->commit();

                $votedCategories = implode(' dan ', array_keys($selection));
                setFlash('Suara ' . $votedCategories . ' berhasil dicatat. Terima kasih telah berpartisipasi.');
                redirectTo('voting.php');
            } catch (mysqli_sql_exception $exception) {
                $conn->rollback();
                error_log('Vote submission failed: ' . $exception->getMessage());
                if ((int) $exception->getCode() === 1062) {
                    $message = 'Suara pada salah satu kategori sudah tercatat. Muat ulang halaman untuk melihat status terbaru.';
                } else {
                    $message = 'Suara belum dapat disimpan. Silakan coba kembali atau hubungi administrator.';
                }
            }
        }
    }
}

renderHeader('Voting', 'voting', '..');
?>
<section class="page-hero voting-hero">
    <div class="container">
        <span class="section-label">PILIH DENGAN HATI</span>
        <h1 class="section-title text-white mb-2">Satu suara, <span>masa depan Ponorogo.</span></h1>
        <p class="text-white-50 mb-0">Kenali para finalis, lalu pilih Kakang dan Senduk favorit Anda.</p>
    </div>
</section>
<section class="page-section voting-page">
    <div class="container">
        <?php if ($message !== ''): ?>
            <div class="alert alert-danger" role="alert"><?= h($message) ?></div>
        <?php endif; ?>

        <?php if (!$period): ?>
            <div class="empty-state">
                <h2>Voting belum dibuka</h2>
                <p>Halaman voting akan aktif sesuai jadwal pemilihan yang ditetapkan administrator.</p>
                <a class="btn btn-outline-maroon" href="kontestan.php">Kenali para kontestan</a>
            </div>
        <?php else: ?>
            <?php
            $availableCategories = [];
            foreach (['Kakang', 'Senduk'] as $category) {
                if (!isset($castVotes[$category]) && $candidates[$category]) {
                    $availableCategories[] = $category;
                }
            }
            ?>
            <div class="voting-intro">
                <div>
                    <span class="candidate-category">PERIODE PEMILIHAN AKTIF</span>
                    <h2><?= h($period['nama_periode']) ?></h2>
                    <p>Voting ditutup <?= h(date('d M Y · H:i', strtotime($period['tanggal_selesai']))) ?> WIB</p>
                </div>
                <div class="voting-progress" aria-label="Status pilihan Anda">
                    <?php foreach (['Kakang', 'Senduk'] as $category): ?>
                        <span class="voting-progress-item <?= isset($castVotes[$category]) ? 'is-complete' : '' ?>">
                            <span class="voting-progress-icon"><?= isset($castVotes[$category]) ? '✓' : '○' ?></span>
                            <?= h($category) ?>
                            <strong><?= isset($castVotes[$category]) ? 'Selesai' : 'Belum dipilih' ?></strong>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="voting-guide">
                <span class="voting-guide-icon">i</span>
                <p>Anda mendapatkan <strong>satu suara untuk setiap kategori</strong>. Pilih satu atau kedua kategori yang tersedia; suara yang sudah dikirim tidak dapat diubah.</p>
            </div>

            <form method="post" class="voting-form">
                <?= csrfField() ?>
                <div class="row g-4">
                    <?php foreach (['Kakang', 'Senduk'] as $category): ?>
                        <div class="col-lg-6">
                            <section class="vote-category-card <?= isset($castVotes[$category]) ? 'is-voted' : '' ?>">
                                <div class="vote-category-heading">
                                    <div class="vote-category-icon"><?= $category === 'Kakang' ? 'K' : 'S' ?></div>
                                    <div>
                                        <span class="candidate-category">KATEGORI</span>
                                        <h2><?= h($category) ?></h2>
                                    </div>
                                    <?php if (isset($castVotes[$category])): ?>
                                        <span class="vote-status-complete">✓ Terkirim</span>
                                    <?php elseif ($candidates[$category]): ?>
                                        <span class="vote-status-open">Pilih satu</span>
                                    <?php endif; ?>
                                </div>

                                <?php if (isset($castVotes[$category])): ?>
                                    <article class="voted-candidate">
                                        <div class="voted-candidate-photo">
                                            <?php if ($castVotes[$category]['foto']): ?>
                                                <img src="../uploads/kontestan/<?= rawurlencode($castVotes[$category]['foto']) ?>" alt="Foto <?= h($castVotes[$category]['nama']) ?>">
                                            <?php else: ?>
                                                <span><?= h(mb_strtoupper(mb_substr($castVotes[$category]['nama'], 0, 1))) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <span class="candidate-category">PILIHAN ANDA</span>
                                            <h3><?= h($castVotes[$category]['nama']) ?></h3>
                                            <p>Nomor peserta <?= h($castVotes[$category]['nomor_peserta']) ?></p>
                                        </div>
                                    </article>
                                <?php elseif (!$candidates[$category]): ?>
                                    <div class="category-unavailable">
                                        <span class="category-unavailable-icon">✦</span>
                                        <h3>Kontestan <?= h($category) ?> belum tersedia</h3>
                                        <p>Anda tetap bisa memberikan suara untuk kategori yang sudah memiliki kontestan.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="candidate-options">
                                        <?php foreach ($candidates[$category] as $candidate): ?>
                                            <?php $inputId = 'candidate-' . strtolower($category) . '-' . (int) $candidate['id']; ?>
                                            <label class="candidate-option" for="<?= h($inputId) ?>">
                                                <input
                                                    id="<?= h($inputId) ?>"
                                                    type="radio"
                                                    name="kontestan[<?= h($category) ?>]"
                                                    value="<?= (int) $candidate['id'] ?>"
                                                >
                                                <span class="vote-candidate-photo">
                                                    <?php if ($candidate['foto']): ?>
                                                        <img src="../uploads/kontestan/<?= rawurlencode($candidate['foto']) ?>" alt="" loading="lazy">
                                                    <?php else: ?>
                                                        <span><?= h(mb_strtoupper(mb_substr($candidate['nama'], 0, 1))) ?></span>
                                                    <?php endif; ?>
                                                </span>
                                                <span class="vote-candidate-info">
                                                    <strong><?= h($candidate['nama']) ?></strong>
                                                    <small><?= h($candidate['asal_instansi'] ?: $category . ' Ponorogo') ?></small>
                                                    <span class="vote-candidate-number">NOMOR <?= h($candidate['nomor_peserta']) ?></span>
                                                </span>
                                                <span class="vote-radio-mark" aria-hidden="true"></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </section>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($availableCategories): ?>
                    <div class="voting-submit-panel">
                        <p>Pilihan sudah yakin? Suara yang dikirim akan langsung tercatat.</p>
                        <button class="btn btn-gold btn-vote-submit" type="submit">
                            <span>Kirim suara pilihan saya</span><span aria-hidden="true">→</span>
                        </button>
                    </div>
                <?php else: ?>
                    <div class="voting-complete-panel">
                        <span class="voting-complete-icon">✓</span>
                        <div><h2>Terima kasih atas partisipasi Anda!</h2><p>Semua suara untuk kontestan yang tersedia sudah tercatat.</p></div>
                    </div>
                <?php endif; ?>
            </form>
        <?php endif; ?>
    </div>
</section>
<?php renderFooter('..'); ?>
