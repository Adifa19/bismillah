-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 30, 2025 at 02:21 AM
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
-- Database: `bismillah`
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

--
-- Dumping data for table `income`
--


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
(1, 'admin', '$2y$10$a2I8rmKxr.HsPJ6j8.EzHebwZ80/jACz/98mLSAz6tdvboK4Bqk.C', 'admin', 'Aktif', NULL, 0, '2025-05-31 10:00:18', NULL);
-- --------------------------------------------------------

--
-- Table structure for table `user_bills`
--

CREATE TABLE `user_bills` (
  `id` smallint(5) UNSIGNED NOT NULL,
  `bill_id` smallint(5) UNSIGNED NOT NULL,
  `user_id` smallint(5) UNSIGNED NOT NULL,
  `status` enum('menunggu_pembayaran','menunggu_konfirmasi','konfirmasi','tolak') NOT NULL DEFAULT 'menunggu_pembayaran',
  `bukti_pembayaran` varchar(255) DEFAULT NULL,
  `tanggal_upload` datetime DEFAULT NULL,
  `tanggal` date NOT NULL,
  `qr_code_hash` varchar(64) DEFAULT NULL,
  `qr_code_data` text DEFAULT NULL,
  `payment_token` varchar(32) DEFAULT NULL,
  `ocr_jumlah` int(11) DEFAULT NULL,
  `ocr_kode_found` tinyint(1) DEFAULT 0,
  `ocr_date_found` tinyint(1) DEFAULT 0,
  `ocr_confidence` decimal(5,2) DEFAULT 0.00,
  `ocr_details` text DEFAULT NULL,
  `is_terlambat` tinyint(1) DEFAULT 0,
  `selisih_hari` int(11) DEFAULT NULL,
  `composite_image` varchar(255) DEFAULT NULL,
  `is_ontime` tinyint(1) DEFAULT NULL COMMENT '1=tepat waktu, 0=terlambat, NULL=belum upload'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_bills`
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
  ADD KEY `idx_user_bills_ontime` (`is_ontime`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `anggota_keluarga`
--
ALTER TABLE `anggota_keluarga`
  MODIFY `id` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `bills`
--
ALTER TABLE `bills`
  MODIFY `id` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=115;

--
-- AUTO_INCREMENT for table `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `income`
--
ALTER TABLE `income`
  MODIFY `id` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `kegiatan`
--
ALTER TABLE `kegiatan`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `keluaran`
--
ALTER TABLE `keluaran`
  MODIFY `id` smallint(6) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `nomor_kk`
--
ALTER TABLE `nomor_kk`
  MODIFY `id` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT for table `pendataan`
--
ALTER TABLE `pendataan`
  MODIFY `id` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=74;

--
-- AUTO_INCREMENT for table `tagihan_oke`
--
ALTER TABLE `tagihan_oke`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=74;

--
-- AUTO_INCREMENT for table `user_bills`
--
ALTER TABLE `user_bills`
  MODIFY `id` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=368;

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
