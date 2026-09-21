#!/bin/bash
# Regenerate vendor/ untuk produksi (dijalankan LOKAL, bukan di server —
# shared hosting tidak punya akses SSH untuk composer). Hasilnya di-commit.
# Lihat cookbook/deploy.md bagian 1-2.
set -e

cd "$(dirname "$0")"

echo "Menjalankan composer install --no-dev --no-scripts --optimize-autoloader ..."
composer install --no-dev --no-scripts --optimize-autoloader

echo ""
echo "Selesai. Langkah selanjutnya (lihat cookbook/deploy.md):"
echo "  1. Review 'git status' pada folder vendor/ — commit perubahan kalau ada."
echo "  2. Pastikan .env TIDAK ikut ter-commit (cek .gitignore)."
echo "  3. Jalankan checklist go-live di cookbook/deploy.md bagian 7"
echo "     dan checklist keamanan di cookbook/security.md bagian 10."
