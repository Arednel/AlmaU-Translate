## Requirements
1. Python in path / globally
2. Python modules:
    1. pytesseract
    2. PIL (pillow)
    3. with gpu:
        1. torch
        2. torchvision
        3. torchaudio
3. [FFmpeg](https://github.com/FFmpeg/FFmpeg) in path / globally
4. [Tesseract OCR](https://github.com/tesseract-ocr/tesseract) in path

## Installation process
#### from project folder

1. composer install
2. php artisan key:generate
3. php artisan voyager:install
4. php artisan db:seed --class=VideosBreadSeeder
5. php artisan db:seed --class=BreadPermissionsSeeder
6. php artisan voyager:admin admin@admin.com --create

### python modules/packages unstallation process
#### from project folder

1. pip install --target python/modules pytesseract pillow openai-whisper

## Using

1. ### Run background jobs queue (should always be running for translation to work)

php artisan queue:listen --timeout=0

#### Or run this instead, if pcntl PHP extension installed

php artisan queue:listen

2. ### Change permissions

For example, remove ability to edit videos for admin (you probably should, this doesn't work)