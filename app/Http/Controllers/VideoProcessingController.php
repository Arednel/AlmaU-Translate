<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Facades\Log;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

use ProtoneMedia\LaravelFFMpeg\FFMpeg\FFProbe;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;

use App\Models\Video;

class VideoProcessingController extends Controller
{
    protected $logText = "";
    protected $translateApiCalls = 0;

    public function processVideo($videoID, $videoNameWithExtension)
    {
        $startTime = hrtime(true);

        // Set video status to being processed
        $video = Video::find($videoID);
        $video->is_processing = 1;
        $video->save();

        // Add text to log later
        $this->logText .= "Video processing started \n" .
            "Video ID: $videoID \n" .
            "Video Title: $video->name \n";

        $videoPathFull = storage_path("/app/videos/new/$videoID/$videoNameWithExtension");

        // Get file name
        $videoName = pathinfo($videoPathFull, PATHINFO_FILENAME);

        // Get file extension
        $videoFileExtension = pathinfo($videoPathFull, PATHINFO_EXTENSION);

        // Create folders
        $this->createFolders($videoID);

        // Create empty txt file
        $this->createTxtFile($videoID, $videoName);

        // Divide video to one second parts
        $this->divideVideoToParts($videoPathFull, $videoID, $videoName);

        $video->current_progress = 5;
        $video->save();

        // Get video duration
        $videoDuration = $this->getVideoDuration($videoPathFull);

        $margin = 7; //margin to add to text box (extra width and height)

        $previousTextBlocks = [];

        // For each second take screenshot and process it via Tesseract except last two seconds (this is fix)
        for ($imageNumber = 0; $imageNumber < $videoDuration - 2; $imageNumber++) {
            // Translate video part and return text blocks
            $previousTextBlocks = $this->translateVideoPart($videoID, $videoName, $videoNameWithExtension, $imageNumber, $previousTextBlocks, $margin);

            $video->current_progress = intval((($imageNumber + 1) / ($videoDuration - 2)) * 80) + 5;
            $video->save();
        }

        $this->mergeVideoParts($videoID, $videoName, $videoFileExtension);

        $video->current_progress = 85;
        $video->save();

        $translate_audio = $video->translate_audio;
        // Place original audio track or translate
        $this->fixAudio($videoID, $videoName, $videoFileExtension, $translate_audio);

        // Cleanup
        $this->cleanUp($videoID);

        // Set video status to completed
        $video = Video::find($videoID); //Fix of "Attempt to assign property "is_processing" on null"
        $video->current_progress = 100;
        $video->is_processing = 0;
        $video->is_translated = 1;
        $video->save();

        // Calculate elapsed time
        $endTime = hrtime(true);
        $elapsedTime = ($endTime - $startTime) / 1e9; // Convert nanoseconds to seconds

        // Add text to log later
        $this->logText .= "Processing completed in: $elapsedTime seconds";
        // Create log
        Log::channel('translation')->info($this->logText);
    }

    private function createFolders($videoID)
    {
        // Required folders array
        $folders = [
            storage_path("app/audio/processing/$videoID"),
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

    private function runProcess($command)
    {
        $process = new Process($command);
        $process->setTimeout(null); //No timeout
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException($process->getErrorOutput());
        }

        return $process->getOutput();
    }

    private function divideVideoToParts($videoPathFull, $videoID, $videoName)
    {
        $ffmpegCommand = [
            env('FFMPEG_BINARIES'),
            '-i', $videoPathFull,
            '-map', '0',
            '-force_key_frames', 'expr:gte(t,n_forced*1)',
            '-f', 'segment',
            '-segment_time', '1',
            '-reset_timestamps', '1',
            storage_path("/app/videos/processing/$videoID/"  . $videoName . '_part_%d.mp4'),
        ];

        $this->runProcess($ffmpegCommand);
    }

    private function getVideoDuration($videoPathFull)
    {
        $ffprobe = FFProbe::create(
            ['ffprobe.binaries' => env('FFPROBE_BINARIES')]
        );

        $videoDuration = $ffprobe
            ->format($videoPathFull)
            ->get('duration');

        // Round up and convert to integer
        $videoDuration = intval(ceil($videoDuration));

        return $videoDuration;
    }

    private function translateVideoPart($videoID, $videoName, $videoNameWithExtension, $imageNumber, $previousTextBlocks, $margin)
    {
        FFMpeg::fromDisk('local')
            ->open("videos/new/$videoID/$videoNameWithExtension")
            ->getFrameFromSeconds($imageNumber)
            ->export()
            ->toDisk('local')
            ->save('/images/processing/' . $videoID . '/' . $videoName . '_' . $imageNumber . '.png');

        // Get data from image(text, location, etc.)
        $textBlocks = TesseractController::getTextFromImage($videoID, $videoName, $imageNumber);

        $iteration = 0;
        foreach ($textBlocks as $key => $value) {
            $textChanged = false;
            // Check if text was changed
            if (
                $imageNumber > 0
            ) {
                if (array_key_exists($key, $previousTextBlocks)) {
                    $textChanged = $this->isTextChanged($previousTextBlocks[$key]['text'], $textBlocks[$key]['text']);
                }
            }

            // Translate first image, if text changed or if text not existed before
            if (
                $textChanged
                || $imageNumber == 0
                || !(array_key_exists($key, $previousTextBlocks))
            ) {
                // Translate text
                $translatedText = $this->translateText($value['text']);
                $textBlocks[$key]['translatedText'] = $translatedText;
                // $textBlocks[$key]['translatedText'] = $value['text'];
            } else {
                $textBlocks[$key]['translatedText'] = $previousTextBlocks[$key]['translatedText'];
            }

            $lineWidth = $textBlocks[$key]['leftEnd'] - $textBlocks[$key]['leftStart'];
            $lineHeight = $value['topEnd'] - $value['topStart'];

            // Create blank image
            $this->imageCreate($lineWidth, $lineHeight, $margin, $textBlocks[$key], $videoID, $videoName, $imageNumber);

            $this->addTranslatedTextToImage($textBlocks[$key], $margin, $videoID, $videoName, $imageNumber);

            $iteration = $this->placeImageOverlay(
                $value['leftStart'] - $margin,
                $value['topStart'] - $margin * 2,
                $videoID,
                $videoName,
                $imageNumber,
                $iteration
            );
        }

        $previousTextBlocks = $textBlocks;

        // Check if there was any text
        if ($iteration != 0) {
            // This is fix, so valid iteration number is writed to txt file
            $iteration--;

            // Add video path to txt file
            $this->addToTxtList($videoID, $videoName,  $imageNumber, $iteration, true);
        }
        // If there in no text to translate
        else {
            // Add video path to txt file
            $this->addToTxtList($videoID, $videoName,  $imageNumber, null, false);
        }

        $previousTextBlocks = $textBlocks;

        return $previousTextBlocks;
    }

    private function isTextChanged($str1, $str2, $percentThreshold = 99)
    {
        // Check if text changed (to be precise, if text similarity is more 99% or more)
        similar_text($str1, $str2, $similarity);

        $textChanged = $similarity < $percentThreshold;

        return $textChanged;
    }

    private function imageCreate($width, $height, $margin, $textBlock, $videoID, $videoName, $imageNumber)
    {
        $outputPath = 'images/processing/' . $videoID . "/" . $videoName . "_" . $imageNumber . '_blank.png';

        $lineHeight = $textBlock['topEnd'] - $textBlock['topStart'];
        $lineHeightCalculated = intval($lineHeight / $textBlock['lineNum']);

        $width = $width + $margin * 3;

        $height = $height + $lineHeightCalculated + $margin * 3;

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

        // Create a Guzzle HTTP client (and disable SSL cert check)
        $client = new Client([
            RequestOptions::VERIFY => false
        ]);

        // Make a GET request
        $response = $client->get($apiUrl);

        // Get the response body as a JSON object
        $responseData = json_decode($response->getBody(), true);

        // Access the translated text
        $translatedText = $responseData['data']['translations'][0]['translatedText'];

        // Fix characters like &gt; = >
        $translatedText = $this->fixSpecialCharacters($translatedText);

        $this->translateApiCalls++;

        // Add text to log later
        $this->logText .= "API Call Number: $this->translateApiCalls \n" .
            "Original text  : $textToTranslate \n" .
            "Translated text: $translatedText \n";

        // Output the translated text
        return $translatedText;
    }

    private function fixSpecialCharacters($text)
    {
        $specialCharacters = [
            '&gt;'    => '>',
            '&lt;'    => '<',
            '&amp;'   => '&',
            '&quot;'  => '"',
            '&apos;'  => "'",
            '&nbsp;'  => ' ',
            '&copy;'  => '©',
            '&reg;'   => '®',
            '&trade;' => '™',
            '&euro;'  => '€',
            '&cent;'  => '¢',
            '&pound;' => '£',
            '&yen;'   => '¥',
            '&sect;'  => '§',
            '&deg;'   => '°',
            '&plusmn;' => '±',
            '&micro;' => 'µ',
            '&para;'  => '¶',
            '&middot;' => '·',
            '&raquo;' => '»',
            '&laquo;' => '«',
            '&mdash;' => '—',
            '&ndash;' => '–',
            '&frac14;' => '¼',
            '&frac12;' => '½',
            '&frac34;' => '¾',
            '&times;'  => '×',
            '&divide;' => '÷',
        ];

        foreach ($specialCharacters as $characters => $replaceWith) {
            $text = str_replace($characters, $replaceWith, $text);
        }

        return $text;
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

        // read image from file system
        $image = Image::make(base_path('storage/app/' . $inputPath));

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
                    $font->color(0, 0, 0);
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

    private function placeImageOverlay($leftStart, $topStart, $videoID, $videoName,  $imageNumber, $iteration)
    {
        // If video already edited, then edit further
        if ($iteration == 0) {
            $videoChunkInputPath = storage_path('app/videos/processing/' . $videoID . '/' . $videoName . '_part_' . $imageNumber . '.mp4');
        } else {
            $videoChunkInputPath = storage_path('app/videos/processing/' . $videoID . '/' . $videoName . '_part_' . $imageNumber . '_translated_iteration_' . $iteration - 1 . '.mp4');
        }

        $image = storage_path('app/images/processing/' . $videoID . '/' . $videoName . '_' . $imageNumber . '_translated.png');
        $videoChunkOutputPath = storage_path('app/videos/processing/' . $videoID . '/' . $videoName . '_part_' . $imageNumber . '_translated_iteration_' . $iteration . '.mp4');

        $ffmpegCommand = [
            env('FFMPEG_BINARIES'),
            '-y', // -y option for overwrite
            '-i', $videoChunkInputPath,
            '-i', $image,
            '-filter_complex', "overlay=$leftStart:$topStart",
            '-c:v', 'libx264', // Set the video codec explicitly
            '-preset', 'ultrafast',
            '-c:a', 'copy',
            $videoChunkOutputPath,
        ];

        $this->runProcess($ffmpegCommand);

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

    private function addToTxtList($videoID, $videoName,  $imageNumber, $iteration, $mergeTranslatedPart)
    {
        $fileName = $videoName . '_video_parts_list.txt';
        $fileFullPath = "output/$videoID/$fileName";

        if ($mergeTranslatedPart) {
            $videoName = storage_path('app/videos/processing/' . $videoID . '/' . $videoName . '_part_' . $imageNumber . '_translated_iteration_' . $iteration . '.mp4');
        }
        // If there was no text to translate
        else {
            $videoName = storage_path('app/videos/processing/' . $videoID . '/' . $videoName . '_part_' . $imageNumber . '.mp4');
        }

        $contents = "file '$videoName'";

        // Append video part path to txt file
        Storage::disk('local')->append($fileFullPath, $contents);
    }

    private function mergeVideoParts($videoID, $videoName)
    {
        $fileName = $videoName . '_video_parts_list.txt';
        $fileFullPath = base_path("storage/app/output/$videoID/$fileName");

        $videoOutput = storage_path('app/videos/completed/' . $videoID . "/" . $videoName . '_translated.mp4');

        $ffmpegCommand = [
            env('FFMPEG_BINARIES'),
            '-y', // -y option for overwrite
            '-safe', '0',
            '-f', 'concat',
            '-i', $fileFullPath,
            '-c', 'copy',
            $videoOutput,
        ];

        $this->runProcess($ffmpegCommand);
    }

    private function fixAudio($videoID, $videoName, $videoFileExtension, $translate_audio)
    {
        $videoNameWithExtension = "$videoName.$videoFileExtension";
        $originalVideoPath = storage_path("/app/videos/new/$videoID/$videoNameWithExtension");
        $translatedVideoPath = storage_path('app/videos/completed/' . $videoID . '/' . $videoName . '_translated.mp4');
        $translatedVideoPathWithAudioFixPath = storage_path('app/videos/completed/' . $videoID . '/' . $videoName . '_translated_audio_fixed.mp4');

        $audioFormat = $this->recognizeAudioFormat($originalVideoPath, $videoID, $videoNameWithExtension);

        $extractedAudioPath = storage_path('app/audio/processing/' . $videoID . '/' . $videoName);
        $this->extractAudio($originalVideoPath, $extractedAudioPath, $audioFormat);

        // If user wants to translate speech
        //TO DO get output from user, does he want to translate speech in video
        if ($translate_audio) {
            // Get speech data from audio
            $whisperOutputDir = storage_path('app/audio/processing/' . $videoID);
            $speechData = $this->whisperRecognizeSpeech($extractedAudioPath, $audioFormat, $whisperOutputDir);

            $translatedTextPath = $this->translateAndSaveSpeech($speechData, $extractedAudioPath);

            $this->coquiAISpeechGenerate($translatedTextPath, $extractedAudioPath);

            //TO DO Replace below with translated audio path
            $replactAudioWith = $extractedAudioPath . '_generated_speech.wav';
        } else {
            // Apply default audio fix
            $replactAudioWith = "$extractedAudioPath.$audioFormat";
        }

        //Replace audio
        $ffmpegCommand = [
            env('FFMPEG_BINARIES'),
            '-y', // -y option for overwrite
            '-i', $translatedVideoPath,
            '-i', $replactAudioWith,
            '-c:v', 'copy',
            '-map', '0:v:0',
            '-map', '1:a:0',
            $translatedVideoPathWithAudioFixPath,
        ];

        $this->runProcess($ffmpegCommand);
    }

    private function recognizeAudioFormat($originalVideoPath)
    {
        $ffprobeCommand = [
            env('FFPROBE_BINARIES'),
            '-v', 'error',
            '-select_streams', 'a:0',
            '-show_entries', 'stream=codec_name',
            '-of', 'default=nokey=1:noprint_wrappers=1',
            $originalVideoPath,
        ];

        $processOutput = $this->runProcess($ffprobeCommand);

        $audioFormat = trim($processOutput);

        return $audioFormat;
    }

    private function extractAudio($originalVideoPath, $extractedAudioPath, $audioFormat)
    {
        $ffmpegCommand = [
            env('FFMPEG_BINARIES'),
            '-y', // -y option for overwrite
            '-i', $originalVideoPath,
            '-vn', '-acodec',
            'copy',
            '-f', $audioFormat,
            "$extractedAudioPath.$audioFormat",
        ];

        $this->runProcess($ffmpegCommand);
    }

    private function whisperRecognizeSpeech($extractedAudioPath, $audioFormat, $whisperOutputDir)
    {
        $path = base_path('python/Whisper.py');
        $modulesPath = base_path('python/modules');

        $process = new Process(['py', $path, $modulesPath, $extractedAudioPath, $audioFormat, $whisperOutputDir]);

        // Set infinite timeout
        $process->setTimeout(0);
        $process->run();

        // Show any errors
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        $json = File::get("$extractedAudioPath.json");
        $speechData = json_decode($json, true);

        return $speechData;
    }

    private function translateAndSaveSpeech($speechData, $extractedAudioPath)
    {
        // Add text to log later
        $this->logText .= "Speech translation \n";

        $translatedText = $this->translateText($speechData['text']);
        // Convert data to JSON format
        $translatedTextJson = json_encode(['text' => $translatedText]);
        // Specify the file path
        $translatedTextPath = $extractedAudioPath . '_translated_speech.json';
        // Save data to the file
        File::put($translatedTextPath, $translatedTextJson);

        return $translatedTextPath;
    }

    private function coquiAISpeechGenerate($translatedTextPath, $audioOutputPath)
    {
        $path = base_path('python/Coqui_ai.py');
        $modulesPath = base_path('python/modules');

        $process = new Process(['py', $path, $modulesPath, $translatedTextPath, $audioOutputPath]);

        // Set infinite timeout
        $process->setTimeout(0);
        $process->run();

        // Show any errors
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }

    private function cleanUp($videoID)
    {
        // Folders to cleanup
        $folders = [
            storage_path("app/audio/processing/$videoID"),
            storage_path("app/images/processing/$videoID"),
            storage_path("app/output/$videoID"),
            storage_path("app/videos/processing/$videoID"),
        ];

        // Delete folders
        foreach ($folders as $folderPath) {
            File::deleteDirectory($folderPath);
        }
    }
}
