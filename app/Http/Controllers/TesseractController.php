<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\File;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class TesseractController extends Controller
{
    public static function getTextFromImage($videoID, $videoName, $imageNumber)
    {
        $storageDir = storage_path('app/');

        // Run pytesseract
        $path = base_path('python/Tesseract.py');
        $process = new Process(['py', $path, $videoID, $videoName, $imageNumber, $storageDir]);
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
            if (
                $dataArray['text'][$key] != '' &&
                $dataArray['text'][$key] != ' '
            ) {
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

                // Calculate font size, if there any text
                if ($textBlocks[$previousBlockNum]['text']) {
                    $textBlocks[$previousBlockNum]['fontSize'] = TesseractController::calculateFontSize($textBlocks[$previousBlockNum]);
                }
            }
            // Set previous block value as current block
            $previousBlockNum = $value;
        }

        $textBlocks = TesseractController::cleanUpTextBlocks($textBlocks);

        $textBlocks = TesseractController::mergeCloseTextBlocks($textBlocks);

        return $textBlocks;
    }

    private static function calculateFontSize($textBlock)
    {
        $lineWidth = $textBlock['leftEnd'] - $textBlock['leftStart'];

        // Translated text
        $text = $textBlock['text'];

        // Calculate font size
        $fontSize = 5;
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

        //Make font size slightly smaller
        $fontSize--;

        return $fontSize;
    }

    private static function cleanUpTextBlocks($textBlocks)
    {
        // Remove english only and 1 or 2 character text blocks
        foreach ($textBlocks as $key => $textBlock) {
            if (
                preg_match('/^[a-zA-Z ]+$/', $textBlock['text']) ||
                strlen($textBlock['text']) < 3
            ) {
                unset($textBlocks[$key]);
            }
        }

        return $textBlocks;
    }

    private static function mergeCloseTextBlocks($textBlocks)
    {
        $previousTextBlock = null;

        foreach ($textBlocks as $key => $textBlock) {
            if ($previousTextBlock) {
                // If current text block inside previous text block
                if (
                    $textBlock['leftStart'] >= $previousTextBlock['leftStart']
                    && $textBlock['leftStart'] <= $previousTextBlock['leftEnd']
                    && $textBlock['topStart'] >= $previousTextBlock['topStart']
                    && $textBlock['topStart'] <= $previousTextBlock['topEnd']
                ) {
                    // Merge text in text blocks
                    $textBlocks[$key - 1]['text'] .= ' ' . $textBlocks[$key]['text'];

                    // Set size
                    $textBlocks[$key - 1]['leftEnd'] = $textBlocks[$key]['leftEnd'];
                    $textBlocks[$key - 1]['topEnd'] = $textBlocks[$key]['topEnd'];

                    // Calculate amount of lines to add
                    $currentBlockLineHegiht = ($textBlock['topEnd'] - $textBlock['topStart']) / $textBlock['lineNum'];
                    $extraLines = intval(
                        floor(
                            ($textBlock['topEnd'] - $previousTextBlock['topEnd']) / $currentBlockLineHegiht
                        )
                    );
                    $textBlocks[$key - 1]['lineNum'] += $extraLines;

                    // Calculate font size
                    $textBlocks[$key - 1]['fontSize'] = TesseractController::calculateFontSize($textBlocks[$key - 1]);

                    //TO DO Fix
                    // unset($textBlocks[$key]);
                }
            }
            $previousTextBlock = $textBlock;
        }

        return $textBlocks;
    }
}
