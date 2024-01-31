<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\File;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class TesseractController extends Controller
{
    public static function getTextFromImage($videoID, $videoName, $imageNumber)
    {
        // Run pytesseract
        $path = base_path('python/Tesseract.py');
        $process = new Process(['py', $path, $videoID, $videoName, $imageNumber]);
        $process->run();

        // Show any errors
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        // Get output from file
        $outputPath = base_path('storage/app/output/' . $videoID . "/" . $videoName . "_" . $imageNumber . '_output.json');
        $outputContents = File::get($outputPath);

        $dataArray = json_decode($outputContents, true);

        $textBlocks = TesseractController::getTextBlocks($dataArray);

        return $textBlocks;
    }

    private static function getTextBlocks($dataArray)
    {
        $textBlocks = [];
        $previousBlockNum = 0;

        // Get text as blocks
        foreach ($dataArray['block_num'] as $key => $value) {
            // If in this block exists any text
            if ($dataArray['text'][$key] != '') {
                // Check if it is new block, then set (first) or add to (second) value
                if (array_key_exists($previousBlockNum, $textBlocks)) {
                    $textBlocks[$previousBlockNum]['text'] .= ' ' . $dataArray['text'][$key];
                } else {
                    $textBlocks[$previousBlockNum]['text'] = $dataArray['text'][$key];

                    // Block borders
                    $textBlocks[$previousBlockNum]['leftStart'] = $dataArray['left'][$key];
                    $textBlocks[$previousBlockNum]['topStart'] = $dataArray['top'][$key];
                    $textBlocks[$previousBlockNum]['leftEnd'] = $dataArray['left'][$key] + $dataArray['width'][$key];
                }
                // Set box end from left
                if (array_key_exists('leftEnd', $textBlocks[$previousBlockNum])) {
                    // If current leftEnd is smaller then set it
                    if ($textBlocks[$previousBlockNum]['leftEnd'] < $dataArray['left'][$key] + $dataArray['width'][$key]) {
                        $textBlocks[$previousBlockNum]['leftEnd'] = $dataArray['left'][$key] + $dataArray['width'][$key];
                    }
                }

                //Set box end from top
                $textBlocks[$previousBlockNum]['topEnd'] = $dataArray['top'][$key] + $dataArray['height'][$key];
                $textBlocks[$previousBlockNum]['lineNum'] = $dataArray['line_num'][$key];
            }

            // Set block value
            $previousBlockNum = $value;
        }

        $textBlocks = TesseractController::cleanUpTextBlocks($textBlocks);

        return $textBlocks;
    }

    private static function cleanUpTextBlocks($textBlocks)
    {
        // Remove empty, english only and 1 or 2 character arrays
        foreach ($textBlocks as $key => $value) {
            if (
                $value['text'] == ' ' ||
                preg_match('/^[a-zA-Z ]+$/', $value['text']) ||
                strlen($value['text']) < 3
            ) {
                unset($textBlocks[$key]);
            }
        }

        return $textBlocks;
    }
}
