<?php

namespace App\Http\Controllers;

use TCG\Voyager\Http\Controllers\VoyagerBaseController;

use Illuminate\Http\Request;

use App\Models\Video;

use App\Jobs\TranslateVideo;

class VideoController extends VoyagerBaseController
{
    public function store(Request $request)
    {
        $request->validate([
            'video_file' => 'nullable|file|mimetypes:video/*',
            'video_url' => 'nullable|url',
        ]);

        if (
            !$request->has('video_file') &&
            (!$request->has('video_url') || $request->video_url == null)
        ) {
            // If neither vide_file nor video_url is provided, return validation error
            return redirect()->back()->withErrors('Укажите ссылку на видео либо загружите видеофайл');
        }

        // If video_url is present, then 
        if ($request->has('video_url') && $request->video_url != null) {
            // download video file via yt-dlp
            // TO DO do logic
            return redirect()->back()->withErrors('Загрузите файл, перевод по ссылке в разработке');
        } else {
            // save video file
            $file = $request->file('video_file');

            // Generate a unique file name
            $fileName = $file->getClientOriginalName();

            $video = Video::create();
            $videoID = $video->id;

            // Specify the directory where you want to save the file
            $filePath = "videos/new/$videoID";

            // Save the uploaded file to the specified directory
            $file->storeAs($filePath, $fileName);
        }

        $video->name = $fileName;
        $video->save();

        // Create translate job
        TranslateVideo::dispatch($videoID, $fileName);

        return redirect('admin/videos');
    }
}
