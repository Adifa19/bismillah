<?php
require_once '../config.php';
requireLogin();
requireAdmin();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $stmt = $pdo->prepare("INSERT INTO kegiatan (judul, deskripsi, hari, tanggal_kegiatan, alamat) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([
                    $_POST['judul'],
                    $_POST['deskripsi'],
                    $_POST['hari'],
                    $_POST['tanggal_kegiatan'],
                    $_POST['alamat']
                ]);
                $success = "Kegiatan berhasil ditambahkan!";
                break;
                
            case 'update_status':
                $foto_kegiatan = null;
                $dokumentasi_link = $_POST['dokumentasi_link'] ?? null;
                
                // Handle file upload
                if (isset($_FILES['foto_kegiatan']) && $_FILES['foto_kegiatan']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = '../uploads/kegiatan/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    $file_extension = pathinfo($_FILES['foto_kegiatan']['name'], PATHINFO_EXTENSION);
                    $foto_kegiatan = 'kegiatan_' . time() . '.' . $file_extension;
                    move_uploaded_file($_FILES['foto_kegiatan']['tmp_name'], $upload_dir . $foto_kegiatan);
                }
                
                $stmt = $pdo->prepare("UPDATE kegiatan SET status_kegiatan = 'Selesai', foto_kegiatan = ?, dokumentasi_link = ? WHERE id = ?");
                $stmt->execute([$foto_kegiatan, $dokumentasi_link, $_POST['id']]);
                $success = "Status kegiatan berhasil diupdate!";
                break;
                
            case 'delete':
                $stmt = $pdo->prepare("DELETE FROM kegiatan WHERE id = ?");
                $stmt->execute([$_POST['id']]);
                $success = "Kegiatan berhasil dihapus!";
                break;
        }
    }
}

// Get all activities
$stmt = $pdo->query("SELECT * FROM kegiatan ORDER BY tanggal_kegiatan DESC");
$kegiatan = $stmt->fetchAll();

// Calculate statistics
$total_kegiatan = count($kegiatan);
$kegiatan_selesai = count(array_filter($kegiatan, function($item) {
    return $item['status_kegiatan'] === 'Selesai';
}));
$kegiatan_direncanakan = $total_kegiatan - $kegiatan_selesai;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Kelola Kegiatan RT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4f46e5;
            --primary-dark: #3730a3;
            --secondary-color: #f8fafc;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --text-dark: #1f2937;
            --text-muted: #6b7280;
            --border-color: #e5e7eb;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        body {
            background-color: var(--secondary-color);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            color: var(--text-dark);
            line-height: 1.6;
        }

        /* Layout with Sidebar */
        .wrapper {
            display: flex;
            min-height: 100vh;
        }

        .content {
            flex: 1;
            margin-left: 0;
            transition: margin-left 0.3s ease;
        }

        /* Header Section */
        .page-header {
            background: linear-gradient(135deg, #4f46e5 0%, #581c87 100%);
            color: white;
            padding: 2rem;
            border-radius: 16px;
            margin-bottom: 2rem;
            box-shadow: 0 10px 25px rgba(79, 70, 229, 0.15);
        }

        .page-header h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .page-header p {
            opacity: 0.9;
            font-size: 1.1rem;
            margin: 0;
        }

        /* Statistics Cards */
        .stats-row {
            margin-top: -4rem;
            position: relative;
            z-index: 10;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border-color);
            height: 100%;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--primary-color);
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }

        .stat-card.total::before { background: var(--primary-color); }
        .stat-card.planned::before { background: var(--warning-color); }
        .stat-card.completed::before { background: var(--success-color); }

        .stat-icon {
            width: 3rem;
            height: 3rem;
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            margin-bottom: 1rem;
            color: white;
        }

        .stat-card.total .stat-icon { background: var(--primary-color); }
        .stat-card.planned .stat-icon { background: var(--warning-color); }
        .stat-card.completed .stat-icon { background: var(--success-color); }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
            color: var(--text-dark);
        }

        .stat-label {
            color: var(--text-muted);
            font-size: 0.875rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        /* Content Cards */
        .content-card {
            background: white;
            border-radius: 1rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .card-header-custom {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }

        .card-title {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Form Styling */
        .form-label {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }

        .form-control, .form-select {
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
            transition: all 0.2s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        /* Button Styling */
        .btn {
            border-radius: 0.5rem;
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            font-size: 0.875rem;
            transition: all 0.2s ease;
            border: none;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }

        .btn-success {
            background: var(--success-color);
            color: white;
        }

        .btn-danger {
            background: var(--danger-color);
            color: white;
        }

        .btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.75rem;
        }

        /* Table Styling */
        .table-container {
            overflow-x: auto;
        }

        .table {
            margin: 0;
        }

        .table th {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            color: var(--text-dark);
            font-weight: 600;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 1rem;
            border: none;
            border-bottom: 2px solid var(--border-color);
        }

        .table td {
            padding: 1rem;
            vertical-align: middle;
            border-color: var(--border-color);
        }

        .table tbody tr:hover {
            background-color: #f8fafc;
        }

        /* Badge Styling */
        .badge {
            padding: 0.5rem 0.75rem;
            font-size: 0.75rem;
            font-weight: 500;
            border-radius: 0.375rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .badge.bg-success {
            background: var(--success-color) !important;
        }

        .badge.bg-warning {
            background: var(--warning-color) !important;
            color: white;
        }

        /* Alert Styling */
        .alert {
            border: none;
            border-radius: 0.75rem;
            padding: 1rem 1.5rem;
            margin-bottom: 2rem;
            border-left: 4px solid var(--success-color);
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: #065f46;
        }

        /* Modal Styling */
        .modal-content {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        .modal-header {
            border-bottom: 1px solid var(--border-color);
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        }

        .modal-title {
            font-weight: 600;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--text-muted);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .page-title {
                font-size: 1.75rem;
            }
            
            .stats-row {
                margin-top: -2rem;
            }
            
            .stat-card {
                margin-bottom: 1rem;
            }
            
            .table-container {
                font-size: 0.875rem;
            }
            
            .btn-group .btn {
                padding: 0.25rem 0.5rem;
            }
        }

        @media (max-width: 576px) {
            .page-header {
                padding: 1.5rem 0;
            }
            
            .content-card {
                margin-left: 0.5rem;
                margin-right: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <?php include('sidebar.php'); ?>
        
        <div class="content">
            <!-- Page Header -->
            <div class="page-header">
                <div class="container-fluid">
                    <h1 class="page-title">
                        <i class="fas fa-calendar-alt"></i>
                        Kelola Kegiatan Warga
                    </h1>
                    <p class="page-subtitle">Catat dan pantau seluruh kegiatan warga dengan mudah dan terorganisir</p>
                </div>
            </div>

            <div class="container-fluid">
                <!-- Statistics Cards -->
                <div class="row stats-row">
                    <div class="col-lg-4 col-md-4 col-sm-6 mb-4">
                        <div class="stat-card total">
                            <div class="stat-icon">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                            <div class="stat-value"><?= $total_kegiatan ?></div>
                            <div class="stat-label">Total Kegiatan</div>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-4 col-sm-6 mb-4">
                        <div class="stat-card planned">
                            <div class="stat-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="stat-value"><?= $kegiatan_direncanakan ?></div>
                            <div class="stat-label">Direncanakan</div>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-4 col-sm-12 mb-4">
                        <div class="stat-card completed">
                            <div class="stat-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="stat-value"><?= $kegiatan_selesai ?></div>
                            <div class="stat-label">Selesai</div>
                        </div>
                    </div>
                </div>

                <?php if (isset($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle me-2"></i>
                        <?= $success ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Form Tambah Kegiatan -->
                <div class="content-card">
                    <div class="card-header-custom">
                        <h5 class="card-title">
                            <i class="fas fa-plus-circle"></i>
                            Tambah Kegiatan Baru
                        </h5>
                    </div>
                    <div class="p-4">
                        <form method="POST">
                            <input type="hidden" name="action" value="add">
                            <div class="row">
                                <div class="col-lg-6 col-md-6 mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-heading me-1"></i>
                                        Judul Kegiatan
                                    </label>
                                    <input type="text" class="form-control" name="judul" required maxlength="50" 
                                           placeholder="Masukkan judul kegiatan">
                                </div>
                                <div class="col-lg-3 col-md-6 mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-calendar-day me-1"></i>
                                        Hari
                                    </label>
                                    <select class="form-select" name="hari" required>
                                        <option value="">Pilih Hari</option>
                                        <option value="Senin">Senin</option>
                                        <option value="Selasa">Selasa</option>
                                        <option value="Rabu">Rabu</option>
                                        <option value="Kamis">Kamis</option>
                                        <option value="Jumat">Jumat</option>
                                        <option value="Sabtu">Sabtu</option>
                                        <option value="Minggu">Minggu</option>
                                    </select>
                                </div>
                                <div class="col-lg-3 col-md-6 mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-calendar me-1"></i>
                                        Tanggal Kegiatan
                                    </label>
                                    <input type="date" class="form-control" name="tanggal_kegiatan" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-lg-6 col-md-6 mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-map-marker-alt me-1"></i>
                                        Alamat/Lokasi
                                    </label>
                                    <input type="text" class="form-control" name="alamat" required maxlength="100" 
                                           placeholder="Masukkan lokasi kegiatan">
                                </div>
                                <div class="col-lg-6 col-md-6 mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-align-left me-1"></i>
                                        Deskripsi
                                    </label>
                                    <textarea class="form-control" name="deskripsi" rows="3" required 
                                              placeholder="Deskripsikan kegiatan secara singkat"></textarea>
                                </div>
                            </div>
                            <div class="d-flex justify-content-end mt-3">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>
                                    Simpan Kegiatan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Daftar Kegiatan -->
                <div class="content-card">
                    <div class="card-header-custom">
                        <h5 class="card-title">
                            <i class="fas fa-list-alt"></i>
                            Daftar Kegiatan
                        </h5>
                    </div>
                    <div class="table-container">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th style="width: 5%">No</th>
                                    <th style="width: 30%">Judul & Deskripsi</th>
                                    <th style="width: 15%">Tanggal</th>
                                    <th style="width: 20%">Alamat</th>
                                    <th style="width: 10%">Status</th>
                                    <th style="width: 20%">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($kegiatan)): ?>
                                    <tr>
                                        <td colspan="6">
                                            <div class="empty-state">
                                                <i class="fas fa-inbox"></i>
                                                <p class="mb-0">Belum ada kegiatan yang terdaftar</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($kegiatan as $index => $item): ?>
                                        <tr>
                                            <td class="fw-bold"><?= $index + 1 ?></td>
                                            <td>
                                                <div class="fw-semibold text-dark mb-1">
                                                    <?= sanitize($item['judul']) ?>
                                                </div>
                                                <small class="text-muted">
                                                    <?= sanitize(substr($item['deskripsi'], 0, 100)) ?>
                                                    <?= strlen($item['deskripsi']) > 100 ? '...' : '' ?>
                                                </small>
                                            </td>
                                            <td>
                                                <div class="fw-semibold"><?= sanitize($item['hari']) ?></div>
                                                <small class="text-muted">
                                                    <?= date('d M Y', strtotime($item['tanggal_kegiatan'])) ?>
                                                </small>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?= sanitize(substr($item['alamat'], 0, 50)) ?>
                                                    <?= strlen($item['alamat']) > 50 ? '...' : '' ?>
                                                </small>
                                            </td>
                                            <td>
                                                <?php if ($item['status_kegiatan'] === 'Selesai'): ?>
                                                    <span class="badge bg-success">
                                                        <i class="fas fa-check me-1"></i>
                                                        Selesai
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">
                                                        <i class="fas fa-clock me-1"></i>
                                                        Direncanakan
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <?php if ($item['status_kegiatan'] === 'Direncanakan'): ?>
                                                        <button class="btn btn-success" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#updateModal<?= $item['id'] ?>" 
                                                                title="Selesaikan Kegiatan">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <button class="btn btn-danger" 
                                                            onclick="deleteKegiatan(<?= $item['id'] ?>)" 
                                                            title="Hapus Kegiatan">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                                
                                                <!-- Modal Update Status -->
                                                <?php if ($item['status_kegiatan'] === 'Direncanakan'): ?>
                                                <div class="modal fade" id="updateModal<?= $item['id'] ?>" tabindex="-1">
                                                    <div class="modal-dialog modal-dialog-centered">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">
                                                                    <i class="fas fa-check-circle me-2"></i>
                                                                    Selesaikan Kegiatan
                                                                </h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <form method="POST" enctype="multipart/form-data">
                                                                <div class="modal-body">
                                                                    <input type="hidden" name="action" value="update_status">
                                                                    <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                                                    
                                                                    <div class="alert alert-info mb-3">
                                                                        <i class="fas fa-info-circle me-2"></i>
                                                                        Menyelesaikan kegiatan: <strong><?= sanitize($item['judul']) ?></strong>
                                                                    </div>
                                                                    
                                                                    <div class="mb-3">
                                                                        <label class="form-label">
                                                                            <i class="fas fa-camera me-2"></i>
                                                                            Upload Foto Kegiatan
                                                                        </label>
                                                                        <input type="file" class="form-control" name="foto_kegiatan" accept="image/*">
                                                                        <div class="form-text">Format: JPG, PNG, GIF (Opsional)</div>
                                                                    </div>
                                                                    
                                                                    <div class="mb-3">
                                                                        <label class="form-label">
                                                                            <i class="fas fa-link me-2"></i>
                                                                            Link Dokumentasi
                                                                        </label>
                                                                        <input type="url" class="form-control" name="dokumentasi_link" 
                                                                               placeholder="https://...">
                                                                        <div class="form-text">Link ke album foto/video (Opsional)</div>
                                                                    </div>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                                                        <i class="fas fa-times me-2"></i>
                                                                        Batal
                                                                    </button>
                                                                    <button type="submit" class="btn btn-success">
                                                                        <i class="fas fa-check me-2"></i>
                                                                        Selesaikan Kegiatan
                                                                    </button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php endif; ?>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function deleteKegiatan(id) {
            if (confirm('Apakah Anda yakin ingin menghapus kegiatan ini?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    const bsAlert = bootstrap.Alert.getInstance(alert);
                    if (bsAlert) {
                        bsAlert.close();
                    }
                }, 5000);
            });
        });

        // Add loading states to buttons
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function() {
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Menyimpan...';
                }
            });
        });
    </script>
</body>
</html>