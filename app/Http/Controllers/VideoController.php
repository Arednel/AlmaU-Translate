<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use TCG\Voyager\Http\Controllers\VoyagerBaseController;

class VideoController extends VoyagerBaseController
{
    public function store(Request $request)
    {
        dd($request);
    }
}
