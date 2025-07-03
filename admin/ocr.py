import easyocr
import sys
import os
import re
import json
from datetime import datetime
import logging

# Setup logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

def preprocess_text(text):
    """Preprocessing teks untuk memperbaiki kesalahan OCR umum"""
    # Normalisasi karakter yang sering salah dibaca OCR
    replacements = {
        # Hindari replacement yang terlalu agresif untuk angka
        # 'oo': '00', 'O': '0', 'o': '0',  # Hapus ini karena terlalu agresif
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

def extract_amount(text):
    """Ekstrak jumlah uang dari teks dengan berbagai pattern yang lebih akurat"""
    # Log text yang akan diproses
    logger.info(f"Extracting amount from: {text}")
    
    patterns = [
        # Pattern untuk Rupiah dengan format yang lebih spesifik
        r'Rp\.?\s*(\d{1,3}(?:[.,]\d{3})*(?:[.,]\d{2})?)',
        r'(?:JUMLAH|NOMINAL|TOTAL|BAYAR|TRANSFER|NILAI|AMOUNT)\s*:?\s*Rp\.?\s*(\d{1,3}(?:[.,]\d{3})*(?:[.,]\d{2})?)',
        r'(?:JUMLAH|NOMINAL|TOTAL|BAYAR|TRANSFER|NILAI|AMOUNT)\s*:?\s*(\d{1,3}(?:[.,]\d{3})*(?:[.,]\d{2})?)',
        
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
        r'(?:transfer|bayar|pembayaran|setoran)\s*:?\s*Rp\.?\s*(\d{1,3}(?:[.,\s]\d{3})*)',
        r'(?:transfer|bayar|pembayaran|setoran)\s*:?\s*(\d{1,3}(?:[.,\s]\d{3})*)',
    ]
    
    found_amounts = []
    
    for pattern in patterns:
        matches = re.findall(pattern, text, re.IGNORECASE)
        for match in matches:
            # Bersihkan dan konversi ke integer
            clean_amount = re.sub(r'[^\d]', '', match)
            if clean_amount and len(clean_amount) >= 3:  # Minimal 3 digit (100)
                try:
                    amount = int(clean_amount)
                    if amount >= 100:  # Minimal 100 rupiah
                        found_amounts.append(amount)
                        logger.info(f"Amount found: {amount} from match: {match} with pattern: {pattern}")
                except ValueError:
                    continue
    
    # Jika ada beberapa amount, prioritaskan yang masuk akal
    if found_amounts:
        # Urutkan dan ambil yang paling masuk akal
        found_amounts.sort(reverse=True)
        
        # Filter amount yang terlalu kecil atau terlalu besar
        valid_amounts = [amt for amt in found_amounts if 100 <= amt <= 100000000]
        
        if valid_amounts:
            # Jika ada expected amount, cari yang paling dekat
            selected_amount = valid_amounts[0]
            logger.info(f"Selected amount: {selected_amount} from candidates: {valid_amounts}")
            return selected_amount
    
    logger.info("No valid amount found")
    return None

def extract_date(text):
    """Ekstrak tanggal dari teks dengan berbagai format yang lebih akurat"""
    logger.info(f"Extracting date from: {text}")
    
    patterns = [
        # Format DD/MM/YYYY atau DD-MM-YYYY
        r'(\d{1,2}[-/]\d{1,2}[-/]\d{4})',
        r'(\d{1,2}[-/]\d{1,2}[-/]\d{2})',
        
        # Format Indonesia: DD Bulan YYYY
        r'(\d{1,2}\s+(?:Januari|Februari|Maret|April|Mei|Juni|Juli|Agustus|September|Oktober|November|Desember)\s+\d{4})',
        r'(\d{1,2}\s+(?:Jan|Feb|Mar|Apr|Mei|Jun|Jul|Ags|Sep|Okt|Nov|Des)\s+\d{4})',
        
        # Format dengan kata kunci
        r'(?:TANGGAL|DATE|TGL|WAKTU|TRANSFER|BAYAR|PEMBAYARAN|WAKTU\s+TRANSFER)\s*:?\s*(\d{1,2}[-/]\d{1,2}[-/]\d{2,4})',
        r'(?:TANGGAL|DATE|TGL|WAKTU|TRANSFER|BAYAR|PEMBAYARAN)\s*:?\s*(\d{1,2}\s+\w+\s+\d{4})',
        
        # Format timestamp bank
        r'(\d{2}[-/]\d{2}[-/]\d{4}\s+\d{2}:\d{2})',
        r'(\d{4}[-/]\d{2}[-/]\d{2})',
        
        # Format dengan spasi
        r'(\d{1,2}\s+\d{1,2}\s+\d{4})',
        
        # Format khusus untuk receipt/struk
        r'(?:TGL|TANGGAL|DATE)\s*:?\s*(\d{1,2}[-/\.]\d{1,2}[-/\.]\d{2,4})',
        r'(\d{1,2}[-/\.]\d{1,2}[-/\.]\d{2,4})\s+\d{2}:\d{2}',
    ]
    
    found_dates = []
    
    for pattern in patterns:
        matches = re.findall(pattern, text, re.IGNORECASE)
        for match in matches:
            # Validasi dan normalize tanggal
            normalized_date = normalize_date(match)
            if normalized_date:
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
    return None

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

def extract_code(text, expected_code=""):
    """Ekstrak kode tagihan dari teks dengan akurasi lebih tinggi"""
    logger.info(f"Extracting code from: {text}, expected: {expected_code}")
    
    patterns = [
        # Pattern untuk kode tagihan umum
        r'(?:KODE|CODE|REF|REFERENSI|TAG|TAGIHAN|INVOICE|NO\.?\s*REF)\s*:?\s*([A-Z0-9\-_]{4,15})',
        r'([A-Z]{2,4}[-\s]?\d{4,10})',
        r'([A-Z]+\d{4,})',
        r'(TAG[-\s]?\d{4,10})',
        r'(\d{4,}[-\s]?[A-Z]{2,})',
        
        # Pattern berdasarkan format umum kode tagihan
        r'([A-Z]{2,4}\d{4,}[A-Z0-9]*)',
        r'([A-Z0-9]{6,15})',
    ]
    
    # Jika ada expected_code, tambahkan pattern khusus
    if expected_code:
        prefix = expected_code[:3] if len(expected_code) > 3 else expected_code
        patterns.insert(0, rf'({re.escape(prefix)}[-\s]?\w+)')
        patterns.insert(0, rf'({re.escape(expected_code)})')
    
    found_codes = []
    
    for pattern in patterns:
        matches = re.findall(pattern, text, re.IGNORECASE)
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
    return None

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
        print(json.dumps({"error": "No image path provided."}))
        sys.exit(1)
    
    image_path = sys.argv[1]
    expected_code = sys.argv[2] if len(sys.argv) > 2 else ""
    expected_amount = int(sys.argv[3]) if len(sys.argv) > 3 and sys.argv[3].isdigit() else 0
    
    if not os.path.exists(image_path):
        print(json.dumps({"error": "File not found."}))
        sys.exit(1)
    
    try:
        # Inisialisasi EasyOCR dengan parameter yang lebih baik
        reader = easyocr.Reader(['id', 'en'], gpu=False)
        
        # Jalankan OCR dengan parameter yang disesuaikan untuk akurasi lebih tinggi
        results = reader.readtext(
            image_path,
            detail=1,
            paragraph=False,
            width_ths=0.8,      # Threshold untuk menggabungkan teks secara horizontal
            height_ths=0.8,     # Threshold untuk menggabungkan teks secara vertikal
            adjust_contrast=0.3, # Penyesuaian kontras yang lebih halus
            filter_ths=0.2,     # Filter untuk menghilangkan teks dengan confidence rendah
            text_threshold=0.5,  # Threshold untuk deteksi teks
            low_text=0.3,       # Threshold untuk deteksi teks dengan confidence rendah
            link_threshold=0.3   # Threshold untuk menggabungkan karakter
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
        extracted_amount = extract_amount(processed_text)
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
        print(f"AMOUNT:{extracted_amount or 0}")
        print(f"DATE:{extracted_date or ''}")
        print(f"CODE:{extracted_code or ''}")
        print(f"CONFIDENCE:{confidence}")
        print(f"TEXT:{raw_text}")
        
        # Format output JSON untuk debugging
        output = {
            "extracted_text": raw_text,
            "normalized_text": processed_text,
            "jumlah": extracted_amount or 0,
            "tanggal": extracted_date or '',
            "kode_tagihan": extracted_code or '',
            "confidence": confidence,
            "results_count": len(results),
            "expected_code": expected_code,
            "expected_amount": expected_amount
        }
        
        print(f"JSON:{json.dumps(output, ensure_ascii=False)}")
        
    except Exception as e:
        logger.error(f"OCR Error: {str(e)}")
        print(json.dumps({"error": str(e)}))
        sys.exit(1)

if __name__ == "__main__":
    main()
