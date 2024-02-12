<?php

namespace App\Http\Controllers;

use TCG\Voyager\Http\Controllers\VoyagerBaseController;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\File;

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

    public function destroy(Request $request, $id)
    {
        // Check if user wants delete multiple videos
        if ($request->input('ids')) {
            // Remove ',' from input
            $ids = explode(",", $request->input('ids'));

            // Delete each video
            foreach ($ids as $id) {
                $this->deleteVideo($id);
            }
        } else {
            // Delete single video
            $this->deleteVideo($id);
        }

        // Redirect user back
        return redirect('admin/videos');
    }

    private function deleteVideo($id)
    {
        // Find the Video model instance by ID
        $video = Video::find($id);

        // Check if the video exists
        if (!$video) {
            // If the video does not exist, redirect back with an error message
            return redirect()->back()->withErrors('Video not found');
        }

        // Delete video from database
        $video->delete();

        // Remove all video folders
        $this->removeFolders($video->id);
    }

    private function removeFolders($videoID)
    {
        // Folders to cleanup
        $folders = [
            storage_path("app/audio/processing/$videoID"),
            storage_path("app/images/processing/$videoID"),
            storage_path("app/output/$videoID"),
            storage_path("app/videos/completed/$videoID"),
            storage_path("app/videos/new/$videoID"),
            storage_path("app/videos/processing/$videoID"),
        ];

        // Delete folders
        foreach ($folders as $folderPath) {
            File::deleteDirectory($folderPath);
        }
    }
}
