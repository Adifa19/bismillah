<?php
require_once '../config.php';
requireLogin();

// Hanya untuk admin
if (!isAdmin()) {
    header('Location: ../index.php');
    exit;
}

$message = '';
$error = '';

// Jalankan OCR jika tombol diklik
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'run_ocr') {
    $user_bill_id = $_POST['user_bill_id'];

    $stmt = $pdo->prepare("SELECT ub.*, b.kode_tagihan, b.jumlah FROM user_bills ub JOIN bills b ON ub.bill_id = b.id WHERE ub.id = ?");
    $stmt->execute([$user_bill_id]);
    $bill = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($bill && !empty($bill['bukti_pembayaran'])) {
        $image_path = realpath("../warga/uploads/bukti_pembayaran/" . $bill['bukti_pembayaran']);
        $kode_tagihan = $bill['kode_tagihan'];
        $jumlah = $bill['jumlah'];

        if ($image_path && file_exists($image_path)) {
            $cmd = escapeshellcmd("python3 ocr.py " . escapeshellarg($image_path) . " " . escapeshellarg($kode_tagihan) . " " . escapeshellarg($jumlah));
            $output = shell_exec($cmd);
            $ocr_result = json_decode($output, true);

            if ($ocr_result) {
                $stmt = $pdo->prepare("UPDATE user_bills SET 
                    ocr_jumlah = :jumlah, 
                    ocr_kode_found = :kode_found, 
                    ocr_date_found = :date_found,
                    ocr_confidence = :confidence, 
                    ocr_details = :details
                    WHERE id = :id");

                $stmt->execute([
                    ':jumlah' => $ocr_result['jumlah'] ?? null,
                    ':kode_found' => isset($ocr_result['kode_tagihan']) ? 1 : 0,
                    ':date_found' => isset($ocr_result['tanggal']) ? 1 : 0,
                    ':confidence' => $ocr_result['confidence'] ?? 0.0,
                    ':details' => json_encode([
                        'extracted_text' => $ocr_result['extracted_text'] ?? '',
                        'normalized_text' => $ocr_result['normalized_text'] ?? '',
                        'extracted_code' => $ocr_result['kode_tagihan'] ?? '',
                        'extracted_date' => $ocr_result['tanggal'] ?? '',
                    ]),
                    ':id' => $user_bill_id
                ]);

                $message = "OCR berhasil dijalankan.";
            } else {
                $error = "Gagal membaca hasil OCR.";
            }
        } else {
            $error = "File gambar tidak ditemukan.";
        }
    } else {
        $error = "Data tidak valid.";
    }
    header("Location: konfirmasi.php");
    exit;
}

// Ambil data tagihan yang menunggu konfirmasi
$stmt = $pdo->prepare("SELECT ub.*, b.kode_tagihan, b.jumlah, b.deskripsi, b.tenggat_waktu, b.tanggal AS tanggal_tagihan, u.username 
    FROM user_bills ub 
    JOIN bills b ON ub.bill_id = b.id
    JOIN users u ON ub.user_id = u.id
    WHERE ub.status = 'menunggu_konfirmasi'");
$stmt->execute();
$bills = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Konfirmasi Pembayaran</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .image-preview {
            max-height: 100px;
            cursor: pointer;
        }
        .status-sesuai { color: green; }
        .status-tidak-sesuai { color: orange; }
        .status-belum { color: gray; }
    </style>
</head>
<body class="p-4">
    <div class="container">
        <h2>Daftar Pembayaran Menunggu Konfirmasi</h2>

        <?php if ($message): ?>
            <div class="alert alert-success"><?= $message ?></div>
        <?php elseif ($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>

        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Kode Tagihan</th>
                    <th>Jumlah</th>
                    <th>Deskripsi</th>
                    <th>Tanggal</th>
                    <th>Tenggat</th>
                    <th>Bukti</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bills as $bill): ?>
                    <tr>
                        <td><?= htmlspecialchars($bill['username']) ?></td>
                        <td><?= htmlspecialchars($bill['kode_tagihan']) ?></td>
                        <td>Rp <?= number_format($bill['jumlah'], 0, ',', '.') ?></td>
                        <td><?= nl2br(htmlspecialchars($bill['deskripsi'])) ?></td>
                        <td><?= htmlspecialchars($bill['tanggal_tagihan']) ?></td>
                        <td><?= htmlspecialchars($bill['tenggat_waktu']) ?></td>
                        <td>
                            <?php if ($bill['bukti_pembayaran']): ?>
                                <img src="../warga/uploads/bukti_pembayaran/<?= htmlspecialchars($bill['bukti_pembayaran']) ?>" class="image-preview" alt="Bukti">
                            <?php else: ?>
                                Tidak ada
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="user_bill_id" value="<?= $bill['id'] ?>">
                                <input type="hidden" name="action" value="run_ocr">
                                <button type="submit" class="btn btn-secondary btn-sm" onclick="return confirm('Jalankan OCR untuk bukti ini?')">
                                    <i class="fas fa-search"></i> Jalankan OCR
                                </button>
                            </form>
                            <form method="POST" action="proses_konfirmasi.php" style="display:inline-block; margin-top:5px;">
                                <input type="hidden" name="user_bill_id" value="<?= $bill['id'] ?>">
                                <select name="status" class="form-select form-select-sm" required>
                                    <option value="">Pilih Aksi</option>
                                    <option value="konfirmasi">Konfirmasi</option>
                                    <option value="tolak">Tolak</option>
                                </select>
                                <button type="submit" class="btn btn-primary btn-sm mt-1" onclick="return confirm('Apakah Anda yakin?')">
                                    Proses
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
