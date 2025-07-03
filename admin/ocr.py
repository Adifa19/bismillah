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
    # Normalisasi karakter yang sering salah dibaca
    replacements = {
        'oo': '00', 'O': '0', 'o': '0',
        'S': '5', 'l': '1', 'I': '1',
        'B': '8', 'G': '6', 'Z': '2',
        'Q': '0', 'D': '0',
        # Perbaikan untuk mata uang
        'Rp.': 'Rp', 'RP': 'Rp', 'rp': 'Rp'
    }
    
    processed = text
    for old, new in replacements.items():
        processed = processed.replace(old, new)
    
    return processed

def extract_amount(text):
    """Ekstrak jumlah uang dari teks dengan berbagai pattern"""
    patterns = [
        # Pattern untuk Rupiah dengan berbagai format
        r'Rp\.?\s*(\d{1,3}(?:[.,]\d{3})*(?:[.,]\d{2})?)',
        r'(?:JUMLAH|NOMINAL|TOTAL|BAYAR|TRANSFER)\s*:?\s*Rp\.?\s*(\d{1,3}(?:[.,]\d{3})*(?:[.,]\d{2})?)',
        r'(?:JUMLAH|NOMINAL|TOTAL|BAYAR|TRANSFER)\s*:?\s*(\d{1,3}(?:[.,]\d{3})*(?:[.,]\d{2})?)',
        # Pattern untuk angka besar tanpa prefix
        r'(\d{1,3}(?:[.,]\d{3}){2,})',  # Minimal 3 digit grup (misal: 100.000)
        r'(\d{6,})',  # Minimal 6 digit berturut-turut
        # Pattern dengan separator spasi
        r'(\d{1,3}(?:\s\d{3})*)',
    ]
    
    for pattern in patterns:
        matches = re.findall(pattern, text, re.IGNORECASE)
        for match in matches:
            # Bersihkan dan konversi ke integer
            clean_amount = re.sub(r'[^\d]', '', match)
            if clean_amount and len(clean_amount) >= 4:  # Minimal 4 digit
                try:
                    amount = int(clean_amount)
                    if amount >= 1000:  # Minimal 1000 rupiah
                        logger.info(f"Amount found: {amount} from pattern: {pattern}")
                        return amount
                except ValueError:
                    continue
    
    return None

def extract_date(text):
    """Ekstrak tanggal dari teks dengan berbagai format"""
    patterns = [
        # Format DD/MM/YYYY atau DD-MM-YYYY
        r'(\d{1,2}[-/]\d{1,2}[-/]\d{4})',
        r'(\d{1,2}[-/]\d{1,2}[-/]\d{2})',
        
        # Format Indonesia: DD Bulan YYYY
        r'(\d{1,2}\s+(?:Januari|Februari|Maret|April|Mei|Juni|Juli|Agustus|September|Oktober|November|Desember)\s+\d{4})',
        r'(\d{1,2}\s+(?:Jan|Feb|Mar|Apr|Mei|Jun|Jul|Ags|Sep|Okt|Nov|Des)\s+\d{4})',
        
        # Format dengan kata kunci
        r'(?:TANGGAL|DATE|TGL|WAKTU|TRANSFER|BAYAR|PEMBAYARAN)\s*:?\s*(\d{1,2}[-/]\d{1,2}[-/]\d{2,4})',
        r'(?:TANGGAL|DATE|TGL|WAKTU|TRANSFER|BAYAR|PEMBAYARAN)\s*:?\s*(\d{1,2}\s+\w+\s+\d{4})',
        
        # Format timestamp bank
        r'(\d{2}[-/]\d{2}[-/]\d{4}\s+\d{2}:\d{2})',
        r'(\d{4}[-/]\d{2}[-/]\d{2})',
        
        # Format dengan spasi
        r'(\d{1,2}\s+\d{1,2}\s+\d{4})',
    ]
    
    for pattern in patterns:
        matches = re.findall(pattern, text, re.IGNORECASE)
        for match in matches:
            # Validasi dan normalize tanggal
            normalized_date = normalize_date(match)
            if normalized_date:
                logger.info(f"Date found: {normalized_date} from pattern: {pattern}")
                return normalized_date
    
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
    if re.match(r'\d{1,2}[-/]\d{1,2}[-/]\d{2,4}', date_str):
        parts = re.split(r'[-/]', date_str)
        if len(parts) == 3:
            day, month, year = parts
            # Konversi tahun 2 digit ke 4 digit
            if len(year) == 2:
                year = '20' + year if int(year) <= 30 else '19' + year
            
            try:
                # Validasi tanggal
                datetime.strptime(f"{year}-{month.zfill(2)}-{day.zfill(2)}", "%Y-%m-%d")
                return f"{year}-{month.zfill(2)}-{day.zfill(2)}"
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
    if re.match(r'\d{4}[-/]\d{1,2}[-/]\d{1,2}', date_str):
        parts = re.split(r'[-/]', date_str)
        if len(parts) == 3:
            year, month, day = parts
            try:
                datetime.strptime(f"{year}-{month.zfill(2)}-{day.zfill(2)}", "%Y-%m-%d")
                return f"{year}-{month.zfill(2)}-{day.zfill(2)}"
            except ValueError:
                pass
    
    return None

def extract_code(text, expected_code=""):
    """Ekstrak kode tagihan dari teks"""
    patterns = [
        # Pattern untuk kode tagihan umum
        r'(TAG[-\s]?\d{6,10}[-\s]?\d{1,3})',
        r'([A-Z]{2,4}[-\s]?\d{6,10})',
        r'([A-Z]+\d{6,})',
        
        # Pattern dengan kata kunci
        r'(?:KODE|CODE|REF|REFERENSI|TAG|TAGIHAN)\s*:?\s*([A-Z0-9\-_]{6,})',
        
        # Pattern berdasarkan expected_code jika ada
        r'([A-Z]{2,4}\d{6,})',
        r'(\d{6,}[A-Z]{2,})',
    ]
    
    # Jika ada expected_code, tambahkan pattern khusus
    if expected_code:
        prefix = expected_code[:3] if len(expected_code) > 3 else expected_code
        patterns.insert(0, rf'({re.escape(prefix)}[-\s]?\d+[-\s]?\d*)')
    
    for pattern in patterns:
        matches = re.findall(pattern, text, re.IGNORECASE)
        for match in matches:
            clean_code = re.sub(r'\s+', '', match).upper()
            if len(clean_code) >= 6:  # Minimal 6 karakter
                logger.info(f"Code found: {clean_code} from pattern: {pattern}")
                return clean_code
    
    return None

def calculate_confidence(results):
    """Hitung confidence score berdasarkan hasil OCR"""
    total_confidence = 0
    count = 0
    
    for result in results:
        if len(result) >= 3:  # Format: [bbox, text, confidence]
            confidence = result[2]
            text_length = len(result[1].strip())
            
            # Bobot berdasarkan panjang teks
            weight = min(text_length / 10, 1.0)
            total_confidence += confidence * weight
            count += weight
    
    return (total_confidence / count * 100) if count > 0 else 0

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
        
        # Jalankan OCR dengan parameter yang disesuaikan
        results = reader.readtext(
            image_path,
            detail=1,
            paragraph=False,
            width_ths=0.7,
            height_ths=0.7,
            adjust_contrast=0.5,
            filter_ths=0.1
        )
        
        # Gabungkan semua teks yang terdeteksi
        raw_text = ' '.join([res[1] for res in results if len(res[1].strip()) > 1])
        
        # Preprocessing teks
        processed_text = preprocess_text(raw_text)
        
        # Hitung confidence
        confidence = calculate_confidence(results)
        
        # Ekstrak informasi
        extracted_amount = extract_amount(processed_text)
        extracted_date = extract_date(processed_text)
        extracted_code = extract_code(processed_text, expected_code)
        
        # Log hasil untuk debugging
        logger.info(f"Raw text: {raw_text}")
        logger.info(f"Processed text: {processed_text}")
        logger.info(f"Extracted amount: {extracted_amount}")
        logger.info(f"Extracted date: {extracted_date}")
        logger.info(f"Extracted code: {extracted_code}")
        logger.info(f"Confidence: {confidence}")
        
        # Format output sesuai yang diharapkan PHP
        output = {
            "extracted_text": raw_text,
            "normalized_text": processed_text,
            "jumlah": extracted_amount,
            "tanggal": extracted_date,
            "kode_tagihan": extracted_code,
            "confidence": confidence,
            "results_count": len(results)
        }
        
        # Output dalam format yang diharapkan PHP
        print(f"AMOUNT:{extracted_amount or 0}")
        print(f"DATE:{extracted_date or ''}")
        print(f"CODE:{extracted_code or ''}")
        print(f"CONFIDENCE:{confidence}")
        print(f"TEXT:{raw_text}")
        
        # Juga output JSON untuk debugging
        print(f"JSON:{json.dumps(output, ensure_ascii=False)}")
        
    except Exception as e:
        logger.error(f"OCR Error: {str(e)}")
        print(json.dumps({"error": str(e)}))
        sys.exit(1)

if __name__ == "__main__":
    main()
