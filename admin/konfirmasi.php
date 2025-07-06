<?php
require_once '../config.php';
requireLogin();

ini_set('display_errors', 1);
error_reporting(E_ALL);

$message = '';

if (isset($_POST['run_ocr']) && isset($_POST['user_bill_id'])) {
    $user_bill_id = $_POST['user_bill_id'];

    $stmt = $pdo->prepare("SELECT ub.*, b.kode_tagihan, b.jumlah FROM user_bills ub
                           JOIN bills b ON ub.bill_id = b.id
                           WHERE ub.id = ?");
    $stmt->execute([$user_bill_id]);
    $user_bill = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user_bill && $user_bill['bukti_pembayaran']) {
        $image_path = '../warga/uploads/bukti_pembayaran/' . $user_bill['bukti_pembayaran'];

        $command = escapeshellcmd("python3 ../ocr.py " . escapeshellarg($image_path) . " " . escapeshellarg($user_bill['kode_tagihan']) . " " . escapeshellarg($user_bill['jumlah']));
        $output = shell_exec($command);

        if ($output) {
            $ocr_result = json_decode($output, true);
            if ($ocr_result) {
                $ocr_details = json_encode([
                    'extracted_text' => $ocr_result['extracted_text'] ?? '',
                    'normalized_text' => $ocr_result['normalized_text'] ?? '',
                    'extracted_code' => $ocr_result['kode_tagihan'] ?? '',
                    'extracted_date' => $ocr_result['tanggal'] ?? '',
                ]);

                $stmt = $pdo->prepare("UPDATE user_bills SET 
                    ocr_jumlah = ?, 
                    ocr_kode_found = ?, 
                    ocr_date_found = ?, 
                    ocr_confidence = ?, 
                    ocr_details = ?
                    WHERE id = ?");
                $stmt->execute([
                    $ocr_result['jumlah'] ?? 0,
                    isset($ocr_result['kode_tagihan']) ? 1 : 0,
                    isset($ocr_result['tanggal']) ? 1 : 0,
                    $ocr_result['confidence'] ?? 0.0,
                    $ocr_details,
                    $user_bill_id
                ]);
                $message = "OCR berhasil dijalankan.";
            } else {
                $message = "Gagal parsing output OCR.";
            }
        } else {
            $message = "OCR gagal dijalankan.";
        }
    } else {
        $message = "Data tidak ditemukan atau belum ada bukti pembayaran.";
    }
}

$stmt = $pdo->query("SELECT ub.*, b.kode_tagihan, b.jumlah, b.deskripsi, b.tenggat_waktu, b.tanggal AS tanggal_tagihan, u.username
                     FROM user_bills ub
                     JOIN bills b ON ub.bill_id = b.id
                     JOIN users u ON ub.user_id = u.id
                     WHERE ub.status = 'menunggu_konfirmasi'");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Konfirmasi Pembayaran</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container mt-5">
    <h2>Daftar Pembayaran Menunggu Konfirmasi</h2>

    <?php if ($message): ?>
        <div class="alert alert-info"><?= htmlspecialchars($message) ?></div>
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
                <th>Ekstrak OCR</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['username']) ?></td>
                    <td><?= htmlspecialchars($row['kode_tagihan']) ?></td>
                    <td>Rp <?= number_format($row['jumlah'], 0, ',', '.') ?></td>
                    <td><?= htmlspecialchars($row['deskripsi']) ?></td>
                    <td><?= htmlspecialchars($row['tanggal_tagihan']) ?></td>
                    <td><?= htmlspecialchars($row['tenggat_waktu']) ?></td>
                    <td>
                        <?php if ($row['bukti_pembayaran']): ?>
                            <img src="../warga/uploads/bukti_pembayaran/<?= htmlspecialchars($row['bukti_pembayaran']) ?>" alt="Bukti" style="width:100px">
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php
                        $ocr = json_decode($row['ocr_details'], true);
                        echo '<strong>Jumlah:</strong> Rp ' . number_format($row['ocr_jumlah'], 0, ',', '.') . '<br>';
                        echo '<strong>Kode:</strong> ' . ($ocr['extracted_code'] ?? '-') . '<br>';
                        echo '<strong>Tanggal:</strong> ' . ($ocr['extracted_date'] ?? '-') . '<br>';
                        echo '<strong>Confidence:</strong> ' . ($row['ocr_confidence'] ?? '0') . '%';
                        ?>
                    </td>
                    <td>
                        <form method="POST" class="mb-2">
                            <input type="hidden" name="user_bill_id" value="<?= $row['id'] ?>">
                            <button type="submit" name="run_ocr" class="btn btn-warning btn-sm">Jalankan OCR</button>
                        </form>
                        <form method="POST">
                            <input type="hidden" name="user_bill_id" value="<?= $row['id'] ?>">
                            <select name="status" class="form-select form-select-sm mb-1">
                                <option value="konfirmasi">Konfirmasi</option>
                                <option value="tolak">Tolak</option>
                            </select>
                            <button type="submit" name="update_status" class="btn btn-success btn-sm">Simpan</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
