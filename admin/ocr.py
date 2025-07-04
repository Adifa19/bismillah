import easyocr
import cv2
import numpy as np
import re
from datetime import datetime
import json
import sys
import os
from pathlib import Path
import mysql.connector
from mysql.connector import Error
import argparse

class BuktiTransferOCR:
    def __init__(self, db_config=None):
        """
        Initialize OCR reader dan database connection
        """
        self.reader = easyocr.Reader(['en', 'id'])  # English dan Indonesian
        self.db_config = db_config or {
            'host': 'localhost',
            'database': 'tetangga.id',
            'user': 'root',
            'password': ''
        }
        self.connection = None
        self.connect_database()
        
    def connect_database(self):
        """Connect ke database MySQL"""
        try:
            self.connection = mysql.connector.connect(**self.db_config)
            if self.connection.is_connected():
                print("Database connection successful")
        except Error as e:
            print(f"Error connecting to database: {e}")
            self.connection = None
    
    def preprocess_image(self, image_path):
        """
        Preprocessing gambar untuk meningkatkan akurasi OCR
        """
        try:
            # Baca gambar
            image = cv2.imread(image_path)
            if image is None:
                raise ValueError(f"Cannot read image: {image_path}")
            
            # Convert ke grayscale
            gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
            
            # Apply gaussian blur untuk mengurangi noise
            blurred = cv2.GaussianBlur(gray, (5, 5), 0)
            
            # Threshold untuk meningkatkan kontras
            _, thresh = cv2.threshold(blurred, 0, 255, cv2.THRESH_BINARY + cv2.THRESH_OTSU)
            
            # Morphological operations untuk membersihkan noise
            kernel = np.ones((1, 1), np.uint8)
            cleaned = cv2.morphologyEx(thresh, cv2.MORPH_CLOSE, kernel)
            
            return cleaned
            
        except Exception as e:
            print(f"Error preprocessing image: {e}")
            return None
    
    def extract_text_from_image(self, image_path):
        """
        Ekstrak teks dari gambar menggunakan EasyOCR
        """
        try:
            # Preprocess gambar
            processed_image = self.preprocess_image(image_path)
            
            if processed_image is None:
                # Fallback ke gambar asli
                processed_image = image_path
            
            # OCR extraction
            results = self.reader.readtext(processed_image, detail=1)
            
            # Extract text dan confidence
            extracted_data = []
            for (bbox, text, confidence) in results:
                if confidence > 0.5:  # Filter low confidence results
                    extracted_data.append({
                        'text': text.strip(),
                        'confidence': confidence,
                        'bbox': bbox
                    })
            
            return extracted_data
            
        except Exception as e:
            print(f"Error extracting text: {e}")
            return []
    
    def find_nominal_amount(self, text_data):
        """
        Cari nominal transfer dalam teks
        """
        nominal_patterns = [
            r'(?:rp\.?\s*|idr\s*)?(\d{1,3}(?:[.,]\d{3})*(?:[.,]\d{2})?)',  # Format rupiah
            r'(\d{1,3}(?:[.,]\d{3})*(?:[.,]\d{2})?)\s*(?:rp|idr)?',  # Angka + mata uang
            r'(?:nominal|jumlah|amount)[:\s]*(?:rp\.?\s*)?(\d{1,3}(?:[.,]\d{3})*(?:[.,]\d{2})?)',  # Label + nominal
            r'(?:transfer|kirim|bayar)[:\s]*(?:rp\.?\s*)?(\d{1,3}(?:[.,]\d{3})*(?:[.,]\d{2})?)'  # Action + nominal
        ]
        
        found_amounts = []
        
        for item in text_data:
            text = item['text'].lower()
            confidence = item['confidence']
            
            for pattern in nominal_patterns:
                matches = re.finditer(pattern, text, re.IGNORECASE)
                for match in matches:
                    amount_str = match.group(1)
                    # Bersihkan format dan convert ke integer
                    amount_clean = re.sub(r'[.,]', '', amount_str)
                    if amount_clean.isdigit():
                        amount = int(amount_clean)
                        # Filter nominal yang masuk akal (min 1000, max 100juta)
                        if 1000 <= amount <= 100000000:
                            found_amounts.append({
                                'amount': amount,
                                'original_text': amount_str,
                                'confidence': confidence,
                                'full_text': item['text']
                            })
        
        # Return nominal dengan confidence tertinggi
        if found_amounts:
            return max(found_amounts, key=lambda x: x['confidence'])
        return None
    
    def find_transaction_date(self, text_data):
        """
        Cari tanggal transaksi dalam teks
        """
        date_patterns = [
            r'(\d{1,2}[/\-\.]\d{1,2}[/\-\.]\d{2,4})',  # DD/MM/YYYY or DD-MM-YYYY
            r'(\d{1,2}\s+(?:jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)\s+\d{2,4})',  # DD MMM YYYY
            r'(\d{1,2}\s+(?:januari|februari|maret|april|mei|juni|juli|agustus|september|oktober|november|desember)\s+\d{2,4})',  # Indonesian months
            r'(\d{4}[/\-\.]\d{1,2}[/\-\.]\d{1,2})',  # YYYY/MM/DD
        ]
        
        found_dates = []
        
        for item in text_data:
            text = item['text'].lower()
            confidence = item['confidence']
            
            for pattern in date_patterns:
                matches = re.finditer(pattern, text, re.IGNORECASE)
                for match in matches:
                    date_str = match.group(1)
                    parsed_date = self.parse_date(date_str)
                    if parsed_date:
                        found_dates.append({
                            'date': parsed_date,
                            'original_text': date_str,
                            'confidence': confidence,
                            'full_text': item['text']
                        })
        
        # Return tanggal dengan confidence tertinggi
        if found_dates:
            return max(found_dates, key=lambda x: x['confidence'])
        return None
    
    def parse_date(self, date_str):
        """
        Parse string tanggal ke format datetime
        """
        date_formats = [
            '%d/%m/%Y', '%d-%m-%Y', '%d.%m.%Y',
            '%d/%m/%y', '%d-%m-%y', '%d.%m.%y',
            '%Y/%m/%d', '%Y-%m-%d', '%Y.%m.%d',
            '%d %b %Y', '%d %B %Y'
        ]
        
        # Indonesian month mapping
        month_mapping = {
            'januari': 'january', 'februari': 'february', 'maret': 'march',
            'april': 'april', 'mei': 'may', 'juni': 'june',
            'juli': 'july', 'agustus': 'august', 'september': 'september',
            'oktober': 'october', 'november': 'november', 'desember': 'december'
        }
        
        # Replace Indonesian months with English
        date_str_en = date_str.lower()
        for indo, eng in month_mapping.items():
            date_str_en = date_str_en.replace(indo, eng)
        
        for fmt in date_formats:
            try:
                return datetime.strptime(date_str_en, fmt).date()
            except ValueError:
                continue
        
        return None
    
    def find_billing_code(self, text_data, expected_code=None):
        """
        Cari kode tagihan dalam teks
        """
        # Pattern untuk kode tagihan
        code_patterns = [
            r'(?:kode|code|ref|reference)[:\s]*([A-Z0-9]{6,20})',  # Label + kode
            r'([A-Z0-9]{8,15})',  # Kode standalone
            r'(?:tagihan|bill)[:\s]*([A-Z0-9]{6,20})',  # Bill code
        ]
        
        found_codes = []
        
        for item in text_data:
            text = item['text'].upper()
            confidence = item['confidence']
            
            for pattern in code_patterns:
                matches = re.finditer(pattern, text, re.IGNORECASE)
                for match in matches:
                    code = match.group(1).upper()
                    found_codes.append({
                        'code': code,
                        'confidence': confidence,
                        'full_text': item['text']
                    })
        
        # Jika ada expected_code, cari yang paling mirip
        if expected_code and found_codes:
            for code_data in found_codes:
                if expected_code.upper() in code_data['code'] or code_data['code'] in expected_code.upper():
                    return code_data
        
        # Return kode dengan confidence tertinggi
        if found_codes:
            return max(found_codes, key=lambda x: x['confidence'])
        return None
    
    def get_bill_info(self, user_bill_id):
        """
        Get informasi tagihan dari database
        """
        if not self.connection:
            return None
            
        try:
            cursor = self.connection.cursor()
            query = """
            SELECT ub.*, b.kode_tagihan, b.jumlah as jumlah_tagihan, b.tanggal as tanggal_tagihan
            FROM user_bills ub
            JOIN bills b ON ub.bill_id = b.id
            WHERE ub.id = %s
            """
            cursor.execute(query, (user_bill_id,))
            result = cursor.fetchone()
            cursor.close()
            return result
        except Error as e:
            print(f"Database error: {e}")
            return None
    
    def update_ocr_results(self, user_bill_id, ocr_results):
        """
        Update hasil OCR ke database
        """
        if not self.connection:
            return False
            
        try:
            cursor = self.connection.cursor()
            query = """
            UPDATE user_bills SET
                ocr_jumlah = %s,
                ocr_kode_found = %s,
                ocr_date_found = %s,
                ocr_confidence = %s,
                ocr_details = %s
            WHERE id = %s
            """
            
            cursor.execute(query, (
                ocr_results.get('nominal', {}).get('amount'),
                1 if ocr_results.get('kode_found') else 0,
                1 if ocr_results.get('date_found') else 0,
                ocr_results.get('avg_confidence', 0),
                json.dumps(ocr_results, ensure_ascii=False),
                user_bill_id
            ))
            
            self.connection.commit()
            cursor.close()
            return True
            
        except Error as e:
            print(f"Database error: {e}")
            return False
    
    def process_bukti_transfer(self, image_path, user_bill_id):
        """
        Process bukti transfer lengkap
        """
        # Get bill info
        bill_info = self.get_bill_info(user_bill_id)
        if not bill_info:
            return {'error': 'Bill not found'}
        
        # Extract text from image
        text_data = self.extract_text_from_image(image_path)
        if not text_data:
            return {'error': 'Failed to extract text from image'}
        
        # Find nominal
        nominal_result = self.find_nominal_amount(text_data)
        
        # Find date
        date_result = self.find_transaction_date(text_data)
        
        # Find billing code
        expected_code = bill_info.get('kode_tagihan') if bill_info else None
        code_result = self.find_billing_code(text_data, expected_code)
        
        # Calculate confidence
        confidences = [item['confidence'] for item in text_data]
        avg_confidence = sum(confidences) / len(confidences) if confidences else 0
        
        # Prepare results
        results = {
            'user_bill_id': user_bill_id,
            'nominal': nominal_result,
            'tanggal': date_result,
            'kode_tagihan': code_result,
            'expected_amount': bill_info.get('jumlah_tagihan') if bill_info else None,
            'expected_code': expected_code,
            'kode_found': code_result is not None,
            'date_found': date_result is not None,
            'nominal_match': False,
            'avg_confidence': round(avg_confidence, 2),
            'all_text': [item['text'] for item in text_data]
        }
        
        # Check nominal match
        if nominal_result and bill_info:
            expected_amount = bill_info.get('jumlah_tagihan')
            if expected_amount:
                results['nominal_match'] = abs(nominal_result['amount'] - expected_amount) <= 1000
        
        # Update database
        self.update_ocr_results(user_bill_id, results)
        
        return results

def main():
    parser = argparse.ArgumentParser(description='OCR Bukti Transfer')
    parser.add_argument('image_path', help='Path to image file')
    parser.add_argument('user_bill_id', type=int, help='User bill ID')
    parser.add_argument('--output', '-o', help='Output JSON file')
    
    args = parser.parse_args()
    
    # Initialize OCR
    ocr = BuktiTransferOCR()
    
    # Process image
    results = ocr.process_bukti_transfer(args.image_path, args.user_bill_id)
    
    # Output results
    if args.output:
        with open(args.output, 'w', encoding='utf-8') as f:
            json.dump(results, f, ensure_ascii=False, indent=2, default=str)
        print(f"Results saved to {args.output}")
    else:
        print(json.dumps(results, ensure_ascii=False, indent=2, default=str))

if __name__ == "__main__":
    main()
