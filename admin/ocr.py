import cv2
import pytesseract
import re
import mysql.connector
from datetime import datetime
import json
import os
import sys
import logging
from PIL import Image
import numpy as np
import traceback

# Setup logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler('ocr_processing.log'),
        logging.StreamHandler()
    ]
)

# Konfigurasi database
DB_CONFIG = {
    'host': 'localhost',
    'database': 'tetangga_id',
    'user': 'root',
    'password': 'Dipa190503!@#',
    'charset': 'utf8mb4',
    'collation': 'utf8mb4_unicode_ci'
}

# Konfigurasi path Tesseract (uncomment dan sesuaikan jika diperlukan)
# pytesseract.pytesseract.tesseract_cmd = r'C:\Program Files\Tesseract-OCR\tesseract.exe'

class OCRProcessor:
    def __init__(self):
        self.db = None
        self.connect_db()
        logging.info("OCR Processor initialized")
    
    def connect_db(self):
        """Koneksi ke database dengan error handling yang lebih baik"""
        try:
            self.db = mysql.connector.connect(**DB_CONFIG)
            if self.db.is_connected():
                logging.info("Database connection established successfully")
            else:
                raise mysql.connector.Error("Connection failed")
        except mysql.connector.Error as err:
            logging.error(f"Database connection failed: {err}")
            print(f"ERROR: Database connection failed: {err}")
            sys.exit(1)
        except Exception as e:
            logging.error(f"Unexpected error during database connection: {e}")
            print(f"ERROR: Unexpected error: {e}")
            sys.exit(1)
    
    def check_tesseract(self):
        """Cek apakah Tesseract terinstall dan bisa diakses"""
        try:
            version = pytesseract.get_tesseract_version()
            logging.info(f"Tesseract version: {version}")
            return True
        except Exception as e:
            logging.error(f"Tesseract not found or not accessible: {e}")
            print(f"ERROR: Tesseract not found. Please install Tesseract OCR")
            print("Download from: https://github.com/tesseract-ocr/tesseract")
            return False
    
    def preprocess_image(self, image_path):
        """Preprocessing image untuk meningkatkan akurasi OCR"""
        try:
            # Cek apakah file ada
            if not os.path.exists(image_path):
                raise FileNotFoundError(f"Image file not found: {image_path}")
            
            # Cek ukuran file
            file_size = os.path.getsize(image_path)
            if file_size == 0:
                raise ValueError(f"Image file is empty: {image_path}")
            
            logging.info(f"Processing image: {image_path} (size: {file_size} bytes)")
            
            # Baca gambar
            image = cv2.imread(image_path)
            if image is None:
                # Coba baca dengan PIL sebagai fallback
                try:
                    pil_image = Image.open(image_path)
                    image = cv2.cvtColor(np.array(pil_image), cv2.COLOR_RGB2BGR)
                except Exception as pil_err:
                    raise ValueError(f"Could not read image with OpenCV or PIL: {image_path}, Error: {pil_err}")
            
            # Resize jika terlalu besar
            height, width = image.shape[:2]
            if width > 2000 or height > 2000:
                scale_factor = min(2000/width, 2000/height)
                new_width = int(width * scale_factor)
                new_height = int(height * scale_factor)
                image = cv2.resize(image, (new_width, new_height), interpolation=cv2.INTER_AREA)
                logging.info(f"Image resized from {width}x{height} to {new_width}x{new_height}")
            
            # Convert ke grayscale
            gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
            
            # Noise reduction
            denoised = cv2.medianBlur(gray, 3)
            
            # Contrast enhancement
            clahe = cv2.createCLAHE(clipLimit=2.0, tileGridSize=(8,8))
            enhanced = clahe.apply(denoised)
            
            # Thresholding
            _, thresh = cv2.threshold(enhanced, 0, 255, cv2.THRESH_BINARY + cv2.THRESH_OTSU)
            
            # Save preprocessed image untuk debugging (optional)
            debug_path = f"debug_preprocessed_{os.path.basename(image_path)}"
            cv2.imwrite(debug_path, thresh)
            logging.info(f"Preprocessed image saved to: {debug_path}")
            
            return thresh
            
        except Exception as e:
            logging.error(f"Image preprocessing error for {image_path}: {e}")
            logging.error(traceback.format_exc())
            return None
    
    def extract_text_from_image(self, image_path):
        """Ekstrak teks dari gambar menggunakan OCR"""
        try:
            # Preprocessing
            processed_image = self.preprocess_image(image_path)
            if processed_image is None:
                return "", 0
            
            # Multiple OCR configurations untuk hasil yang lebih baik
            configs = [
                r'--oem 3 --psm 6 -c tessedit_char_whitelist=0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz.,/-: ',
                r'--oem 3 --psm 3',
                r'--oem 3 --psm 4',
                r'--oem 3 --psm 6'
            ]
            
            best_text = ""
            best_confidence = 0
            
            for config in configs:
                try:
                    # Extract text
                    text = pytesseract.image_to_string(processed_image, config=config, lang='ind+eng')
                    
                    # Calculate confidence
                    data = pytesseract.image_to_data(processed_image, output_type=pytesseract.Output.DICT, config=config)
                    confidences = [int(conf) for conf in data['conf'] if int(conf) > 0]
                    avg_confidence = sum(confidences) / len(confidences) if confidences else 0
                    
                    # Pilih hasil terbaik
                    if avg_confidence > best_confidence and text.strip():
                        best_text = text
                        best_confidence = avg_confidence
                        
                except Exception as config_err:
                    logging.warning(f"OCR config failed: {config}, Error: {config_err}")
                    continue
            
            logging.info(f"OCR completed. Best confidence: {best_confidence:.2f}%")
            logging.info(f"Extracted text length: {len(best_text)} characters")
            
            return best_text, best_confidence
            
        except Exception as e:
            logging.error(f"OCR Error for {image_path}: {e}")
            logging.error(traceback.format_exc())
            return "", 0
    
    def extract_nominal(self, text):
        """Ekstrak nominal dari teks dengan pattern yang lebih komprehensif"""
        # Pattern untuk nominal (Rp, angka dengan titik/koma)
        patterns = [
            r'Rp\s*(\d{1,3}(?:[.,]\d{3})*(?:[.,]\d{2})?)',
            r'(\d{1,3}(?:[.,]\d{3})*(?:[.,]\d{2})?)\s*(?:Rp|rupiah)',
            r'(?:jumlah|nominal|total|bayar|transfer|amount)\s*:?\s*Rp?\s*(\d{1,3}(?:[.,]\d{3})*(?:[.,]\d{2})?)',
            r'(\d{1,3}(?:[.,]\d{3})+)(?!\d)',  # Angka dengan pemisah ribuan
            r'(\d{4,})(?!\d)',  # Angka 4 digit atau lebih
        ]
        
        found_nominals = []
        
        for pattern in patterns:
            matches = re.findall(pattern, text, re.IGNORECASE)
            if matches:
                for match in matches:
                    # Bersihkan dan konversi ke integer
                    clean_number = re.sub(r'[.,]', '', match)
                    if clean_number.isdigit():
                        nominal = int(clean_number)
                        # Filter nominal yang masuk akal (lebih dari 1000 dan kurang dari 100 juta)
                        if 1000 <= nominal <= 100000000:
                            found_nominals.append(nominal)
        
        if found_nominals:
            # Return nominal yang paling sering muncul, atau yang terbesar jika semua unik
            from collections import Counter
            counter = Counter(found_nominals)
            most_common = counter.most_common(1)
            if most_common:
                return most_common[0][0]
            else:
                return max(found_nominals)
        
        return None
    
    def extract_date(self, text):
        """Ekstrak tanggal dari teks dengan pattern yang lebih lengkap"""
        # Pattern untuk berbagai format tanggal
        patterns = [
            r'(\d{1,2})[/-](\d{1,2})[/-](\d{4})',  # DD/MM/YYYY atau DD-MM-YYYY
            r'(\d{4})[/-](\d{1,2})[/-](\d{1,2})',  # YYYY/MM/DD atau YYYY-MM-DD
            r'(\d{1,2})\s+(Jan|Feb|Mar|Apr|Mei|Jun|Jul|Aug|Sep|Okt|Nov|Des)\s+(\d{4})',  # DD Month YYYY
            r'(Jan|Feb|Mar|Apr|Mei|Jun|Jul|Aug|Sep|Okt|Nov|Des)\s+(\d{1,2}),?\s+(\d{4})',  # Month DD, YYYY
            r'(\d{1,2})\s+(Januari|Februari|Maret|April|Mei|Juni|Juli|Agustus|September|Oktober|November|Desember)\s+(\d{4})',  # Indonesian months
        ]
        
        month_map = {
            'Jan': '01', 'Feb': '02', 'Mar': '03', 'Apr': '04',
            'Mei': '05', 'Jun': '06', 'Jul': '07', 'Aug': '08',
            'Sep': '09', 'Okt': '10', 'Nov': '11', 'Des': '12',
            'Januari': '01', 'Februari': '02', 'Maret': '03', 'April': '04',
            'Juni': '06', 'Juli': '07', 'Agustus': '08', 'September': '09',
            'Oktober': '10', 'November': '11', 'Desember': '12'
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
        """Cek apakah kode tagihan ada dalam teks dengan fuzzy matching"""
        # Bersihkan teks dan kode tagihan
        text_clean = re.sub(r'[^a-zA-Z0-9]', '', text.upper())
        kode_clean = re.sub(r'[^a-zA-Z0-9]', '', kode_tagihan.upper())
        
        # Exact match
        if kode_clean in text_clean:
            return True
        
        # Fuzzy matching dengan toleransi
        if len(kode_clean) > 4:
            for i in range(len(text_clean) - len(kode_clean) + 1):
                substr = text_clean[i:i+len(kode_clean)]
                diff = sum(c1 != c2 for c1, c2 in zip(kode_clean, substr))
                tolerance = max(1, len(kode_clean) // 4)  # Toleransi 25% dari panjang kode
                if diff <= tolerance:
                    return True
        
        return False
    
    def process_payment_proof(self, user_bill_id, image_path):
        """Proses bukti pembayaran dengan OCR dan error handling yang lebih baik"""
        try:
            logging.info(f"Processing payment proof for user_bill_id: {user_bill_id}")
            
            # Cek Tesseract
            if not self.check_tesseract():
                return False
            
            # Cek apakah file gambar ada
            if not os.path.exists(image_path):
                logging.error(f"Image file not found: {image_path}")
                return False
            
            # Ekstrak teks dari gambar
            text, confidence = self.extract_text_from_image(image_path)
            
            if not text.strip():
                logging.warning(f"No text extracted from image: {image_path}")
                # Masih lanjutkan proses untuk update database
            
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
                logging.error(f"Bill data not found for user_bill_id: {user_bill_id}")
                cursor.close()
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
                'extracted_text': text[:2000],  # Batasi panjang teks
                'extracted_nominal': ocr_nominal,
                'extracted_date': ocr_date,
                'expected_nominal': bill_data[11],
                'expected_kode': bill_data[10],
                'processing_time': datetime.now().isoformat(),
                'image_path': image_path,
                'confidence_breakdown': {
                    'base_confidence': confidence,
                    'nominal_match': ocr_nominal == bill_data[11] if ocr_nominal else False,
                    'kode_found': ocr_kode_found,
                    'date_found': ocr_date is not None
                }
            }
            
            update_query = """
                UPDATE user_bills 
                SET ocr_jumlah = %s, 
                    ocr_kode_found = %s, 
                    ocr_date_found = %s, 
                    ocr_confidence = %s,
                    ocr_details = %s,
                    updated_at = NOW()
                WHERE id = %s
            """
            
            cursor.execute(update_query, (
                ocr_nominal,
                1 if ocr_kode_found else 0,
                1 if ocr_date else 0,
                extraction_confidence,
                json.dumps(ocr_details, ensure_ascii=False),
                user_bill_id
            ))
            
            self.db.commit()
            cursor.close()
            
            logging.info(f"OCR processing completed for user_bill_id: {user_bill_id}")
            logging.info(f"Extracted - Nominal: {ocr_nominal}, Date: {ocr_date}, Kode Found: {ocr_kode_found}")
            logging.info(f"Confidence: {extraction_confidence:.2f}%")
            
            return True
            
        except Exception as e:
            logging.error(f"Error processing payment proof: {e}")
            logging.error(traceback.format_exc())
            try:
                self.db.rollback()
            except:
                pass
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
                AND (ocr_confidence = 0.00 OR ocr_confidence IS NULL)
                ORDER BY tanggal_upload DESC
            """
            cursor.execute(query)
            pending_uploads = cursor.fetchall()
            
            logging.info(f"Found {len(pending_uploads)} pending uploads to process")
            
            if len(pending_uploads) == 0:
                print("No pending uploads found")
                return True
            
            success_count = 0
            error_count = 0
            
            for upload in pending_uploads:
                user_bill_id = upload[0]
                image_filename = upload[1]
                
                # Coba beberapa path yang mungkin
                possible_paths = [
                    f"../warga/uploads/bukti_pembayaran/{image_filename}",
                    f"uploads/bukti_pembayaran/{image_filename}",
                    f"warga/uploads/bukti_pembayaran/{image_filename}",
                    f"./uploads/bukti_pembayaran/{image_filename}",
                    image_filename  # Jika sudah full path
                ]
                
                image_path = None
                for path in possible_paths:
                    if os.path.exists(path):
                        image_path = path
                        break
                
                if image_path:
                    logging.info(f"Processing: {image_path}")
                    if self.process_payment_proof(user_bill_id, image_path):
                        success_count += 1
                    else:
                        error_count += 1
                else:
                    logging.error(f"Image not found in any of the expected paths for: {image_filename}")
                    error_count += 1
            
            cursor.close()
            
            logging.info(f"Processing completed. Success: {success_count}, Errors: {error_count}")
            print(f"OCR processing completed. Success: {success_count}, Errors: {error_count}")
            
            return error_count == 0
            
        except Exception as e:
            logging.error(f"Error processing pending uploads: {e}")
            logging.error(traceback.format_exc())
            return False
    
    def test_ocr_setup(self):
        """Test OCR setup"""
        try:
            # Test Tesseract
            if not self.check_tesseract():
                return False
            
            # Test database connection
            cursor = self.db.cursor()
            cursor.execute("SELECT 1")
            result = cursor.fetchone()
            cursor.close()
            
            if result:
                logging.info("Database connection test passed")
                print("✓ Database connection: OK")
            else:
                logging.error("Database connection test failed")
                print("✗ Database connection: FAILED")
                return False
            
            # Test OCR dengan gambar dummy
            try:
                # Buat gambar dummy
                dummy_image = np.ones((100, 300, 3), dtype=np.uint8) * 255
                cv2.putText(dummy_image, "Test 12345", (10, 50), cv2.FONT_HERSHEY_SIMPLEX, 1, (0, 0, 0), 2)
                dummy_path = "test_dummy_image.jpg"
                cv2.imwrite(dummy_path, dummy_image)
                
                # Test OCR
                text, confidence = self.extract_text_from_image(dummy_path)
                
                # Cleanup
                if os.path.exists(dummy_path):
                    os.remove(dummy_path)
                
                if "Test" in text or "12345" in text:
                    logging.info("OCR test passed")
                    print("✓ OCR functionality: OK")
                    return True
                else:
                    logging.warning("OCR test failed - no expected text found")
                    print("⚠ OCR functionality: WARNING - text extraction may not work properly")
                    return False
                    
            except Exception as ocr_err:
                logging.error(f"OCR test failed: {ocr_err}")
                print(f"✗ OCR functionality: FAILED - {ocr_err}")
                return False
            
        except Exception as e:
            logging.error(f"Setup test failed: {e}")
            print(f"✗ Setup test failed: {e}")
            return False
    
    def __del__(self):
        """Destruktor untuk menutup koneksi database"""
        try:
            if self.db and self.db.is_connected():
                self.db.close()
                logging.info("Database connection closed")
        except:
            pass

def main():
    """Fungsi utama dengan improved error handling"""
    if len(sys.argv) < 2:
        print("Usage: python ocr.py <command> [arguments]")
        print("Commands:")
        print("  process_single <user_bill_id> <image_path>")
        print("  process_pending")
        print("  test_setup")
        return
    
    command = sys.argv[1]
    
    try:
        ocr_processor = OCRProcessor()
        
        if command == "process_single" and len(sys.argv) == 4:
            user_bill_id = int(sys.argv[2])
            image_path = sys.argv[3]
            
            if ocr_processor.process_payment_proof(user_bill_id, image_path):
                print("✓ Payment proof processed successfully")
                sys.exit(0)
            else:
                print("✗ Payment proof processing failed")
                sys.exit(1)
        
        elif command == "process_pending":
            if ocr_processor.process_pending_uploads():
                print("✓ All pending uploads processed successfully")
                sys.exit(0)
            else:
                print("✗ Some uploads failed to process")
                sys.exit(1)
        
        elif command == "test_setup":
            if ocr_processor.test_ocr_setup():
                print("✓ All tests passed")
                sys.exit(0)
            else:
                print("✗ Some tests failed")
                sys.exit(1)
        
        else:
            print("Invalid command or arguments")
            sys.exit(1)
            
    except KeyboardInterrupt:
        print("\nProcess interrupted by user")
        sys.exit(1)
    except Exception as e:
        logging.error(f"Fatal error: {e}")
        logging.error(traceback.format_exc())
        print(f"✗ Fatal error: {e}")
        sys.exit(1)

if __name__ == "__main__":
    main()
