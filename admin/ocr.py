import easyocr
import sys
import os
import re
import json
from datetime import datetime

def extract_amount(text):
    """Ekstrak jumlah uang dari teks"""
    # Normalisasi teks
    normalized = text.replace('oo', '00').replace('O', '0').replace('o', '0')
    normalized = normalized.replace(',', '.')
    
    # Pattern untuk mencari jumlah uang
    patterns = [
        r'(?:nominal|jumlah|total|bayar|rp\.?)\s*:?\s*([\d\.,]+)',
        r'rp\.?\s*([\d\.,]+)',
        r'(\d{1,3}(?:[.,]\d{3})*(?:[.,]\d{2})?)',
        r'(\d+\.?\d*)',
        r'(\d+,?\d*)'
    ]
    
    amounts = []
    for pattern in patterns:
        matches = re.findall(pattern, normalized, re.IGNORECASE)
        for match in matches:
            # Bersihkan dan konversi ke integer
            clean_amount = re.sub(r'[^\d.,]', '', match)
            # Hapus titik sebagai pemisah ribuan, ganti koma dengan titik
            clean_amount = clean_amount.replace('.', '').replace(',', '.')
            try:
                amount = float(clean_amount)
                if amount > 1000:  # Filter jumlah yang masuk akal
                    amounts.append(int(amount))
            except ValueError:
                continue
    
    # Kembalikan jumlah terbesar yang ditemukan
    return max(amounts) if amounts else 0

def extract_date(text):
    """Ekstrak tanggal dari teks"""
    # Pattern untuk berbagai format tanggal
    patterns = [
        r'(?:tanggal|date|tgl|waktu|transfer|pembayaran|bayar)\s*:?\s*(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})',
        r'(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})',
        r'(\d{1,2}\s+(?:januari|februari|maret|april|mei|juni|juli|agustus|september|oktober|november|desember)\s+\d{2,4})',
        r'(\d{1,2}\s+(?:jan|feb|mar|apr|mei|jun|jul|ags|sep|okt|nov|des)\s+\d{2,4})',
        r'(\d{4}[\/\-\.]\d{1,2}[\/\-\.]\d{1,2})',
        r'(\d{8})'  # Format DDMMYYYY
    ]
    
    for pattern in patterns:
        matches = re.findall(pattern, text, re.IGNORECASE)
        for match in matches:
            # Normalisasi format tanggal
            normalized_date = normalize_date(match)
            if normalized_date:
                return normalized_date
    
    return None

def normalize_date(date_str):
    """Normalisasi format tanggal"""
    # Mapping bulan Indonesia ke angka
    month_map = {
        'januari': '01', 'februari': '02', 'maret': '03', 'april': '04',
        'mei': '05', 'juni': '06', 'juli': '07', 'agustus': '08',
        'september': '09', 'oktober': '10', 'november': '11', 'desember': '12',
        'jan': '01', 'feb': '02', 'mar': '03', 'apr': '04',
        'mei': '05', 'jun': '06', 'jul': '07', 'ags': '08',
        'sep': '09', 'okt': '10', 'nov': '11', 'des': '12'
    }
    
    date_str = date_str.lower().strip()
    
    # Format Indonesia: 15 Januari 2024
    for month_name, month_num in month_map.items():
        if month_name in date_str:
            parts = date_str.split()
            if len(parts) >= 3:
                day = parts[0].zfill(2)
                year = parts[2]
                if len(year) == 2:
                    year = '20' + year
                return f"{year}-{month_num}-{day}"
    
    # Format DD/MM/YYYY atau DD-MM-YYYY
    if re.match(r'\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4}', date_str):
        parts = re.split(r'[\/\-\.]', date_str)
        if len(parts) == 3:
            day = parts[0].zfill(2)
            month = parts[1].zfill(2)
            year = parts[2]
            if len(year) == 2:
                year = '20' + year
            return f"{year}-{month}-{day}"
    
    # Format YYYY-MM-DD
    if re.match(r'\d{4}[\/\-\.]\d{1,2}[\/\-\.]\d{1,2}', date_str):
        parts = re.split(r'[\/\-\.]', date_str)
        if len(parts) == 3:
            year = parts[0]
            month = parts[1].zfill(2)
            day = parts[2].zfill(2)
            return f"{year}-{month}-{day}"
    
    # Format DDMMYYYY
    if len(date_str) == 8 and date_str.isdigit():
        day = date_str[:2]
        month = date_str[2:4]
        year = date_str[4:]
        return f"{year}-{month}-{day}"
    
    return None

def extract_code(text, expected_code=''):
    """Ekstrak kode tagihan dari teks"""
    # Jika ada expected code, cari berdasarkan pattern yang mirip
    if expected_code:
        # Cari pattern yang mirip dengan expected code
        prefix = expected_code[:3]
        pattern = f'{prefix}[-\\s]?\\d{{3,6}}[-\\s]?\\d{{1,3}}'
        match = re.search(pattern, text, re.IGNORECASE)
        if match:
            return match.group(0).replace(' ', '').upper()
    
    # Pattern umum untuk kode tagihan
    patterns = [
        r'([A-Z]{2,3}[\-_]?\d{3,6}[\-_]?\d{1,3})',
        r'(TAG[\-_]?\d{3,6}[\-_]?\d{1,3})',
        r'([A-Z]+\d+)',
        r'(TAG\d+)'
    ]
    
    for pattern in patterns:
        matches = re.findall(pattern, text, re.IGNORECASE)
        for match in matches:
            return match.upper().replace(' ', '')
    
    return None

def calculate_confidence(results):
    """Hitung confidence score berdasarkan hasil OCR"""
    if not results:
        return 0
    
    total_confidence = 0
    total_chars = 0
    
    for result in results:
        confidence = result[2]  # EasyOCR confidence
        text_length = len(result[1])
        total_confidence += confidence * text_length
        total_chars += text_length
    
    return (total_confidence / total_chars * 100) if total_chars > 0 else 0

def main():
    # Ambil argumen
    if len(sys.argv) < 2:
        print("ERROR: No image path provided")
        sys.exit(1)
    
    image_path = sys.argv[1]
    expected_code = sys.argv[2] if len(sys.argv) > 2 else ''
    expected_amount = int(sys.argv[3]) if len(sys.argv) > 3 and sys.argv[3].isdigit() else 0
    
    # Cek file exists
    if not os.path.exists(image_path):
        print("ERROR: File not found")
        sys.exit(1)
    
    try:
        # Inisialisasi EasyOCR
        reader = easyocr.Reader(['id', 'en'], gpu=False)
        
        # Jalankan OCR
        results = reader.readtext(image_path)
        
        # Gabungkan semua teks
        all_text = ' '.join([res[1] for res in results if len(res[1].strip()) > 1])
        
        # Ekstrak data
        extracted_amount = extract_amount(all_text)
        extracted_date = extract_date(all_text)
        extracted_code = extract_code(all_text, expected_code)
        confidence = calculate_confidence(results)
        
        # Output dengan format yang diharapkan PHP
        print(f"AMOUNT:{extracted_amount}")
        print(f"DATE:{extracted_date or ''}")
        print(f"CODE:{extracted_code or ''}")
        print(f"CONFIDENCE:{confidence:.2f}")
        print(f"TEXT:{all_text}")
        
    except Exception as e:
        print(f"ERROR: {str(e)}")
        sys.exit(1)

if __name__ == "__main__":
    main()
