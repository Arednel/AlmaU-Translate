## Requirements
1. Python globally
2. Python modules globally:
    1. pytesseract
    2. cv2 (opencv-python)
3. FFmpeg in path / globally
4. Tesseract OCR in path

## Installation process

1. composer install
2. php artisan key:generate
3. php artisan voyager:install
4. php artisan db:seed --class=VideosBreadSeeder
5. php artisan db:seed --class=BreadPermissionsSeeder
6. php artisan voyager:admin admin@admin.com --create

## Using

### Run background jobs queue (should always be running for translation to work)
1. php artisan queue:listen --timeout=0
#### Or run this instead, if pcntl PHP extension installed
2. php artisan queue:listen