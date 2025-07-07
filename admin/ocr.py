import easyocr
import sys
import os
import re
import json
from pathlib import Path

os.environ['EASYOCR_MODULE_PATH'] = '/tmp/easyocr_model'
os.environ['EASYOCR_USER_NETWORK_DIRECTORY'] = '/tmp/easyocr_model/user_network'

Path('/tmp/easyocr_model').mkdir(parents=True, exist_ok=True)
Path('/tmp/easyocr_model/user_network').mkdir(parents=True, exist_ok=True)

if len(sys.argv) < 2:
    print(json.dumps({"error": "No image path provided."}))
    sys.exit()

image_path = sys.argv[1]
if not os.path.exists(image_path):
    print(json.dumps({"error": "File not found."}))
    sys.exit()

reader = easyocr.Reader(['id', 'en'], gpu=False, model_storage_directory='/tmp/easyocr_model')
results = reader.readtext(image_path)

filtered_text = ' '.join([res[1] for res in results if len(res[1].strip()) > 1])

# Normalisasi teks
normalized = filtered_text.replace('oo', '00').replace('O', '0').replace('o', '0')
normalized = normalized.replace(',', '.').replace('–', '-').replace('—', '-')
normalized = re.sub(r'\s+', ' ', normalized).strip()

# Ekstrak jumlah uang
jumlah = None
match = re.search(r'Rp\s?(\d{1,3}(?:[\.,]?\d{3})+)', normalized, re.IGNORECASE)
if match:
    jumlah = re.sub(r'[^\d]', '', match.group(1))
else:
    fallback = re.findall(r'\b\d{4,}\b', normalized)
    if fallback:
        jumlah = fallback[0]

# Ekstrak tanggal (format: 06 Jul 2025 atau 06/07/2025)
tanggal = None
match = re.search(r'\b\d{1,2}[\s/-](Jan|Feb|Mar|Apr|Mei|Jun|Jul|Agu|Sep|Okt|Nov|Des|Jul)[a-z]*[\s/-]\d{2,4}\b', normalized, re.IGNORECASE)
if not match:
    match = re.search(r'\b\d{1,2}[\s/-]\d{1,2}[\s/-]\d{2,4}\b', normalized)
if match:
    tanggal = match.group(0)

# Ekstrak kode tagihan
kode_tagihan = None
match = re.search(r'TAG[-_\s]?\d{4,6}[-_\s]?\d{1,3}', normalized, re.IGNORECASE)
if match:
    kode_tagihan = match.group(0).replace(' ', '').replace('_', '-').upper()

output = {
    "extracted_text": filtered_text,
    "normalized_text": normalized,
    "jumlah": jumlah,
    "tanggal": tanggal,
    "kode_tagihan": kode_tagihan
}

print(json.dumps(output, ensure_ascii=False))
