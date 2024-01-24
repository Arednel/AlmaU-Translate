<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\File;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

use ProtoneMedia\LaravelFFMpeg\FFMpeg\FFProbe;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;

class TesseractController extends Controller
{
    public function processVideo()
    {
        // Kinda fixed
        $ffprobe = FFProbe::create([
            "ffprobe.binaries" => env('FFPROBE_BINARIES')
        ]);

        $videoName = "1.mkv";
        $videoPath = storage_path("/app/videos/"  . $videoName);

        // Get video duration
        $videoDuration = $ffprobe
            ->format($videoPath) // extracts file informations
            ->get('duration');
        // To int
        $videoDuration = intval($videoDuration);

        // For each second take screenshot and process it via Tesseract
        for ($i = 1; $i < $videoDuration; $i++) {
            FFMpeg::fromDisk("local")
                ->open("videos/" . $videoName)
                ->getFrameFromSeconds($i)
                ->export()
                ->toDisk("local")
                ->save("images/videoImage.jpg");

            // Get data from image(text, location, etc.)
            $dataArray = $this->imageProcess();

            // Get text as blocks
            $textBlocks = $this->getTextBlocks($dataArray);

            dd($dataArray, $textBlocks);
        }
        return $videoDuration;
    }

    private function imageProcess()
    {
        // Run pytesseract
        $path = base_path("python/Tesseract.py");
        $process = new Process(["py", $path]);
        $process->run();

        // Show any errors
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        // Get output from file
        $filePath = base_path("storage/app/output/output.json");
        $fileContents = File::get($filePath);

        // Delete file
        File::delete($filePath);

        $dataArray = json_decode($fileContents, true);

        return $dataArray;
    }

    private function getTextBlocks($dataArray)
    {
        $textBlocks = [];
        $previousBlockNum = 0;
        // Get text as blocks
        foreach ($dataArray["block_num"] as $key => $value) {
            // If in this block exists any text
            if ($dataArray["text"][$key] != "") {
                // Check if it is new block, then set (first) or add to (second) value
                if (array_key_exists($previousBlockNum, $textBlocks)) {
                    $textBlocks[$previousBlockNum] .= " " . $dataArray["text"][$key];
                } else {
                    $textBlocks[$previousBlockNum] = $dataArray["text"][$key];
                }
            }

            // Set block value
            $previousBlockNum = $value;
        }

        return $textBlocks;
    }
}
