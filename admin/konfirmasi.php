<?php
include('../config.php');

// Cek apakah user sudah login dan merupakan admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}

// Fungsi untuk format tanggal Indonesia
function format_tanggal_indo($tanggal) {
    if (empty($tanggal)) return '-';
    
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    
    $timestamp = strtotime($tanggal);
    if ($timestamp === false) return $tanggal;
    
    $hari = date('d', $timestamp);
    $bulan_num = date('n', $timestamp);
    $tahun = date('Y', $timestamp);
    
    return $hari . ' ' . $bulan[$bulan_num] . ' ' . $tahun;
}

// Fungsi untuk debug OCR hasil
function debugOCRDate($bill) {
    echo "<div class='debug-info' style='background: #f8f9fa; padding: 10px; margin: 10px 0; border-radius: 5px; font-size: 12px;'>";
    echo "<strong>Debug OCR Date:</strong><br>";
    echo "ocr_date_found: " . ($bill['ocr_date_found'] ? 'true' : 'false') . "<br>";
    
    if ($bill['ocr_details']) {
        $ocr_details = json_decode($bill['ocr_details'], true);
        if ($ocr_details) {
            echo "extracted_date: " . ($ocr_details['extracted_date'] ?? 'null') . "<br>";
            echo "extracted_text (first 200 chars): " . substr($ocr_details['extracted_text'] ?? '', 0, 200) . "...<br>";
        }
    }
    echo "</div>";
}

// Fungsi validasi OCR yang diperbaiki dan disatukan
function getOCRValidationStatus($bill) {
    // Inisialisasi default values
    $is_amount_match = false;
    $is_code_valid = false;
    $is_date_valid = false;
    $confidence = floatval($bill['ocr_confidence']);
    
    // Debug info
    $debug_info = [
        'ocr_jumlah' => $bill['ocr_jumlah'],
        'expected_jumlah' => $bill['jumlah'],
        'ocr_details' => $bill['ocr_details'],
        'confidence' => $confidence
    ];
    
    // 1. Validasi Jumlah
    if ($bill['ocr_jumlah'] > 0) {
        $is_amount_match = ($bill['ocr_jumlah'] == $bill['jumlah']);
    }
    
    // 2. Validasi Kode Tagihan
    if ($bill['ocr_kode_found']) {
        $ocr_details = json_decode($bill['ocr_details'], true);
        if ($ocr_details && isset($ocr_details['extracted_code'])) {
            $extracted_code = trim($ocr_details['extracted_code']);
            if (!empty($extracted_code)) {
                $is_code_valid = (strtoupper($extracted_code) === strtoupper(trim($bill['kode_tagihan'])));
                $debug_info['extracted_code'] = $extracted_code;
                $debug_info['expected_code'] = $bill['kode_tagihan'];
            }
        }
    }
    
    // 3. Validasi Tanggal
    if ($bill['ocr_date_found']) {
        $ocr_details = json_decode($bill['ocr_details'], true);
        if ($ocr_details && isset($ocr_details['extracted_date'])) {
            $extracted_date = trim($ocr_details['extracted_date']);
            if (!empty($extracted_date)) {
                $extracted_timestamp = strtotime($extracted_date);
                if ($extracted_timestamp !== false) {
                    $today = time();
                    $tenggat_timestamp = strtotime($bill['tenggat']);
                    $tanggal_kirim_timestamp = strtotime($bill['tanggal_kirim']);
                    $tolerance_days_before_send = 7;
                    $minimum_allowed_timestamp = $tanggal_kirim_timestamp - ($tolerance_days_before_send * 24 * 60 * 60);
                    
                    $is_date_valid = ($extracted_timestamp <= $today && 
                                    $extracted_timestamp <= $tenggat_timestamp && 
                                    $extracted_timestamp >= $minimum_allowed_timestamp);
                    
                    $debug_info['extracted_date'] = $extracted_date;
                    $debug_info['date_conditions'] = [
                        'extracted_timestamp' => date('Y-m-d H:i:s', $extracted_timestamp),
                        'today' => date('Y-m-d H:i:s', $today),
                        'tenggat' => date('Y-m-d H:i:s', $tenggat_timestamp),
                        'min_allowed' => date('Y-m-d H:i:s', $minimum_allowed_timestamp),
                        'condition_1' => $extracted_timestamp <= $today,
                        'condition_2' => $extracted_timestamp <= $tenggat_timestamp,
                        'condition_3' => $extracted_timestamp >= $minimum_allowed_timestamp
                    ];
                }
            }
        }
    }
    
    // 4. Status Final
    $is_fully_valid = ($is_amount_match && $is_code_valid && $is_date_valid && $confidence >= 70);
    $has_ocr_data = ($bill['ocr_jumlah'] > 0);
    
    return [
        'amount_match' => $is_amount_match,
        'code_valid' => $is_code_valid,
        'date_valid' => $is_date_valid,
        'confidence' => $confidence,
        'is_fully_valid' => $is_fully_valid,
        'has_ocr_data' => $has_ocr_data,
        'debug_info' => $debug_info
    ];
}

// Fungsi untuk mendapatkan kelas confidence
function getConfidenceClass($confidence) {
    if ($confidence >= 80) return 'high';
    if ($confidence >= 60) return 'medium';
    return 'low';
}

// Fungsi ekstrak jumlah dari teks OCR
function extractAmount($text) {
    $patterns = [
        '/(?:nominal|jumlah|total|bayar|rp\.?)\s*:?\s*([\d.,]+)/i',
        '/rp\.?\s*([\d.,]+)/i',
        '/([\d]{1,3}(?:[.,]\d{3})*(?:[.,]\d{2})?)/i'
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $text, $match)) {
            $angka_str = preg_replace('/[^\d.,]/', '', $match[1]);
            $angka_str = str_replace('.', '', $angka_str);
            $angka_str = str_replace(',', '.', $angka_str);
            return round(floatval($angka_str));
        }
    }
    return 0;
}

// Fungsi ekstrak tanggal dari teks OCR yang diperbaiki dan lebih komprehensif
function extractDate($text) {
    // Debug: log teks yang akan diproses
    error_log("OCR Text untuk ekstrak tanggal: " . $text);
    
    // Pattern yang lebih komprehensif untuk mendeteksi tanggal
    $patterns = [
        // Format DD/MM/YYYY atau DD-MM-YYYY atau DD.MM.YYYY
        '/(?:tanggal|date|tgl|waktu|transfer|pembayaran|bayar)\s*:?\s*(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{4})/i',
        '/(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{4})/i',
        
        // Format DD/MM/YY atau DD-MM-YY atau DD.MM.YY  
        '/(?:tanggal|date|tgl|waktu|transfer|pembayaran|bayar)\s*:?\s*(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2})/i',
        '/(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2})/i',
        
        // Format YYYY/MM/DD atau YYYY-MM-DD
        '/(\d{4}[\/\-\.]\d{1,2}[\/\-\.]\d{1,2})/i',
        
        // Format Indonesia: 15 Januari 2024, 15 Jan 2024
        '/(\d{1,2}\s+(?:januari|februari|maret|april|mei|juni|juli|agustus|september|oktober|november|desember|jan|feb|mar|apr|mei|jun|jul|ags|sep|okt|nov|des)\s+\d{2,4})/i',
        
        // Format dengan kata kunci
        '/(?:pada|tgl|tanggal|date|waktu|jam|pukul|transfer|bayar|pembayaran)\s*:?\s*(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})/i',
        
        // Format ISO: YYYY-MM-DD
        '/(\d{4}-\d{2}-\d{2})/i',
        
        // Format dengan spasi: DD MM YYYY
        '/(\d{1,2}\s+\d{1,2}\s+\d{4})/i',
        
        // Format pendek: DDMMYYYY
        '/(\d{8})/i', // akan diproses khusus
        
        // Format dengan teks bank Indonesia
        '/(?:mutasi|saldo|debet|kredit|transfer)\s+(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})/i',
    ];
    
    $found_dates = [];
    
    foreach ($patterns as $pattern) {
        if (preg_match_all($pattern, $text, $matches)) {
            foreach ($matches[1] as $match) {
                $cleaned_date = trim($match);
                
                // Khusus untuk format DDMMYYYY (8 digit)
                if (strlen($cleaned_date) == 8 && is_numeric($cleaned_date)) {
                    $day = substr($cleaned_date, 0, 2);
                    $month = substr($cleaned_date, 2, 2);
                    $year = substr($cleaned_date, 4, 4);
                    $cleaned_date = $day . '/' . $month . '/' . $year;
                }
                
                $normalized_date = normalizeDate($cleaned_date);
                if ($normalized_date && isValidDate($normalized_date)) {
                    $found_dates[] = $normalized_date;
                    error_log("Tanggal ditemukan: " . $cleaned_date . " -> " . $normalized_date);
                }
            }
        }
    }
    
    // Jika tidak ada tanggal ditemukan dengan pattern, coba ekstraksi agresif
    if (empty($found_dates)) {
        error_log("Tidak ada tanggal ditemukan dengan pattern, mencoba ekstraksi agresif...");
        
        // Ekstraksi semua angka yang mungkin tanggal
        preg_match_all('/\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4}/', $text, $all_dates);
        
        foreach ($all_dates[0] as $date) {
            $normalized_date = normalizeDate($date);
            if ($normalized_date && isValidDate($normalized_date)) {
                $found_dates[] = $normalized_date;
                error_log("Tanggal ditemukan (agresif): " . $date . " -> " . $normalized_date);
            }
        }
    }
    
    if (!empty($found_dates)) {
        // Urutkan tanggal dari yang terbaru
        usort($found_dates, function($a, $b) {
            return strtotime($b) - strtotime($a);
        });
        
        error_log("Tanggal terpilih: " . $found_dates[0]);
        return $found_dates[0];
    }
    
    error_log("Tidak ada tanggal yang valid ditemukan");
    return '';
}

// Fungsi untuk normalisasi format tanggal yang diperbaiki
function normalizeDate($date_string) {
    error_log("Normalisasi tanggal: " . $date_string);
    
    // Mapping bulan Indonesia ke Inggris
    $month_map = [
        'januari' => 'january', 'februari' => 'february', 'maret' => 'march',
        'april' => 'april', 'mei' => 'may', 'juni' => 'june',
        'juli' => 'july', 'agustus' => 'august', 'september' => 'september',
        'oktober' => 'october', 'november' => 'november', 'desember' => 'december',
        'jan' => 'jan', 'feb' => 'feb', 'mar' => 'mar', 'apr' => 'apr',
        'mei' => 'may', 'jun' => 'jun', 'jul' => 'jul', 'ags' => 'aug',
        'sep' => 'sep', 'okt' => 'oct', 'nov' => 'nov', 'des' => 'dec'
    ];
    
    $normalized = strtolower($date_string);
    
    // Replace bulan Indonesia dengan Inggris
    foreach ($month_map as $indo => $eng) {
        $normalized = str_replace($indo, $eng, $normalized);
    }
    
    // Coba parsing dengan strtotime dulu
    $timestamp = strtotime($normalized);
    if ($timestamp !== false) {
        error_log("Berhasil parsing dengan strtotime: " . date('Y-m-d', $timestamp));
        return date('Y-m-d', $timestamp);
    }
    
    // Jika gagal, coba parsing manual untuk format DD/MM/YYYY
    if (preg_match('/(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{2,4})/', $date_string, $matches)) {
        $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
        $month = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
        $year = $matches[3];
        
        // Konversi tahun 2 digit ke 4 digit
        if (strlen($year) == 2) {
            $current_year = date('Y');
            $current_century = substr($current_year, 0, 2);
            $year_int = intval($year);
            
            // Jika tahun > 50, anggap abad sebelumnya, jika <= 50, abad ini
            if ($year_int > 50) {
                $year = ($current_century - 1) . $year;
            } else {
                $year = $current_century . $year;
            }
        }
        
        // Validasi tanggal
        if (checkdate($month, $day, $year)) {
            $result = $year . '-' . $month . '-' . $day;
            error_log("Berhasil parsing manual: " . $result);
            return $result;
        } else {
            error_log("Tanggal tidak valid: " . $day . '/' . $month . '/' . $year);
        }
    }
    
    // Coba format YYYY-MM-DD atau YYYY/MM/DD
    if (preg_match('/(\d{4})[\/\-\.](\d{1,2})[\/\-\.](\d{1,2})/', $date_string, $matches)) {
        $year = $matches[1];
        $month = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
        $day = str_pad($matches[3], 2, '0', STR_PAD_LEFT);
        
        if (checkdate($month, $day, $year)) {
            $result = $year . '-' . $month . '-' . $day;
            error_log("Berhasil parsing format YYYY: " . $result);
            return $result;
        }
    }
    
    error_log("Gagal normalisasi tanggal: " . $date_string);
    return false;
}

// Fungsi untuk validasi tanggal yang diperbaiki
function isValidDate($date) {
    $timestamp = strtotime($date);
    if ($timestamp === false) {
        error_log("Timestamp false untuk: " . $date);
        return false;
    }
    
    $current_year = date('Y');
    $date_year = date('Y', $timestamp);
    
    // Validasi range tahun (10 tahun ke belakang, 1 tahun ke depan)
    $is_valid = ($date_year >= $current_year - 10 && $date_year <= $current_year + 1);
    
    if (!$is_valid) {
        error_log("Tanggal di luar range valid: " . $date . " (tahun: " . $date_year . ")");
    }
    
    return $is_valid;
}

// Fungsi ekstrak kode dari teks OCR
function extractCode($text, $expected_code = '') {
    if (!empty($expected_code)) {
        $pattern = '/(' . preg_quote(substr($expected_code, 0, 3), '/') . '[A-Z0-9\-_]+)/i';
        if (preg_match($pattern, $text, $match)) {
            return strtoupper(trim($match[1]));
        }
    }
    
    $patterns = [
        '/([A-Z]{2,3}[\-_]?\d{3,6})/i',
        '/([A-Z]+\d+)/i',
        '/(TAG[\-_]?\d+)/i'
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $text, $match)) {
            return strtoupper(trim($match[1]));
        }
    }
    return '';
}

// Fungsi run_ocr yang diperbaiki dengan error handling yang lebih baik
function run_ocr($file_path, $kode_tagihan = '', $jumlah_tagihan = 0) {
    $full_path = realpath($file_path);
    
    // Validasi file
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
    
    // Validasi ekstensi file
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
    $file_extension = strtolower(pathinfo($full_path, PATHINFO_EXTENSION));
    
    if (!in_array($file_extension, $allowed_extensions)) {
        error_log("Format file tidak didukung: " . $file_extension);
        return array(
            'text' => '', 
            'jumlah' => 0, 
            'tanggal' => '', 
            'kode' => '', 
            'confidence' => 0,
            'error' => 'Format file tidak didukung'
        );
    }
    
    // Validasi ukuran file (maksimal 10MB)
    $file_size = filesize($full_path);
    if ($file_size > 10 * 1024 * 1024) {
        error_log("File terlalu besar: " . $file_size . " bytes");
        return array(
            'text' => '', 
            'jumlah' => 0, 
            'tanggal' => '', 
            'kode' => '', 
            'confidence' => 0,
            'error' => 'File terlalu besar'
        );
    }
    
    // Persiapkan command dengan timeout
    $python_path = 'python3'; // Sesuaikan dengan sistem Anda
    $script_path = __DIR__ . '/ocr.py'; // Sesuaikan dengan lokasi script
    
    $command = sprintf(
        'timeout 60 %s %s %s %s %s 2>&1',
        escapeshellcmd($python_path),
        escapeshellarg($script_path),
        escapeshellarg($full_path),
        escapeshellarg($kode_tagihan),
        escapeshellarg($jumlah_tagihan)
    );
    
    error_log("OCR Command: " . $command);
    
    // Jalankan command
    $output = shell_exec($command);
    $exit_code = shell_exec("echo $?");
    
    error_log("OCR Raw Output: " . $output);
    error_log("OCR Exit Code: " . $exit_code);
    
    // Inisialisasi hasil
    $result = array(
        'text' => '',
        'jumlah' => 0,
        'tanggal' => '',
        'kode' => '',
        'confidence' => 0,
        'error' => ''
    );
    
    if (empty($output) || trim($exit_code) != '0') {
        $result['error'] = 'OCR gagal dijalankan';
        return $result;
    }
    
    // Parse output
    $lines = explode("\n", trim($output));
    $json_data = null;
    
    foreach ($lines as $line) {
        $line = trim($line);
        
        if (strpos($line, 'AMOUNT:') === 0) {
            $amount_str = str_replace('AMOUNT:', '', $line);
            $result['jumlah'] = is_numeric($amount_str) ? intval($amount_str) : 0;
            
        } elseif (strpos($line, 'DATE:') === 0) {
            $result['tanggal'] = trim(str_replace('DATE:', '', $line));
            
        } elseif (strpos($line, 'CODE:') === 0) {
            $result['kode'] = trim(str_replace('CODE:', '', $line));
            
        } elseif (strpos($line, 'CONFIDENCE:') === 0) {
            $confidence_str = str_replace('CONFIDENCE:', '', $line);
            $result['confidence'] = is_numeric($confidence_str) ? floatval($confidence_str) : 0;
            
        } elseif (strpos($line, 'TEXT:') === 0) {
            $result['text'] = trim(str_replace('TEXT:', '', $line));
            
        } elseif (strpos($line, 'JSON:') === 0) {
            $json_str = str_replace('JSON:', '', $line);
            $json_data = json_decode($json_str, true);
            
        } elseif (strpos($line, 'ERROR:') === 0) {
            $result['error'] = trim(str_replace('ERROR:', '', $line));
        }
    }
    
    // Jika ada data JSON, gunakan sebagai fallback
    if ($json_data && is_array($json_data)) {
        if (empty($result['text']) && !empty($json_data['extracted_text'])) {
            $result['text'] = $json_data['extracted_text'];
        }
        if ($result['jumlah'] == 0 && !empty($json_data['jumlah'])) {
            $result['jumlah'] = intval($json_data['jumlah']);
        }
        if (empty($result['tanggal']) && !empty($json_data['tanggal'])) {
            $result['tanggal'] = $json_data['tanggal'];
        }
        if (empty($result['kode']) && !empty($json_data['kode_tagihan'])) {
            $result['kode'] = $json_data['kode_tagihan'];
        }
        if ($result['confidence'] == 0 && !empty($json_data['confidence'])) {
            $result['confidence'] = floatval($json_data['confidence']);
        }
    }
    
    // Fallback parsing jika masih ada yang kosong
    if (!empty($result['text'])) {
        if ($result['jumlah'] == 0) {
            $result['jumlah'] = extractAmount($result['text']);
        }
        if (empty($result['tanggal'])) {
            $result['tanggal'] = extractDate($result['text']);
        }
        if (empty($result['kode'])) {
            $result['kode'] = extractCode($result['text'], $kode_tagihan);
        }
    }
    
    // Validasi hasil
    if ($result['jumlah'] > 0 && $jumlah_tagihan > 0) {
        $difference = abs($result['jumlah'] - $jumlah_tagihan);
        $percentage = ($difference / $jumlah_tagihan) * 100;
        
        // Jika selisih lebih dari 5%, mungkin ada kesalahan
        if ($percentage > 5) {
            error_log("Warning: Selisih jumlah signifikan - OCR: {$result['jumlah']}, Expected: {$jumlah_tagihan}");
        }
    }
    
    error_log("OCR Final Result: " . json_encode($result));
    return $result;
}

// Fungsi extractAmount yang diperbaiki
function extractAmount($text) {
    if (empty($text)) return 0;
    
    // Normalisasi teks
    $text = preg_replace('/\s+/', ' ', $text);
    
    $patterns = [
        // Pattern untuk Rupiah dengan berbagai format
        '/Rp\.?\s*(\d{1,3}(?:[.,]\d{3})*(?:[.,]\d{2})?)/i',
        '/(?:jumlah|nominal|total|bayar|transfer)\s*:?\s*Rp\.?\s*(\d{1,3}(?:[.,]\d{3})*(?:[.,]\d{2})?)/i',
        '/(?:jumlah|nominal|total|bayar|transfer)\s*:?\s*(\d{1,3}(?:[.,]\d{3})*(?:[.,]\d{2})?)/i',
        
        // Pattern untuk angka besar tanpa prefix
        '/(\d{1,3}(?:[.,]\d{3}){2,})/',  // Minimal 3 grup digit
        '/(\d{6,})/',  // Minimal 6 digit berturut-turut
        
        // Pattern dengan separator spasi
        '/(\d{1,3}(?:\s\d{3})*)/i',
        
        // Pattern untuk format bank (misal: 100,000.00)
        '/(\d+[.,]\d{3}(?:[.,]\d{3})*(?:[.,]\d{2})?)/i'
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $text, $matches)) {
            // Bersihkan string angka
            $angka_str = $matches[1];
            $angka_str = str_replace(' ', '', $angka_str);
            
            // Deteksi format (titik vs koma sebagai separator)
            if (preg_match('/\d+\.\d{2}$/', $angka_str)) {
                // Format xxx.xx (titik sebagai desimal)
                $angka_str = str_replace(',', '', $angka_str);
            } else {
                // Format xxx,xxx (koma sebagai separator ribuan)
                $angka_str = str_replace(',', '', $angka_str);
                $angka_str = str_replace('.', '', $angka_str);
            }
            
            $amount = intval($angka_str);
            if ($amount >= 1000) {  // Minimal 1000 rupiah
                return $amount;
            }
        }
    }
    
    return 0;
}

// Fungsi extractDate yang diperbaiki
function extractDate($text) {
    if (empty($text)) return '';
    
    error_log("Extracting date from: " . $text);
    
    $patterns = [
        // Format DD/MM/YYYY atau DD-MM-YYYY
        '/(\d{1,2}[-\/]\d{1,2}[-\/]\d{4})/i',
        '/(\d{1,2}[-\/]\d{1,2}[-\/]\d{2})/i',
        
        // Format Indonesia
        '/(\d{1,2}\s+(?:januari|februari|maret|april|mei|juni|juli|agustus|september|oktober|november|desember)\s+\d{4})/i',
        '/(\d{1,2}\s+(?:jan|feb|mar|apr|mei|jun|jul|ags|sep|okt|nov|des)\s+\d{4})/i',
        
        // Format dengan kata kunci
        '/(?:tanggal|date|tgl|waktu|transfer|bayar|pembayaran)\s*:?\s*(\d{1,2}[-\/]\d{1,2}[-\/]\d{2,4})/i',
        '/(?:tanggal|date|tgl|waktu|transfer|bayar|pembayaran)\s*:?\s*(\d{1,2}\s+\w+\s+\d{4})/i',
        
        // Format ISO
        '/(\d{4}[-\/]\d{1,2}[-\/]\d{1,2})/i',
        
        // Format dengan spasi
        '/(\d{1,2}\s+\d{1,2}\s+\d{4})/i',
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $text, $matches)) {
            $date_str = trim($matches[1]);
            $normalized_date = normalizeDate($date_str);
            
            if ($normalized_date && isValidDate($normalized_date)) {
                error_log("Date extracted: " . $normalized_date);
                return $normalized_date;
            }
        }
    }
    
    error_log("No valid date found in text");
    return '';
}

// Fungsi extractCode yang diperbaiki
function extractCode($text, $expected_code = '') {
    if (empty($text)) return '';
    
    $patterns = [
        // Pattern umum untuk kode tagihan
        '/([A-Z]{2,4}[-\s]?\d{6,10})/i',
        '/([A-Z]+\d{6,})/i',
        '/(TAG[-\s]?\d{6,10})/i',
        
        // Pattern dengan kata kunci
        '/(?:kode|code|ref|referensi|tag|tagihan)\s*:?\s*([A-Z0-9\-_]{6,})/i',
        
        // Pattern berdasarkan expected_code
        '/([A-Z]{2,4}\d{6,})/i',
        '/(\d{6,}[A-Z]{2,})/i',
    ];
    
    // Jika ada expected_code, tambahkan pattern khusus
    if (!empty($expected_code)) {
        $prefix = substr($expected_code, 0, 3);
        $patterns[] = '/(' . preg_quote($prefix, '/') . '[-\s]?\d+)/i';
    }
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $text, $matches)) {
            $code = strtoupper(str_replace([' ', '-'], '', $matches[1]));
            if (strlen($code) >= 6) {
                return $code;
            }
        }
    }
    
    return '';
}

// Fungsi untuk debugging hasil OCR
function debugOCRResult($bill) {
    if (!isset($_GET['debug']) || $_GET['debug'] != '1') {
        return;
    }
    
    echo "<div class='debug-panel' style='background: #f8f9fa; padding: 15px; margin: 10px 0; border-radius: 5px; font-family: monospace; font-size: 12px;'>";
    echo "<h4>Debug OCR Result</h4>";
    echo "<strong>User Bill ID:</strong> " . $bill['user_bill_id'] . "<br>";
    echo "<strong>File:</strong> " . $bill['bukti_pembayaran'] . "<br>";
    echo "<strong>OCR Jumlah:</strong> " . $bill['ocr_jumlah'] . "<br>";
    echo "<strong>Expected Jumlah:</strong> " . $bill['jumlah'] . "<br>";
    echo "<strong>OCR Confidence:</strong> " . $bill['ocr_confidence'] . "%<br>";
    echo "<strong>OCR Kode Found:</strong> " . ($bill['ocr_kode_found'] ? 'Yes' : 'No') . "<br>";
    echo "<strong>OCR Date Found:</strong> " . ($bill['ocr_date_found'] ? 'Yes' : 'No') . "<br>";
    
    if ($bill['ocr_details']) {
        $details = json_decode($bill['ocr_details'], true);
        if ($details) {
            echo "<strong>OCR Details:</strong><br>";
            echo "<pre>" . json_encode($details, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
        }
    }
    echo "</div>";
}

/ Lanjutan dari fungsi testOCR
function testOCR($file_path, $expected_code = '', $expected_amount = 0) {
    echo "<div class='test-ocr-panel' style='background: #e3f2fd; padding: 15px; margin: 10px 0; border-radius: 5px;'>";
    echo "<h4>Test OCR Result</h4>";
    echo "<strong>File:</strong> " . basename($file_path) . "<br>";
    
    $result = run_ocr($file_path, $expected_code, $expected_amount);
    
    echo "<strong>Text Found:</strong> " . (empty($result['text']) ? 'None' : substr($result['text'], 0, 100) . '...') . "<br>";
    echo "<strong>Amount:</strong> " . ($result['jumlah'] ?: 'Not found') . "<br>";
    echo "<strong>Date:</strong> " . ($result['tanggal'] ?: 'Not found') . "<br>";
    echo "<strong>Code:</strong> " . ($result['kode'] ?: 'Not found') . "<br>";
    echo "<strong>Confidence:</strong> " . $result['confidence'] . "%<br>";
    
    if (!empty($result['error'])) {
        echo "<strong style='color: red;'>Error:</strong> " . $result['error'] . "<br>";
    }
    
    // Validasi hasil
    echo "<h5>Validation:</h5>";
    $validation_score = 0;
    
    if ($result['jumlah'] > 0) {
        echo "✓ Amount detected<br>";
        $validation_score += 25;
        
        if ($expected_amount > 0) {
            $difference = abs($result['jumlah'] - $expected_amount);
            $percentage = ($difference / $expected_amount) * 100;
            
            if ($percentage <= 5) {
                echo "✓ Amount matches expected (within 5%)<br>";
                $validation_score += 25;
            } else {
                echo "⚠ Amount differs from expected: " . $percentage . "% difference<br>";
            }
        }
    } else {
        echo "✗ No amount detected<br>";
    }
    
    if (!empty($result['tanggal'])) {
        echo "✓ Date detected<br>";
        $validation_score += 25;
    } else {
        echo "✗ No date detected<br>";
    }
    
    if (!empty($result['kode'])) {
        echo "✓ Code detected<br>";
        $validation_score += 25;
        
        if (!empty($expected_code) && stripos($result['kode'], $expected_code) !== false) {
            echo "✓ Code matches expected<br>";
        } elseif (!empty($expected_code)) {
            echo "⚠ Code differs from expected<br>";
        }
    } else {
        echo "✗ No code detected<br>";
    }
    
    echo "<strong>Overall Score:</strong> " . $validation_score . "/100<br>";
    echo "</div>";
    
    return $result;
}

// Fungsi untuk normalisasi tanggal
function normalizeDate($date_str) {
    if (empty($date_str)) return '';
    
    // Bulan Indonesia ke angka
    $months = [
        'januari' => '01', 'jan' => '01',
        'februari' => '02', 'feb' => '02',
        'maret' => '03', 'mar' => '03',
        'april' => '04', 'apr' => '04',
        'mei' => '05',
        'juni' => '06', 'jun' => '06',
        'juli' => '07', 'jul' => '07',
        'agustus' => '08', 'ags' => '08',
        'september' => '09', 'sep' => '09',
        'oktober' => '10', 'okt' => '10',
        'november' => '11', 'nov' => '11',
        'desember' => '12', 'des' => '12'
    ];
    
    $date_str = trim(strtolower($date_str));
    
    // Ganti nama bulan dengan angka
    foreach ($months as $month_name => $month_num) {
        $date_str = str_replace($month_name, $month_num, $date_str);
    }
    
    // Normalisasi separator
    $date_str = preg_replace('/[-\s]+/', '-', $date_str);
    
    // Parse berbagai format
    $patterns = [
        '/^(\d{1,2})-(\d{1,2})-(\d{4})$/',     // DD-MM-YYYY
        '/^(\d{1,2})-(\d{1,2})-(\d{2})$/',     // DD-MM-YY
        '/^(\d{4})-(\d{1,2})-(\d{1,2})$/',     // YYYY-MM-DD
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $date_str, $matches)) {
            if (count($matches) == 4) {
                $part1 = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
                $part2 = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
                $part3 = $matches[3];
                
                // Jika tahun 2 digit, ubah ke 4 digit
                if (strlen($part3) == 2) {
                    $part3 = '20' . $part3;
                }
                
                // Tentukan format berdasarkan pattern
                if ($pattern === '/^(\d{4})-(\d{1,2})-(\d{1,2})$/') {
                    // YYYY-MM-DD
                    return $part1 . '-' . $part2 . '-' . $part3;
                } else {
                    // DD-MM-YYYY
                    return $part1 . '-' . $part2 . '-' . $part3;
                }
            }
        }
    }
    
    return '';
}

// Fungsi untuk validasi tanggal
function isValidDate($date_str) {
    if (empty($date_str)) return false;
    
    $parts = explode('-', $date_str);
    if (count($parts) != 3) return false;
    
    $day = intval($parts[0]);
    $month = intval($parts[1]);
    $year = intval($parts[2]);
    
    // Validasi range
    if ($year < 1900 || $year > 2100) return false;
    if ($month < 1 || $month > 12) return false;
    if ($day < 1 || $day > 31) return false;
    
    // Validasi dengan checkdate
    return checkdate($month, $day, $year);
}

// Fungsi untuk preprocessing gambar sebelum OCR
function preprocessImage($file_path) {
    $processed_path = $file_path . '_processed.jpg';
    
    // Cek apakah ImageMagick tersedia
    if (!extension_loaded('imagick')) {
        error_log("ImageMagick not available, using original image");
        return $file_path;
    }
    
    try {
        $image = new Imagick($file_path);
        
        // Resize jika terlalu besar
        if ($image->getImageWidth() > 2000 || $image->getImageHeight() > 2000) {
            $image->resizeImage(2000, 2000, Imagick::FILTER_LANCZOS, 1, true);
        }
        
        // Enhance contrast dan brightness
        $image->normalizeImage();
        $image->enhanceImage();
        
        // Convert to grayscale untuk OCR yang lebih baik
        $image->setImageColorspace(Imagick::COLORSPACE_GRAY);
        
        // Sharpen sedikit
        $image->sharpenImage(0, 1);
        
        // Simpan sebagai JPEG dengan kualitas tinggi
        $image->setImageFormat('jpeg');
        $image->setImageCompressionQuality(95);
        $image->writeImage($processed_path);
        
        $image->clear();
        $image->destroy();
        
        error_log("Image preprocessed: " . $processed_path);
        return $processed_path;
        
    } catch (Exception $e) {
        error_log("Image preprocessing failed: " . $e->getMessage());
        return $file_path;
    }
}

// Fungsi untuk cleanup file temporary
function cleanupTempFiles($file_path) {
    $temp_files = [
        $file_path . '_processed.jpg',
        $file_path . '_ocr_temp.txt',
        $file_path . '_debug.json'
    ];
    
    foreach ($temp_files as $temp_file) {
        if (file_exists($temp_file)) {
            unlink($temp_file);
        }
    }
}

// Fungsi untuk batch OCR processing
function batchOCR($file_list, $progress_callback = null) {
    $results = [];
    $total = count($file_list);
    
    foreach ($file_list as $index => $file_info) {
        $file_path = $file_info['path'];
        $expected_code = $file_info['code'] ?? '';
        $expected_amount = $file_info['amount'] ?? 0;
        
        if ($progress_callback) {
            call_user_func($progress_callback, $index + 1, $total, $file_path);
        }
        
        $result = run_ocr($file_path, $expected_code, $expected_amount);
        $result['file_path'] = $file_path;
        $result['index'] = $index;
        
        $results[] = $result;
        
        // Cleanup temp files
        cleanupTempFiles($file_path);
        
        // Small delay to prevent system overload
        usleep(100000); // 0.1 second
    }
    
    return $results;
}

// Fungsi untuk generate laporan OCR
function generateOCRReport($results, $output_path = null) {
    $report = [
        'summary' => [
            'total_files' => count($results),
            'successful_ocr' => 0,
            'failed_ocr' => 0,
            'amount_detected' => 0,
            'date_detected' => 0,
            'code_detected' => 0,
            'average_confidence' => 0,
            'total_amount' => 0
        ],
        'details' => []
    ];
    
    $total_confidence = 0;
    $confidence_count = 0;
    
    foreach ($results as $result) {
        $detail = [
            'file' => basename($result['file_path']),
            'success' => empty($result['error']),
            'amount' => $result['jumlah'],
            'date' => $result['tanggal'],
            'code' => $result['kode'],
            'confidence' => $result['confidence'],
            'error' => $result['error']
        ];
        
        $report['details'][] = $detail;
        
        // Update summary
        if ($detail['success']) {
            $report['summary']['successful_ocr']++;
            
            if ($result['jumlah'] > 0) {
                $report['summary']['amount_detected']++;
                $report['summary']['total_amount'] += $result['jumlah'];
            }
            
            if (!empty($result['tanggal'])) {
                $report['summary']['date_detected']++;
            }
            
            if (!empty($result['kode'])) {
                $report['summary']['code_detected']++;
            }
            
            if ($result['confidence'] > 0) {
                $total_confidence += $result['confidence'];
                $confidence_count++;
            }
        } else {
            $report['summary']['failed_ocr']++;
        }
    }
    
    // Calculate average confidence
    if ($confidence_count > 0) {
        $report['summary']['average_confidence'] = round($total_confidence / $confidence_count, 2);
    }
    
    // Calculate percentages
    $total = $report['summary']['total_files'];
    if ($total > 0) {
        $report['summary']['success_rate'] = round(($report['summary']['successful_ocr'] / $total) * 100, 2);
        $report['summary']['amount_detection_rate'] = round(($report['summary']['amount_detected'] / $total) * 100, 2);
        $report['summary']['date_detection_rate'] = round(($report['summary']['date_detected'] / $total) * 100, 2);
        $report['summary']['code_detection_rate'] = round(($report['summary']['code_detected'] / $total) * 100, 2);
    }
    
    // Save report if output path specified
    if ($output_path) {
        file_put_contents($output_path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    
    return $report;
}

// Fungsi untuk optimasi OCR berdasarkan jenis dokumen
function optimizeOCRForDocument($file_path, $document_type = 'receipt') {
    $optimization_params = [
        'receipt' => [
            'dpi' => 300,
            'psm' => 6,  // Uniform block of text
            'oem' => 1,  // Neural nets LSTM only
            'config' => '-c tessedit_char_whitelist=0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz.,-/:() '
        ],
        'invoice' => [
            'dpi' => 300,
            'psm' => 6,
            'oem' => 1,
            'config' => ''
        ],
        'bank_statement' => [
            'dpi' => 300,
            'psm' => 4,  // Single column of text
            'oem' => 1,
            'config' => ''
        ],
        'form' => [
            'dpi' => 300,
            'psm' => 6,
            'oem' => 1,
            'config' => ''
        ]
    ];
    
    return $optimization_params[$document_type] ?? $optimization_params['receipt'];
}

// Fungsi untuk validasi format kode tagihan
function validateBillCode($code, $expected_pattern = '') {
    if (empty($code)) return false;
    
    // Pattern umum untuk kode tagihan Indonesia
    $common_patterns = [
        '/^[A-Z]{2,4}\d{6,12}$/',           // 2-4 huruf + 6-12 angka
        '/^\d{6,12}[A-Z]{2,4}$/',           // 6-12 angka + 2-4 huruf
        '/^[A-Z]{2,4}[-]\d{6,12}$/',        // 2-4 huruf + strip + 6-12 angka
        '/^\d{6,12}[-][A-Z]{2,4}$/',        // 6-12 angka + strip + 2-4 huruf
        '/^[A-Z]\d{6,12}[A-Z]$/',           // 1 huruf + 6-12 angka + 1 huruf
    ];
    
    // Jika ada expected pattern, gunakan itu
    if (!empty($expected_pattern)) {
        return preg_match($expected_pattern, $code);
    }
    
    // Cek dengan pattern umum
    foreach ($common_patterns as $pattern) {
        if (preg_match($pattern, $code)) {
            return true;
        }
    }
    
    return false;
}

// Fungsi untuk post-processing hasil OCR
function postProcessOCRResult($result, $context = []) {
    // Koreksi common OCR errors
    $corrections = [
        'jumlah' => [
            'O' => '0',  // Huruf O ke angka 0
            'I' => '1',  // Huruf I ke angka 1
            'S' => '5',  // Huruf S ke angka 5
            'B' => '8',  // Huruf B ke angka 8
        ],
        'kode' => [
            '0' => 'O',  // Angka 0 ke huruf O dalam kode
            '1' => 'I',  // Angka 1 ke huruf I dalam kode
            '5' => 'S',  // Angka 5 ke huruf S dalam kode
            '8' => 'B',  // Angka 8 ke huruf B dalam kode
        ]
    ];
    
    // Apply corrections
    if (!empty($result['kode'])) {
        // Tentukan apakah kode harus diawali huruf atau angka
        $first_char = substr($result['kode'], 0, 1);
        if (is_numeric($first_char)) {
            // Kode diawali angka, koreksi huruf yang salah
            foreach ($corrections['jumlah'] as $wrong => $correct) {
                $result['kode'] = str_replace($wrong, $correct, $result['kode']);
            }
        } else {
            // Kode diawali huruf, koreksi angka yang salah
            foreach ($corrections['kode'] as $wrong => $correct) {
                $result['kode'] = str_replace($wrong, $correct, $result['kode']);
            }
        }
    }
    
    // Validasi dan koreksi jumlah
    if ($result['jumlah'] > 0) {
        // Cek apakah jumlah masuk akal (tidak terlalu kecil atau besar)
        if ($result['jumlah'] < 100) {
            // Mungkin ada kesalahan parsing, coba ekstrak ulang
            $result['jumlah'] = extractAmount($result['text']);
        }
        
        // Bulatkan ke kelipatan 100 jika memungkinkan
        if ($result['jumlah'] % 100 < 50 && $result['jumlah'] % 100 > 0) {
            $rounded = floor($result['jumlah'] / 100) * 100;
            if ($rounded > 0) {
                $result['jumlah'] = $rounded;
            }
        }
    }
    
    return $result;
}

// Export fungsi untuk penggunaan external
if (!function_exists('ocr_extract_data')) {
    function ocr_extract_data($file_path, $options = []) {
        $kode_tagihan = $options['expected_code'] ?? '';
        $jumlah_tagihan = $options['expected_amount'] ?? 0;
        $document_type = $options['document_type'] ?? 'receipt';
        
        // Preprocess image jika diperlukan
        if (!empty($options['preprocess'])) {
            $file_path = preprocessImage($file_path);
        }
        
        // Run OCR
        $result = run_ocr($file_path, $kode_tagihan, $jumlah_tagihan);
        
        // Post-process hasil
        $result = postProcessOCRResult($result, $options);
        
        // Cleanup temp files
        cleanupTempFiles($file_path);
        
        return $result;
    }
}


// Function untuk generate QR Code URL
function generateQRCodeURL($data) {
    return "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($data);
}

// Proses OCR untuk semua bukti pembayaran
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'run_ocr') {
    try {
        $stmt = $pdo->prepare("
            SELECT ub.id, ub.bukti_pembayaran, b.kode_tagihan, b.jumlah
            FROM user_bills ub 
            JOIN bills b ON ub.bill_id = b.id
            WHERE ub.status = 'menunggu_konfirmasi' AND ub.bukti_pembayaran IS NOT NULL
        ");
        $stmt->execute();
        $bills = $stmt->fetchAll();
        
        $processed = 0;
        
        foreach ($bills as $bill) {
            $file_path = '../warga/uploads/bukti_pembayaran/' . $bill['bukti_pembayaran'];
            if (file_exists($file_path)) {
                error_log("Memproses OCR untuk file: " . $file_path);
                
                $ocr_result = run_ocr($file_path, $bill['kode_tagihan'], $bill['jumlah']);
                
                // Log hasil OCR
                error_log("Hasil OCR untuk " . $bill['bukti_pembayaran'] . ": " . json_encode($ocr_result));
                
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
                    'file_path' => $file_path
                ]);
                
                $date_found = !empty($ocr_result['tanggal']) ? 1 : 0;
                error_log("Date found status: " . $date_found . " untuk tanggal: " . $ocr_result['tanggal']);
                
                $update_stmt->execute([
                    $ocr_result['jumlah'],
                    !empty($ocr_result['kode']) ? 1 : 0,
                    $date_found,
                    $ocr_result['confidence'],
                    $ocr_details,
                    $bill['id']
                ]);
                
                $processed++;
            } else {
                error_log("File tidak ditemukan: " . $file_path);
            }
        }
        
        $success = "OCR berhasil dijalankan untuk $processed bukti pembayaran!";
    } catch(PDOException $e) {
        error_log("Error OCR: " . $e->getMessage());
        $error = "Error OCR: " . $e->getMessage();
    }
}

// Proses update manual status dari admin
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['user_bill_id'], $_POST['status']) && (!isset($_POST['action']) || $_POST['action'] != 'run_ocr')) {
    $user_bill_id = intval($_POST['user_bill_id']);
    $status = $_POST['status'];

    if (!in_array($status, ['konfirmasi', 'tolak'])) {
        $error = "Status tidak valid!";
    } else {
        try {
            $stmt_info = $pdo->prepare("
                SELECT ub.user_id, ub.bill_id, b.kode_tagihan, b.jumlah, b.deskripsi, u.username 
                FROM user_bills ub
                JOIN bills b ON ub.bill_id = b.id 
                JOIN users u ON ub.user_id = u.id
                WHERE ub.id = ?
            ");
            $stmt_info->execute([$user_bill_id]);
            $bill_info = $stmt_info->fetch();

            if ($bill_info && $status == 'konfirmasi') {
                $qr_data = generateQRCodeData($bill_info);
                $qr_hash = hash('sha256', $qr_data);
                
                $stmt = $pdo->prepare("
                    UPDATE user_bills SET 
                        status = ?, 
                        qr_code_data = ?, 
                        qr_code_hash = ?
                    WHERE id = ?
                ");
                $result = $stmt->execute([$status, $qr_data, $qr_hash, $user_bill_id]);
                
                $stmt_tagihan_oke = $pdo->prepare("
                    INSERT INTO tagihan_oke (user_bill_id, kode_tagihan, jumlah, tanggal, user_id, qr_code_hash, bukti_pembayaran) 
                    VALUES (?, ?, ?, CURDATE(), ?, ?, (SELECT bukti_pembayaran FROM user_bills WHERE id = ?))
                    ON DUPLICATE KEY UPDATE qr_code_hash = VALUES(qr_code_hash)
                ");
                $stmt_tagihan_oke->execute([
                    $user_bill_id, 
                    $bill_info['kode_tagihan'], 
                    $bill_info['jumlah'], 
                    $bill_info['user_id'], 
                    $qr_hash,
                    $user_bill_id
                ]);
                
            } else {
                $stmt = $pdo->prepare("UPDATE user_bills SET status = ? WHERE id = ?");
                $result = $stmt->execute([$status, $user_bill_id]);
            }

            $status_text = ($status == 'konfirmasi') ? 'dikonfirmasi' : 'ditolak';
            $success = "Pembayaran berhasil $status_text!" . ($status == 'konfirmasi' ? ' QR Code telah dibuat.' : '');
        } catch(PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Ambil tagihan menunggu konfirmasi dengan info OCR yang lebih lengkap
try {
    $stmt = $pdo->prepare("
        SELECT 
            ub.id as user_bill_id, 
            u.username, 
            b.kode_tagihan,
            b.deskripsi, 
            b.jumlah, 
            ub.bukti_pembayaran, 
            ub.ocr_jumlah, 
            ub.ocr_kode_found,
            ub.ocr_date_found,
            ub.ocr_confidence,
            ub.ocr_details,
            ub.tanggal as tanggal_kirim,
            b.tanggal as tenggat,
            ub.qr_code_data
        FROM user_bills ub
        JOIN bills b ON ub.bill_id = b.id 
        JOIN users u ON ub.user_id = u.id 
        WHERE ub.status = 'menunggu_konfirmasi'
        ORDER BY ub.tanggal DESC
    ");
    $stmt->execute();
    $bills_result = $stmt->fetchAll();
} catch(PDOException $e) {
    $bills_result = [];
    $error = "Error mengambil data: " . $e->getMessage();
}

// Hitung statistik yang lebih detail
$stats = $pdo->query("
    SELECT 
        COUNT(*) as total_menunggu,
        SUM(CASE WHEN ocr_jumlah > 0 THEN 1 ELSE 0 END) as sudah_ocr,
        SUM(CASE WHEN ocr_kode_found = 1 THEN 1 ELSE 0 END) as kode_ditemukan,
        SUM(CASE WHEN ocr_date_found = 1 THEN 1 ELSE 0 END) as tanggal_ditemukan,
        SUM(b.jumlah) as total_nominal,
        AVG(CASE WHEN ocr_confidence > 0 THEN ocr_confidence ELSE NULL END) as avg_confidence
    FROM user_bills ub
    JOIN bills b ON ub.bill_id = b.id
    WHERE ub.status = 'menunggu_konfirmasi'
")->fetch();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfirmasi Pembayaran - Admin</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8fafc;
            color: #334155;
            line-height: 1.6;
            min-height: 100vh;
        }

        /* Full Screen Layout */
        .layout-container {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 280px;
            background: #312e81;
            flex-shrink: 0;
        }

        .main-content {
            flex: 1;
            padding: 2rem;
            overflow-x: auto;
            background: #f8fafc;
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
        }

        /* Navigation Tabs */
        .nav-tabs {
            background: white;
            border-bottom: 2px solid #e2e8f0;
            padding: 0 2rem;
            display: flex;
            gap: 0;
            margin-bottom: 2rem;
        }

        .nav-tab {
            padding: 1rem 1.5rem;
            text-decoration: none;
            color: #64748b;
            font-weight: 500;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .nav-tab:hover {
            color: #4f46e5;
            background: #f1f5f9;
        }

        .nav-tab.active {
            color: #4f46e5;
            border-bottom-color: #4f46e5;
            background: #f1f5f9;
        }

        /* Alert Messages */
        .alert {
            padding: 1rem 1.25rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 500;
        }

        .alert-success {
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .alert-error {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        /* Card Components */
        .card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .card-header {
            padding: 1.5rem 2rem;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }

        .card-header h2 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #4f46e5;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .card-body {
            padding: 2rem;
        }

        /* Statistics Grid */
        .stats-grid {
            display: grid;         
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 16px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
            text-align: center;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .stat-card.pending {
            border-left: 4px solid #4f46e5;
        }

        .stat-card.ocr {
            border-left: 4px solid #10b981;
        }

        .stat-card.nominal {
            border-left: 4px solid #f59e0b;
        }

        .stat-card.accuracy {
            border-left: 4px solid #8b5cf6;
        }

        .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: #1e293b;
        }

        .stat-label {
            color: #64748b;
            font-size: 0.875rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        /* OCR Section */
        .ocr-section {
            text-align: center;
            margin-bottom: 2rem;
        }

        .ocr-description {
            margin-top: 0.75rem;
            color: #64748b;
            font-size: 0.875rem;
        }

        /* Button Styles */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.875rem 1.5rem;
            background: linear-gradient(135deg, #4f46e5 0%, #581c87 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 25px rgba(79, 70, 229, 0.25);
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }

        .btn-success:hover {
            box-shadow: 0 10px 25px rgba(16, 185, 129, 0.25);
        }

        .btn-small {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }

        /* Table Styles */
        .table-container {
            overflow: auto;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            max-height: 800px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1400px;
        }

        th {
            background: linear-gradient(135deg, #312e81 0%, #581c87 100%);
            color: white;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        td {
            padding: 1rem;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        tr:hover {
            background: #f8fafc;
        }

        /* OCR Results */
        .ocr-results {
            font-size: 0.875rem;
        }

        .ocr-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.25rem;
        }

        .ocr-found {
            color: #10b981;
        }

        .ocr-not-found {
            color: #ef4444;
        }

        .confidence-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .confidence-high {
            background: #dcfce7;
            color: #166534;
        }

        .confidence-medium {
            background: #fef3c7;
            color: #92400e;
        }

        .confidence-low {
            background: #fee2e2;
            color: #dc2626;
        }

        /* Status Badge */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.375rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            white-space: nowrap;
        }

        .status-sesuai {
            background: #dcfce7;
            color: #166534;
        }

        .status-tidak-sesuai {
            background: #fee2e2;
            color: #dc2626;
        }

        .status-belum {
            background: #fef3c7;
            color: #92400e;
        }

        /* Image Preview */
        .image-preview {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
            cursor: pointer;
            transition: transform 0.3s ease;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
        }

        .image-preview:hover {
            transform: scale(1.1);
        }

        /* Amount Display */
        .amount-display {
            font-weight: 700;
            color: #10b981;
            font-size: 1.1em;
        }

        .ocr-amount {
            font-weight: 700;
            color: #4f46e5;
        }

        .difference {
            font-size: 0.75rem;
            color: #64748b;
            font-style: italic;
            margin-top: 0.25rem;
        }

        /* Date Display */
        .date-cell {
            white-space: nowrap;
            font-size: 0.875rem;
        }

        .date-tenggat {
            color: #dc2626;
            font-weight: 600;
        }

        .date-kirim {
            color: #64748b;
        }

        /* Action Form */
        .action-form {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .action-select {
            padding: 0.5rem 0.75rem;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            font-size: 0.875rem;
            min-width: 120px;
            background: white;
        }

        .action-select:focus {
            border-color: #4f46e5;
            outline: none;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        /* Username and Description */
        .username-cell {
            font-weight: 600;
            color: #1e293b;
        }

        .description-cell {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #64748b;
        }

        .empty-state .icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 1.25rem;
            margin-bottom: 0.5rem;
            color: #374151;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .layout-container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                position: static;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }
            
            .page-header {
                padding: 1.5rem;
            }
            
            .page-header h1 {
                font-size: 1.5rem;
            }
            
            .card-body {
                padding: 1.5rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .nav-tabs {
                flex-direction: column;
                padding: 0;
            }
            
            .nav-tab {
                border-bottom: 1px solid #e2e8f0;
                border-left: 3px solid transparent;
            }
            
            .nav-tab.active {
                border-left-color: #4f46e5;
                border-bottom-color: transparent;
            }
            
            table {
                font-size: 0.875rem;
                min-width: 1200px;
            }
            
            th, td {
                padding: 0.75rem 0.5rem;
            }
        }

        /* QR Code Modal */
        .qr-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .qr-modal.show {
            display: flex;
        }

        .qr-modal-content {
            background: white;
            padding: 2rem;
            border-radius: 16px;
            text-align: center;
            max-width: 400px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .qr-modal-close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #64748b;
        }

        .qr-code-container {
            margin: 1rem 0;
        }

        .qr-code-info {
            background: #f8fafc;
            padding: 1rem;
            border-radius: 8px;
            margin-top: 1rem;
            text-align: left;
            font-size: 0.875rem;
            color: #64748b;
        }

        /* Loading States */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #4f46e5;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 0.5rem;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Tooltips */
        .tooltip {
            position: relative;
            cursor: help;
        }

        .tooltip:hover::after {
            content: attr(data-tooltip);
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: #1e293b;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.75rem;
            white-space: nowrap;
            z-index: 1000;
            margin-bottom: 0.5rem;
        }

        .tooltip:hover::before {
            content: '';
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            border: 5px solid transparent;
            border-top-color: #1e293b;
            z-index: 1000;
        }
        .ocr-results-container {
    display: flex;
    flex-direction: column;
    gap: 12px;
    padding: 8px;
}

.ocr-card {
    background: #fff;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 12px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    transition: all 0.2s ease;
}

.ocr-card:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.ocr-header {
    display: flex;
    align-items: flex-start;
    gap: 12px;
}

.status-indicator {
    flex-shrink: 0;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    margin-top: 2px;
}

.status-indicator.success {
    background-color: #d4edda;
    color: #155724;
    border: 2px solid #c3e6cb;
}

.status-indicator.error {
    background-color: #f8d7da;
    color: #721c24;
    border: 2px solid #f5c6cb;
}

.status-indicator.warning {
    background-color: #fff3cd;
    color: #856404;
    border: 2px solid #ffeaa7;
}

.status-indicator.info {
    background-color: #d1ecf1;
    color: #0c5460;
    border: 2px solid #bee5eb;
}

.ocr-content {
    flex: 1;
    min-width: 0;
}

.ocr-label {
    font-size: 12px;
    font-weight: 600;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 4px;
}

.ocr-value {
    font-size: 14px;
    font-weight: 600;
    color: #212529;
    margin-bottom: 4px;
    word-break: break-all;
}

.ocr-status {
    font-size: 13px;
    font-weight: 500;
    margin-bottom: 2px;
}

.ocr-status.success {
    color: #155724;
}

.ocr-status.error {
    color: #721c24;
}

.ocr-detail {
    font-size: 12px;
    color: #6c757d;
    font-style: italic;
    margin-top: 2px;
}

.valid-range-info {
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    padding: 8px;
    margin-top: 8px;
    font-size: 12px;
    color: #495057;
    display: flex;
    align-items: center;
    gap: 6px;
}

.valid-range-info i {
    color: #17a2b8;
    flex-shrink: 0;
}

.confidence-badge {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
}

.confidence-badge.high {
    background-color: #d4edda;
    color: #155724;
}

.confidence-badge.medium {
    background-color: #fff3cd;
    color: #856404;
}

.confidence-badge.low {
    background-color: #f8d7da;
    color: #721c24;
}

.confidence-card {
    border-color: #17a2b8;
    border-width: 1px;
    border-style: solid;
}

/* Responsive design */
@media (max-width: 768px) {
    .ocr-results-container {
        gap: 8px;
        padding: 4px;
    }
    
    .ocr-card {
        padding: 8px;
    }
    
    .ocr-header {
        gap: 8px;
    }
    
    .status-indicator {
        width: 20px;
        height: 20px;
        font-size: 12px;
    }
}

/* Dark mode support */
@media (prefers-color-scheme: dark) {
    .ocr-card {
        background: #2d3748;
        border-color: #4a5568;
    }
    
    .ocr-value {
        color: #e2e8f0;
    }
    
    .ocr-detail {
        color: #a0aec0;
    }
    
    .valid-range-info {
        background-color: #4a5568;
        border-color: #718096;
        color: #e2e8f0;
    }
}
/* Navigation Tabs */
.nav-tabs {
    background: white;
    border-bottom: 2px solid #e2e8f0;
    padding: 0 2rem;
    display: flex;
    gap: 0;
    margin-bottom: 2rem;
}

.nav-tab {
    padding: 1rem 1.5rem;
    text-decoration: none;
    color: #64748b;
    font-weight: 500;
    border-bottom: 3px solid transparent;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.nav-tab:hover {
    color: #4f46e5;
    background: #f1f5f9;
}

.nav-tab.active {
    color: #4f46e5;
    border-bottom-color: #4f46e5;
    background: #f1f5f9;
}
    </style>
</head>
<body>
    <div class="layout-container">
        <?php include 'sidebar.php'; ?>
        
        <div class="main-content">
            <!-- Page Header -->
            <div class="page-header">
                <h1>
                    <i class="fas fa-check-circle"></i>
                    Konfirmasi Pembayaran
                </h1>
                <p>Kelola dan konfirmasi pembayaran tagihan warga dengan bantuan teknologi OCR</p>
            </div>

            <!-- Navigation Tabs -->
            <div class="nav-tabs">
                <a href="tagihan.php" class="nav-tab ">
                    <i class="fas fa-plus-circle"></i> Buat Tagihan
                </a>
                <a href="konfirmasi.php" class="nav-tab active">
                    <i class="fas fa-check-circle"></i> Konfirmasi Pembayaran
                </a>
            </div>

            <!-- Alert Messages -->
            <?php if (isset($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Statistics Section -->
            <div class="stats-grid">
                <div class="stat-card pending">
                    <div class="stat-number"><?php echo $stats['total_menunggu']; ?></div>
                    <div class="stat-label">
                        <i class="fas fa-clock"></i>
                        Menunggu Konfirmasi
                    </div>
                </div>
                
                <div class="stat-card ocr">
                    <div class="stat-number"><?php echo $stats['sudah_ocr']; ?></div>
                    <div class="stat-label">
                        <i class="fas fa-eye"></i>
                        Sudah di-OCR
                    </div>
                </div>
                
                <div class="stat-card nominal">
                    <div class="stat-number">Rp <?php echo number_format($stats['total_nominal'], 0, ',', '.'); ?></div>
                    <div class="stat-label">
                        <i class="fas fa-money-bill-wave"></i>
                        Total Nominal
                    </div>
                </div>
            </div>

            <!-- OCR Action Section -->
            <div class="card">
                <div class="card-header">
                    <h2>
                        <i class="fas fa-robot"></i>
                        Proses OCR Otomatis
                    </h2>
                </div>
                <div class="card-body">
                    <div class="ocr-section">
                        <form method="POST" style="display: inline-block;">
                            <input type="hidden" name="action" value="run_ocr">
                            <button type="submit" class="btn btn-success" id="ocrButton">
                                <i class="fas fa-robot"></i>
                                Jalankan OCR untuk Semua Bukti
                            </button>
                        </form>
                        <div class="ocr-description">
                            OCR akan mengekstrak informasi dari bukti pembayaran untuk membantu proses verifikasi
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bills Table -->
            <div class="card">
                <div class="card-header">
                    <h2>
                        <i class="fas fa-list"></i>
                        Daftar Pembayaran Menunggu Konfirmasi
                    </h2>
                </div>
                <div class="card-body">
                    <?php if (empty($bills_result)): ?>
                        <div class="empty-state">
                            <div class="icon">
                                <i class="fas fa-inbox"></i>
                            </div>
                            <h3>Tidak Ada Pembayaran Menunggu</h3>
                            <p>Semua pembayaran telah dikonfirmasi atau belum ada yang dikirim</p>
                        </div>
                    <?php else: ?>
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Username</th>
                                        <th>Kode Tagihan</th>
                                        <th>Deskripsi</th>
                                        <th>Jumlah Tagihan</th>
                                        <th>OCR Hasil</th>
                                        <th>Status Kesesuaian</th>
                                        <th>Tanggal</th>
                                        <th>Bukti</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($bills_result as $bill): ?>
                                        <?php
                                        // Determine status kesesuaian
                                        $jumlah_sesuai = false;
                                        $kode_sesuai = false;
                                        
                                        if ($bill['ocr_jumlah'] > 0) {
                                            $selisih = abs($bill['jumlah'] - $bill['ocr_jumlah']);
                                            $persentase_selisih = ($selisih / $bill['jumlah']) * 100;
                                            $jumlah_sesuai = $persentase_selisih <= 5; // Toleransi 5%
                                        }
                                        
                                        if ($bill['ocr_kode_found']) {
                                            $kode_sesuai = true;
                                        }
                                        
                                        $overall_status = '';
                                        if ($jumlah_sesuai && $kode_sesuai) {
                                            $overall_status = 'sesuai';
                                        } elseif ($bill['ocr_jumlah'] > 0 || $bill['ocr_kode_found']) {
                                            $overall_status = 'tidak-sesuai';
                                        } else {
                                            $overall_status = 'belum';
                                        }
                                        
                                        $confidence_class = 'confidence-low';
                                        if ($bill['ocr_confidence'] >= 80) {
                                            $confidence_class = 'confidence-high';
                                        } elseif ($bill['ocr_confidence'] >= 60) {
                                            $confidence_class = 'confidence-medium';
                                        }
                                        ?>
                                        <tr>
                                            <td class="username-cell">
                                                <?php echo htmlspecialchars($bill['username']); ?>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($bill['kode_tagihan']); ?></strong>
                                            </td>
                                            <td class="description-cell" title="<?php echo htmlspecialchars($bill['deskripsi']); ?>">
                                                <?php echo htmlspecialchars($bill['deskripsi']); ?>
                                            </td>
                                            <td>
                                                <div class="amount-display">
                                                    Rp <?php echo number_format($bill['jumlah'], 0, ',', '.'); ?>
                                                </div>
                                            </td>
                                            <td>
                                                    <div class="ocr-results-container">
                                                        <!-- Jumlah Transfer -->
                                                        <div class="ocr-card">
                                                            <div class="ocr-header">
                                                                <?php if ($bill['ocr_jumlah'] > 0): ?>
                                                                    <?php $amount_match = ($bill['ocr_jumlah'] == $bill['jumlah']); ?>
                                                                    <div class="status-indicator <?php echo $amount_match ? 'success' : 'warning'; ?>">
                                                                        <i class="fas <?php echo $amount_match ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?>"></i>
                                                                    </div>
                                                                    <div class="ocr-content">
                                                                        <div class="ocr-label">Jumlah Transfer</div>
                                                                        <div class="ocr-value">Rp <?php echo number_format($bill['ocr_jumlah'], 0, ',', '.'); ?></div>
                                                                        <?php if (!$amount_match): ?>
                                                                            <div class="ocr-status error">
                                                                                ✗ Tidak sesuai (seharusnya: Rp <?php echo number_format($bill['jumlah'], 0, ',', '.'); ?>)
                                                                            </div>
                                                                            <div class="ocr-detail">
                                                                                Selisih: Rp <?php echo number_format(abs($bill['jumlah'] - $bill['ocr_jumlah']), 0, ',', '.'); ?>
                                                                            </div>
                                                                        <?php else: ?>
                                                                            <div class="ocr-status success">✓ Sesuai</div>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                <?php else: ?>
                                                                    <div class="status-indicator error">
                                                                        <i class="fas fa-times-circle"></i>
                                                                    </div>
                                                                    <div class="ocr-content">
                                                                        <div class="ocr-label">Jumlah Transfer</div>
                                                                        <div class="ocr-status error">✗ Tidak ditemukan</div>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>

                                                        <!-- Kode Tagihan -->
                                                        <div class="ocr-card">
                                                            <div class="ocr-header">
                                                                <?php if ($bill['ocr_kode_found']): ?>
                                                                    <?php
                                                                    // Parse OCR details untuk mendapatkan kode yang ditemukan
                                                                    $ocr_details = json_decode($bill['ocr_details'], true);
                                                                    $extracted_code = '';
                                                                    
                                                                    if ($ocr_details && isset($ocr_details['extracted_code'])) {
                                                                        $extracted_code = $ocr_details['extracted_code'];
                                                                    }
                                                                    
                                                                    // Cek apakah kode cocok atau tidak
                                                                    $is_code_match = false;
                                                                    if (!empty($extracted_code)) {
                                                                        $is_code_match = (strtoupper($extracted_code) === strtoupper($bill['kode_tagihan']));
                                                                    }
                                                                    ?>
                                                                    
                                                                    <div class="status-indicator <?php echo $is_code_match ? 'success' : 'error'; ?>">
                                                                        <i class="fas <?php echo $is_code_match ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                                                                    </div>
                                                                    <div class="ocr-content">
                                                                        <div class="ocr-label">Kode Tagihan</div>
                                                                        <?php if (!empty($extracted_code)): ?>
                                                                            <div class="ocr-value"><?php echo htmlspecialchars($extracted_code); ?></div>
                                                                            <?php if ($is_code_match): ?>
                                                                                <div class="ocr-status success">✓ Sesuai</div>
                                                                            <?php else: ?>
                                                                                <div class="ocr-status error">✗ Tidak sesuai</div>
                                                                                <div class="ocr-detail">Seharusnya: <?php echo htmlspecialchars($bill['kode_tagihan']); ?></div>
                                                                            <?php endif; ?>
                                                                        <?php else: ?>
                                                                            <div class="ocr-status error">✗ Kode terdeteksi tapi tidak terbaca</div>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                <?php else: ?>
                                                                    <div class="status-indicator error">
                                                                        <i class="fas fa-times-circle"></i>
                                                                    </div>
                                                                    <div class="ocr-content">
                                                                        <div class="ocr-label">Kode Tagihan</div>
                                                                        <div class="ocr-status error">✗ Tidak ditemukan</div>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>

                                                        <!-- Tanggal Transfer -->
                                                        <div class="ocr-card">
                                                            <div class="ocr-header">
                                                                <?php if ($bill['ocr_date_found']): ?>
                                                                    <?php
                                                                    // Parse OCR details untuk mendapatkan tanggal yang ditemukan
                                                                    $ocr_details = json_decode($bill['ocr_details'], true);
                                                                    $extracted_date = '';
                                                                    
                                                                    if ($ocr_details && isset($ocr_details['extracted_date'])) {
                                                                        $extracted_date = $ocr_details['extracted_date'];
                                                                    }
                                                                    
                                                                    // Format tanggal yang ditemukan
                                                                    $formatted_extracted_date = '';
                                                                    if (!empty($extracted_date)) {
                                                                        $formatted_extracted_date = format_tanggal_indo($extracted_date);
                                                                    }
                                                                    
                                                                    // Validasi tanggal berdasarkan tenggat dan tanggal kirim
                                                                    $is_date_valid = false;
                                                                    $date_status = '';
                                                                    
                                                                    // Konfigurasi toleransi (dalam hari)
                                                                    $tolerance_days_before_send = 7; // Toleransi 7 hari sebelum tagihan dikirim
                                                                    
                                                                    if (!empty($extracted_date)) {
                                                                        $extracted_timestamp = strtotime($extracted_date);
                                                                        $today = time();
                                                                        $tenggat_timestamp = strtotime($bill['tenggat']);
                                                                        $tanggal_kirim_timestamp = strtotime($bill['tanggal_kirim']);
                                                                        
                                                                        // Hitung batas minimum yang diizinkan (tanggal kirim - toleransi)
                                                                        $minimum_allowed_timestamp = $tanggal_kirim_timestamp - ($tolerance_days_before_send * 24 * 60 * 60);
                                                                        
                                                                        if ($extracted_timestamp !== false) {
                                                                            // Cek apakah tanggal transfer valid
                                                                            if ($extracted_timestamp > $today) {
                                                                                // Tanggal masa depan - tidak valid
                                                                                $date_status = 'masa_depan';
                                                                            } elseif ($extracted_timestamp > $tenggat_timestamp) {
                                                                                // Tanggal transfer melebihi tenggat - tidak valid
                                                                                $date_status = 'melebihi_tenggat';
                                                                            } elseif ($extracted_timestamp < $minimum_allowed_timestamp) {
                                                                                // Tanggal transfer terlalu jauh sebelum tagihan dikirim - tidak valid
                                                                                $date_status = 'terlalu_awal';
                                                                            } else {
                                                                                // Tanggal transfer valid
                                                                                $is_date_valid = true;
                                                                                
                                                                                if ($extracted_timestamp < $tanggal_kirim_timestamp) {
                                                                                    $date_status = 'valid_sebelum_kirim';
                                                                                } else {
                                                                                    $date_status = 'valid';
                                                                                }
                                                                            }
                                                                        } else {
                                                                            $date_status = 'tidak_valid';
                                                                        }
                                                                    }
                                                                    
                                                                    // Hitung selisih hari
                                                                    $days_from_deadline = 0;
                                                                    $days_from_send = 0;
                                                                    
                                                                    if (!empty($extracted_date) && $extracted_timestamp !== false) {
                                                                        $days_from_deadline = floor(($tenggat_timestamp - $extracted_timestamp) / (60 * 60 * 24));
                                                                        $days_from_send = floor(($extracted_timestamp - $tanggal_kirim_timestamp) / (60 * 60 * 24));
                                                                    }
                                                                    ?>
                                                                    
                                                                    <div class="status-indicator <?php echo $is_date_valid ? 'success' : 'error'; ?>">
                                                                        <i class="fas <?php echo $is_date_valid ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                                                                    </div>
                                                                    <div class="ocr-content">
                                                                        <div class="ocr-label">Tanggal Transfer</div>
                                                                        <?php if (!empty($formatted_extracted_date)): ?>
                                                                            <div class="ocr-value"><?php echo htmlspecialchars($formatted_extracted_date); ?></div>
                                                                            
                                                                            <?php if ($date_status == 'valid'): ?>
                                                                                <div class="ocr-status success">✓ Valid</div>
                                                                                <?php if ($days_from_deadline > 0): ?>
                                                                                    <div class="ocr-detail"><?php echo $days_from_deadline; ?> hari sebelum tenggat</div>
                                                                                <?php elseif ($days_from_deadline == 0): ?>
                                                                                    <div class="ocr-detail">Tepat di tenggat</div>
                                                                                <?php endif; ?>
                                                                                
                                                                            <?php elseif ($date_status == 'valid_sebelum_kirim'): ?>
                                                                                <div class="ocr-status success">✓ Valid (Pembayaran Dini)</div>
                                                                                <div class="ocr-detail"><?php echo abs($days_from_send); ?> hari sebelum tagihan dikirim</div>
                                                                                
                                                                            <?php elseif ($date_status == 'masa_depan'): ?>
                                                                                <div class="ocr-status error">✗ Tanggal masa depan</div>
                                                                                
                                                                            <?php elseif ($date_status == 'melebihi_tenggat'): ?>
                                                                                <div class="ocr-status error">✗ Melebihi tenggat</div>
                                                                                <div class="ocr-detail"><?php echo abs($days_from_deadline); ?> hari setelah tenggat</div>
                                                                                
                                                                            <?php elseif ($date_status == 'terlalu_awal'): ?>
                                                                                <div class="ocr-status error">✗ Terlalu awal</div>
                                                                                <div class="ocr-detail">Lebih dari <?php echo $tolerance_days_before_send; ?> hari sebelum tagihan dikirim</div>
                                                                                
                                                                            <?php else: ?>
                                                                                <div class="ocr-status error">✗ Format tidak valid</div>
                                                                            <?php endif; ?>
                                                                            
                                                                        <?php else: ?>
                                                                            <div class="ocr-status error">✗ Tanggal terdeteksi tapi tidak terbaca</div>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                <?php else: ?>
                                                                    <div class="status-indicator error">
                                                                        <i class="fas fa-times-circle"></i>
                                                                    </div>
                                                                    <div class="ocr-content">
                                                                        <div class="ocr-label">Tanggal Transfer</div>
                                                                        <div class="ocr-status error">✗ Tidak ditemukan</div>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>

                                                        <!-- Akurasi OCR -->
                                                        <?php if ($bill['ocr_confidence'] > 0): ?>
                                                            <div class="ocr-card confidence-card">
                                                                <div class="ocr-header">
                                                                    <div class="status-indicator info">
                                                                        <i class="fas fa-chart-line"></i>
                                                                    </div>
                                                                    <div class="ocr-content">
                                                                        <div class="ocr-label">Akurasi OCR</div>
                                                                        <div class="ocr-value">
                                                                            <span class="confidence-badge <?php echo $confidence_class; ?>">
                                                                                <?php echo round($bill['ocr_confidence'], 1); ?>%
                                                                            </span>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php 
                                                        // Cek validasi jumlah
                                                        $is_amount_match = $bill['ocr_jumlah'] > 0 && $bill['ocr_jumlah'] == $bill['jumlah'];
                                                        
                                                        // Cek validasi kode tagihan
                                                        $is_code_valid = false;
                                                        if ($bill['ocr_kode_found']) {
                                                            $ocr_details = json_decode($bill['ocr_details'], true);
                                                            $extracted_code = '';
                                                            
                                                            if ($ocr_details && isset($ocr_details['extracted_code'])) {
                                                                $extracted_code = $ocr_details['extracted_code'];
                                                            }
                                                            
                                                            if (!empty($extracted_code)) {
                                                                $is_code_valid = (strtoupper($extracted_code) === strtoupper($bill['kode_tagihan']));
                                                            }
                                                        }
                                                        
                                                        // Cek validasi tanggal
                                                        $is_date_valid = false;
                                                        if ($bill['ocr_date_found']) {
                                                            $ocr_details = json_decode($bill['ocr_details'], true);
                                                            $extracted_date = '';
                                                            
                                                            if ($ocr_details && isset($ocr_details['extracted_date'])) {
                                                                $extracted_date = $ocr_details['extracted_date'];
                                                            }
                                                            
                                                            if (!empty($extracted_date)) {
                                                                $extracted_timestamp = strtotime($extracted_date);
                                                                $today = time();
                                                                $tenggat_timestamp = strtotime($bill['tenggat']);
                                                                $tanggal_kirim_timestamp = strtotime($bill['tanggal_kirim']);
                                                                $tolerance_days_before_send = 7;
                                                                $minimum_allowed_timestamp = $tanggal_kirim_timestamp - ($tolerance_days_before_send * 24 * 60 * 60);
                                                                
                                                                if ($extracted_timestamp !== false) {
                                                                    if ($extracted_timestamp <= $today && 
                                                                        $extracted_timestamp <= $tenggat_timestamp && 
                                                                        $extracted_timestamp >= $minimum_allowed_timestamp) {
                                                                        $is_date_valid = true;
                                                                    }
                                                                }
                                                            }
                                                        }
                                                        
                                                        $confidence = $bill['ocr_confidence'];
                                                        
                                                        // Status final: semua harus valid
                                                        if ($is_amount_match && $is_code_valid && $is_date_valid && $confidence): ?>
                                                            <span class="status-badge status-sesuai">
                                                                <i class="fas fa-check-circle"></i>
                                                                Sesuai
                                                            </span>
                                                        <?php elseif ($bill['ocr_jumlah'] > 0): ?>
                                                            <span class="status-badge status-tidak-sesuai">
                                                                <i class="fas fa-exclamation-triangle"></i>
                                                                Tidak Sesuai
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="status-badge status-belum">
                                                                <i class="fas fa-clock"></i>
                                                                Belum Diproses
                                                            </span>
                                                        <?php endif; ?>
                                                </td>
                                            <td class="date-cell">
                                                <div class="date-kirim">
                                                    <i class="fas fa-calendar-plus"></i>
                                                    Dikirim: <?php echo format_tanggal_indo($bill['tanggal_kirim']); ?>
                                                </div>
                                                <div class="date-tenggat">
                                                    <i class="fas fa-calendar-times"></i>
                                                    Tenggat: <?php echo format_tanggal_indo($bill['tenggat']); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($bill['bukti_pembayaran']): ?>
                                                    <img src="../warga/uploads/bukti_pembayaran/<?php echo htmlspecialchars($bill['bukti_pembayaran']); ?>" 
                                                         alt="Bukti Pembayaran" 
                                                         class="image-preview"
                                                         onclick="openImageModal(this.src)">
                                                <?php else: ?>
                                                    <span class="text-muted">Tidak ada</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <form method="POST" class="action-form">
                                                    <input type="hidden" name="user_bill_id" value="<?php echo $bill['user_bill_id']; ?>">
                                                    <input type="hidden" name="action" value="confirm_bill">
                                                    <select name="status" class="action-select" required>
                                                        <option value="">Pilih Aksi</option>
                                                        <option value="konfirmasi">✓ Konfirmasi</option>
                                                        <option value="tolak">✗ Tolak</option>
                                                    </select>
                                                    <button type="submit" class="btn btn-small" 
                                                            onclick="return confirm('Apakah Anda yakin?')">
                                                        <i class="fas fa-check"></i>
                                                        Proses
                                                    </button>
                                                </form>          
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Image Modal -->
    <div id="imageModal" class="qr-modal">
        <div class="qr-modal-content">
            <button class="qr-modal-close" onclick="closeImageModal()">&times;</button>
            <h3>Bukti Pembayaran</h3>
            <img id="modalImage" src="" alt="Bukti Pembayaran" style="max-width: 100%; height: auto; border-radius: 8px;">
        </div>
    </div>

    <!-- QR Code Modal -->
    <div id="qrModal" class="qr-modal">
        <div class="qr-modal-content">
            <button class="qr-modal-close" onclick="closeQRModal()">&times;</button>
            <h3>QR Code Konfirmasi</h3>
            <div class="qr-code-container">
                <img id="qrCodeImage" src="" alt="QR Code" style="max-width: 200px; height: auto;">
            </div>
            <div class="qr-code-info">
                <h4>Informasi QR Code:</h4>
                <p id="qrCodeInfo"></p>
            </div>
            <button onclick="downloadQRCode()" class="btn btn-small" style="margin-top: 1rem;">
                <i class="fas fa-download"></i>
                Download QR Code
            </button>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentQRData = '';
        let currentQRCode = '';

        function openImageModal(src) {
            document.getElementById('modalImage').src = src;
            document.getElementById('imageModal').classList.add('show');
        }

        function closeImageModal() {
            document.getElementById('imageModal').classList.remove('show');
        }

        function showQRCode(data, kodeTagihan) {
            currentQRData = data;
            currentQRCode = kodeTagihan;
            
            const qrCodeURL = `https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(data)}`;
            document.getElementById('qrCodeImage').src = qrCodeURL;
            document.getElementById('qrCodeInfo').innerHTML = data.replace(/\n/g, '<br>');
            document.getElementById('qrModal').classList.add('show');
        }

        function closeQRModal() {
            document.getElementById('qrModal').classList.remove('show');
        }

        function downloadQRCode() {
            if (currentQRData) {
                const qrCodeURL = `https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=${encodeURIComponent(currentQRData)}`;
                const link = document.createElement('a');
                link.href = qrCodeURL;
                link.download = `qr-code-${currentQRCode}.png`;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }
        }

        // Close modals when clicking outside
        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('qr-modal')) {
                event.target.classList.remove('show');
            }
        });

        // Auto-refresh setiap 30 detik untuk update real-time
        setInterval(function() {
            if (!document.querySelector('.qr-modal.show')) {
                location.reload();
            }
        }, 30000);

        // Konfirmasi sebelum submit form
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const select = this.querySelector('select[name="status"]');
                if (select && select.value) {
                    const action = select.value === 'konfirmasi' ? 'mengkonfirmasi' : 'menolak';
                    if (!confirm(`Apakah Anda yakin ingin ${action} pembayaran ini?`)) {
                        e.preventDefault();
                    }
                }
            });
        });

        // Highlight row on hover
        document.querySelectorAll('tbody tr').forEach(row => {
            row.addEventListener('mouseenter', function() {
                this.style.backgroundColor = '#f1f5f9';
            });
            
            row.addEventListener('mouseleave', function() {
                this.style.backgroundColor = '';
            });
        });

        // Auto-scroll to top after form submission
        if (window.location.hash) {
            window.scrollTo(0, 0);
        }

        // Loading state untuk tombol OCR
        document.querySelector('form[action="run_ocr"]')?.addEventListener('submit', function() {
            const button = this.querySelector('button[type="submit"]');
            button.innerHTML = '<div class="loading"></div>Memproses OCR...';
            button.disabled = true;
        });
    </script>
</body>
</html>
