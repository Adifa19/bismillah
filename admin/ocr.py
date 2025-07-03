import easyocr
import sys
import os
import re
import json
from datetime import datetime

def extract_amount(text):
    """Ekstrak jumlah uang dari teks dengan berbagai format"""
    # Bersihkan teks
    normalized = text.replace('oo', '00').replace('O', '0').replace('o', '0')
    normalized = normalized.replace(',', '.')
    
    # Pattern untuk mencari jumlah uang
    patterns = [
        r'(?:nominal|jumlah|total|bayar|rp\.?)\s*:?\s*([\d.,]+)',
        r'rp\.?\s*([\d.,]+)',
        r'(\d{1,3}(?:\.\d{3})*(?:,\d{2})?)',  # Format Indonesia: 123.456,00
        r'(\d{1,3}(?:,\d{3})*(?:\.\d{2})?)',  # Format US: 123,456.00
        r'(\d{4,})',  # Angka 4+ digit
    ]
    
    found_amounts = []
    for pattern in patterns:
        matches = re.findall(pattern, normalized, re.IGNORECASE)
        for match in matches:
            # Bersihkan dan konversi
            clean_match = re.sub(r'[^\d.,]', '', match)
            
            # Coba parsing sebagai format Indonesia (123.456,00)
            if ',' in clean_match and '.' in clean_match:
                # Format: 123.456,00
                amount_str = clean_match.replace('.', '').replace(',', '.')
            elif '.' in clean_match:
                # Jika ada titik, cek apakah ribuan atau desimal
                parts = clean_match.split('.')
                if len(parts) == 2 and len(parts[1]) <= 2:
                    # Kemungkinan desimal: 123.45
                    amount_str = clean_match
                else:
                    # Kemungkinan ribuan: 123.456
                    amount_str = clean_match.replace('.', '')
            else:
                amount_str = clean_match
            
            try:
                amount = float(amount_str)
                if 1000 <= amount <= 100000000:  # Range wajar untuk tagihan
                    found_amounts.append(int(amount))
            except ValueError:
                continue
    
    return max(found_amounts) if found_amounts else 0

def extract_date(text):
    """Ekstrak tanggal dari teks dengan berbagai format"""
    # Pattern untuk tanggal
    date_patterns = [
        r'(?:tanggal|date|tgl|waktu|transfer|pembayaran|bayar)\s*:?\s*(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})',
        r'(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})',
        r'(\d{1,2}\s+(?:jan|feb|mar|apr|mei|jun|jul|ags|sep|okt|nov|des)\w*\s+\d{2,4})',
        r'(\d{4}[\/\-\.]\d{1,2}[\/\-\.]\d{1,2})',
    ]
    
    found_dates = []
    for pattern in date_patterns:
        matches = re.findall(pattern, text, re.IGNORECASE)
        for match in matches:
            # Validasi dan normalisasi tanggal
            try:
                # Coba parsing dengan berbagai format
                date_formats = [
                    '%d/%m/%Y', '%d-%m-%Y', '%d.%m.%Y',
                    '%d/%m/%y', '%d-%m-%y', '%d.%m.%y',
                    '%Y/%m/%d', '%Y-%m-%d', '%Y.%m.%d',
                ]
                
                for fmt in date_formats:
                    try:
                        parsed_date = datetime.strptime(match, fmt)
                        # Konversi tahun 2 digit
                        if parsed_date.year < 100:
                            if parsed_date.year < 50:
                                parsed_date = parsed_date.replace(year=parsed_date.year + 2000)
                            else:
                                parsed_date = parsed_date.replace(year=parsed_date.year + 1900)
                        
                        # Validasi range tahun
                        current_year = datetime.now().year
                        if current_year - 2 <= parsed_date.year <= current_year + 1:
                            found_dates.append(parsed_date.strftime('%Y-%m-%d'))
                            break
                    except ValueError:
                        continue
            except:
                continue
    
    return found_dates[0] if found_dates else ''

def extract_code(text, expected_code=''):
    """Ekstrak kode tagihan dari teks"""
    # Jika ada expected code, cari yang mirip
    if expected_code:
        # Cari kode yang dimulai dengan 3 karakter pertama expected code
        prefix = expected_code[:3].upper()
        pattern = rf'({re.escape(prefix)}[\-\s]?[\dA-Z]+)'
        match = re.search(pattern, text.upper())
        if match:
            return match.group(1).replace(' ', '').replace('-', '')
    
    # Pattern umum untuk kode tagihan
    code_patterns = [
        r'(TAG[\-\s]?\d{6,10}[\-\s]?\d{1,3})',
        r'([A-Z]{2,3}[\-\s]?\d{3,8})',
        r'([A-Z]+\d{3,})',
        r'(REF[\-\s]?[\dA-Z]+)',
        r'(BILL[\-\s]?[\dA-Z]+)',
    ]
    
    for pattern in code_patterns:
        match = re.search(pattern, text.upper())
        if match:
            return match.group(1).replace(' ', '').replace('-', '')
    
    return ''

def calculate_confidence(results):
    """Hitung confidence score berdasarkan hasil OCR"""
    if not results:
        return 0
    
    total_confidence = sum([res[2] for res in results])
    avg_confidence = total_confidence / len(results)
    
    # Faktor tambahan berdasarkan kualitas teks
    text_quality = 0
    total_text = ' '.join([res[1] for res in results])
    
    # Semakin banyak teks yang terbaca, semakin tinggi confidence
    if len(total_text) > 50:
        text_quality += 20
    elif len(total_text) > 20:
        text_quality += 10
    
    # Bonus jika ada kata kunci yang relevan
    keywords = ['transfer', 'bayar', 'pembayaran', 'nominal', 'jumlah', 'rp', 'rupiah']
    for keyword in keywords:
        if keyword in total_text.lower():
            text_quality += 5
    
    final_confidence = min(100, avg_confidence * 100 + text_quality)
    return round(final_confidence, 2)

def main():
    # Validasi argumen
    if len(sys.argv) < 2:
        print("ERROR: No image path provided")
        sys.exit(1)
    
    image_path = sys.argv[1]
    expected_code = sys.argv[2] if len(sys.argv) > 2 else ''
    expected_amount = int(sys.argv[3]) if len(sys.argv) > 3 and sys.argv[3].isdigit() else 0
    
    # Validasi file
    if not os.path.exists(image_path):
        print("ERROR: File not found")
        sys.exit(1)
    
    try:
        # Inisialisasi EasyOCR
        reader = easyocr.Reader(['id', 'en'], gpu=False)
        
        # Jalankan OCR
        results = reader.readtext(image_path)
        
        if not results:
            print("ERROR: No text detected")
            sys.exit(1)
        
        # Gabungkan semua teks
        all_text = ' '.join([res[1] for res in results if len(res[1].strip()) > 1])
        
        # Ekstrak informasi
        extracted_amount = extract_amount(all_text)
        extracted_date = extract_date(all_text)
        extracted_code = extract_code(all_text, expected_code)
        confidence = calculate_confidence(results)
        
        # Output dalam format yang diharapkan PHP
        print(f"EXTRACTED_TEXT: {all_text}")
        print(f"AMOUNT: {extracted_amount}")
        print(f"DATE: {extracted_date}")
        print(f"CODE: {extracted_code}")
        print(f"CONFIDENCE: {confidence}")
        
        # Debug info (akan diabaikan PHP jika tidak diperlukan)
        print(f"DEBUG_EXPECTED_AMOUNT: {expected_amount}")
        print(f"DEBUG_EXPECTED_CODE: {expected_code}")
        print(f"DEBUG_AMOUNT_MATCH: {extracted_amount == expected_amount}")
        print(f"DEBUG_CODE_MATCH: {extracted_code.upper() == expected_code.upper() if expected_code else 'No expected code'}")
        
    except Exception as e:
        print(f"ERROR: {str(e)}")
        sys.exit(1)

if __name__ == "__main__":
    main()
