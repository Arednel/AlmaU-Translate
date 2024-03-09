## Requirements
1. [Python 3.11.7](https://www.python.org/downloads/release/python-3117) (tested on this version) and [pip](https://pypi.org/project/pip) (for easier installation process) in path / globally 
2. Python modules:
    1. [pytesseract](https://pypi.org/project/pytesseract)
    2. [PIL (pillow)](https://pypi.org/project/pillow)
    3. [whisper (openai-whisper)](https://github.com/openai/whisper)
    4. [coqui-ai/tts](https://github.com/coqui-ai/TTS) 0.21.0 globally
    5. with gpu:
        1. [torch](https://pypi.org/project/torch)
        2. [torchvision](https://pypi.org/project/torchvision)
        3. [torchaudio](https://pypi.org/project/torchaudio)
    6. [yt-dlp](https://github.com/yt-dlp/yt-dlp)
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

### python modules/packages installation process
#### from project folder

1. pip install --target python/modules --force-reinstall https://github.com/yt-dlp/yt-dlp/archive/master.tar.gz
2. pip install --target python/modules pytesseract pillow openai-whisper tts==0.21.0

## Using

1. ### Run background jobs queue (should always be running for background translation to work)

php artisan queue:listen --timeout=0 --tries=5

#### Or run this instead, if pcntl PHP extension installed

php artisan queue:listen --tries=5

## Recommended 

### Change Voyager permissions

For example, remove ability to edit videos (this doesn't work, so you probably should)