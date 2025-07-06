<?php
require_once '../config.php';
requireAdmin();

// Path ke skrip OCR Python
$ocrScriptPath = __DIR__ . '/../ocr.py';
$uploadDir = __DIR__ . '/../warga/uploads/bukti_pembayaran/';

// Proses aksi (konfirmasi, tolak, jalankan_ocr)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['user_bill_id'])) {
        $user_bill_id = (int)$_POST['user_bill_id'];

        if ($_POST['action'] === 'confirm_bill' && in_array($_POST['status'], ['konfirmasi', 'tolak'])) {
            $status = $_POST['status'];
            $stmt = $pdo->prepare("UPDATE user_bills SET status = :status WHERE id = :id");
            $stmt->execute(['status' => $status, 'id' => $user_bill_id]);
        } elseif ($_POST['action'] === 'run_ocr') {
            // Ambil data tagihan
            $stmt = $pdo->prepare("SELECT ub.*, b.kode_tagihan, b.jumlah, b.tenggat_waktu, b.tanggal AS tanggal_kirim 
                                   FROM user_bills ub 
                                   JOIN bills b ON ub.bill_id = b.id
                                   WHERE ub.id = :id");
            $stmt->execute(['id' => $user_bill_id]);
            $bill = $stmt->fetch();

            if ($bill && $bill['bukti_pembayaran']) {
                $imagePath = $uploadDir . $bill['bukti_pembayaran'];
                $kode_tagihan = escapeshellarg($bill['kode_tagihan']);
                $jumlah = escapeshellarg($bill['jumlah']);

                $command = "python3 " . escapeshellarg($ocrScriptPath) . " " . escapeshellarg($imagePath) . " $kode_tagihan $jumlah";
                $output = shell_exec($command);
                
                $ocrResult = json_decode($output, true);

                if ($ocrResult) {
                    $stmt = $pdo->prepare("UPDATE user_bills SET 
                        ocr_jumlah = :jumlah,
                        ocr_kode_found = :kode_found,
                        ocr_date_found = :date_found,
                        ocr_confidence = :confidence,
                        ocr_details = :details
                        WHERE id = :id");

                    $stmt->execute([
                        'jumlah' => isset($ocrResult['jumlah']) ? (int)$ocrResult['jumlah'] : 0,
                        'kode_found' => isset($ocrResult['kode_tagihan']) ? 1 : 0,
                        'date_found' => isset($ocrResult['tanggal']) ? 1 : 0,
                        'confidence' => isset($ocrResult['confidence']) ? (float)$ocrResult['confidence'] : 0.0,
                        'details' => json_encode($ocrResult),
                        'id' => $user_bill_id
                    ]);
                }
            }
        }
    }
}

// Ambil data tagihan yang menunggu konfirmasi
$stmt = $pdo->query("SELECT ub.*, b.kode_tagihan, b.deskripsi, b.jumlah, b.tanggal AS tanggal_tagihan, b.waktu_mulai, b.tenggat_waktu, u.username
                     FROM user_bills ub 
                     JOIN bills b ON ub.bill_id = b.id
                     JOIN users u ON ub.user_id = u.id
                     WHERE ub.status = 'menunggu_konfirmasi'");
$bills = $stmt->fetchAll();

function format_tanggal_indo($tanggal) {
    return date('d M Y', strtotime($tanggal));
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Konfirmasi Pembayaran</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <style>
        .image-preview {
            max-height: 100px;
            cursor: pointer;
        }
        .status-sesuai { color: green; font-weight: bold; }
        .status-tidak-sesuai { color: red; font-weight: bold; }
        .status-belum { color: gray; }
    </style>
</head>
<body class="p-4">
    <h2>Daftar Tagihan - Menunggu Konfirmasi</h2>
    <table class="table table-bordered table-striped">
        <thead class="table-dark">
            <tr>
                <th>Nama</th>
                <th>Kode Tagihan</th>
                <th>Jumlah</th>
                <th>Deskripsi</th>
                <th>Tanggal Kirim</th>
                <th>Tenggat</th>
                <th>Bukti</th>
                <th>Aksi</th>
                <th>Status OCR</th>
                <th>Ekstrak</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($bills as $bill): ?>
            <tr>
                <td><?= htmlspecialchars($bill['username']) ?></td>
                <td><?= htmlspecialchars($bill['kode_tagihan']) ?></td>
                <td>Rp <?= number_format($bill['jumlah'], 0, ',', '.') ?></td>
                <td><?= htmlspecialchars($bill['deskripsi']) ?></td>
                <td><?= format_tanggal_indo($bill['tanggal_tagihan']) ?></td>
                <td><?= format_tanggal_indo($bill['tenggat_waktu']) ?></td>
                <td>
                    <?php if ($bill['bukti_pembayaran']): ?>
                        <img src="../warga/uploads/bukti_pembayaran/<?= htmlspecialchars($bill['bukti_pembayaran']) ?>" class="image-preview">
                    <?php else: ?>
                        Tidak ada
                    <?php endif; ?>
                </td>
                <td>
                    <form method="POST">
                        <input type="hidden" name="user_bill_id" value="<?= $bill['id'] ?>">
                        <select name="status" class="form-select mb-1" required>
                            <option value="">-- Pilih --</option>
                            <option value="konfirmasi">Konfirmasi</option>
                            <option value="tolak">Tolak</option>
                        </select>
                        <button type="submit" name="action" value="confirm_bill" class="btn btn-primary btn-sm">Proses</button>
                    </form>
                    <form method="POST" class="mt-2">
                        <input type="hidden" name="user_bill_id" value="<?= $bill['id'] ?>">
                        <button type="submit" name="action" value="run_ocr" class="btn btn-warning btn-sm">Jalankan OCR</button>
                    </form>
                </td>
                <td>
                    <?php
                        $is_valid = $bill['ocr_jumlah'] == $bill['jumlah'] && $bill['ocr_kode_found'] && $bill['ocr_date_found'];
                        if ($bill['ocr_jumlah']):
                            echo $is_valid ? '<span class="status-sesuai">✓ Sesuai</span>' : '<span class="status-tidak-sesuai">✗ Tidak Sesuai</span>';
                        else:
                            echo '<span class="status-belum">Belum Diproses</span>';
                        endif;
                    ?>
                </td>
                <td style="max-width:300px">
                    <?php
                    $details = json_decode($bill['ocr_details'] ?? '', true);
                    if ($details):
                        echo '<strong>Jumlah:</strong> Rp ' . number_format($details['jumlah'] ?? 0, 0, ',', '.') . '<br>';
                        echo '<strong>Kode:</strong> ' . htmlspecialchars($details['kode_tagihan'] ?? '-') . '<br>';
                        echo '<strong>Tanggal:</strong> ' . htmlspecialchars($details['tanggal'] ?? '-') . '<br>';
                    else:
                        echo '-';
                    endif;
                    ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
