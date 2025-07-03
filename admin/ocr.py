import easyocr
import sys
import os
import re
import json
from datetime import datetime
import logging
import cv2
import numpy as np
from PIL import Image, ImageEnhance

# Setup logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

def preprocess_image(image_path):
    """Preprocessing gambar untuk meningkatkan akurasi OCR"""
    try:
        # Baca gambar dengan OpenCV
        img = cv2.imread(image_path)
        if img is None:
            logger.error(f"Cannot read image: {image_path}")
            return image_path
        
        # Convert ke grayscale
        gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
        
        # Tingkatkan kontras
        clahe = cv2.createCLAHE(clipLimit=2.0, tileGridSize=(8,8))
        enhanced = clahe.apply(gray)
        
        # Noise reduction
        denoised = cv2.fastNlMeansDenoising(enhanced)
        
        # Threshold untuk binarisasi
        _, thresh = cv2.threshold(denoised, 0, 255, cv2.THRESH_BINARY + cv2.THRESH_OTSU)
        
        # Simpan gambar hasil preprocessing
        temp_path = image_path + '_processed.jpg'
        cv2.imwrite(temp_path, thresh)
        
        logger.info(f"Image preprocessed: {temp_path}")
        return temp_path
        
    except Exception as e:
        logger.error(f"Error preprocessing image: {str(e)}")
        return image_path

def preprocess_text(text):
    """Preprocessing teks untuk memperbaiki kesalahan OCR umum"""
    # Normalisasi karakter yang sering salah dibaca OCR
    replacements = {
        'S': '5', 'l': '1', 'I': '1',
        'B': '8', 'G': '6', 'Z': '2',
        'Q': '0', 'D': '0',
        # Perbaikan untuk mata uang
        'Rp.': 'Rp', 'RP': 'Rp', 'rp': 'Rp',
        'Rupiah': 'Rp'
    }
    
    processed = text
    # Hanya replace jika bukan bagian dari angka yang valid
    for old, new in replacements.items():
        # Jangan replace jika karakter adalah bagian dari angka
        processed = re.sub(r'(?<!\d)' + re.escape(old) + r'(?!\d)', new, processed)
    
    return processed

def extract_amount(text, expected_amount=0):
    """Ekstrak jumlah uang dari teks dengan berbagai pattern yang lebih akurat"""
    logger.info(f"Extracting amount from: {text[:200]}...")
    
    # Bersihkan teks terlebih dahulu
    clean_text = re.sub(r'\s+', ' ', text)
    
    patterns = [
        # Pattern untuk Rupiah dengan format yang lebih spesifik
        r'Rp\.?\s*(\d{1,3}(?:[.,]\d{3})*(?:[.,]\d{2})?)',
        r'(?:JUMLAH|NOMINAL|TOTAL|BAYAR|TRANSFER|NILAI|AMOUNT|SEBESAR)\s*:?\s*Rp\.?\s*(\d{1,3}(?:[.,]\d{3})*(?:[.,]\d{2})?)',
        r'(?:JUMLAH|NOMINAL|TOTAL|BAYAR|TRANSFER|NILAI|AMOUNT|SEBESAR)\s*:?\s*(\d{1,3}(?:[.,]\d{3})*(?:[.,]\d{2})?)',
        
        # Pattern untuk format dengan titik sebagai pemisah ribuan
        r'Rp\.?\s*(\d{1,3}(?:\.\d{3})+)',
        
        # Pattern untuk format dengan koma sebagai pemisah ribuan
        r'Rp\.?\s*(\d{1,3}(?:,\d{3})+)',
        
        # Pattern untuk angka besar dengan format Indonesia
        r'(\d{1,3}(?:\.\d{3}){1,})',  # 1.000, 10.000, 100.000, dst
        r'(\d{1,3}(?:,\d{3}){1,})',  # 1,000, 10,000, 100,000, dst
        
        # Pattern untuk angka minimal 4 digit (1000 ke atas)
        r'(\d{4,})',  # 1000, 10000, dst
        
        # Pattern dengan separator spasi
        r'(\d{1,3}(?:\s\d{3})+)',
        
        # Pattern untuk transfer/pembayaran
        r'(?:transfer|bayar|pembayaran|setoran|debit|kredit|saldo|mutasi)\s*:?\s*Rp\.?\s*(\d{1,3}(?:[.,\s]\d{3})*)',
        r'(?:transfer|bayar|pembayaran|setoran|debit|kredit|saldo|mutasi)\s*:?\s*(\d{1,3}(?:[.,\s]\d{3})*)',
        
        # Pattern untuk struk ATM/m-banking
        r'(?:nominal|jumlah|total|nilai)\s*:?\s*(\d{1,3}(?:[.,]\d{3})*)',
        r'(?:BERHASIL|SUCCESS|SUKSES)\s*.*?(\d{1,3}(?:[.,]\d{3})*)',
    ]
    
    found_amounts = []
    
    for pattern in patterns:
        matches = re.findall(pattern, clean_text, re.IGNORECASE)
        for match in matches:
            # Bersihkan dan konversi ke integer
            clean_amount = re.sub(r'[^\d]', '', match)
            if clean_amount and len(clean_amount) >= 3:  # Minimal 3 digit (100)
                try:
                    amount = int(clean_amount)
                    if 100 <= amount <= 100000000:  # Range wajar untuk tagihan
                        found_amounts.append(amount)
                        logger.info(f"Amount found: {amount} from match: {match} with pattern: {pattern}")
                except ValueError:
                    continue
    
    # Jika ada expected_amount, cari yang paling dekat
    if expected_amount > 0 and found_amounts:
        closest_amount = min(found_amounts, key=lambda x: abs(x - expected_amount))
        if abs(closest_amount - expected_amount) / expected_amount <= 0.1:  # Toleransi 10%
            logger.info(f"Found closest amount to expected: {closest_amount} (expected: {expected_amount})")
            return closest_amount
    
    # Jika tidak ada yang cocok dengan expected, ambil yang paling masuk akal
    if found_amounts:
        found_amounts.sort(reverse=True)
        valid_amounts = [amt for amt in found_amounts if 100 <= amt <= 100000000]
        
        if valid_amounts:
            selected_amount = valid_amounts[0]
            logger.info(f"Selected amount: {selected_amount} from candidates: {valid_amounts}")
            return selected_amount
    
    logger.info("No valid amount found")
    return 0

def extract_date(text):
    """Ekstrak tanggal dari teks dengan berbagai format yang lebih akurat"""
    logger.info(f"Extracting date from: {text[:200]}...")
    
    # Bersihkan teks
    clean_text = re.sub(r'\s+', ' ', text)
    
    patterns = [
        # Format DD/MM/YYYY atau DD-MM-YYYY
        r'(\d{1,2}[-/]\d{1,2}[-/]\d{4})',
        r'(\d{1,2}[-/]\d{1,2}[-/]\d{2})',
        
        # Format Indonesia: DD Bulan YYYY
        r'(\d{1,2}\s+(?:Januari|Februari|Maret|April|Mei|Juni|Juli|Agustus|September|Oktober|November|Desember)\s+\d{4})',
        r'(\d{1,2}\s+(?:Jan|Feb|Mar|Apr|Mei|Jun|Jul|Ags|Sep|Okt|Nov|Des)\s+\d{4})',
        
        # Format dengan kata kunci
        r'(?:TANGGAL|DATE|TGL|WAKTU|TRANSFER|BAYAR|PEMBAYARAN|WAKTU\s+TRANSFER|TRANSAKSI)\s*:?\s*(\d{1,2}[-/]\d{1,2}[-/]\d{2,4})',
        r'(?:TANGGAL|DATE|TGL|WAKTU|TRANSFER|BAYAR|PEMBAYARAN|TRANSAKSI)\s*:?\s*(\d{1,2}\s+\w+\s+\d{4})',
        
        # Format timestamp bank
        r'(\d{2}[-/]\d{2}[-/]\d{4})\s+\d{2}:\d{2}',
        r'(\d{4}[-/]\d{2}[-/]\d{2})',
        
        # Format dengan spasi
        r'(\d{1,2}\s+\d{1,2}\s+\d{4})',
        
        # Format khusus untuk receipt/struk
        r'(?:TGL|TANGGAL|DATE|WAKTU|TRANSFER|TRANSAKSI)\s*:?\s*(\d{1,2}[-/\.]\d{1,2}[-/\.]\d{2,4})',
        r'(\d{1,2}[-/\.]\d{1,2}[-/\.]\d{2,4})\s+\d{2}:\d{2}',
        
        # Format m-banking/internet banking
        r'(?:pada|tgl|tanggal|waktu|jam|pukul)\s*:?\s*(\d{1,2}[-/\.]\d{1,2}[-/\.]\d{2,4})',
        r'(\d{1,2}[-/\.]\d{1,2}[-/\.]\d{2,4})\s*(?:WIB|WITA|WIT)',
    ]
    
    found_dates = []
    
    for pattern in patterns:
        matches = re.findall(pattern, clean_text, re.IGNORECASE)
        for match in matches:
            # Validasi dan normalize tanggal
            normalized_date = normalize_date(match)
            if normalized_date and is_valid_date(normalized_date):
                found_dates.append(normalized_date)
                logger.info(f"Date found: {normalized_date} from match: {match} with pattern: {pattern}")
    
    # Jika ada beberapa tanggal, ambil yang paling masuk akal (paling baru tapi tidak masa depan)
    if found_dates:
        # Urutkan berdasarkan tanggal
        valid_dates = []
        today = datetime.now()
        
        for date_str in found_dates:
            try:
                date_obj = datetime.strptime(date_str, '%Y-%m-%d')
                # Hanya ambil tanggal yang tidak terlalu lama (max 1 tahun) dan tidak masa depan
                if date_obj <= today and (today - date_obj).days <= 365:
                    valid_dates.append((date_obj, date_str))
            except ValueError:
                continue
        
        if valid_dates:
            # Urutkan berdasarkan tanggal (terbaru dulu)
            valid_dates.sort(key=lambda x: x[0], reverse=True)
            selected_date = valid_dates[0][1]
            logger.info(f"Selected date: {selected_date}")
            return selected_date
    
    logger.info("No valid date found")
    return ""

def normalize_date(date_str):
    """Normalisasi format tanggal ke YYYY-MM-DD"""
    # Mapping bulan Indonesia ke nomor
    month_map = {
        'januari': '01', 'februari': '02', 'maret': '03', 'april': '04',
        'mei': '05', 'juni': '06', 'juli': '07', 'agustus': '08',
        'september': '09', 'oktober': '10', 'november': '11', 'desember': '12',
        'jan': '01', 'feb': '02', 'mar': '03', 'apr': '04',
        'mei': '05', 'jun': '06', 'jul': '07', 'ags': '08',
        'sep': '09', 'okt': '10', 'nov': '11', 'des': '12'
    }
    
    date_str = date_str.strip().lower()
    
    # Format DD/MM/YYYY atau DD-MM-YYYY
    if re.match(r'\d{1,2}[-/\.]\d{1,2}[-/\.]\d{2,4}', date_str):
        parts = re.split(r'[-/\.]', date_str)
        if len(parts) == 3:
            day, month, year = parts
            # Konversi tahun 2 digit ke 4 digit
            if len(year) == 2:
                year_int = int(year)
                current_year = datetime.now().year
                if year_int > 50:
                    year = str(1900 + year_int)
                else:
                    year = str(2000 + year_int)
            
            try:
                # Validasi tanggal
                datetime.strptime(f"{year}-{month.zfill(2)}-{day.zfill(2)}", "%Y-%m-%d")
                return f"{year}-{month.zfill(2)}-{day.zfill(2)}"
            except ValueError:
                # Coba format MM/DD/YYYY
                try:
                    datetime.strptime(f"{year}-{day.zfill(2)}-{month.zfill(2)}", "%Y-%m-%d")
                    return f"{year}-{day.zfill(2)}-{month.zfill(2)}"
                except ValueError:
                    pass
    
    # Format Indonesia: DD Bulan YYYY
    for indo_month, num_month in month_map.items():
        if indo_month in date_str:
            parts = date_str.split()
            if len(parts) >= 3:
                day = parts[0]
                year = parts[-1]
                try:
                    datetime.strptime(f"{year}-{num_month}-{day.zfill(2)}", "%Y-%m-%d")
                    return f"{year}-{num_month}-{day.zfill(2)}"
                except ValueError:
                    pass
    
    # Format YYYY-MM-DD
    if re.match(r'\d{4}[-/\.]\d{1,2}[-/\.]\d{1,2}', date_str):
        parts = re.split(r'[-/\.]', date_str)
        if len(parts) == 3:
            year, month, day = parts
            try:
                datetime.strptime(f"{year}-{month.zfill(2)}-{day.zfill(2)}", "%Y-%m-%d")
                return f"{year}-{month.zfill(2)}-{day.zfill(2)}"
            except ValueError:
                pass
    
    return None

def is_valid_date(date_str):
    """Validasi apakah tanggal valid dan masuk akal"""
    try:
        date_obj = datetime.strptime(date_str, '%Y-%m-%d')
        current_date = datetime.now()
        
        # Validasi range tahun (10 tahun ke belakang, 1 tahun ke depan)
        if date_obj.year < current_date.year - 10 or date_obj.year > current_date.year + 1:
            return False
        
        # Validasi tidak lebih dari hari ini
        if date_obj > current_date:
            return False
        
        return True
    except ValueError:
        return False

def extract_code(text, expected_code=""):
    """Ekstrak kode tagihan dari teks dengan akurasi lebih tinggi"""
    logger.info(f"Extracting code from: {text[:200]}..., expected: {expected_code}")
    
    # Bersihkan teks
    clean_text = re.sub(r'\s+', ' ', text)
    
    patterns = [
        # Pattern untuk kode tagihan umum
        r'(?:KODE|CODE|REF|REFERENSI|TAG|TAGIHAN|INVOICE|NO\.?\s*REF|KODE\s*TAGIHAN)\s*:?\s*([A-Z0-9\-_]{4,15})',
        r'([A-Z]{2,4}[-\s]?\d{4,10})',
        r'([A-Z]+\d{4,})',
        r'(TAG[-\s]?\d{4,10})',
        r'(\d{4,}[-\s]?[A-Z]{2,})',
        
        # Pattern berdasarkan format umum kode tagihan
        r'([A-Z]{2,4}\d{4,}[A-Z0-9]*)',
        r'([A-Z0-9]{6,15})',
        
        # Pattern untuk kode dengan format khusus
        r'([A-Z]{2,}[-_]\d{4,})',
        r'([A-Z]{2,}\d{4,}[A-Z]*)',
        
        # Pattern untuk nomor referensi
        r'(?:NO\.|NOMOR|NUMBER|REF|REFERENCE)\s*:?\s*([A-Z0-9\-_]{4,15})',
    ]
    
    # Jika ada expected_code, tambahkan pattern khusus
    if expected_code:
        prefix = expected_code[:3] if len(expected_code) > 3 else expected_code
        patterns.insert(0, rf'({re.escape(prefix)}[-\s]?\w+)')
        patterns.insert(0, rf'({re.escape(expected_code)})')
    
    found_codes = []
    
    for pattern in patterns:
        matches = re.findall(pattern, clean_text, re.IGNORECASE)
        for match in matches:
            clean_code = re.sub(r'\s+', '', match).upper()
            if len(clean_code) >= 4:  # Minimal 4 karakter
                # Filter kode yang tidak valid (terlalu banyak angka berurutan)
                if not re.match(r'^\d+$', clean_code):  # Bukan hanya angka
                    found_codes.append(clean_code)
                    logger.info(f"Code found: {clean_code} from match: {match} with pattern: {pattern}")
    
    # Jika ada expected_code, cari yang paling mirip
    if expected_code and found_codes:
        expected_upper = expected_code.upper()
        for code in found_codes:
            if code == expected_upper:
                logger.info(f"Exact match found: {code}")
                return code
            elif expected_upper in code or code in expected_upper:
                logger.info(f"Partial match found: {code}")
                return code
    
    # Jika tidak ada yang cocok, ambil yang pertama
    if found_codes:
        selected_code = found_codes[0]
        logger.info(f"Selected code: {selected_code}")
        return selected_code
    
    logger.info("No valid code found")
    return ""

def calculate_confidence(results):
    """Hitung confidence score berdasarkan hasil OCR"""
    if not results:
        return 0
    
    total_confidence = 0
    total_weight = 0
    
    for result in results:
        if len(result) >= 3:  # Format: [bbox, text, confidence]
            confidence = result[2]
            text_length = len(result[1].strip())
            
            # Bobot berdasarkan panjang teks (teks lebih panjang = lebih penting)
            weight = min(text_length / 5, 2.0)  # Max weight = 2.0
            total_confidence += confidence * weight
            total_weight += weight
    
    final_confidence = (total_confidence / total_weight) if total_weight > 0 else 0
    return round(final_confidence, 2)

def main():
    # Validasi argumen
    if len(sys.argv) < 2:
        print("ERROR: No image path provided.")
        sys.exit(1)
    
    image_path = sys.argv[1]
    expected_code = sys.argv[2] if len(sys.argv) > 2 else ""
    expected_amount = int(sys.argv[3]) if len(sys.argv) > 3 and sys.argv[3].isdigit() else 0
    
    if not os.path.exists(image_path):
        print("ERROR: File not found.")
        sys.exit(1)
    
    try:
        # Preprocessing gambar
        processed_image = preprocess_image(image_path)
        
        # Inisialisasi EasyOCR dengan parameter yang lebih baik
        reader = easyocr.Reader(['id', 'en'], gpu=False)
        
        # Jalankan OCR dengan parameter yang disesuaikan untuk akurasi lebih tinggi
        results = reader.readtext(
            processed_image,
            detail=1,
            paragraph=True,  # Ubah ke True untuk menggabungkan teks dalam paragraf
            width_ths=0.7,      # Threshold untuk menggabungkan teks secara horizontal
            height_ths=0.7,     # Threshold untuk menggabungkan teks secara vertikal
            adjust_contrast=0.5, # Penyesuaian kontras yang lebih agresif
            filter_ths=0.1,     # Filter untuk menghilangkan teks dengan confidence rendah
            text_threshold=0.6,  # Threshold untuk deteksi teks
            low_text=0.4,       # Threshold untuk deteksi teks dengan confidence rendah
            link_threshold=0.4   # Threshold untuk menggabungkan karakter
        )
        
        # Gabungkan semua teks yang terdeteksi
        all_texts = []
        for res in results:
            if len(res) >= 2 and len(res[1].strip()) > 0:
                all_texts.append(res[1].strip())
        
        raw_text = ' '.join(all_texts)
        
        # Preprocessing teks
        processed_text = preprocess_text(raw_text)
        
        # Hitung confidence
        confidence = calculate_confidence(results)
        
        # Ekstrak informasi
        extracted_amount = extract_amount(processed_text, expected_amount)
        extracted_date = extract_date(processed_text)
        extracted_code = extract_code(processed_text, expected_code)
        
        # Log hasil untuk debugging
        logger.info(f"Image: {image_path}")
        logger.info(f"Raw text: {raw_text}")
        logger.info(f"Processed text: {processed_text}")
        logger.info(f"Expected code: {expected_code}")
        logger.info(f"Expected amount: {expected_amount}")
        logger.info(f"Extracted amount: {extracted_amount}")
        logger.info(f"Extracted date: {extracted_date}")
        logger.info(f"Extracted code: {extracted_code}")
        logger.info(f"Confidence: {confidence}")
        
        # Output dalam format yang diharapkan PHP
        print(f"AMOUNT:{extracted_amount}")
        print(f"DATE:{extracted_date}")
        print(f"CODE:{extracted_code}")
        print(f"CONFIDENCE:{confidence}")
        print(f"TEXT:{raw_text}")
        
        # Hapus file temporary jika ada
        if processed_image != image_path and os.path.exists(processed_image):
            os.remove(processed_image)
        
    except Exception as e:
        logger.error(f"OCR Error: {str(e)}")
        print(f"ERROR: {str(e)}")
        sys.exit(1)

if __name__ == "__main__":
    main()
