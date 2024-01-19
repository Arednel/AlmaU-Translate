<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use thiagoalessio\TesseractOCR\TesseractOCR;

class TesseractController extends Controller
{
    public function imageProcess()
    {
        $imagePath = public_path();

        echo (new TesseractOCR($imagePath . '/Images/1.png'))
            ->lang('rus', 'eng')
            ->run();
    }
}
