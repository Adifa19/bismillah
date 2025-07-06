<?php
require_once '../config.php';
requireLogin();
?>
<!DOCTYPE html>
<html lang="id">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>FAQ & Pusat Bantuan - Portal Warga</title>
    <style>
      * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
      }

      body {
        font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
        line-height: 1.6;
        color: #333;
        background: white;
        min-height: 100vh;
      }

      .container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
      }

      .header {
        text-align: center;
        margin-bottom: 40px;
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 20px;
        padding: 30px;
        color: white;
      }

      .header h1 {
        font-size: 2.5em;
        margin-bottom: 10px;
        text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
      }

      .header p {
        font-size: 1.1em;
        opacity: 0.9;
      }

      .search-box {
        background: rgba(255, 255, 255, 0.95);
        border-radius: 15px;
        padding: 20px;
        margin-bottom: 30px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
      }

      .search-input {
        width: 100%;
        padding: 15px 20px;
        border: 2px solid #e0e0e0;
        border-radius: 10px;
        font-size: 16px;
        outline: none;
        transition: all 0.3s ease;
      }

      .search-input:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
      }

      .faq-categories {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
        gap: 25px;
        margin-bottom: 40px;
      }

      .category {
        background: rgba(255, 255, 255, 0.95);
        border-radius: 15px;
        padding: 25px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
        border: 1px solid rgba(255, 255, 255, 0.2);
      }

      .category:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 45px rgba(0, 0, 0, 0.15);
      }

      .category-header {
        display: flex;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 2px solid #f0f0f0;
      }

      .category-icon {
        width: 50px;
        height: 50px;
        background: linear-gradient(135deg, #667eea, #764ba2);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 15px;
        font-size: 24px;
        color: white;
      }

      .category-title {
        font-size: 1.4em;
        font-weight: 600;
        color: #333;
      }

      .faq-item {
        margin-bottom: 15px;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        overflow: hidden;
        transition: all 0.3s ease;
      }

      .faq-item:hover {
        border-color: #667eea;
      }

      .faq-question {
        background: #f8f9fa;
        padding: 15px 20px;
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-weight: 500;
        transition: all 0.3s ease;
      }

      .faq-question:hover {
        background: #e9ecef;
      }

      .faq-question.active {
        background: #667eea;
        color: white;
      }

      .faq-arrow {
        transition: transform 0.3s ease;
        font-size: 18px;
      }

      .faq-arrow.active {
        transform: rotate(180deg);
      }

      .faq-answer {
        padding: 0 20px;
        max-height: 0;
        overflow: hidden;
        transition: all 0.3s ease;
        background: white;
      }

      .faq-answer.active {
        padding: 20px;
        max-height: 500px;
      }

      .faq-answer ul {
        margin: 10px 0;
        padding-left: 20px;
      }

      .faq-answer li {
        margin: 5px 0;
      }

      .highlight {
        background: #fff3cd;
        padding: 2px 4px;
        border-radius: 4px;
      }

      @media (max-width: 768px) {
        .container {
          padding: 10px;
        }

        .faq-categories {
          grid-template-columns: 1fr;
        }
      }
      .page-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 2rem;
    border-radius: 16px;
    margin-bottom: 2rem;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    text-align: center;
}

.page-header h1 {
    color: #ffffff; /* Putih agar kontras di atas gradien biru */
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1rem;
}

.page-header p {
    color: #e0f2fe; /* Biru muda terang */
    font-size: 1.1rem;
}
    </style>
  </head>
  <body>
    <?php include('sidebar.php'); ?>   
    <div class="container">
      <div class="page-header">
                <h1>🏘️ FAQ & Pusat Bantuan</h1>
        <p>Temukan jawaban untuk semua pertanyaan Anda tentang layanan warga</p>
            </div>
      </div>

      <div class="search-box">
        <input
          type="text"
          class="search-input"
          placeholder="🔍 Cari pertanyaan atau kata kunci..."
          id="searchInput"
        />
      </div>

      <div class="faq-categories">
        <!-- Kategori Tagihan -->
        <div class="category">
          <div class="category-header">
            <div class="category-icon">💳</div>
            <h2 class="category-title">Tagihan & Pembayaran</h2>
          </div>

          <div class="faq-item">
            <div class="faq-question">
              Bagaimana cara mengirim bukti pembayaran sebelum tenggat waktu?
              <span class="faq-arrow">▼</span>
            </div>
            <div class="faq-answer">
              <p>Untuk mengirim bukti pembayaran:</p>
              <ul>
                <li>Masuk ke menu <strong>"Tagihan"</strong></li>
                <li>Pilih tagihan yang ingin dibayar</li>
                <li>Klik tombol <strong>"Upload Bukti Bayar"</strong></li>
                <li>Pilih file foto/dokumen bukti pembayaran</li>
                <li>Tambahkan keterangan jika diperlukan</li>
                <li>Klik <strong>"Kirim"</strong> untuk mengunggah</li>
              </ul>
              <p>
                <span class="highlight">Tips:</span> Pastikan foto bukti
                pembayaran jelas dan dapat dibaca. Format yang diterima: JPG,
                PNG, PDF (maksimal 5MB)
              </p>
            </div>
          </div>

          <div class="faq-item">
            <div class="faq-question">
              Bagaimana cara melihat riwayat pembayaran?
              <span class="faq-arrow">▼</span>
            </div>
            <div class="faq-answer">
              <p>Untuk melihat riwayat pembayaran:</p>
              <ul>
                <li>Buka menu <strong>"Riwayat Pembayaran"</strong></li>
                <li>Pilih periode waktu yang ingin dilihat</li>
                <li>
                  Gunakan filter berdasarkan:
                  <ul>
                    <li>Tanggal pembayaran</li>
                    <li>Jenis tagihan</li>
                    <li>Status pembayaran</li>
                    <li>Jumlah pembayaran</li>
                  </ul>
                </li>
                <li>
                  Klik <strong>"Terapkan Filter"</strong> untuk melihat hasil
                </li>
              </ul>
            </div>
          </div>

          <div class="faq-item">
            <div class="faq-question">
              Kapan tenggat waktu pembayaran tagihan?
              <span class="faq-arrow">▼</span>
            </div>
            <div class="faq-answer">
              <p>Tenggat waktu pembayaran:</p>
              <ul>
                <li><strong>Iuran Bulanan:</strong> Setiap tanggal 10</li>
                <li>
                  <strong>Tagihan Khusus:</strong> Sesuai yang tertera di
                  tagihan
                </li>
                <li><strong>Denda Keterlambatan:</strong> 2% per bulan</li>
              </ul>
              <p>
                Sistem akan mengirimkan notifikasi pengingat 3 hari sebelum
                tenggat waktu.
              </p>
            </div>
          </div>
        </div>

        <!-- Kategori Keuangan -->
        <div class="category">
          <div class="category-header">
            <div class="category-icon">📊</div>
            <h2 class="category-title">Data Keuangan</h2>
          </div>

          <div class="faq-item">
            <div class="faq-question">
              Bagaimana cara melihat data pemasukan dan pengeluaran?
              <span class="faq-arrow">▼</span>
            </div>
            <div class="faq-answer">
              <p>Untuk melihat data keuangan:</p>
              <ul>
                <li>Masuk ke menu <strong>"Laporan Keuangan"</strong></li>
                <li>
                  Pilih tab <strong>"Pemasukan"</strong> atau
                  <strong>"Pengeluaran"</strong>
                </li>
                <li>
                  Gunakan filter untuk:
                  <ul>
                    <li>Rentang tanggal</li>
                    <li>Kategori transaksi</li>
                    <li>Jumlah minimum/maksimum</li>
                    <li>Status verifikasi</li>
                  </ul>
                </li>
                <li>Data akan ditampilkan dalam bentuk tabel dan grafik</li>
              </ul>
            </div>
          </div>
        </div>

        <!-- Kategori Kegiatan -->
        <div class="category">
          <div class="category-header">
            <div class="category-icon">📅</div>
            <h2 class="category-title">Kegiatan & Acara</h2>
          </div>

          <div class="faq-item">
            <div class="faq-question">
              Bagaimana cara melihat kegiatan yang sudah selesai dan yang belum?
              <span class="faq-arrow">▼</span>
            </div>
            <div class="faq-answer">
              <p>Untuk melihat status kegiatan:</p>
              <ul>
                <li>Buka menu <strong>"Kegiatan"</strong></li>
                <li>
                  Akan muncul tabel dengan:
                  <ul>
                    <li>
                      <strong>"Akan Datang"</strong> - Kegiatan yang belum
                      dimulai
                    </li>
                    <li>
                      <strong>"Sedang Berlangsung"</strong> - Kegiatan aktif
                    </li>
                    </ul>
                </li>
              </ul>
            </div>
          </div>
          </div>
        <!-- Kategori Akun -->
        <div class="category">
          <div class="category-header">
            <div class="category-icon">👤</div>
            <h2 class="category-title">Akun & Profil</h2>
          </div>

          <div class="faq-item">
            <div class="faq-question">
              Bagaimana cara mengganti password?
              <span class="faq-arrow">▼</span>
            </div>
            <div class="faq-answer">
              <p>Untuk mengganti password:</p>
              <ul>
                <li>Masuk ke menu <strong>"Profil"</strong></li>
                <li>Klik <strong>"Keamanan"</strong></li>
                <li>Pilih <strong>"Ubah Password"</strong></li>
                <li>Masukkan password lama</li>
                <li>Masukkan password baru (minimal 8 karakter)</li>
                <li>Konfirmasi password baru</li>
                <li>Klik <strong>"Simpan"</strong></li>
              </ul>
              <p>
                <span class="highlight">Tips Keamanan:</span> Gunakan kombinasi
                huruf besar, kecil, angka, dan simbol untuk password yang kuat.
              </p>
            </div>
          </div>

          <div class="faq-item">
            <div class="faq-question">
              Bagaimana cara mengubah dan melihat profil?
              <span class="faq-arrow">▼</span>
            </div>
            <div class="faq-answer">
              <p>Untuk mengubah profil:</p>
              <ul>
                <li>Buka menu <strong>"Profil"</strong></li>
                <li>Klik <strong>"Edit Profil"</strong></li>
                <li>
                  Ubah informasi yang diperlukan:
                  <ul>
                    <li>Nama lengkap</li>
                    <li>Email</li>
                    <li>Nomor telepon</li>
                    <li>Alamat</li>
                    <li>Foto profil</li>
                  </ul>
                </li>
                <li>Klik <strong>"Simpan Perubahan"</strong></li>
              </ul>
              <p>
                Untuk melihat profil, cukup klik menu
                <strong>"Profil"</strong> dan semua informasi akan ditampilkan.
              </p>
            </div>
          </div>

          <div class="faq-item">
            <div class="faq-question">
              Informasi apa saja yang ditampilkan di halaman profil?
              <span class="faq-arrow">▼</span>
            </div>
            <div class="faq-answer">
              <p>Halaman profil menampilkan:</p>
              <ul>
                <li>
                  <strong>Informasi Pribadi:</strong> Nama, email, telepon,
                  alamat
                </li>
                <li>
                  <strong>Status Keanggotaan:</strong> Aktif/tidak aktif,
                  tanggal bergabung
                </li>
                <li>
                  <strong>Riwayat Aktivitas:</strong> Kegiatan yang diikuti,
                  pembayaran terbaru
                </li>
                <li>
                  <strong>Statistik:</strong> Total pembayaran, jumlah kegiatan
                  yang diikuti
                </li>
                <li>
                  <strong>Dokumen:</strong> Kartu Tanda Penduduk, Kartu Keluarga
                </li>
              </ul>
            </div>
          </div>
        </div>

        <!-- Kategori Bantuan -->
        <div class="category">
          <div class="category-header">
            <div class="category-icon">🆘</div>
            <h2 class="category-title">Bantuan & Dukungan</h2>
          </div>

          <div class="faq-item">
            <div class="faq-question">
              Bagaimana cara menghubungi admin?
              <span class="faq-arrow">▼</span>
            </div>
            <div class="faq-answer">
              <p>Anda dapat menghubungi admin melalui:</p>
              <ul>
                <li>
                  <strong>Chat Langsung:</strong> Klik bubble chat di pojok
                  kanan bawah
                </li>
                <li><strong>Email:</strong> tetangga.id@gmail.com</li>
                <li><strong>WhatsApp:</strong> +62 821-2543-2469</li>
              </ul>
              <p>
                Admin tersedia setiap hari Senin-Jumat pukul 08:00-17:00 WIB.
              </p>
            </div>
          </div>

          <div class="faq-item">
            <div class="faq-question">
              Apa yang harus dilakukan jika ada masalah teknis?
              <span class="faq-arrow">▼</span>
            </div>
            <div class="faq-answer">
              <p>Jika mengalami masalah teknis:</p>
              <ul>
                <li>Coba refresh halaman (F5)</li>
                <li>Bersihkan cache browser</li>
                <li>Coba menggunakan browser lain</li>
                <li>Pastikan koneksi internet stabil</li>
                <li>Jika masih bermasalah, hubungi admin melalui chat</li>
              </ul>
            </div>
          </div>

          <div class="faq-item">
            <div class="faq-question">
              Bagaimana cara memberikan masukan atau saran?
              <span class="faq-arrow">▼</span>
            </div>
            <div class="faq-answer">
              <p>Masukan dan saran sangat kami hargai:</p>
              <ul>
                <li>Gunakan fitur chat dengan admin</li>
                <li>Kirim email ke tetangga.id@gmail.com</li>
                <li>Isi form saran di menu "Kontak"</li>
                <li>Sampaikan saat rapat warga</li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>

    <script>
      // FAQ Toggle functionality
      document.querySelectorAll(".faq-question").forEach((question) => {
        question.addEventListener("click", () => {
          const answer = question.nextElementSibling;
          const arrow = question.querySelector(".faq-arrow");

          // Toggle active state
          question.classList.toggle("active");
          answer.classList.toggle("active");
          arrow.classList.toggle("active");
        });
      });

      // Search functionality
      document
        .getElementById("searchInput")
        .addEventListener("input", function (e) {
          const searchTerm = e.target.value.toLowerCase();
          const faqItems = document.querySelectorAll(".faq-item");

          faqItems.forEach((item) => {
            const question = item
              .querySelector(".faq-question")
              .textContent.toLowerCase();
            const answer = item
              .querySelector(".faq-answer")
              .textContent.toLowerCase();

            if (question.includes(searchTerm) || answer.includes(searchTerm)) {
              item.style.display = "block";
              // Highlight search term
              if (searchTerm) {
                const questionEl = item.querySelector(".faq-question");
                const originalText = questionEl.textContent;
                const highlightedText = originalText.replace(
                  new RegExp(searchTerm, "gi"),
                  (match) => `<span class="highlight">${match}</span>`
                );
                questionEl.innerHTML =
                  highlightedText + '<span class="faq-arrow">▼</span>';
              }
            } else {
              item.style.display = "none";
            }
          });
        });

      function sendMessage() {
        const input = document.getElementById("chatInput");
        const message = input.value.trim();

        if (message) {
          // Add user message
          const messagesContainer = document.getElementById("chatMessages");
          const userMessage = document.createElement("div");
          userMessage.className = "message user";
          userMessage.textContent = message;
          messagesContainer.appendChild(userMessage);

          // Clear input
          input.value = "";

          // Auto-scroll to bottom
          messagesContainer.scrollTop = messagesContainer.scrollHeight;

          // Simulate admin response
          setTimeout(() => {
            const adminMessage = document.createElement("div");
            adminMessage.className = "message admin";
            adminMessage.textContent =
              "Terima kasih atas pertanyaan Anda. Admin akan merespons dalam waktu singkat. 👍";
            messagesContainer.appendChild(adminMessage);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
          }, 1000);
        }
      }

      function handleEnter(event) {
        if (event.key === "Enter") {
          sendMessage();
        }
      }

      // Add smooth scrolling animation
      document.addEventListener("DOMContentLoaded", function () {
        const categories = document.querySelectorAll(".category");
        categories.forEach((category, index) => {
          category.style.animationDelay = `${index * 0.1}s`;
          category.style.animation = "fadeInUp 0.6s ease forwards";
        });
      });

      // Add CSS for animation
      const style = document.createElement("style");
      style.textContent = `
            @keyframes fadeInUp {
                from {
                    opacity: 0;
                    transform: translateY(30px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
        `;
      document.head.appendChild(style);
    </script>
  </body>
</html>
