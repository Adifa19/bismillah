<?php
$host = 'localhost';
$dbname = 'tetangga_id';
$username = 'tetangga_app';
$password = 'Dipa190503!@#';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✅ Koneksi berhasil!<br>";
    
    // Tampilkan tabel yang ada
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Tabel yang tersedia:<br>";
    foreach($tables as $table) {
        echo "- $table<br>";
    }
    
} catch(PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
