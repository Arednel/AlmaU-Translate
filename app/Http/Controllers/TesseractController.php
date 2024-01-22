<?php

namespace App\Http\Controllers;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

use Illuminate\Support\Facades\File;

class TesseractController extends Controller
{
    public function imageProcess()
    {
        // Run pytesseract
        $path = base_path() . "\python\Tesseract.py ";
        $process = new Process(["py", $path]);
        $process->run();

        // Show any errors
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        // Get output from file
        $filePath = base_path('python\output.json');
        $fileContents = File::get($filePath);
        // Delete file
        File::delete($filePath);

        $dataArray = json_decode($fileContents, true);

        dd($dataArray);
    }
}
