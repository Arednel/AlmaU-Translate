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
        $modulesPath = base_path('python/modules');

        $process = new Process(['py', $path, $modulesPath, $videoID, $videoName, $imageNumber, $storageDir]);

        // Set infinite timeout
        $process->setTimeout(0);
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
        $textBlockNumber = 0;

        $previousBlockNum = $dataArray['block_num'][0];
        $previousParNum = $dataArray['par_num'][0];

        // Get text as blocks
        foreach ($dataArray['block_num'] as $key => $value) {
            // If in this block exists any text
            if (
                $dataArray['text'][$key] != '' &&
                $dataArray['text'][$key] != ' '
            ) {
                // Check if it is new block or diffirent par_num
                $isNewBlock = !array_key_exists($textBlockNumber, $textBlocks) ||
                    ($textBlocks[$textBlockNumber]['previousBlockNum'] != $previousBlockNum || $textBlocks[$textBlockNumber]['previousParNum'] != $previousParNum);

                if ($isNewBlock) {
                    $textBlockNumber++;

                    $textBlocks[$textBlockNumber] = [
                        'previousBlockNum' => $previousBlockNum,
                        'previousParNum' => $previousParNum,
                        'text' => $dataArray['text'][$key],
                        'leftStart' => $dataArray['left'][$key],
                        'topStart' => $dataArray['top'][$key],
                        'leftEnd' => $dataArray['left'][$key] + $dataArray['width'][$key],
                    ];
                } else {
                    $textBlocks[$textBlockNumber]['text'] .= ' ' . $dataArray['text'][$key];
                }

                // If current leftEnd is smaller then set it
                if ($textBlocks[$textBlockNumber]['leftEnd'] < $dataArray['left'][$key] + $dataArray['width'][$key]) {
                    $textBlocks[$textBlockNumber]['leftEnd'] = $dataArray['left'][$key] + $dataArray['width'][$key];
                }

                //Set box end from top
                $textBlocks[$textBlockNumber]['topEnd'] = $dataArray['top'][$key] + $dataArray['height'][$key];
                $textBlocks[$textBlockNumber]['lineNum'] = $dataArray['line_num'][$key];

                // Calculate font size
                $textBlocks[$textBlockNumber]['fontSize'] = TesseractController::calculateFontSize($textBlocks[$textBlockNumber]);
            }

            // Set previous block value as current block
            $previousBlockNum = $value;
            // Set previous par_num value as current par_num
            $previousParNum = $dataArray['par_num'][$key];
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
                $fontSize = 5;
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
                    $textBlocks[$key]['text'] = $previousTextBlock['text'] . ' ' . $textBlock['text'];

                    // Calculate amount of lines to add
                    $currentBlockLineHegiht = ($textBlock['topEnd'] - $textBlock['topStart']) / $textBlock['lineNum'];
                    $extraLines = intval(
                        floor(
                            ($textBlock['topStart'] - $previousTextBlock['topStart']) / $currentBlockLineHegiht
                        )
                    );
                    $textBlocks[$key]['lineNum'] += $extraLines;

                    // Set size
                    $textBlocks[$key]['leftStart'] = $previousTextBlock['leftStart'];
                    $textBlocks[$key]['topStart'] = $previousTextBlock['topStart'];
                    if ($previousTextBlock['leftEnd'] > $textBlock['leftEnd']) {
                        $textBlocks[$key]['leftEnd'] = $previousTextBlock['leftEnd'];
                    }
                    if ($previousTextBlock['topEnd'] > $textBlock['topEnd']) {
                        $textBlocks[$key]['topEnd'] = $previousTextBlock['topEnd'];
                    }

                    // Calculate font size
                    $textBlocks[$key]['fontSize'] = TesseractController::calculateFontSize($textBlocks[$key]);

                    //Remove previous textBlock
                    unset($textBlocks[$key - 1]);
                }
            }
            $previousTextBlock = $textBlock;
        }

        return $textBlocks;
    }
}
