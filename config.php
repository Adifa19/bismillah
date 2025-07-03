<?php
session_start();
$host = 'localhost';
$dbname = 'tetangga_id';
$username = 'tetangga_app';  // <- pastikan ini bukan 'root'
$password = 'Dipa190503!@#'; // <- password yang Anda buat tadi

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

// Function untuk cek login
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Function untuk cek admin
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Function untuk redirect jika belum login
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

// Function untuk redirect jika bukan admin
function requireAdmin() {
    if (!isAdmin()) {
        header('Location: admin/pendataan.php');
        exit;
    }
}

// Fungsi sanitize output (simple)
function sanitize($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}
?>
