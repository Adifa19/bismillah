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

# ✅ Inisialisasi EasyOCR dengan folder model aman
reader = easyocr.Reader(['id', 'en'], gpu=False, model_storage_directory='/tmp/easyocr_model')

# Jalankan OCR
results = reader.readtext(image_path)
filtered_text = ' '.join([res[1] for res in results if len(res[1].strip()) > 1])

# Normalisasi teks
normalized = filtered_text.replace('oo', '00').replace('O', '0').replace('o', '0')
normalized = normalized.replace(',', '.').replace('  ', ' ')

# Ekstrak jumlah uang (Rp atau angka besar saja)
jumlah = None
jumlah_match = re.search(r'Rp\s?[\d.]+', normalized, re.IGNORECASE)
if jumlah_match:
    jumlah = re.sub(r'[^\d]', '', jumlah_match.group(0))
else:
    fallback_jumlah = re.findall(r'\b\d{4,}\b', normalized)
    if fallback_jumlah:
        jumlah = fallback_jumlah[0]

# Ekstrak tanggal
tanggal = None
tanggal_match = re.search(r'\b\d{1,2}\s+(Jan|Feb|Mar|Apr|Mei|Jun|Jul|Agu|Sep|Okt|Nov|Des)[a-z]*\s+\d{4}\b', normalized, re.IGNORECASE)
if tanggal_match:
    tanggal = tanggal_match.group(0)
else:
    tanggal_match = re.search(r'\b\d{1,2}[/-]\d{1,2}[/-]\d{2,4}\b', normalized)
    if tanggal_match:
        tanggal = tanggal_match.group(0)

# Ekstrak kode tagihan
kode_tagihan = None
kode_match = re.search(r'TAG[-_\s]?\d{4,6}[-_\s]?\d{1,3}', normalized, re.IGNORECASE)
if kode_match:
    kode_tagihan = kode_match.group(0).replace(' ', '').replace('_', '-').upper()

# Output JSON
output = {
    "extracted_text": filtered_text,
    "normalized_text": normalized,
    "jumlah": jumlah,
    "tanggal": tanggal,
    "kode_tagihan": kode_tagihan
}

print(json.dumps(output, ensure_ascii=False))
