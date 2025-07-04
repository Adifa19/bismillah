import cv2
import pytesseract
import re
import mysql.connector
from datetime import datetime
import json
import os
import sys
from PIL import Image
import numpy as np

# Konfigurasi database
DB_CONFIG = {
$host = 'localhost';
$dbname = 'tetangga_id';
$username = 'root';
$password = 'Dipa190503!@#';
}

# Konfigurasi path Tesseract (sesuaikan dengan instalasi)
# pytesseract.pytesseract.tesseract_cmd = r'C:\Program Files\Tesseract-OCR\tesseract.exe'

class OCRProcessor:
    def __init__(self):
        self.db = None
        self.connect_db()
    
    def connect_db(self):
        """Koneksi ke database"""
        try:
            self.db = mysql.connector.connect(**DB_CONFIG)
            print("Database connection established")
        except mysql.connector.Error as err:
            print(f"Database connection failed: {err}")
            sys.exit(1)
    
    def preprocess_image(self, image_path):
        """Preprocessing image untuk meningkatkan akurasi OCR"""
        # Baca gambar
        image = cv2.imread(image_path)
        if image is None:
            raise ValueError(f"Could not read image: {image_path}")
        
        # Convert ke grayscale
        gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
        
        # Noise reduction
        denoised = cv2.medianBlur(gray, 3)
        
        # Contrast enhancement
        clahe = cv2.createCLAHE(clipLimit=2.0, tileGridSize=(8,8))
        enhanced = clahe.apply(denoised)
        
        # Thresholding
        _, thresh = cv2.threshold(enhanced, 0, 255, cv2.THRESH_BINARY + cv2.THRESH_OTSU)
        
        return thresh
    
    def extract_text_from_image(self, image_path):
        """Ekstrak teks dari gambar menggunakan OCR"""
        try:
            # Preprocessing
            processed_image = self.preprocess_image(image_path)
            
            # OCR dengan konfigurasi khusus
            custom_config = r'--oem 3 --psm 6 -c tessedit_char_whitelist=0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz.,/-: '
            text = pytesseract.image_to_string(processed_image, config=custom_config)
            
            # OCR dengan confidence score
            data = pytesseract.image_to_data(processed_image, output_type=pytesseract.Output.DICT)
            confidences = [int(conf) for conf in data['conf'] if int(conf) > 0]
            avg_confidence = sum(confidences) / len(confidences) if confidences else 0
            
            return text, avg_confidence
            
        except Exception as e:
            print(f"OCR Error: {e}")
            return "", 0
    
    def extract_nominal(self, text):
        """Ekstrak nominal dari teks"""
        # Pattern untuk nominal (Rp, angka dengan titik/koma)
        patterns = [
            r'Rp\s*(\d{1,3}(?:[.,]\d{3})*(?:[.,]\d{2})?)',
            r'(\d{1,3}(?:[.,]\d{3})*(?:[.,]\d{2})?)\s*(?:Rp|rupiah)',
            r'(?:jumlah|nominal|total)\s*:?\s*Rp?\s*(\d{1,3}(?:[.,]\d{3})*(?:[.,]\d{2})?)',
            r'(\d{1,3}(?:[.,]\d{3})+)(?!\d)',  # Angka dengan pemisah ribuan
        ]
        
        for pattern in patterns:
            matches = re.findall(pattern, text, re.IGNORECASE)
            if matches:
                # Ambil nominal terbesar (biasanya yang paling relevan)
                nominals = []
                for match in matches:
                    # Bersihkan dan konversi ke integer
                    clean_number = re.sub(r'[.,]', '', match)
                    if clean_number.isdigit():
                        nominals.append(int(clean_number))
                
                if nominals:
                    return max(nominals)  # Return nominal terbesar
        
        return None
    
    def extract_date(self, text):
        """Ekstrak tanggal dari teks"""
        # Pattern untuk berbagai format tanggal
        patterns = [
            r'(\d{1,2})[/-](\d{1,2})[/-](\d{4})',  # DD/MM/YYYY atau DD-MM-YYYY
            r'(\d{4})[/-](\d{1,2})[/-](\d{1,2})',  # YYYY/MM/DD atau YYYY-MM-DD
            r'(\d{1,2})\s+(Jan|Feb|Mar|Apr|Mei|Jun|Jul|Aug|Sep|Okt|Nov|Des)\s+(\d{4})',  # DD Month YYYY
            r'(Jan|Feb|Mar|Apr|Mei|Jun|Jul|Aug|Sep|Okt|Nov|Des)\s+(\d{1,2}),?\s+(\d{4})',  # Month DD, YYYY
        ]
        
        month_map = {
            'Jan': '01', 'Feb': '02', 'Mar': '03', 'Apr': '04',
            'Mei': '05', 'Jun': '06', 'Jul': '07', 'Aug': '08',
            'Sep': '09', 'Okt': '10', 'Nov': '11', 'Des': '12'
        }
        
        for pattern in patterns:
            matches = re.findall(pattern, text, re.IGNORECASE)
            if matches:
                for match in matches:
                    try:
                        if len(match) == 3:
                            if match[1] in month_map:  # DD Month YYYY
                                day, month, year = match[0], month_map[match[1]], match[2]
                            elif match[0] in month_map:  # Month DD YYYY
                                day, month, year = match[1], month_map[match[0]], match[2]
                            else:
                                # Numeric format
                                if len(match[0]) == 4:  # YYYY-MM-DD
                                    year, month, day = match[0], match[1], match[2]
                                else:  # DD-MM-YYYY
                                    day, month, year = match[0], match[1], match[2]
                            
                            # Validasi dan format
                            day = day.zfill(2)
                            month = month.zfill(2)
                            
                            # Validasi tanggal
                            date_str = f"{year}-{month}-{day}"
                            datetime.strptime(date_str, '%Y-%m-%d')
                            return date_str
                            
                    except ValueError:
                        continue
        
        return None
    
    def extract_kode_tagihan(self, text, kode_tagihan):
        """Cek apakah kode tagihan ada dalam teks"""
        # Bersihkan teks dan kode tagihan
        text_clean = re.sub(r'[^a-zA-Z0-9]', '', text.upper())
        kode_clean = re.sub(r'[^a-zA-Z0-9]', '', kode_tagihan.upper())
        
        # Cek apakah kode tagihan ada dalam teks
        if kode_clean in text_clean:
            return True
        
        # Cek dengan toleransi 1-2 karakter berbeda
        if len(kode_clean) > 5:
            for i in range(len(text_clean) - len(kode_clean) + 1):
                substr = text_clean[i:i+len(kode_clean)]
                diff = sum(c1 != c2 for c1, c2 in zip(kode_clean, substr))
                if diff <= 2:  # Toleransi 2 karakter berbeda
                    return True
        
        return False
    
    def process_payment_proof(self, user_bill_id, image_path):
        """Proses bukti pembayaran dengan OCR"""
        try:
            # Ekstrak teks dari gambar
            text, confidence = self.extract_text_from_image(image_path)
            
            if not text.strip():
                print("No text extracted from image")
                return False
            
            # Ambil data tagihan dari database
            cursor = self.db.cursor()
            query = """
                SELECT ub.*, b.kode_tagihan, b.jumlah, b.tanggal 
                FROM user_bills ub 
                JOIN bills b ON ub.bill_id = b.id 
                WHERE ub.id = %s
            """
            cursor.execute(query, (user_bill_id,))
            bill_data = cursor.fetchone()
            
            if not bill_data:
                print(f"Bill data not found for user_bill_id: {user_bill_id}")
                return False
            
            # Ekstrak informasi dari OCR
            ocr_nominal = self.extract_nominal(text)
            ocr_date = self.extract_date(text)
            ocr_kode_found = self.extract_kode_tagihan(text, bill_data[10])  # kode_tagihan
            
            # Hitung confidence berdasarkan hasil ekstraksi
            extraction_confidence = confidence
            if ocr_nominal and ocr_nominal == bill_data[11]:  # jumlah
                extraction_confidence += 20
            if ocr_kode_found:
                extraction_confidence += 30
            if ocr_date:
                extraction_confidence += 25
            
            extraction_confidence = min(extraction_confidence, 100)
            
            # Simpan hasil OCR ke database
            ocr_details = {
                'extracted_text': text[:1000],  # Batasi panjang teks
                'extracted_nominal': ocr_nominal,
                'extracted_date': ocr_date,
                'expected_nominal': bill_data[11],
                'expected_kode': bill_data[10],
                'processing_time': datetime.now().isoformat()
            }
            
            update_query = """
                UPDATE user_bills 
                SET ocr_jumlah = %s, 
                    ocr_kode_found = %s, 
                    ocr_date_found = %s, 
                    ocr_confidence = %s,
                    ocr_details = %s
                WHERE id = %s
            """
            
            cursor.execute(update_query, (
                ocr_nominal,
                1 if ocr_kode_found else 0,
                1 if ocr_date else 0,
                extraction_confidence,
                json.dumps(ocr_details),
                user_bill_id
            ))
            
            self.db.commit()
            cursor.close()
            
            print(f"OCR processing completed for user_bill_id: {user_bill_id}")
            print(f"Extracted - Nominal: {ocr_nominal}, Date: {ocr_date}, Kode Found: {ocr_kode_found}")
            print(f"Confidence: {extraction_confidence:.2f}%")
            
            return True
            
        except Exception as e:
            print(f"Error processing payment proof: {e}")
            return False
    
    def process_pending_uploads(self):
        """Proses semua upload yang belum diproses OCR"""
        try:
            cursor = self.db.cursor()
            query = """
                SELECT id, bukti_pembayaran 
                FROM user_bills 
                WHERE status = 'menunggu_konfirmasi' 
                AND bukti_pembayaran IS NOT NULL 
                AND ocr_confidence = 0.00
            """
            cursor.execute(query)
            pending_uploads = cursor.fetchall()
            
            for upload in pending_uploads:
                user_bill_id = upload[0]
                image_filename = upload[1]
                image_path = f"../warga/uploads/bukti_pembayaran/{image_filename}"
                
                if os.path.exists(image_path):
                    print(f"Processing: {image_path}")
                    self.process_payment_proof(user_bill_id, image_path)
                else:
                    print(f"Image not found: {image_path}")
            
            cursor.close()
            
        except Exception as e:
            print(f"Error processing pending uploads: {e}")
    
    def __del__(self):
        """Destruktor untuk menutup koneksi database"""
        if self.db:
            self.db.close()

def main():
    """Fungsi utama"""
    if len(sys.argv) < 2:
        print("Usage: python ocr.py <command> [arguments]")
        print("Commands:")
        print("  process_single <user_bill_id> <image_path>")
        print("  process_pending")
        return
    
    command = sys.argv[1]
    ocr_processor = OCRProcessor()
    
    if command == "process_single" and len(sys.argv) == 4:
        user_bill_id = int(sys.argv[2])
        image_path = sys.argv[3]
        ocr_processor.process_payment_proof(user_bill_id, image_path)
    
    elif command == "process_pending":
        ocr_processor.process_pending_uploads()
    
    else:
        print("Invalid command or arguments")

if __name__ == "__main__":
    main()
