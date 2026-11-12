<?php
require_once __DIR__ . '/../includes/app.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Metode tidak diizinkan.');
}

verifyCsrf();
$action = $_POST['action'] ?? '';
$destinations = [
    'contestant_save' => '../admin/kontestan.php',
    'contestant_delete' => '../admin/kontestan.php',
    'period_save' => '../admin/periode.php',
    'period_delete' => '../admin/periode.php',
    'voter_status' => '../admin/pemilih.php',
];
$destination = $destinations[$action] ?? '../admin/dashboard.php';
$uploadedPhoto = null;
$transactionStarted = false;

function postedId(string $key): int
{
    $value = filter_var($_POST[$key] ?? null, FILTER_VALIDATE_INT);

    return $value !== false && $value > 0 ? $value : 0;
}

function nullableText(string $key): ?string
{
    $value = trim($_POST[$key] ?? '');

    return $value === '' ? null : $value;
}

function saveContestantPhoto(?array $file): ?string
{
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Foto gagal diunggah. Silakan pilih ulang berkas.');
    }

    if (($file['size'] ?? 0) > 3 * 1024 * 1024) {
        throw new RuntimeException('Ukuran foto maksimal 3 MB.');
    }

    $imageInfo = @getimagesize($file['tmp_name']);
    $mime = $imageInfo['mime'] ?? '';
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if (!isset($extensions[$mime])) {
        throw new RuntimeException('Foto harus berformat JPG, PNG, atau WebP.');
    }

    $directory = __DIR__ . '/../uploads/kontestan';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('Folder penyimpanan foto tidak dapat dibuat.');
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($file['tmp_name'], $directory . '/' . $filename)) {
        throw new RuntimeException('Foto tidak dapat disimpan ke server.');
    }

    return $filename;
}

function removeContestantPhoto(?string $filename): void
{
    if (!$filename || !preg_match('/\A[a-f0-9]{32}\.(jpg|png|webp)\z/', $filename)) {
        return;
    }

    $path = __DIR__ . '/../uploads/kontestan/' . $filename;
    if (is_file($path) && !unlink($path)) {
        error_log('Unable to remove contestant photo: ' . $path);
    }
}

try {
    if ($action === 'contestant_save') {
        $id = postedId('id');
        $periodId = postedId('periode_id');
        $number = trim($_POST['nomor_peserta'] ?? '');
        $name = trim($_POST['nama'] ?? '');
        $category = $_POST['kategori'] ?? '';
        $status = $_POST['status'] ?? 'Aktif';

        if (
            $periodId < 1 ||
            $number === '' ||
            strlen($number) > 20 ||
            $name === '' ||
            mb_strlen($name) > 100 ||
            !in_array($category, ['Kakang', 'Senduk'], true) ||
            !in_array($status, ['Aktif', 'Nonaktif'], true)
        ) {
            throw new RuntimeException('Lengkapi nomor, nama, kategori, periode, dan status kontestan dengan benar.');
        }

        $periodCheck = $conn->prepare('SELECT id FROM periode_voting WHERE id = ?');
        $periodCheck->bind_param('i', $periodId);
        $periodCheck->execute();
        $periodExists = $periodCheck->get_result()->num_rows === 1;
        $periodCheck->close();
        if (!$periodExists) {
            throw new RuntimeException('Periode voting tidak ditemukan.');
        }

        $uploadedPhoto = saveContestantPhoto($_FILES['foto'] ?? null);
        $photo = $uploadedPhoto;
        $institution = nullableText('asal_instansi');
        $birthplace = nullableText('tempat_lahir');
        $birthdate = nullableText('tanggal_lahir');
        $address = nullableText('alamat');
        $education = nullableText('pendidikan');
        $occupation = nullableText('pekerjaan');
        $vision = nullableText('visi');
        $mission = nullableText('misi');
        $description = nullableText('deskripsi');

        if ($birthdate !== null) {
            $parsedBirthdate = DateTime::createFromFormat('!Y-m-d', $birthdate);
            $dateErrors = DateTime::getLastErrors();
            if (
                !$parsedBirthdate ||
                $parsedBirthdate->format('Y-m-d') !== $birthdate ||
                ($dateErrors !== false && ($dateErrors['warning_count'] || $dateErrors['error_count']))
            ) {
                throw new RuntimeException('Tanggal lahir tidak valid.');
            }
        }

        if ($id > 0) {
            $photoQuery = $conn->prepare('SELECT foto FROM kontestan WHERE id = ?');
            $photoQuery->bind_param('i', $id);
            $photoQuery->execute();
            $existing = $photoQuery->get_result()->fetch_assoc();
            $photoQuery->close();
            if (!$existing) {
                throw new RuntimeException('Kontestan yang akan diperbarui tidak ditemukan.');
            }

            if ($photo === null) {
                $photo = $existing['foto'];
            }

            $stmt = $conn->prepare(
                'UPDATE kontestan SET periode_id = ?, nomor_peserta = ?, nama = ?,
                    kategori = ?, foto = ?, asal_instansi = ?, tempat_lahir = ?,
                    tanggal_lahir = ?, alamat = ?, pendidikan = ?, pekerjaan = ?,
                    visi = ?, misi = ?, deskripsi = ?, status = ?
                 WHERE id = ?'
            );
            $stmt->bind_param(
                'issssssssssssssi',
                $periodId,
                $number,
                $name,
                $category,
                $photo,
                $institution,
                $birthplace,
                $birthdate,
                $address,
                $education,
                $occupation,
                $vision,
                $mission,
                $description,
                $status,
                $id
            );
            $stmt->execute();
            $stmt->close();

            if ($uploadedPhoto !== null) {
                removeContestantPhoto($existing['foto']);
            }
        } else {
            $stmt = $conn->prepare(
                'INSERT INTO kontestan
                    (periode_id, nomor_peserta, nama, kategori, foto, asal_instansi,
                     tempat_lahir, tanggal_lahir, alamat, pendidikan, pekerjaan,
                     visi, misi, deskripsi, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->bind_param(
                'issssssssssssss',
                $periodId,
                $number,
                $name,
                $category,
                $photo,
                $institution,
                $birthplace,
                $birthdate,
                $address,
                $education,
                $occupation,
                $vision,
                $mission,
                $description,
                $status
            );
            $stmt->execute();
            $stmt->close();
        }

        $uploadedPhoto = null;
        setFlash('Data kontestan berhasil disimpan.');
    } elseif ($action === 'contestant_delete') {
        $id = postedId('id');
        $query = $conn->prepare('SELECT foto FROM kontestan WHERE id = ?');
        $query->bind_param('i', $id);
        $query->execute();
        $contestant = $query->get_result()->fetch_assoc();
        $query->close();
        if (!$contestant) {
            throw new RuntimeException('Kontestan tidak ditemukan.');
        }

        $stmt = $conn->prepare('DELETE FROM kontestan WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        removeContestantPhoto($contestant['foto']);
        setFlash('Kontestan berhasil dihapus.');
    } elseif ($action === 'period_save') {
        $id = postedId('id');
        $name = trim($_POST['nama_periode'] ?? '');
        $start = str_replace('T', ' ', trim($_POST['tanggal_mulai'] ?? ''));
        $end = str_replace('T', ' ', trim($_POST['tanggal_selesai'] ?? ''));
        $status = $_POST['status'] ?? 'Persiapan';
        $validStatuses = ['Persiapan', 'Aktif', 'Selesai', 'Nonaktif'];
        $parseDateTime = static function (string $value): ?DateTime {
            foreach (['!Y-m-d H:i', '!Y-m-d H:i:s'] as $format) {
                $date = DateTime::createFromFormat($format, $value);
                $errors = DateTime::getLastErrors();
                if (
                    $date &&
                    $date->format(ltrim($format, '!')) === $value &&
                    ($errors === false || (!$errors['warning_count'] && !$errors['error_count']))
                ) {
                    return $date;
                }
            }

            return null;
        };
        $startDate = $parseDateTime($start);
        $endDate = $parseDateTime($end);

        if (
            $name === '' ||
            mb_strlen($name) > 150 ||
            !$startDate ||
            !$endDate ||
            $endDate <= $startDate ||
            !in_array($status, $validStatuses, true)
        ) {
            throw new RuntimeException('Periksa nama, jadwal, dan status periode voting.');
        }

        if ($id > 0) {
            $exists = $conn->prepare('SELECT id FROM periode_voting WHERE id = ?');
            $exists->bind_param('i', $id);
            $exists->execute();
            $found = $exists->get_result()->num_rows === 1;
            $exists->close();
            if (!$found) {
                throw new RuntimeException('Periode yang akan diperbarui tidak ditemukan.');
            }
        }

        $conn->begin_transaction();
        $transactionStarted = true;
        if ($status === 'Aktif') {
            $conn->query("UPDATE periode_voting SET status = 'Nonaktif' WHERE status = 'Aktif'");
        }

        if ($id > 0) {
            $stmt = $conn->prepare(
                'UPDATE periode_voting SET nama_periode = ?, tanggal_mulai = ?,
                    tanggal_selesai = ?, status = ? WHERE id = ?'
            );
            $startValue = $startDate->format('Y-m-d H:i:s');
            $endValue = $endDate->format('Y-m-d H:i:s');
            $stmt->bind_param('ssssi', $name, $startValue, $endValue, $status, $id);
        } else {
            $stmt = $conn->prepare(
                'INSERT INTO periode_voting
                    (nama_periode, tanggal_mulai, tanggal_selesai, status)
                 VALUES (?, ?, ?, ?)'
            );
            $startValue = $startDate->format('Y-m-d H:i:s');
            $endValue = $endDate->format('Y-m-d H:i:s');
            $stmt->bind_param('ssss', $name, $startValue, $endValue, $status);
        }
        $stmt->execute();
        $stmt->close();
        $conn->commit();
        $transactionStarted = false;
        setFlash('Pengaturan periode voting berhasil disimpan.');
    } elseif ($action === 'period_delete') {
        $id = postedId('id');
        $stmt = $conn->prepare('DELETE FROM periode_voting WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        setFlash('Periode berhasil dihapus.');
    } elseif ($action === 'voter_status') {
        $id = postedId('id');
        $status = $_POST['status'] ?? '';
        if (!in_array($status, ['Aktif', 'Nonaktif'], true)) {
            throw new RuntimeException('Status akun tidak valid.');
        }
        $stmt = $conn->prepare(
            "UPDATE users SET status = ? WHERE id = ? AND role = 'pemilih'"
        );
        $stmt->bind_param('si', $status, $id);
        $stmt->execute();
        $changed = $stmt->affected_rows;
        $stmt->close();
        if ($changed < 1) {
            throw new RuntimeException('Akun pemilih tidak ditemukan atau statusnya tidak berubah.');
        }
        setFlash('Status akun pemilih berhasil diperbarui.');
    } else {
        http_response_code(400);
        exit('Aksi tidak dikenali.');
    }
} catch (RuntimeException $exception) {
    if ($uploadedPhoto !== null) {
        removeContestantPhoto($uploadedPhoto);
    }
    if ($transactionStarted) {
        $conn->rollback();
    }
    setFlash($exception->getMessage(), 'danger');
} catch (mysqli_sql_exception $exception) {
    if ($uploadedPhoto !== null) {
        removeContestantPhoto($uploadedPhoto);
    }
    if ($transactionStarted) {
        $conn->rollback();
    }
    error_log('Admin action failed: ' . $exception->getMessage());
    if ((int) $exception->getCode() === 1451 || (int) $exception->getCode() === 1452) {
        setFlash('Data tidak dapat dihapus atau diubah karena masih dipakai oleh suara atau data lain.', 'danger');
    } elseif ((int) $exception->getCode() === 1062) {
        setFlash('Nomor kontestan tersebut sudah digunakan dalam kategori dan periode yang sama.', 'danger');
    } else {
        setFlash('Perubahan gagal disimpan. Periksa log PHP untuk detail kesalahan.', 'danger');
    }
}

redirectTo($destination);
