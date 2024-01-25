<?php

namespace App\Http\Controllers;

use ProtoneMedia\LaravelFFMpeg\FFMpeg\FFProbe;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;

use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

use GuzzleHttp\Client;

class VideoController extends Controller
{
    public function processVideo()
    {
        // Kinda fixed
        $ffprobe = FFProbe::create([
            'ffprobe.binaries' => env('FFPROBE_BINARIES')
        ]);

        $videoName = '1.mkv';
        $videoPath = storage_path('/app/videos/'  . $videoName);

        // Get video duration
        $videoDuration = $ffprobe
            ->format($videoPath) // extracts file informations
            ->get('duration');
        // To int
        $videoDuration = intval($videoDuration);

        $translatedTextBlocks = [];
        // For each second take screenshot and process it via Tesseract
        for ($i = 1; $i < 5; $i++) {
            FFMpeg::fromDisk('local')
                ->open('videos/' . $videoName)
                ->getFrameFromSeconds($i)
                ->export()
                ->toDisk('local')
                ->save('images/' . $videoName . '.jpg');

            // Get data from image(text, location, etc.)
            $textBlocks = TesseractController::getTextFromImage($videoName);

            // Create rectangle over text with 4px margin Left and Top
            $imageEdited = false;
            foreach ($textBlocks as $key => $value) {
                $imageEdited = $this->addRectangleToImage(
                    $value['leftStart'] - 4,
                    $value['topStart'] - 4,
                    $value['leftEnd'],
                    $value['topEnd'],
                    $videoName,
                    $imageEdited,
                );
                // Translate text disabled for now
                //                 $translatedText = $this->translateText($value['text']);
                // 
                //                 $textBlocks[$key]['translatedText'] = $translatedText;
            }

            // TO DO Replace in video each second

            dd($textBlocks);
        }

        dd('Complete');
    }

    private function addRectangleToImage(
        $leftStart,
        $topStart,
        $leftEnd,
        $topEnd,
        $videoName,
        $imageEdited = false,
    ) {
        // If image already edited, then edit further
        if (!$imageEdited) {
            $inputPath = 'images/' . $videoName . '.jpg';
        } else {
            $inputPath = 'images/' . $videoName . '_Edited.jpg';
        }

        // Output path
        $outputPath = 'images/' . $videoName . '_Edited.jpg';

        // width and height
        $width = $leftStart - $leftEnd;
        $height = $topStart - $topEnd;

        // Create imageManager
        $manager = new ImageManager(new Driver());

        // read image from file system
        $image = $manager->read(base_path('storage/app/' . $inputPath));

        // Draw rectangle
        $image->drawRectangle($leftStart, $topStart, function ($rectangle) use ($width, $height) {
            $rectangle->size($width, $height); // width & height of rectangle
            $rectangle->background('orange'); // background color of rectangle
            $rectangle->border('white', 0); // border color & size of rectangle
        });

        // Save the modified image
        $image->save(base_path('storage/app/' . $outputPath));

        return true;
    }

    private function translateText($textToTranslate)
    {
        // Replace 'YOUR_API_KEY' with your actual API key
        $apiKey = env('GOOGLE_TRANSLATE_API_KEY');

        // Target language code
        $targetLanguage = 'kk';

        // API endpoint URL
        $apiUrl = "https://translation.googleapis.com/language/translate/v2?key={$apiKey}&q={$textToTranslate}&target={$targetLanguage}";

        // Create a Guzzle HTTP client
        $client = new Client();

        // Make a GET request
        $response = $client->get($apiUrl);

        // Get the response body as a JSON object
        $responseData = json_decode($response->getBody(), true);

        // Access the translated text
        $translatedText = $responseData['data']['translations'][0]['translatedText'];

        // Output the translated text
        return $translatedText;
    }
}
