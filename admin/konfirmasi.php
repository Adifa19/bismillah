<?php
include('../config.php');

// Cek apakah user sudah login dan merupakan admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}

// Fungsi run_ocr yang diperbaiki
function run_ocr($file_path, $kode_tagihan = '', $jumlah_tagihan = 0) {
    $full_path = realpath($file_path);
    if (!file_exists($full_path)) {
        error_log("File tidak ditemukan: " . $file_path);
        return array(
            'text' => '', 
            'jumlah' => 0, 
            'tanggal' => '', 
            'kode' => '', 
            'confidence' => 0,
            'error' => 'File tidak ditemukan'
        );
    }

    // Pastikan path python dan script OCR benar
    $python_path = '/usr/bin/python3'; // Sesuaikan dengan path python di server
    $ocr_script = __DIR__ . '/ocr.py'; // Pastikan path script OCR benar
    
    // Validasi script OCR ada
    if (!file_exists($ocr_script)) {
        error_log("Script OCR tidak ditemukan: " . $ocr_script);
        return array(
            'text' => '', 
            'jumlah' => 0, 
            'tanggal' => '', 
            'kode' => '', 
            'confidence' => 0,
            'error' => 'Script OCR tidak ditemukan'
        );
    }

    // Build command dengan proper escaping
    $command = sprintf(
        '%s %s %s %s %s 2>&1',
        escapeshellcmd($python_path),
        escapeshellarg($ocr_script),
        escapeshellarg($full_path),
        escapeshellarg($kode_tagihan),
        escapeshellarg($jumlah_tagihan)
    );
    
    error_log("OCR Command: " . $command);
    
    // Execute command
    exec($command, $output, $return_code);
    
    // Join output
    $output_string = implode("\n", $output);
    
    error_log("OCR Return Code: " . $return_code);
    error_log("OCR Raw Output: " . $output_string);
    
    // Initialize result
    $result = array(
        'text' => '',
        'jumlah' => 0,
        'tanggal' => '',
        'kode' => '',
        'confidence' => 0,
        'error' => ''
    );
    
    // Check for errors
    if ($return_code !== 0) {
        $result['error'] = 'Python script failed with code ' . $return_code;
        error_log("Python script failed: " . $output_string);
        return $result;
    }
    
    if (empty($output_string)) {
        $result['error'] = 'No output from Python script';
        error_log("No output from Python script");
        return $result;
    }
    
    // Parse output
    $lines = explode("\n", $output_string);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        
        // Check for errors first
        if (strpos($line, 'ERROR:') === 0) {
            $result['error'] = str_replace('ERROR:', '', $line);
            error_log("OCR Error: " . $result['error']);
            continue;
        }
        
        // Parse data
        if (strpos($line, 'EXTRACTED_TEXT:') === 0) {
            $result['text'] = trim(str_replace('EXTRACTED_TEXT:', '', $line));
        } elseif (strpos($line, 'AMOUNT:') === 0) {
            $amount_str = trim(str_replace('AMOUNT:', '', $line));
            $result['jumlah'] = intval($amount_str);
        } elseif (strpos($line, 'DATE:') === 0) {
            $result['tanggal'] = trim(str_replace('DATE:', '', $line));
        } elseif (strpos($line, 'CODE:') === 0) {
            $result['kode'] = trim(str_replace('CODE:', '', $line));
        } elseif (strpos($line, 'CONFIDENCE:') === 0) {
            $confidence_str = trim(str_replace('CONFIDENCE:', '', $line));
            $result['confidence'] = floatval($confidence_str);
        }
    }
    
    // Log hasil
    error_log("OCR Parsed Result: " . json_encode($result));
    
    return $result;
}

// Fungsi untuk test OCR secara manual
function test_ocr($file_path) {
    echo "<h3>Testing OCR</h3>";
    echo "<p>File: " . htmlspecialchars($file_path) . "</p>";
    
    if (!file_exists($file_path)) {
        echo "<p style='color: red;'>File tidak ditemukan!</p>";
        return;
    }
    
    $result = run_ocr($file_path, 'TAG123456', 50000);
    
    echo "<pre>";
    echo "Hasil OCR:\n";
    echo "Text: " . htmlspecialchars($result['text']) . "\n";
    echo "Jumlah: " . $result['jumlah'] . "\n";
    echo "Tanggal: " . $result['tanggal'] . "\n";
    echo "Kode: " . $result['kode'] . "\n";
    echo "Confidence: " . $result['confidence'] . "%\n";
    if (!empty($result['error'])) {
        echo "Error: " . htmlspecialchars($result['error']) . "\n";
    }
    echo "</pre>";
}

// Fungsi untuk debugging environment
function debug_ocr_environment() {
    echo "<h3>OCR Environment Debug</h3>";
    
    // Check Python
    $python_paths = ['/usr/bin/python3', '/usr/bin/python', 'python3', 'python'];
    foreach ($python_paths as $path) {
        $output = shell_exec("which $path 2>/dev/null");
        if ($output) {
            echo "<p>Python ditemukan: " . trim($output) . "</p>";
            
            // Check version
            $version = shell_exec("$path --version 2>&1");
            echo "<p>Version: " . trim($version) . "</p>";
            
            // Check EasyOCR
            $easyocr_check = shell_exec("$path -c 'import easyocr; print(easyocr.__version__)' 2>&1");
            echo "<p>EasyOCR: " . trim($easyocr_check) . "</p>";
            break;
        }
    }
    
    // Check OCR script
    $ocr_script = __DIR__ . '/ocr.py';
    if (file_exists($ocr_script)) {
        echo "<p>OCR Script: ✓ Ditemukan</p>";
        echo "<p>Path: " . $ocr_script . "</p>";
        echo "<p>Permissions: " . substr(sprintf('%o', fileperms($ocr_script)), -4) . "</p>";
    } else {
        echo "<p style='color: red;'>OCR Script: ✗ Tidak ditemukan</p>";
    }
    
    // Check uploads directory
    $uploads_dir = '../warga/uploads/bukti_pembayaran/';
    if (is_dir($uploads_dir)) {
        echo "<p>Uploads Directory: ✓ Ditemukan</p>";
        echo "<p>Path: " . realpath($uploads_dir) . "</p>";
        echo "<p>Permissions: " . substr(sprintf('%o', fileperms($uploads_dir)), -4) . "</p>";
        
        // List files
        $files = scandir($uploads_dir);
        $image_files = array_filter($files, function($file) {
            return in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif']);
        });
        
        echo "<p>Image files: " . count($image_files) . "</p>";
        if (count($image_files) > 0) {
            echo "<p>Sample file: " . $image_files[0] . "</p>";
        }
    } else {
        echo "<p style='color: red;'>Uploads Directory: ✗ Tidak ditemukan</p>";
    }
}

// Proses OCR yang diperbaiki
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'run_ocr') {
        try {
            $stmt = $pdo->prepare("
                SELECT ub.id, ub.bukti_pembayaran, b.kode_tagihan, b.jumlah
                FROM user_bills ub 
                JOIN bills b ON ub.bill_id = b.id
                WHERE ub.status = 'menunggu_konfirmasi' AND ub.bukti_pembayaran IS NOT NULL
                LIMIT 5
            ");
            $stmt->execute();
            $bills = $stmt->fetchAll();
            
            $processed = 0;
            $errors = [];
            
            foreach ($bills as $bill) {
                $file_path = '../warga/uploads/bukti_pembayaran/' . $bill['bukti_pembayaran'];
                
                if (file_exists($file_path)) {
                    error_log("Memproses OCR untuk file: " . $file_path);
                    
                    $ocr_result = run_ocr($file_path, $bill['kode_tagihan'], $bill['jumlah']);
                    
                    if (!empty($ocr_result['error'])) {
                        $errors[] = "File {$bill['bukti_pembayaran']}: {$ocr_result['error']}";
                        continue;
                    }
                    
                    // Update database
                    $update_stmt = $pdo->prepare("
                        UPDATE user_bills SET 
                            ocr_jumlah = ?, 
                            ocr_kode_found = ?, 
                            ocr_date_found = ?, 
                            ocr_confidence = ?, 
                            ocr_details = ?
                        WHERE id = ?
                    ");
                    
                    $ocr_details = json_encode([
                        'extracted_text' => $ocr_result['text'],
                        'extracted_date' => $ocr_result['tanggal'],
                        'extracted_code' => $ocr_result['kode'],
                        'processed_at' => date('Y-m-d H:i:s'),
                        'file_path' => $file_path,
                        'confidence' => $ocr_result['confidence']
                    ]);
                    
                    $update_stmt->execute([
                        $ocr_result['jumlah'],
                        !empty($ocr_result['kode']) ? 1 : 0,
                        !empty($ocr_result['tanggal']) ? 1 : 0,
                        $ocr_result['confidence'],
                        $ocr_details,
                        $bill['id']
                    ]);
                    
                    $processed++;
                } else {
                    $errors[] = "File {$bill['bukti_pembayaran']}: File tidak ditemukan";
                }
            }
            
            if ($processed > 0) {
                $success = "OCR berhasil dijalankan untuk $processed bukti pembayaran!";
            }
            
            if (!empty($errors)) {
                $error = "Beberapa file gagal diproses:\n" . implode("\n", $errors);
            }
            
        } catch(PDOException $e) {
            error_log("Error OCR: " . $e->getMessage());
            $error = "Error OCR: " . $e->getMessage();
        }
    } elseif ($_POST['action'] == 'test_ocr' && isset($_POST['test_file'])) {
        // Test OCR untuk file tertentu
        $test_file = '../warga/uploads/bukti_pembayaran/' . $_POST['test_file'];
        test_ocr($test_file);
    } elseif ($_POST['action'] == 'debug_env') {
        debug_ocr_environment();
    }
}

// Ambil data untuk ditampilkan
try {
    // Ambil bills yang menunggu konfirmasi
    $stmt = $pdo->prepare("
        SELECT ub.id, ub.bukti_pembayaran, ub.status, ub.ocr_jumlah, 
               ub.ocr_kode_found, ub.ocr_date_found, ub.ocr_confidence, 
               ub.ocr_details, ub.created_at,
               b.kode_tagihan, b.jumlah, b.nama_tagihan,
               u.nama, u.email
        FROM user_bills ub 
        JOIN bills b ON ub.bill_id = b.id
        JOIN users u ON ub.user_id = u.id
        WHERE ub.status = 'menunggu_konfirmasi' AND ub.bukti_pembayaran IS NOT NULL
        ORDER BY ub.created_at DESC
        LIMIT 20
    ");
    $stmt->execute();
    $pending_bills = $stmt->fetchAll();
    
    // Ambil files untuk testing
    $uploads_dir = '../warga/uploads/bukti_pembayaran/';
    $test_files = [];
    if (is_dir($uploads_dir)) {
        $files = scandir($uploads_dir);
        foreach ($files as $file) {
            if (in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif'])) {
                $test_files[] = $file;
            }
        }
    }
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $error = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OCR Management - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .confidence-high { color: #28a745; }
        .confidence-medium { color: #ffc107; }
        .confidence-low { color: #dc3545; }
        .ocr-result { 
            font-family: monospace; 
            font-size: 0.9em; 
            background: #f8f9fa; 
            padding: 10px; 
            border-radius: 4px; 
            margin: 5px 0; 
        }
        .image-preview { 
            max-width: 200px; 
            max-height: 200px; 
            object-fit: cover; 
            border: 1px solid #ddd; 
            border-radius: 4px; 
        }
        .status-pending { background-color: #fff3cd; }
        .status-processed { background-color: #d1ecf1; }
        .btn-group-vertical .btn { margin-bottom: 5px; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 bg-dark text-white vh-100 p-3">
                <h4>Admin Panel</h4>
                <nav class="nav flex-column">
                    <a class="nav-link text-white" href="dashboard.php">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                    <a class="nav-link text-white" href="bills.php">
                        <i class="fas fa-file-invoice"></i> Tagihan
                    </a>
                    <a class="nav-link text-white active" href="ocr.php">
                        <i class="fas fa-eye"></i> OCR Management
                    </a>
                    <a class="nav-link text-white" href="users.php">
                        <i class="fas fa-users"></i> Users
                    </a>
                    <a class="nav-link text-white" href="logout.php">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </nav>
            </div>

            <!-- Main Content -->
            <div class="col-md-10 p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1><i class="fas fa-eye"></i> OCR Management</h1>
                    <div class="btn-group">
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="action" value="run_ocr">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-play"></i> Run OCR
                            </button>
                        </form>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="action" value="debug_env">
                            <button type="submit" class="btn btn-info">
                                <i class="fas fa-bug"></i> Debug Environment
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Alert Messages -->
                <?php if (isset($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo nl2br(htmlspecialchars($error)); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- OCR Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body">
                                <h5><i class="fas fa-clock"></i> Menunggu Konfirmasi</h5>
                                <h3><?php echo count($pending_bills); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <h5><i class="fas fa-robot"></i> Dengan OCR</h5>
                                <h3><?php echo count(array_filter($pending_bills, function($b) { return $b['ocr_confidence'] > 0; })); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <h5><i class="fas fa-check"></i> Confidence > 80%</h5>
                                <h3><?php echo count(array_filter($pending_bills, function($b) { return $b['ocr_confidence'] > 80; })); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-danger text-white">
                            <div class="card-body">
                                <h5><i class="fas fa-exclamation"></i> Confidence < 50%</h5>
                                <h3><?php echo count(array_filter($pending_bills, function($b) { return $b['ocr_confidence'] > 0 && $b['ocr_confidence'] < 50; })); ?></h3>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Test OCR Section -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-vial"></i> Test OCR</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label">Pilih File untuk Test:</label>
                                <select name="test_file" class="form-select" required>
                                    <option value="">-- Pilih File --</option>
                                    <?php foreach ($test_files as $file): ?>
                                        <option value="<?php echo htmlspecialchars($file); ?>">
                                            <?php echo htmlspecialchars($file); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" name="action" value="test_ocr" class="btn btn-warning w-100">
                                    <i class="fas fa-test-tube"></i> Test OCR
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Pending Bills Table -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-list"></i> Bukti Pembayaran Menunggu Konfirmasi</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>User</th>
                                        <th>Tagihan</th>
                                        <th>Jumlah</th>
                                        <th>Bukti</th>
                                        <th>OCR Result</th>
                                        <th>Confidence</th>
                                        <th>Status</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($pending_bills)): ?>
                                        <tr>
                                            <td colspan="9" class="text-center text-muted">
                                                <i class="fas fa-inbox"></i> Tidak ada bukti pembayaran yang menunggu konfirmasi
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($pending_bills as $bill): ?>
                                            <tr class="<?php echo $bill['ocr_confidence'] > 0 ? 'status-processed' : 'status-pending'; ?>">
                                                <td><?php echo $bill['id']; ?></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($bill['nama']); ?></strong><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($bill['email']); ?></small>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($bill['nama_tagihan']); ?></strong><br>
                                                    <small class="text-muted">Kode: <?php echo htmlspecialchars($bill['kode_tagihan']); ?></small>
                                                </td>
                                                <td>
                                                    <strong>Rp <?php echo number_format($bill['jumlah'], 0, ',', '.'); ?></strong>
                                                    <?php if ($bill['ocr_jumlah'] > 0): ?>
                                                        <br><small class="text-info">OCR: Rp <?php echo number_format($bill['ocr_jumlah'], 0, ',', '.'); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($bill['bukti_pembayaran']): ?>
                                                        <a href="../warga/uploads/bukti_pembayaran/<?php echo htmlspecialchars($bill['bukti_pembayaran']); ?>" 
                                                           target="_blank" class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-image"></i> Lihat
                                                        </a>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($bill['bukti_pembayaran']); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($bill['ocr_confidence'] > 0): ?>
                                                        <div class="ocr-result">
                                                            <?php
                                                            $ocr_details = json_decode($bill['ocr_details'], true);
                                                            if ($ocr_details) {
                                                                echo "<strong>Code Found:</strong> " . ($bill['ocr_kode_found'] ? 'Yes' : 'No') . "<br>";
                                                                echo "<strong>Date Found:</strong> " . ($bill['ocr_date_found'] ? 'Yes' : 'No') . "<br>";
                                                                if (!empty($ocr_details['extracted_code'])) {
                                                                    echo "<strong>Extracted Code:</strong> " . htmlspecialchars($ocr_details['extracted_code']) . "<br>";
                                                                }
                                                                if (!empty($ocr_details['extracted_date'])) {
                                                                    echo "<strong>Extracted Date:</strong> " . htmlspecialchars($ocr_details['extracted_date']) . "<br>";
                                                                }
                                                            }
                                                            ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-muted"><i class="fas fa-clock"></i> Belum diproses</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($bill['ocr_confidence'] > 0): ?>
                                                        <span class="badge <?php 
                                                            echo $bill['ocr_confidence'] >= 80 ? 'bg-success' : 
                                                                ($bill['ocr_confidence'] >= 50 ? 'bg-warning' : 'bg-danger'); 
                                                        ?>">
                                                            <?php echo number_format($bill['ocr_confidence'], 1); ?>%
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-warning">Menunggu</span>
                                                </td>
                                                <td>
                                                    <div class="btn-group-vertical">
                                                        <a href="verify_payment.php?id=<?php echo $bill['id']; ?>" 
                                                           class="btn btn-sm btn-success">
                                                            <i class="fas fa-check"></i> Verifikasi
                                                        </a>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="action" value="test_ocr">
                                                            <input type="hidden" name="test_file" value="<?php echo htmlspecialchars($bill['bukti_pembayaran']); ?>">
                                                            <button type="submit" class="btn btn-sm btn-info">
                                                                <i class="fas fa-robot"></i> Test OCR
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-refresh halaman setiap 30 detik
        setTimeout(function() {
            if (!document.querySelector('.alert')) {
                location.reload();
            }
        }, 30000);
        
        // Konfirmasi sebelum menjalankan OCR
        document.querySelector('form[method="POST"] button[value="run_ocr"]').addEventListener('click', function(e) {
            if (!confirm('Apakah Anda yakin ingin menjalankan OCR untuk semua bukti pembayaran yang menunggu?')) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>
