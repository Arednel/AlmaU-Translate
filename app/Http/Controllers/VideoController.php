<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;

use Illuminate\Support\Facades\Storage;
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
        $ffprobe = FFProbe::create();

        $videoName = '1';
        $videoFileExtension = 'mkv';
        $videoID = 10;
        $fullVideoName = "$videoName.$videoFileExtension";

        $videoPathFull = storage_path("/app/videos/new/$videoID/$fullVideoName");
        $videoPathShort = "videos/new/$videoID/$fullVideoName";

        // Create folders
        $this->createFolders($videoID);

        // Create empty txt file
        $this->createTxtFile($videoID, $videoName);

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
        for ($imageNumber = 0; $imageNumber < $videoDuration; $imageNumber++) {
            FFMpeg::fromDisk('local')
                ->open($videoPathShort)
                ->getFrameFromSeconds($imageNumber)
                ->export()
                ->toDisk('local')
                ->save('/images/processing/' . $videoID . '/' . $videoName . '_' . $imageNumber . '.jpg');

            // Get data from image(text, location, etc.)
            $textBlocks = TesseractController::getTextFromImage($videoID, $videoName, $imageNumber);

            $iteration = 0;
            foreach ($textBlocks as $key => $value) {

                // Translate text
                // TO DO Disabled for now, translate only if text changed
                // $translatedText = $this->translateText($value['text']);
                // $textBlocks[$key]['translatedText'] = $translatedText;
                $textBlocks[$key]['translatedText'] = $value['text'];

                $lineWidth = $textBlocks[$key]['leftEnd'] - $textBlocks[$key]['leftStart'];
                $lineHeight = $value['topEnd'] - $value['topStart'];

                // Create blank image
                $this->imageCreate($lineWidth, $lineHeight, $margin, $textBlocks[$key], $videoID, $videoName, $imageNumber);

                $this->addTranslatedTextToImage($textBlocks[$key], $margin, $videoID, $videoName, $imageNumber);

                $iteration = $this->placeImageOverlay(
                    $value['leftStart'] - $margin,
                    $value['topStart'] - $margin,
                    $videoID,
                    $videoName,
                    $videoFileExtension,
                    $imageNumber,
                    $iteration
                );
            }

            // Check if there was any text
            if ($iteration != 0) {
                // This is fix, so valid iteration is writed to txt file
                $iteration--;

                // Add video path to txt file
                $this->addToTxtList($videoID, $videoName, $videoFileExtension, $imageNumber, $iteration, true);
            }
            // If there in no text to translate
            else {
                // Add video path to txt file
                $this->addToTxtList($videoID, $videoName, $videoFileExtension, $imageNumber, null, false);
            }


            //TO DO Remove        
            if ($imageNumber == 1) {
                break;
            }
            // dd($textBlocks);
        }

        $this->mergeVideoParts($videoID, $videoName, $videoFileExtension);

        // Cleanup
        $this->cleanUp($videoID, $videoName, $imageNumber);

        dd('Complete');
    }

    private function createFolders($videoID)
    {
        // Required folders array
        $folders = [
            storage_path("app/images/processing/$videoID"),
            storage_path("app/output/$videoID"),
            storage_path("app/videos/processing/$videoID"),
            storage_path("app/videos/completed/$videoID"),
        ];

        // Create folders, if they do not exist
        foreach ($folders as $folderPath) {
            if (!file_exists($folderPath)) {
                mkdir($folderPath, 0777, true);
            }
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

    private function imageCreate($width, $height, $margin, $textBlock, $videoID, $videoName, $imageNumber)
    {
        $outputPath = 'images/processing/' . $videoID . "/" . $videoName . "_" . $imageNumber . '_blank.png';

        $lineHeight = $textBlock['topEnd'] - $textBlock['topStart'];
        $lineHeightCalculated = intval($lineHeight / $textBlock['lineNum']);

        $width = $width + $margin * 3;

        $height = $height + $lineHeightCalculated + $margin;

        // If calculated height is bigger, use it 
        $calculatedHeight = $this->calculateHeight($textBlock);
        if ($height < $calculatedHeight) {
            $height = $calculatedHeight;
        }
        // Create a blank image
        $image = imagecreatetruecolor($width, $height);

        // Set a background color
        $backgroundColor = imagecolorallocate($image, 255, 255, 255);
        imagefill($image, 0, 0, $backgroundColor);

        // Save the image to a file
        $filename = base_path('storage/app/' . $outputPath);
        imagepng($image, $filename);
    }

    private function calculateHeight($textBlock)
    {
        $lineWidth = $textBlock['leftEnd'] - $textBlock['leftStart'];

        // Translated text
        $text = $textBlock['translatedText'];

        $fontSize = $textBlock['fontSize'];

        $bbox = imageftbbox($fontSize, 0, public_path('fonts/Charis-SIL/CharisSILB.ttf'), $text);
        // Calculate text width, (/ 1.38 is to get real width)
        $widthTranslatedTextPx = ceil($bbox[2] / 1.38);

        $charactersAmount = mb_strlen($text);

        // Break lines after this amount of characters at any point (-1 is to add later '-')
        $split_length = floor($lineWidth / (ceil($widthTranslatedTextPx / $charactersAmount))) - 1;

        $linesAmount = ceil($charactersAmount / $split_length);

        $calculatedHeight = ceil((ceil($bbox[1] + (-$bbox[5])) * $linesAmount) / 1.38);

        return $calculatedHeight;
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
        $inputPath = 'images/processing/' . $videoID . "/" . $videoName . "_" . $imageNumber . '_blank.png';

        // Output path
        $outputPath = 'images/processing/' . $videoID . "/" . $videoName . "_" . $imageNumber . '_translated.png';

        $lineWidth = $textBlock['leftEnd'] - $textBlock['leftStart'];
        $lineHeight = $textBlock['topEnd'] - $textBlock['topStart'];

        $fontFile = public_path('fonts/Charis-SIL/CharisSILB.ttf');

        // Create imageManager
        $manager = new ImageManager(new Driver());

        // read image from file system
        $image = $manager->read(base_path('storage/app/' . $inputPath));

        // Translated text
        $text = $textBlock['translatedText'];

        $fontSize = $textBlock['fontSize'];

        $bbox = imageftbbox($fontSize, 0, public_path('fonts/Charis-SIL/CharisSILB.ttf'), $text);
        // Calculate text width, (/ 1.38 is to get real width)
        $widthTranslatedTextPx = ceil($bbox[2] / 1.38);

        $charactersAmount = mb_strlen($text);

        // Break lines after this amount of characters at any point (-1 is to add later '-')
        $split_length = floor($lineWidth / (ceil($widthTranslatedTextPx / $charactersAmount))) - 1;

        mb_internal_encoding('UTF-8');
        mb_regex_encoding('UTF-8');
        $lines = preg_split('/(?<!^)(?!$)/u', $text);
        $chunks = array_chunk($lines, $split_length);
        $lines = array_map('implode', $chunks);

        // Print characters
        $lineHeightCalculated = intval($lineHeight / $textBlock['lineNum']);

        for ($i = 0; $i < count($lines); $i++) {
            // Add word wrap
            $lines[$i] = $this->wordWrap($lines, $i);

            $offset = ($i + 1) * $lineHeightCalculated + $margin;
            $image->text(
                $lines[$i],
                3 + $margin,
                $offset,
                function ($font) use ($fontSize, $fontFile) {
                    $font->file($fontFile);
                    $font->size($fontSize);
                    $font->color('black');
                }
            );
        }

        // Save the modified image
        $image->save(base_path('storage/app/' . $outputPath));
    }

    private function wordWrap($lines, $i)
    {
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

        return $lines[$i];
    }

    private function placeImageOverlay($leftStart, $topStart, $videoID, $videoName, $videoFileExtension, $imageNumber, $iteration)
    {
        // If video already edited, then edit further
        if ($iteration == 0) {
            $videoChunkInputPath = storage_path('app/videos/processing/' . $videoID . "/" . $videoName . "_part_" . $imageNumber . '.' . $videoFileExtension);
        } else {
            $videoChunkInputPath = storage_path('app/videos/processing/' . $videoID . "/" . $videoName . "_part_" . $imageNumber . '_translated_iteration_' . $iteration - 1 . "." . $videoFileExtension);
        }

        $image = storage_path('app/images/processing/' . $videoID . "/" . $videoName . "_" . $imageNumber . '_translated.png');
        $videoChunkOutputPath = storage_path('app/videos/processing/' . $videoID . "/" . $videoName . "_part_" . $imageNumber . '_translated_iteration_' . $iteration . "." . $videoFileExtension);

        $ffmpegCommand = [
            env('FFMPEG_BINARIES'),
            '-y', // -y option for overwrite
            '-i', $videoChunkInputPath,
            '-i', $image,
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

    private function createTxtFile($videoID, $videoName)
    {
        $fileName = $videoName . '_video_parts_list.txt';
        $fileFullPath = "output/$videoID/$fileName";

        $contents = '';

        // Create empty txt file
        Storage::disk('local')->put($fileFullPath, $contents);
    }

    private function addToTxtList($videoID, $videoName, $videoFileExtension, $imageNumber, $iteration, $mergeTranslatedPart)
    {
        $fileName = $videoName . '_video_parts_list.txt';
        $fileFullPath = "output/$videoID/$fileName";

        if ($mergeTranslatedPart) {
            $videoName = storage_path('app/videos/processing/' . $videoID . '/' . $videoName . '_part_' . $imageNumber . '_translated_iteration_' . $iteration . '.' . $videoFileExtension);
        }
        // If there was no text to translate
        else {
            $videoName = storage_path('app/videos/processing/' . $videoID . '/' . $videoName . '_part_' . $imageNumber . '.' . $videoFileExtension);
        }

        $contents = "file '$videoName'";

        // Append video part path to txt file
        Storage::disk('local')->append($fileFullPath, $contents);
    }

    private function mergeVideoParts($videoID, $videoName, $videoFileExtension)
    {
        $fileName = $videoName . '_video_parts_list.txt';
        $fileFullPath = base_path("storage/app/output/$videoID/$fileName");

        $videoOutput = storage_path('app/videos/completed/' . $videoID . "/" . $videoName . "_translated." . $videoFileExtension);

        $ffmpegCommand = [
            env('FFMPEG_BINARIES'),
            '-y', // -y option for overwrite
            '-safe', '0',
            '-f', 'concat',
            '-i', $fileFullPath,
            '-c', 'copy',
            $videoOutput,
        ];

        $process = new Process($ffmpegCommand);
        $process->setTimeout(null); //No timeout
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException($process->getErrorOutput());
        }
    }

    private function cleanUp($videoID, $videoName, $imageNumber)
    {
        // Folders to cleanup
        $folders = [
            storage_path("app/images/processing/$videoID"),
            storage_path("app/output/$videoID"),
            // storage_path("app/videos/processing/$videoID"), //TO DO remove comment
        ];

        // Delete folders
        foreach ($folders as $folderPath) {
            File::deleteDirectory($folderPath);
        }
    }
}
