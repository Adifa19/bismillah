-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 06, 2025 at 05:30 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `tetangga.id`
--

-- --------------------------------------------------------

--
-- Table structure for table `anggota_keluarga`
--

CREATE TABLE `anggota_keluarga` (
  `id` smallint(5) UNSIGNED NOT NULL,
  `pendataan_id` smallint(5) UNSIGNED NOT NULL,
  `nik` char(16) NOT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `jenis_kelamin` enum('Laki-laki','Perempuan') DEFAULT NULL,
  `tanggal_lahir` date DEFAULT NULL,
  `pekerjaan` varchar(100) DEFAULT NULL,
  `status_hubungan` enum('anak','istri','suami','ayah','ibu','saudara','menantu','cucu','orangtua','famililain','mertua') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `anggota_keluarga`
--

INSERT INTO `anggota_keluarga` (`id`, `pendataan_id`, `nik`, `nama_lengkap`, `jenis_kelamin`, `tanggal_lahir`, `pekerjaan`, `status_hubungan`, `created_at`) VALUES
(44, 74, '3216065507780064', 'AL LATIFAH LUBIS', 'Perempuan', '1978-07-15', 'Ibu Rumah Tangga', 'istri', '2025-06-30 04:44:17'),
(45, 74, '3216066206000030', 'AURA DHIA RIZKI ATTHAR', 'Perempuan', '2000-06-22', 'Karyawan Swasta', 'anak', '2025-06-30 04:44:17'),
(46, 74, '3216065905030018', 'ADIFA FATIMAH AZ ZAHRA', 'Perempuan', '2003-05-19', 'Mahasiswa', 'anak', '2025-06-30 04:44:17'),
(47, 74, '3216062201080019', 'ASRAF ARIQ NAUFAL', 'Laki-laki', '2008-01-22', 'Pelajar', 'anak', '2025-06-30 04:44:17');

-- --------------------------------------------------------

--
-- Table structure for table `bills`
--

CREATE TABLE `bills` (
  `id` smallint(5) UNSIGNED NOT NULL,
  `admin_id` smallint(5) UNSIGNED NOT NULL,
  `kode_tagihan` varchar(50) NOT NULL,
  `jumlah` mediumint(8) UNSIGNED NOT NULL,
  `deskripsi` text NOT NULL,
  `tanggal` date NOT NULL,
  `waktu_mulai` date DEFAULT NULL,
  `tenggat_waktu` date DEFAULT NULL,
  `qr_code_data` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bills`
--

INSERT INTO `bills` (`id`, `admin_id`, `kode_tagihan`, `jumlah`, `deskripsi`, `tanggal`, `waktu_mulai`, `tenggat_waktu`, `qr_code_data`) VALUES
(115, 72, 'TAG-52415-75', 1000, 'Iuran bulanan', '2025-07-12', NULL, NULL, '{\"kode\":\"TAG-52415-75\",\"jumlah\":\"1000\",\"user_id\":\"75\"}'),
(116, 72, 'TAG-32406-75', 1000, 'Iuran bulanan', '2025-07-12', NULL, NULL, '{\"kode\":\"TAG-32406-75\",\"jumlah\":\"1000\",\"user_id\":\"75\"}'),
(117, 72, 'TAG-65378-75', 42000, 'Iuran Sampah bulan Juni', '2025-07-12', NULL, NULL, '{\"kode\":\"TAG-65378-75\",\"jumlah\":\"42000\",\"user_id\":\"75\"}'),
(118, 1, 'TAG-77508-75', 3000, 'oci', '2025-07-09', NULL, NULL, '{\"kode\":\"TAG-77508-75\",\"jumlah\":\"3000\",\"user_id\":\"75\"}'),
(119, 1, 'TAG-08983-75', 3000, 'oci', '2025-07-09', NULL, NULL, '{\"kode\":\"TAG-08983-75\",\"jumlah\":\"3000\",\"user_id\":\"75\"}'),
(120, 1, 'TAG-45799-75', 50000, 'iuran oke', '2025-07-12', NULL, NULL, '{\"kode\":\"TAG-45799-75\",\"jumlah\":\"50000\",\"user_id\":\"75\"}'),
(121, 1, 'TAG-92992-75', 50000, 'iuran oke', '2025-07-12', NULL, NULL, '{\"kode\":\"TAG-92992-75\",\"jumlah\":\"50000\",\"user_id\":\"75\"}'),
(122, 1, 'TAG-33048-75', 20000, 'sapi', '2025-07-12', NULL, NULL, '{\"kode\":\"TAG-33048-75\",\"jumlah\":\"20000\",\"user_id\":\"75\"}'),
(123, 1, 'TAG-23892-75', 2000, 'makan', '2025-07-12', NULL, NULL, '{\"kode\":\"TAG-23892-75\",\"jumlah\":\"2000\",\"user_id\":\"75\"}'),
(124, 1, 'TAG-84877-75', 4000, 'rab', '2025-07-12', NULL, NULL, '{\"kode\":\"TAG-84877-75\",\"jumlah\":\"4000\",\"user_id\":\"75\"}'),
(125, 1, 'TAG-31681-75', 5000, 'cu', '2025-07-12', NULL, NULL, '{\"kode\":\"TAG-31681-75\",\"jumlah\":\"5000\",\"user_id\":\"75\"}'),
(126, 1, 'TAG-46338-75', 7000, 'cape', '2025-07-12', NULL, NULL, '{\"kode\":\"TAG-46338-75\",\"jumlah\":\"7000\",\"user_id\":\"75\"}'),
(127, 1, 'TAG-53245-75', 9000, 'wkwk', '2025-07-12', NULL, NULL, '{\"kode\":\"TAG-53245-75\",\"jumlah\":\"9000\",\"user_id\":\"75\"}'),
(128, 1, 'TAG-28873-75', 34000, 'dinkum', '2025-07-12', NULL, NULL, '{\"kode\":\"TAG-28873-75\",\"jumlah\":\"34000\",\"user_id\":\"75\"}'),
(129, 1, 'TAG-43124-75', 55000, 'seru', '2025-07-12', NULL, NULL, '{\"kode\":\"TAG-43124-75\",\"jumlah\":\"55000\",\"user_id\":\"75\"}'),
(130, 1, 'TAG-39646-75', 17500, 'sampah', '2025-07-12', NULL, NULL, '{\"kode\":\"TAG-39646-75\",\"jumlah\":\"17500\",\"user_id\":\"75\"}'),
(131, 1, 'TAG-57664-75', 13000, 'ok', '2025-07-12', NULL, NULL, '{\"kode\":\"TAG-57664-75\",\"jumlah\":\"13000\",\"user_id\":\"75\"}'),
(132, 1, 'TAG-30294-75', 23000, 'p', '2025-07-12', NULL, NULL, '{\"kode\":\"TAG-30294-75\",\"jumlah\":\"23000\",\"user_id\":\"75\"}'),
(133, 1, 'TAG-89976-75', 88998, 'dka', '2025-07-12', NULL, NULL, '{\"kode\":\"TAG-89976-75\",\"jumlah\":\"88998\",\"user_id\":\"75\"}'),
(134, 1, 'TAG-80603-75', 34499, 'ntah', '2025-07-12', NULL, NULL, '{\"kode\":\"TAG-80603-75\",\"jumlah\":\"34499\",\"user_id\":\"75\"}'),
(135, 1, 'TAG-91031-75', 34499, 'ntah', '2025-07-12', NULL, NULL, '{\"kode\":\"TAG-91031-75\",\"jumlah\":\"34499\",\"user_id\":\"75\"}');

-- --------------------------------------------------------

--
-- Table structure for table `chat_messages`
--

CREATE TABLE `chat_messages` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `recipient_id` int(11) DEFAULT NULL,
  `username` varchar(100) NOT NULL,
  `message` text DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_type` varchar(100) DEFAULT NULL,
  `is_admin` tinyint(1) DEFAULT 0,
  `is_read` tinyint(1) DEFAULT 0,
  `is_private` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chat_messages`
--

INSERT INTO `chat_messages` (`id`, `user_id`, `recipient_id`, `username`, `message`, `file_path`, `file_name`, `file_type`, `is_admin`, `is_read`, `is_private`, `created_at`) VALUES
(35, 75, NULL, 'Jasaruddin', 'permisi kak, saya mau tanya', NULL, NULL, NULL, 0, 1, 0, '2025-06-30 00:48:22'),
(36, 75, NULL, 'admin', 'iya kenapa ya kak? ada masalah?', NULL, NULL, NULL, 1, 0, 0, '2025-06-30 00:48:44'),
(37, 75, NULL, 'Jasaruddin', 'kenapa tagihan saya ditolak ya?', NULL, NULL, NULL, 0, 1, 0, '2025-06-30 01:39:40'),
(38, 75, NULL, 'Jasaruddin', 'apakah ada sesuatu?', NULL, NULL, NULL, 0, 1, 0, '2025-06-30 01:39:52'),
(39, 75, NULL, 'admin', 'oh tagihan bapak, tidak sesuai dengan kode tagihannya pak', NULL, NULL, NULL, 1, 0, 0, '2025-06-30 01:40:22'),
(40, 75, NULL, 'Jasaruddin', 'permisi ka', NULL, NULL, NULL, 0, 0, 0, '2025-06-30 13:39:03');

-- --------------------------------------------------------

--
-- Table structure for table `chat_online_users`
--

CREATE TABLE `chat_online_users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_admin` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chat_online_users`
--

INSERT INTO `chat_online_users` (`user_id`, `username`, `last_activity`, `is_admin`) VALUES
(1, 'admin', '2025-06-30 01:40:22', 1),
(72, 'admin', '2025-06-30 00:48:44', 1),
(75, 'Jasaruddin', '2025-06-30 13:39:03', 0);

-- --------------------------------------------------------

--
-- Table structure for table `income`
--

CREATE TABLE `income` (
  `id` mediumint(8) UNSIGNED NOT NULL,
  `username` varchar(20) NOT NULL,
  `deskripsi` text NOT NULL,
  `jumlah` mediumint(8) UNSIGNED NOT NULL,
  `status` enum('menunggu_pembayaran','menunggu_konfirmasi','konfirmasi','tolak') NOT NULL DEFAULT 'menunggu_pembayaran',
  `tanggal` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `kegiatan`
--

CREATE TABLE `kegiatan` (
  `id` int(10) UNSIGNED NOT NULL,
  `judul` varchar(50) NOT NULL,
  `deskripsi` text NOT NULL,
  `hari` varchar(15) NOT NULL,
  `tanggal_kegiatan` date NOT NULL,
  `alamat` varchar(100) NOT NULL,
  `foto_kegiatan` varchar(255) DEFAULT NULL,
  `dokumentasi_link` varchar(255) DEFAULT NULL,
  `status_kegiatan` enum('Direncanakan','Selesai') NOT NULL DEFAULT 'Direncanakan'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `kegiatan`
--

INSERT INTO `kegiatan` (`id`, `judul`, `deskripsi`, `hari`, `tanggal_kegiatan`, `alamat`, `foto_kegiatan`, `dokumentasi_link`, `status_kegiatan`) VALUES
(3, 'Pengajian', 'Pengajian rutin bulanan', 'Minggu', '2025-07-05', 'Musholla Al Ikhlas', 'kegiatan_1751246609.jpg', '', 'Selesai'),
(4, 'Kerja bakti', 'Kerja bakti bulanan warga RT 007', 'Sabtu', '2025-07-05', 'Lapangan RT 007', 'kegiatan_1751246254.jpeg', '', 'Selesai'),
(6, 'Acara vaksin bulanan', 'Dalam rangka mendukung program imunisasi nasional, akan diadakan vaksinasi anak-anak di lingkungan RT 007 Graha Prima.', 'Rabu', '2025-06-25', 'Posyandu RT 007', NULL, NULL, 'Direncanakan');

-- --------------------------------------------------------

--
-- Table structure for table `keluaran`
--

CREATE TABLE `keluaran` (
  `id` smallint(6) NOT NULL,
  `deskripsi` text NOT NULL,
  `jumlah` mediumint(9) NOT NULL,
  `tanggal` date NOT NULL,
  `bukti_file` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `keluaran`
--

INSERT INTO `keluaran` (`id`, `deskripsi`, `jumlah`, `tanggal`, `bukti_file`, `created_at`, `updated_at`) VALUES
(3, 'Pembuatan gapura gang 1', 50000, '2025-06-21', '1751245250_graha.jpg', '2025-06-30 01:00:50', '2025-06-30 01:00:50');

-- --------------------------------------------------------

--
-- Table structure for table `nomor_kk`
--

CREATE TABLE `nomor_kk` (
  `id` smallint(5) UNSIGNED NOT NULL,
  `no_kk` char(16) NOT NULL,
  `jumlah_pengguna` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `max_pengguna` tinyint(3) UNSIGNED NOT NULL DEFAULT 2,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('Aktif','Tidak Aktif') DEFAULT 'Aktif'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `nomor_kk`
--

INSERT INTO `nomor_kk` (`id`, `no_kk`, `jumlah_pengguna`, `max_pengguna`, `created_at`, `status`) VALUES
(46, '3171031601091593', 1, 2, '2025-06-30 00:36:49', 'Aktif');

-- --------------------------------------------------------

--
-- Table structure for table `pendataan`
--

CREATE TABLE `pendataan` (
  `id` smallint(5) UNSIGNED NOT NULL,
  `user_id` smallint(5) UNSIGNED NOT NULL,
  `no_kk` char(16) NOT NULL,
  `nik` char(16) DEFAULT NULL,
  `nama_lengkap` varchar(100) DEFAULT NULL,
  `tanggal_lahir` date DEFAULT NULL,
  `jenis_kelamin` enum('Laki-laki','Perempuan') DEFAULT NULL,
  `pekerjaan` varchar(100) DEFAULT NULL,
  `jumlah_anggota_keluarga` tinyint(3) UNSIGNED DEFAULT NULL,
  `alamat` varchar(100) NOT NULL,
  `no_telp` varchar(15) DEFAULT NULL,
  `foto_ktp` varchar(255) DEFAULT NULL,
  `foto_kk` varchar(255) DEFAULT NULL,
  `status_warga` enum('Aktif','Tidak Aktif') NOT NULL DEFAULT 'Aktif',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_registered` tinyint(1) DEFAULT 0,
  `status_rumah` enum('Pribadi','Mengontrak') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pendataan`
--

INSERT INTO `pendataan` (`id`, `user_id`, `no_kk`, `nik`, `nama_lengkap`, `tanggal_lahir`, `jenis_kelamin`, `pekerjaan`, `jumlah_anggota_keluarga`, `alamat`, `no_telp`, `foto_ktp`, `foto_kk`, `status_warga`, `created_at`, `is_registered`, `status_rumah`) VALUES
(74, 75, '3171031601091593', '3171031108700008', 'DRS. Jasaruddin', '1970-08-11', 'Laki-laki', 'TNI', 4, 'JL. Mataram VI Blok L3 No. 27', '082125432469', '../uploads/3171031108700008_1751243809.jpg', '../uploads/3171031601091593_1751243809.jpg', 'Aktif', '2025-06-30 00:36:49', 1, 'Pribadi');

-- --------------------------------------------------------

--
-- Table structure for table `tagihan_oke`
--

CREATE TABLE `tagihan_oke` (
  `id` int(11) NOT NULL,
  `user_bill_id` smallint(5) UNSIGNED NOT NULL,
  `kode_tagihan` varchar(50) NOT NULL,
  `jumlah` int(11) NOT NULL,
  `tanggal` date NOT NULL,
  `user_id` smallint(5) UNSIGNED NOT NULL,
  `qr_code_hash` varchar(64) DEFAULT NULL,
  `bukti_pembayaran` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tagihan_oke`
--

INSERT INTO `tagihan_oke` (`id`, `user_bill_id`, `kode_tagihan`, `jumlah`, `tanggal`, `user_id`, `qr_code_hash`, `bukti_pembayaran`, `created_at`) VALUES
(16, 368, 'TAG-52415-75', 1000, '2025-06-30', 75, '4ab55731268695952137e2991d91d5a18d5174eedc603f58e6cd5b2413d6aac6', 'bukti_368_1751244177.jpg', '2025-06-30 01:29:26');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` smallint(5) UNSIGNED NOT NULL,
  `username` varchar(20) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','user') NOT NULL DEFAULT 'user',
  `status_pengguna` enum('Aktif','Tidak Aktif') NOT NULL DEFAULT 'Aktif',
  `no_kk` char(16) DEFAULT NULL,
  `data_lengkap` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `status_pengguna`, `no_kk`, `data_lengkap`, `created_at`, `updated_at`) VALUES
(1, 'admin', '$2y$10$a2I8rmKxr.HsPJ6j8.EzHebwZ80/jACz/98mLSAz6tdvboK4Bqk.C', 'admin', 'Aktif', NULL, 0, '2025-05-31 10:00:18', NULL),
(74, '3171031601091593', '$2y$10$j3ou.If.2OuLQIGO4yqxdO9vfoVNeDcxKH8ufyWBf/kGc8rS/GK96', 'user', 'Aktif', '3171031601091593', 0, '2025-06-30 00:36:49', NULL),
(75, 'Jasaruddin', '$2y$10$LzKnVbDEjKPRJIeRgOqLC.sWX9w5HigbayWbdRD6f94bwP6JgTHxK', 'user', 'Aktif', '3171031601091593', 1, '2025-06-30 00:38:27', '2025-06-30 01:25:22');

-- --------------------------------------------------------

--
-- Table structure for table `user_bills`
--

CREATE TABLE `user_bills` (
  `id` smallint(5) UNSIGNED NOT NULL,
  `bill_id` smallint(5) UNSIGNED NOT NULL,
  `user_id` smallint(5) UNSIGNED NOT NULL,
  `status` enum('menunggu_pembayaran','menunggu_konfirmasi','konfirmasi','tolak') NOT NULL DEFAULT 'menunggu_pembayaran',
  `midtrans_status` varchar(20) DEFAULT NULL,
  `payment_type` varchar(20) DEFAULT NULL,
  `bukti_pembayaran` varchar(255) DEFAULT NULL,
  `tanggal_upload` datetime DEFAULT NULL,
  `midtrans_response` text DEFAULT NULL,
  `tanggal` date NOT NULL,
  `qr_code_hash` varchar(64) DEFAULT NULL,
  `qr_code_data` text DEFAULT NULL,
  `payment_token` varchar(100) DEFAULT NULL,
  `tanggal_bayar_online` datetime DEFAULT NULL,
  `midtrans_transaction_id` varchar(255) DEFAULT NULL,
  `midtrans_order_id` varchar(255) DEFAULT NULL,
  `order_id` varchar(50) DEFAULT NULL,
  `ocr_jumlah` int(11) DEFAULT NULL,
  `ocr_kode_found` tinyint(1) DEFAULT 0,
  `ocr_date_found` tinyint(1) DEFAULT 0,
  `ocr_confidence` decimal(5,2) DEFAULT 0.00,
  `ocr_details` text DEFAULT NULL,
  `is_terlambat` tinyint(1) DEFAULT 0,
  `selisih_hari` int(11) DEFAULT NULL,
  `composite_image` varchar(255) DEFAULT NULL,
  `is_ontime` tinyint(1) DEFAULT NULL COMMENT '1=tepat waktu, 0=terlambat, NULL=belum upload',
  `is_hidden` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_bills`
--

INSERT INTO `user_bills` (`id`, `bill_id`, `user_id`, `status`, `midtrans_status`, `payment_type`, `bukti_pembayaran`, `tanggal_upload`, `midtrans_response`, `tanggal`, `qr_code_hash`, `qr_code_data`, `payment_token`, `tanggal_bayar_online`, `midtrans_transaction_id`, `midtrans_order_id`, `order_id`, `ocr_jumlah`, `ocr_kode_found`, `ocr_date_found`, `ocr_confidence`, `ocr_details`, `is_terlambat`, `selisih_hari`, `composite_image`, `is_ontime`, `is_hidden`) VALUES
(368, 115, 75, 'konfirmasi', NULL, NULL, 'bukti_368_1751244177.jpg', '2025-06-30 02:42:57', NULL, '2025-06-30', '4ab55731268695952137e2991d91d5a18d5174eedc603f58e6cd5b2413d6aac6', 'KONFIRMASI PEMBAYARAN\nKode: TAG-52415-75\nUsername: Jasaruddin\nJumlah: Rp 1.000\nDeskripsi: Iuran bulanan\nStatus: TERKONFIRMASI\nTanggal Konfirmasi: 30/06/2025 03:29:26\nHash: 5ee3394c47899d058d9995c2053545be', 'cbab586a258932c8265b8e7d8dd43660', NULL, NULL, NULL, NULL, 1000, 1, 1, 0.00, '{\"extracted_text\":\"{\\\"extracted_text\\\": \\\"07.41 0 @ Rp 1.000 30 Jun 2025, 07.41 Dikirim Dari Adifa kuk k Kirim Ke Ka Rere Sumber Dana Saldo ShopeePay Rincian Referensi Deskripsi TAG-52415-75 Order SN Bagikan\\\", \\\"normalized_text\\\": \\\"07.41 0 @ Rp 1.000 30 Jun 2025. 07.41 Dikirim Dari Adifa kuk k Kirim Ke Ka Rere Sumber Dana Sald0 Sh0peePay Rincian Referensi Deskripsi TAG-52415-75 0rder SN Bagikan\\\", \\\"jumlah\\\": \\\"07.41\\\", \\\"tanggal\\\": null, \\\"kode_tagihan\\\": null}\",\"extracted_date\":\"2025-06-30\",\"extracted_code\":\"TAG-52415-75\",\"processed_at\":\"2025-06-30 03:27:04\",\"file_path\":\"..\\/warga\\/uploads\\/bukti_pembayaran\\/bukti_368_1751244177.jpg\"}', 0, NULL, NULL, NULL, 0),
(369, 116, 75, 'menunggu_konfirmasi', NULL, NULL, 'bukti_369_1751258424.jpg', '2025-06-30 06:40:24', NULL, '2025-06-30', 'f7ab515bf024e80585dea038e1a6675ab258d9c075728f3262197e40c3d83813', '{\"kode\":\"TAG-32406-75\",\"jumlah\":\"1000\",\"user_id\":\"75\"}', 'fd58f9614b5233cc1d34bdc76ba41663', NULL, NULL, NULL, NULL, 1000, 1, 1, 0.00, '{\"extracted_text\":\"{\\\"extracted_text\\\": \\\"07.41 0 @ Rp 1.000 30 Jun 2025, 07.41 Dikirim Dari Adifa kuk k Kirim Ke Ka Rere Sumber Dana Saldo ShopeePay Rincian Referensi Deskripsi TAG-52415-75 Order SN Bagikan\\\", \\\"normalized_text\\\": \\\"07.41 0 @ Rp 1.000 30 Jun 2025. 07.41 Dikirim Dari Adifa kuk k Kirim Ke Ka Rere Sumber Dana Sald0 Sh0peePay Rincian Referensi Deskripsi TAG-52415-75 0rder SN Bagikan\\\", \\\"jumlah\\\": \\\"07.41\\\", \\\"tanggal\\\": null, \\\"kode_tagihan\\\": null}\",\"extracted_date\":\"2025-06-30\",\"extracted_code\":\"TAG-52415-75\",\"processed_at\":\"2025-07-05 02:14:10\",\"file_path\":\"..\\/warga\\/uploads\\/bukti_pembayaran\\/bukti_369_1751258424.jpg\"}', 0, NULL, NULL, NULL, 0),
(370, 117, 75, '', NULL, NULL, NULL, NULL, NULL, '2025-06-30', '01173c4b2a83fd38a302b96e70fe9052c4496b96be6a9124bd050746ec249239', '{\"kode\":\"TAG-65378-75\",\"jumlah\":\"42000\",\"user_id\":\"75\"}', '68ac4e86-a6af-492b-83c3-a7be87e0577e', NULL, NULL, NULL, NULL, NULL, 0, 0, 0.00, NULL, 0, NULL, NULL, NULL, 0),
(371, 118, 75, 'menunggu_konfirmasi', NULL, NULL, 'bukti_371_1751499157.jpg', '2025-07-03 01:32:37', NULL, '2025-07-03', 'e1281c22f89f29b9e3f1e3103bc1406f5e0aa427d7160354009376d8962695ba', '{\"kode\":\"TAG-77508-75\",\"jumlah\":\"3000\",\"user_id\":\"75\"}', '441a7f4e8ca85c37a47703f230d98952', NULL, NULL, NULL, NULL, 1000, 1, 1, 0.00, '{\"extracted_text\":\"{\\\"extracted_text\\\": \\\"05.02 Rp 1.000 01 Jul 2025, 05.02 Dikirim Dari Adifa 4**+* Kirim Ke Ka Rere Sumber Dana Saldo ShopeePay Rincian Referensi Deskripsi TAG-12843-75 Order SN Bagikan\\\", \\\"normalized_text\\\": \\\"05.02 Rp 1.000 01 Jul 2025. 05.02 Dikirim Dari Adifa 4**+* Kirim Ke Ka Rere Sumber Dana Sald0 Sh0peePay Rincian Referensi Deskripsi TAG-12843-75 0rder SN Bagikan\\\", \\\"jumlah\\\": \\\"05.02\\\", \\\"tanggal\\\": null, \\\"kode_tagihan\\\": null}\",\"extracted_date\":\"2025-07-01\",\"extracted_code\":\"TAG-12843-75\",\"processed_at\":\"2025-07-05 02:14:25\",\"file_path\":\"..\\/warga\\/uploads\\/bukti_pembayaran\\/bukti_371_1751499157.jpg\"}', 0, NULL, NULL, NULL, 0),
(372, 119, 75, 'menunggu_konfirmasi', NULL, NULL, 'bukti_372_1751674416.jpg', '2025-07-05 07:13:36', NULL, '2025-07-03', 'ef5227287207d3ba65883218459d5adfdf176008f7d178153fca8c42094ffa1e', '{\"kode\":\"TAG-08983-75\",\"jumlah\":\"3000\",\"user_id\":\"75\"}', 'eb40c932-e873-4e32-8eac-bb3d1ad7', NULL, NULL, NULL, NULL, 1000, 1, 1, 0.00, '{\"extracted_text\":\"{\\\"extracted_text\\\": \\\"05.02 Rp 1.000 01 Jul 2025, 05.02 Dikirim Dari Adifa 4**+* Kirim Ke Ka Rere Sumber Dana Saldo ShopeePay Rincian Referensi Deskripsi TAG-12843-75 Order SN Bagikan\\\", \\\"normalized_text\\\": \\\"05.02 Rp 1.000 01 Jul 2025. 05.02 Dikirim Dari Adifa 4**+* Kirim Ke Ka Rere Sumber Dana Sald0 Sh0peePay Rincian Referensi Deskripsi TAG-12843-75 0rder SN Bagikan\\\", \\\"jumlah\\\": \\\"05.02\\\", \\\"tanggal\\\": null, \\\"kode_tagihan\\\": null}\",\"extracted_date\":\"2025-07-01\",\"extracted_code\":\"TAG-12843-75\",\"processed_at\":\"2025-07-05 02:14:42\",\"file_path\":\"..\\/warga\\/uploads\\/bukti_pembayaran\\/bukti_372_1751674416.jpg\"}', 0, NULL, NULL, NULL, 0),
(373, 120, 75, '', NULL, NULL, NULL, NULL, NULL, '2025-07-05', '44a4e9ae076afec31910b4256f2181343bdc27e73017b41476a371684b6785d0', '{\"kode\":\"TAG-45799-75\",\"jumlah\":\"50000\",\"user_id\":\"75\"}', '8b03e3e8-65af-4559-8330-2e2adc9a8351', NULL, NULL, NULL, NULL, NULL, 0, 0, 0.00, NULL, 0, NULL, NULL, NULL, 0),
(374, 121, 75, '', NULL, NULL, NULL, NULL, NULL, '2025-07-05', 'ac800fdbba84e4ff6069e3fbd6b822b96f475555fa62197815d5cbf051ecd1e1', '{\"kode\":\"TAG-92992-75\",\"jumlah\":\"50000\",\"user_id\":\"75\"}', '79033986-fe79-419a-9683-89cf6dfe9285', NULL, NULL, NULL, NULL, NULL, 0, 0, 0.00, NULL, 0, NULL, NULL, NULL, 0),
(375, 122, 75, '', NULL, NULL, NULL, NULL, NULL, '2025-07-05', '00c464f41bc966aac50ec81d19b2a751ad03902bf46b3bb3f627792e12f2db6a', '{\"kode\":\"TAG-33048-75\",\"jumlah\":\"20000\",\"user_id\":\"75\"}', 'f4cdb802-6532-414f-8f0c-f36a613a1d97', NULL, NULL, NULL, NULL, NULL, 0, 0, 0.00, NULL, 0, NULL, NULL, NULL, 0),
(376, 123, 75, '', NULL, NULL, NULL, NULL, NULL, '2025-07-05', 'd1da34febaa0216ebd675de4394e5cb1df476ec0a82d035780a7ae64f4bf98b8', '{\"kode\":\"TAG-23892-75\",\"jumlah\":\"2000\",\"user_id\":\"75\"}', 'f3c01e49-80f5-460b-b464-8c59418563f5', NULL, NULL, NULL, NULL, NULL, 0, 0, 0.00, NULL, 0, NULL, NULL, NULL, 0),
(377, 124, 75, '', NULL, NULL, NULL, NULL, NULL, '2025-07-05', '4b2917f17d95da5572b93a75b0684d4c7e14b9f5e29d422babca25eaa4bb00b1', '{\"kode\":\"TAG-84877-75\",\"jumlah\":\"4000\",\"user_id\":\"75\"}', 'cf185305-9cce-4d53-88be-071a6d00ca75', NULL, NULL, NULL, NULL, NULL, 0, 0, 0.00, NULL, 0, NULL, NULL, NULL, 0),
(378, 125, 75, 'menunggu_konfirmasi', NULL, NULL, 'bukti_378_1751721788.jpg', '2025-07-05 20:23:08', '{\"status_code\":\"200\",\"transaction_id\":\"896ebcf0-5d70-4772-ac05-6781b248def6\",\"gross_amount\":\"5000.00\",\"currency\":\"IDR\",\"order_id\":\"BILL_125_75_1751721750\",\"payment_type\":\"qris\",\"signature_key\":\"8c26b8ede78305fd25dca78af1a85678bdbe0d09db581cf62e79cbe3625f2adf0f0a0e5a674059986647171fccd7fbc8b0c705d3a50d22ca2a7183a59234892a\",\"transaction_status\":\"settlement\",\"fraud_status\":\"accept\",\"status_message\":\"Success, transaction is found\",\"merchant_id\":\"G801903777\",\"transaction_type\":\"on-us\",\"issuer\":\"gopay\",\"acquirer\":\"gopay\",\"transaction_time\":\"2025-07-05 20:22:38\",\"settlement_time\":\"2025-07-05 20:22:53\",\"expiry_time\":\"2025-07-05 20:37:38\"}', '2025-07-05', 'b8f2b8e91d583fc10e1522a8b34fd9b3530a171552c6164b0344a96b269b3425', '{\"kode\":\"TAG-31681-75\",\"jumlah\":\"5000\",\"user_id\":\"75\"}', 'BILL_125_75_1751721750', '2025-07-05 20:23:00', '896ebcf0-5d70-4772-ac05-6781b248def6', 'BILL_125_75_1751721750', NULL, NULL, 0, 0, 0.00, NULL, 0, NULL, NULL, NULL, 0),
(379, 126, 75, 'menunggu_konfirmasi', NULL, NULL, 'bukti_379_1751720623.jpg', '2025-07-05 20:03:43', '{\"status_code\":\"200\",\"transaction_id\":\"ffa093e9-2315-4662-b442-1eeb3bb74dc5\",\"gross_amount\":\"7000.00\",\"currency\":\"IDR\",\"order_id\":\"BILL_126_75_1751720583\",\"payment_type\":\"echannel\",\"signature_key\":\"b8802936b61fad882d7bbfdf5366483ec577ccc3827ad520de70387a96a6a879f67566ea8ba3c0ae840701099ac9de9b68f6247158faf63fc905fd4569325df6\",\"transaction_status\":\"settlement\",\"fraud_status\":\"accept\",\"status_message\":\"Success, transaction is found\",\"merchant_id\":\"G801903777\",\"bill_key\":\"134609474718\",\"biller_code\":\"70012\",\"transaction_time\":\"2025-07-05 20:03:07\",\"settlement_time\":\"2025-07-05 20:03:22\",\"expiry_time\":\"2025-07-06 20:03:06\"}', '2025-07-05', 'eb97ae39b03a9fda232b0e3159202e05966d56865e09084c556449f07c9345cc', '{\"kode\":\"TAG-46338-75\",\"jumlah\":\"7000\",\"user_id\":\"75\"}', 'BILL_126_75_1751720583', '2025-07-05 20:03:30', 'ffa093e9-2315-4662-b442-1eeb3bb74dc5', 'BILL_126_75_1751720583', 'BILL_TAG-46338-75_75_1751711249', NULL, 0, 0, 0.00, NULL, 0, NULL, NULL, NULL, 0),
(380, 127, 75, '', NULL, NULL, NULL, NULL, NULL, '2025-07-05', '705f6dbef11a479a2d478409764cbe8feba85da1d67b2006d87f64d0ff914443', '{\"kode\":\"TAG-53245-75\",\"jumlah\":\"9000\",\"user_id\":\"75\"}', 'd0f655bb-7089-45ec-a079-124f3d2211ae', NULL, NULL, NULL, 'BILL_TAG-53245-75_75_1751711441', NULL, 0, 0, 0.00, NULL, 0, NULL, NULL, NULL, 0),
(381, 128, 75, 'menunggu_konfirmasi', NULL, NULL, 'bukti_381_1751725973.jpg', '2025-07-05 21:32:53', '{\"status_code\":\"200\",\"transaction_id\":\"73e31f11-f307-41fe-bdfb-e1d2d4aafaf3\",\"gross_amount\":\"34000.00\",\"currency\":\"IDR\",\"order_id\":\"BILL_128_75_1751725911\",\"payment_type\":\"qris\",\"signature_key\":\"92d10bc6d0c2b8564eb16ca6788fcc47f4191723bb603de7f4011e5b191e08ecd998c5428853c8bac6923e918cb5c029b1137f1f5a0048d51062926dfdc1c019\",\"transaction_status\":\"settlement\",\"fraud_status\":\"accept\",\"status_message\":\"Success, transaction is found\",\"merchant_id\":\"G801903777\",\"transaction_type\":\"on-us\",\"issuer\":\"gopay\",\"acquirer\":\"gopay\",\"transaction_time\":\"2025-07-05 21:31:57\",\"settlement_time\":\"2025-07-05 21:32:26\",\"expiry_time\":\"2025-07-05 21:46:57\"}', '2025-07-05', '7bb2e35e59fdef7087f614afe907754b8d6e8c35e9b4450c2d5ad3a92022119f', '{\"kode\":\"TAG-28873-75\",\"jumlah\":\"34000\",\"user_id\":\"75\"}', 'BILL_128_75_1751725911', '2025-07-05 21:32:30', '73e31f11-f307-41fe-bdfb-e1d2d4aafaf3', 'BILL_128_75_1751725911', NULL, NULL, 0, 0, 0.00, NULL, 0, NULL, NULL, NULL, 0),
(382, 129, 75, '', NULL, NULL, NULL, NULL, NULL, '2025-07-05', 'b61e77b83110549fdcee97a5e33579a2709b4abe1c71b9d6d993aa93babb26d6', '{\"kode\":\"TAG-43124-75\",\"jumlah\":\"55000\",\"user_id\":\"75\"}', '70f06749-490d-4ff3-bc3d-325a92e20758', NULL, NULL, NULL, NULL, NULL, 0, 0, 0.00, NULL, 0, NULL, NULL, NULL, 0),
(383, 130, 75, '', NULL, NULL, NULL, NULL, NULL, '2025-07-05', '14da4c7eeee63a426fa977bc0b6ad72661e3fa29d6644d844a7017246d955ad3', '{\"kode\":\"TAG-39646-75\",\"jumlah\":\"17500\",\"user_id\":\"75\"}', '23aa306e-e2ff-4606-8c1f-21c2c71b11da', '2025-07-05 19:31:38', NULL, NULL, 'BILL_TAG-39646-75_75_1751718667', NULL, 0, 0, 0.00, NULL, 0, NULL, NULL, NULL, 0),
(384, 131, 75, '', NULL, NULL, NULL, NULL, NULL, '2025-07-05', '98337c435544720754bdcb5a4359491e32f18ad8bc3c7f1c6fcbdcabd8118153', '{\"kode\":\"TAG-57664-75\",\"jumlah\":\"13000\",\"user_id\":\"75\"}', 'fb2f443d-9b19-4b49-9d2f-d26038779243', '2025-07-05 19:32:19', NULL, NULL, 'BILL_TAG-57664-75_75_1751718713', NULL, 0, 0, 0.00, NULL, 0, NULL, NULL, NULL, 0),
(385, 132, 75, '', NULL, NULL, NULL, NULL, NULL, '2025-07-05', '676700c96a1d6a65e9892f7b41cc5dcec8f241df0ae23212faaaee7a1593300d', '{\"kode\":\"TAG-30294-75\",\"jumlah\":\"23000\",\"user_id\":\"75\"}', '37edfcec-2865-4ec9-8a4b-a8be9d55aa64', '2025-07-05 19:33:49', NULL, NULL, 'BILL_TAG-30294-75_75_1751718788', NULL, 0, 0, 0.00, NULL, 0, NULL, NULL, NULL, 0),
(386, 133, 75, 'menunggu_konfirmasi', NULL, NULL, 'bukti_386_1751762979.jpg', '2025-07-06 07:49:39', '{\"status_code\":\"200\",\"transaction_id\":\"92b83234-5641-42a7-8c96-3a256e03ac22\",\"gross_amount\":\"88998.00\",\"currency\":\"IDR\",\"order_id\":\"BILL_133_75_1751762940\",\"payment_type\":\"bank_transfer\",\"signature_key\":\"4eecf861f6412ab32fefb06ef917c12602c2a394251f322332ae6b21cbb552b7950556c39c37f1b63f118116b1f7ae525dac6821fa87ce2ecd2694ecb945b39c\",\"transaction_status\":\"settlement\",\"fraud_status\":\"accept\",\"status_message\":\"Success, transaction is found\",\"merchant_id\":\"G801903777\",\"va_numbers\":[{\"bank\":\"bca\",\"va_number\":\"03777953644238757766860\"}],\"payment_amounts\":[],\"transaction_time\":\"2025-07-06 07:49:04\",\"settlement_time\":\"2025-07-06 07:49:13\",\"expiry_time\":\"2025-07-07 07:49:04\"}', '2025-07-05', '775af81f24c2d04c86e18f4c6818a3e939c33cf492731d737f65d4809e1d2909', '{\"kode\":\"TAG-89976-75\",\"jumlah\":\"88998\",\"user_id\":\"75\"}', 'BILL_133_75_1751762940', '2025-07-06 07:49:23', '92b83234-5641-42a7-8c96-3a256e03ac22', 'BILL_133_75_1751762940', 'BILL_TAG-89976-75_75_1751762767', NULL, 0, 0, 0.00, NULL, 0, NULL, NULL, NULL, 0),
(387, 134, 75, 'menunggu_konfirmasi', NULL, NULL, 'bukti_387_1751766027.jpg', '2025-07-06 08:40:27', '{\"status_code\":\"200\",\"transaction_id\":\"615dae37-0caa-41d8-97d5-266345a1a28f\",\"gross_amount\":\"34499.00\",\"currency\":\"IDR\",\"order_id\":\"BILL_134_75_1751765974\",\"payment_type\":\"bank_transfer\",\"signature_key\":\"4f17abe5ac07047040a1752d774ea09a9765c3e7bd463d2f820a004e0a96761d308d4a7bfaaf1d94dcc1db0e35715b17373700d76147a24f61cfed1285e15ed9\",\"transaction_status\":\"settlement\",\"fraud_status\":\"accept\",\"status_message\":\"Success, transaction is found\",\"merchant_id\":\"G801903777\",\"va_numbers\":[{\"bank\":\"bca\",\"va_number\":\"03777734667650072157730\"}],\"payment_amounts\":[],\"transaction_time\":\"2025-07-06 08:39:36\",\"settlement_time\":\"2025-07-06 08:39:46\",\"expiry_time\":\"2025-07-07 08:39:36\"}', '2025-07-05', 'ebb77d25fe10b13f6a3e426fd6fe362ed465b2c5d2bd1acbe445d925f5d028d3', '{\"kode\":\"TAG-80603-75\",\"jumlah\":\"34499\",\"user_id\":\"75\"}', 'BILL_134_75_1751765974', '2025-07-06 08:39:51', '615dae37-0caa-41d8-97d5-266345a1a28f', 'BILL_134_75_1751765974', 'BILL_TAG-80603-75_75_1751762827', NULL, 0, 0, 0.00, NULL, 0, NULL, NULL, NULL, 0),
(388, 135, 75, 'menunggu_pembayaran', NULL, NULL, NULL, NULL, NULL, '2025-07-05', '83029ab76e40bee3c97833f115b025052d65443c6f2d5bcae6f015a3b8984551', '{\"kode\":\"TAG-91031-75\",\"jumlah\":\"34499\",\"user_id\":\"75\"}', 'BILL_135_75_1751767924', NULL, NULL, 'BILL_135_75_1751767924', NULL, NULL, 0, 0, 0.00, NULL, 0, NULL, NULL, NULL, 0);

--
-- Triggers `user_bills`
--
DELIMITER $$
CREATE TRIGGER `tr_check_deadline_on_upload` BEFORE UPDATE ON `user_bills` FOR EACH ROW BEGIN
    IF NEW.bukti_pembayaran IS NOT NULL AND OLD.bukti_pembayaran IS NULL THEN
        -- Set tanggal_upload jika belum ada
        IF NEW.tanggal_upload IS NULL THEN
            SET NEW.tanggal_upload = NOW();
        END IF;
        
        -- Check deadline
        SET @tenggat = (SELECT tenggat_waktu FROM bills WHERE id = NEW.bill_id);
        IF @tenggat IS NOT NULL THEN
            SET NEW.is_ontime = CASE 
                WHEN DATE(NEW.tanggal_upload) <= @tenggat THEN 1 
                ELSE 0 
            END;
        END IF;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_user_bills_with_deadline`
-- (See below for the actual view)
--
CREATE TABLE `v_user_bills_with_deadline` (
`id` smallint(5) unsigned
,`bill_id` smallint(5) unsigned
,`user_id` smallint(5) unsigned
,`status` enum('menunggu_pembayaran','menunggu_konfirmasi','konfirmasi','tolak')
,`bukti_pembayaran` varchar(255)
,`tanggal_upload` datetime
,`is_ontime` tinyint(1)
,`kode_tagihan` varchar(50)
,`deskripsi` text
,`jumlah` mediumint(8) unsigned
,`tanggal_tagihan` date
,`waktu_mulai` date
,`tenggat_waktu` date
,`username` varchar(20)
,`status_ketepatan` varchar(17)
,`selisih_hari` int(7)
);

-- --------------------------------------------------------

--
-- Structure for view `v_user_bills_with_deadline`
--
DROP TABLE IF EXISTS `v_user_bills_with_deadline`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_user_bills_with_deadline`  AS SELECT `ub`.`id` AS `id`, `ub`.`bill_id` AS `bill_id`, `ub`.`user_id` AS `user_id`, `ub`.`status` AS `status`, `ub`.`bukti_pembayaran` AS `bukti_pembayaran`, `ub`.`tanggal_upload` AS `tanggal_upload`, `ub`.`is_ontime` AS `is_ontime`, `b`.`kode_tagihan` AS `kode_tagihan`, `b`.`deskripsi` AS `deskripsi`, `b`.`jumlah` AS `jumlah`, `b`.`tanggal` AS `tanggal_tagihan`, `b`.`waktu_mulai` AS `waktu_mulai`, `b`.`tenggat_waktu` AS `tenggat_waktu`, `u`.`username` AS `username`, CASE WHEN `ub`.`tanggal_upload` is null THEN 'Belum Upload' WHEN `b`.`tenggat_waktu` is null THEN 'Tidak Ada Tenggat' WHEN cast(`ub`.`tanggal_upload` as date) <= `b`.`tenggat_waktu` THEN 'Tepat Waktu' ELSE 'Terlambat' END AS `status_ketepatan`, CASE WHEN `ub`.`tanggal_upload` is null AND `b`.`tenggat_waktu` is not null THEN to_days(`b`.`tenggat_waktu`) - to_days(curdate()) WHEN `ub`.`tanggal_upload` is not null AND `b`.`tenggat_waktu` is not null THEN to_days(`b`.`tenggat_waktu`) - to_days(cast(`ub`.`tanggal_upload` as date)) ELSE NULL END AS `selisih_hari` FROM ((`user_bills` `ub` join `bills` `b` on(`ub`.`bill_id` = `b`.`id`)) join `users` `u` on(`ub`.`user_id` = `u`.`id`)) ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `anggota_keluarga`
--
ALTER TABLE `anggota_keluarga`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pendataan_id` (`pendataan_id`);

--
-- Indexes for table `bills`
--
ALTER TABLE `bills`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_id` (`admin_id`),
  ADD KEY `idx_bills_tenggat` (`tenggat_waktu`);

--
-- Indexes for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_recipient` (`recipient_id`),
  ADD KEY `idx_private` (`is_private`),
  ADD KEY `idx_chat_messages_unread` (`is_admin`,`is_read`,`user_id`,`created_at`);

--
-- Indexes for table `chat_online_users`
--
ALTER TABLE `chat_online_users`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `income`
--
ALTER TABLE `income`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `kegiatan`
--
ALTER TABLE `kegiatan`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `keluaran`
--
ALTER TABLE `keluaran`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tanggal` (`tanggal`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `nomor_kk`
--
ALTER TABLE `nomor_kk`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `no_kk` (`no_kk`);

--
-- Indexes for table `pendataan`
--
ALTER TABLE `pendataan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD UNIQUE KEY `nik` (`nik`),
  ADD KEY `fk_no_kk` (`no_kk`);

--
-- Indexes for table `tagihan_oke`
--
ALTER TABLE `tagihan_oke`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_bill_id` (`user_bill_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `no_kk` (`no_kk`);

--
-- Indexes for table `user_bills`
--
ALTER TABLE `user_bills`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `qr_code_hash` (`qr_code_hash`),
  ADD UNIQUE KEY `payment_token` (`payment_token`),
  ADD KEY `bill_id` (`bill_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_user_bills_qr_hash` (`qr_code_hash`),
  ADD KEY `idx_user_bills_payment_token` (`payment_token`),
  ADD KEY `idx_user_bills_upload_date` (`tanggal_upload`),
  ADD KEY `idx_user_bills_ontime` (`is_ontime`),
  ADD KEY `idx_midtrans_transaction_id` (`midtrans_transaction_id`),
  ADD KEY `idx_midtrans_order_id` (`midtrans_order_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `anggota_keluarga`
--
ALTER TABLE `anggota_keluarga`
  MODIFY `id` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT for table `bills`
--
ALTER TABLE `bills`
  MODIFY `id` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=136;

--
-- AUTO_INCREMENT for table `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `income`
--
ALTER TABLE `income`
  MODIFY `id` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `kegiatan`
--
ALTER TABLE `kegiatan`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `keluaran`
--
ALTER TABLE `keluaran`
  MODIFY `id` smallint(6) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `nomor_kk`
--
ALTER TABLE `nomor_kk`
  MODIFY `id` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `pendataan`
--
ALTER TABLE `pendataan`
  MODIFY `id` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=75;

--
-- AUTO_INCREMENT for table `tagihan_oke`
--
ALTER TABLE `tagihan_oke`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=76;

--
-- AUTO_INCREMENT for table `user_bills`
--
ALTER TABLE `user_bills`
  MODIFY `id` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=389;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `anggota_keluarga`
--
ALTER TABLE `anggota_keluarga`
  ADD CONSTRAINT `anggota_keluarga_ibfk_1` FOREIGN KEY (`pendataan_id`) REFERENCES `pendataan` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `pendataan`
--
ALTER TABLE `pendataan`
  ADD CONSTRAINT `fk_no_kk` FOREIGN KEY (`no_kk`) REFERENCES `nomor_kk` (`no_kk`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `pendataan_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tagihan_oke`
--
ALTER TABLE `tagihan_oke`
  ADD CONSTRAINT `tagihan_oke_ibfk_1` FOREIGN KEY (`user_bill_id`) REFERENCES `user_bills` (`id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`no_kk`) REFERENCES `nomor_kk` (`no_kk`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
