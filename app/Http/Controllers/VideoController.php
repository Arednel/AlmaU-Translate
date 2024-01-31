<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;

use Illuminate\Support\Facades\File;

use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

use ProtoneMedia\LaravelFFMpeg\FFMpeg\FFProbe;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;

use Symfony\Component\Process\Process;

class VideoController extends Controller
{
    public function processVideo()
    {
        // Kinda fixed
        $ffprobe = FFProbe::create([
            'ffprobe.binaries' => env('FFPROBE_BINARIES')
        ]);

        $videoName = '1';
        $videoFileExtension = 'mkv';
        $videoID = 10;
        $fullVideoName = "$videoName.$videoFileExtension";

        $videoPathFull = storage_path("/app/videos/new/$videoID/$fullVideoName");
        $videoPathShort = "videos/new/$videoID/$fullVideoName";

        // Create folder
        $this->createFolder($videoID);

        // Divide video to one second parts
        // $this->divideVideoToChunks($videoPathFull, $videoID, $videoName);

        // Get video duration
        $videoDuration = $ffprobe
            ->format($videoPathFull) // extracts file informations
            ->get('duration');
        // Round up and convert to integer
        $videoDuration = intval(ceil($videoDuration));

        $margin = 7; //margin for text box
        // For each second take screenshot and process it via Tesseract
        for ($i = 0; $i < $videoDuration; $i++) {
            FFMpeg::fromDisk('local')
                ->open($videoPathShort)
                ->getFrameFromSeconds($i)
                ->export()
                ->toDisk('local')
                ->save('/images/processing/' . $videoID . '/' . $videoName . '_' . $i . '.jpg');

            // Get data from image(text, location, etc.)
            $textBlocks = TesseractController::getTextFromImage($videoID, $videoName, $i);

            // Create rectangle over text with 4px margin Left and Top
            $imageEdited = false;
            $iteration = 0;
            foreach ($textBlocks as $key => $value) {
                $imageEdited = $this->addRectangleToImage(
                    $value['leftStart'] - $margin,
                    $value['topStart'] - $margin,
                    $value['leftEnd'] + $margin,
                    $value['topEnd'] + $margin,
                    $videoID,
                    $videoName,
                    $i,
                    $imageEdited
                );

                // Translate text
                // TO DO Disabled for now, translate only if text changed
                // $translatedText = $this->translateText($value['text']);
                // $textBlocks[$key]['translatedText'] = $translatedText;
                $textBlocks[$key]['translatedText'] = $value['text'];

                $outputArray = $this->addTranslatedTextToImage($textBlocks[$key], $margin, $videoID, $videoName, $i);
                $lineHeightCalculated = $outputArray['lineHeightCalculated'];
                $maxWidth = $outputArray['maxWidth'];

                // First, crop translated text
                $this->cropImage(
                    $value['leftStart'] - $margin,
                    $value['topStart'] - $lineHeightCalculated,
                    $value['topEnd'],
                    $maxWidth,
                    $videoID,
                    $videoName,
                    $i
                );

                $iteration = $this->placeImageOverlay(
                    $value['leftStart'] - $margin,
                    $value['topStart'] - $margin,
                    $videoID,
                    $videoName,
                    $videoFileExtension,
                    $i,
                    $iteration
                );
            }

            // TO DO Replace in video each frame with edited file

            dd($textBlocks);
        }

        // Delete output.json file
        $outputPath = base_path('storage/app/output/' . $videoName . '_output.json');
        File::delete($outputPath);

        dd('Complete');
    }

    private function addRectangleToImage($leftStart, $topStart, $leftEnd, $topEnd, $videoID, $videoName, $imageNumber, $imageEdited)
    {
        // If image already edited, then edit further
        if (!$imageEdited) {
            $inputPath = 'images/processing/' . $videoID . "/" . $videoName . "_" . $imageNumber . '.jpg';
        } else {
            $inputPath = 'images/processing/' . $videoID . "/" . $videoName . "_" . $imageNumber . '_edited.jpg';
        }

        // Output path
        $outputPath = 'images/processing/' . $videoID . "/" . $videoName . "_" . $imageNumber . '_edited.jpg';

        // width and height
        $width = $leftEnd - $leftStart;
        $height = $topEnd - $topStart;

        // Create imageManager
        $manager = new ImageManager(new Driver());

        // read image from file system
        $image = $manager->read(base_path('storage/app/' . $inputPath));

        // Get background color with 5px margin
        $intcolor = $image->pickColor($leftStart - 5, $topStart);

        // Draw rectangle
        $image->drawRectangle($leftStart, $topStart, function ($rectangle) use ($width, $height, $intcolor) {
            $rectangle->size($width, $height); // width & height of rectangle
            $rectangle->background($intcolor); // background color of rectangle
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

    private function addTranslatedTextToImage($textBlock, $margin, $videoID, $videoName, $imageNumber)
    {
        //Input path
        $inputPath = 'images/processing/' . $videoID . "/" . $videoName . "_" . $imageNumber . '_edited.jpg';

        // Output path
        $outputPath = 'images/processing/' . $videoID . "/" . $videoName . "_" . $imageNumber . '_edited.jpg';

        $lineWidth = $textBlock['leftEnd'] - $textBlock['leftStart'];
        $lineHeight = $textBlock['topEnd'] - $textBlock['topStart'];

        // Create imageManager
        $manager = new ImageManager(new Driver());

        // read image from file system
        $image = $manager->read(base_path('storage/app/' . $inputPath));

        // Translated text
        $text = $textBlock['translatedText'];

        // Calculate font size
        $fontSize = 15;
        $calculatedLineWidth = 0;
        while ($calculatedLineWidth < $lineWidth) {
            list($left,, $widthPX) = imageftbbox($fontSize, 0, public_path('fonts/Charis-SIL/CharisSILB.ttf'), $text);
            $calculatedLineWidth = $widthPX / $textBlock['lineNum'];
            $fontSize++;

            // If font size is too big
            if ($fontSize > 140) {
                $fontSize = 15;
                break;
            }
        }

        $charactersAmount = strlen($text);

        // Break lines after this amount of characters at any point
        $split_length = floor($lineWidth / (floor($widthPX / $charactersAmount)) / 1.5) - 1;

        mb_internal_encoding('UTF-8');
        mb_regex_encoding('UTF-8');
        $lines = preg_split('/(?<!^)(?!$)/u', $text);
        $chunks = array_chunk($lines, $split_length);
        $lines = array_map('implode', $chunks);

        // Print characters
        $lineHeightCalculated = round($lineHeight / $textBlock['lineNum']) + 4;

        $maxWidth = 0;
        for ($i = 0; $i < count($lines); $i++) {

            // Check current line is last line
            if (array_key_exists($i + 1, $lines)) {
                //add ' - ' character to the line end
                $lastCharacter = $lines[$i][-1];
                $firstCharacterNextLine = $lines[$i + 1][0];

                // Check if word ended
                if (
                    !($lastCharacter === ' ' || $firstCharacterNextLine === ' ' || $lastCharacter === ')' || $firstCharacterNextLine === ')')
                ) {
                    $lines[$i] .= '-';
                }
            }

            $offset = $textBlock['topStart'] + ($i * $lineHeightCalculated) + $margin;
            $image->text(
                $lines[$i],
                $textBlock['leftStart'],
                $offset,
                function ($font) use ($fontSize) {
                    $font->file(
                        public_path('fonts/Charis-SIL/CharisSILB.ttf')
                    );
                    $font->size($fontSize);
                    $font->color('black');
                }
            );

            // Calculate max width
            $fontFile = public_path('fonts/Charis-SIL/CharisSILB.ttf');
            // Get the bounding box of the text
            list($left,, $widthPX2)  = imagettfbbox($fontSize, 0, $fontFile, $lines[$i]);
            // Calculate the width of the text
            if ($maxWidth < $widthPX2) {
                $maxWidth = round($widthPX2 / 1.22);
            }
        }

        // Save the modified image
        $image->save(base_path('storage/app/' . $outputPath));

        $outputArray = array(
            'lineHeightCalculated' => $lineHeightCalculated,
            'maxWidth' => $maxWidth,
        );

        return $outputArray;
    }

    private function createFolder($videoID)
    {
        // Create folder for processing purposes for video chunks
        $folderPath = storage_path("/app/videos/processing/$videoID");
        if (!file_exists($folderPath)) {
            mkdir($folderPath, 0777, true);
        }

        // Create folder for processing purposes for output
        $folderPath = storage_path("/app/output/$videoID");
        if (!file_exists($folderPath)) {
            mkdir($folderPath, 0777, true);
        }
    }

    private function divideVideoToChunks($videoPathFull, $videoID, $videoName)
    {
        $ffmpegCommand = [
            env('FFMPEG_BINARIES'),
            '-i', $videoPathFull,
            '-c:v', 'libx264',
            '-c:a', 'aac',
            '-strict', 'experimental',
            '-b:a', '192k',
            '-force_key_frames', 'expr:gte(t,n_forced*1)',
            '-f', 'segment',
            '-segment_time', '1',
            '-reset_timestamps', '1',
            '-map', '0',
            storage_path("/app/videos/processing/$videoID/"  . $videoName . '_part_%d.mkv'),
        ];

        $process = new Process($ffmpegCommand);
        $process->setTimeout(null); //No timeout
        $process->run();
        if (!$process->isSuccessful()) {
            throw new \RuntimeException($process->getErrorOutput());
        }
    }

    private function cropImage($leftStart, $topStart, $topEnd, $width, $videoID, $videoName, $imageNumber)
    {
        $imagePath = storage_path('app/images/processing/' . $videoID . "/" . $videoName . "_" . $imageNumber . '_edited');

        // width and height
        $height = $topEnd - $topStart;

        $ffmpegCommand = [
            env('FFMPEG_BINARIES'),
            '-y', // -y option for overwrite
            '-i', "$imagePath.jpg",
            '-vf', "crop=$width:$height:$leftStart:$topStart", //coordinates
            $imagePath . '_cropped.jpg',
        ];

        $process = new Process($ffmpegCommand);
        $process->setTimeout(null); //No timeout
        $process->run();
        if (!$process->isSuccessful()) {
            throw new \RuntimeException($process->getErrorOutput());
        }
    }

    private function placeImageOverlay($leftStart, $topStart, $videoID, $videoName, $videoFileExtension, $imageNumber, $iteration)
    {
        // If video already edited, then edit further
        if ($iteration == 0) {
            $videoChunkInputPath = storage_path('app/videos/processing/' . $videoID . "/" . $videoName . "_part_" . $imageNumber . '.' . $videoFileExtension);
        } else {
            $videoChunkInputPath = storage_path('app/videos/processing/' . $videoID . "/" . $videoName . "_part_" . $imageNumber . '_translated_iteration_' . $iteration - 1 . "." . $videoFileExtension);
        }

        $imagePath = storage_path('app/images/processing/' . $videoID . "/" . $videoName . "_" . $imageNumber . '_edited_cropped');
        $videoChunkOutputPath = storage_path('app/videos/processing/' . $videoID . "/" . $videoName . "_part_" . $imageNumber . '_translated_iteration_' . $iteration . "." . $videoFileExtension);

        $ffmpegCommand = [
            env('FFMPEG_BINARIES'),
            '-y', // -y option for overwrite
            '-i', $videoChunkInputPath,
            '-i', "$imagePath.jpg",
            '-filter_complex', "overlay=$leftStart:$topStart",
            '-preset', 'fast',
            '-c:a', 'copy',
            $videoChunkOutputPath,
        ];

        $process = new Process($ffmpegCommand);
        $process->setTimeout(null); //No timeout
        $process->run();
        if (!$process->isSuccessful()) {
            throw new \RuntimeException($process->getErrorOutput());
        }

        $iteration++;
        return $iteration;
    }
}
