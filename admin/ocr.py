import easyocr
import sys
import os
import re
import json

# Ambil path gambar dari argumen
if len(sys.argv) < 2:
    print(json.dumps({"error": "No image path provided."}))
    sys.exit()

image_path = sys.argv[1]

if not os.path.exists(image_path):
    print(json.dumps({"error": "File not found."}))
    sys.exit()

# Inisialisasi EasyOCR
reader = easyocr.Reader(['id', 'en'], gpu=False)

# Jalankan OCR
results = reader.readtext(image_path)
filtered_text = ' '.join([res[1] for res in results if len(res[1].strip()) > 1])

# Normalisasi teks
normalized = filtered_text.replace('oo', '00').replace('O', '0').replace('o', '0')
normalized = normalized.replace(',', '.')

# Ekstrak jumlah
jumlah_match = re.search(r'\b\d{1,3}(?:[.\s]?\d{3})*(?:\.\d+)?\b', normalized)
jumlah = jumlah_match.group(0) if jumlah_match else None

# Ekstrak tanggal
tanggal_match = re.search(r'\b\d{1,2}[-/]\d{1,2}[-/]\d{2,4}\b', normalized)
if not tanggal_match:
    tanggal_match = re.search(r'\b\d{1,2}\s+(Januari|Februari|Maret|April|Mei|Juni|Juli|Agustus|September|Oktober|November|Desember)\s+\d{4}\b', normalized, re.IGNORECASE)
tanggal = tanggal_match.group(0) if tanggal_match else None

# Ekstrak kode tagihan
kode_match = re.search(r'TAG[-\s]?\d{6,10}[-\s]?\d{1,3}', normalized, re.IGNORECASE)
kode_tagihan = kode_match.group(0).replace(' ', '').upper() if kode_match else None

# Output JSON
output = {
    "extracted_text": filtered_text,
    "normalized_text": normalized,
    "jumlah": jumlah,
    "tanggal": tanggal,
    "kode_tagihan": kode_tagihan
}

print(json.dumps(output, ensure_ascii=False))
