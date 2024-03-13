<?php

namespace App\Http\Controllers;

use App\Models\Video;

use App\Jobs\TranslateVideo;
use App\Jobs\DownloadVideo;
use App\Jobs\GetPlaylistURLs;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

use TCG\Voyager\Http\Controllers\VoyagerBaseController;

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
            $translateAudio = $request->has('translate_audio');
            // If this link is link to a playlist
            if (
                str_contains($request->video_url, "playlist?list=")
                &&
                !str_contains($request->video_url, "watch?v=")
            ) {
                // Dispath job to get all URLs from playlist and then download and translate them
                GetPlaylistURLs::dispatch($translateAudio, $request->video_url);
            } else {
                // Download and translate video
                $this->downloadVideo($translateAudio, $request->video_url);
            }
        } else {
            // save video file
            $file = $request->file('video_file');

            // Generate a unique file name
            $videoName = $file->getClientOriginalName();

            $video = Video::create();
            $videoID = $video->id;

            // Specify the directory where you want to save the file
            $videoPath = "videos/new/$videoID";

            // Save the uploaded file to the specified directory
            $file->storeAs($videoPath, $videoName);

            // Set if user checked, then audio is also will be translated
            $video->translate_audio = $request->has('translate_audio');

            $video->name = $videoName;
            $video->save();

            // Create translate job
            TranslateVideo::dispatch($videoID, $videoName);
        }

        return redirect('admin/videos');
    }

    public static function downloadVideo($translateAudio, $videoURL)
    {
        $video = Video::create();
        $videoID = $video->id;

        // Set if user checked, then audio is also will be translated
        $video->translate_audio = $translateAudio;

        $video->save();

        // Temporarily set video name as video URL
        $video->name = $videoURL;
        $video->save();

        // download video file via yt-dlp
        DownloadVideo::dispatch($videoID, $videoURL);
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

        // Log that video is deleted
        Log::channel('translation')->info(
            "Video ID: $id \n" .
                "Video title: $video->name \n" .
                "Deleting video by user request..."
        );

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

    public function translatedView($id)
    {
        $video = Video::find($id);

        $videoPathFull = storage_path("/app/videos/new/$id/$video->name");

        // Get file name
        $videoName = pathinfo($videoPathFull, PATHINFO_FILENAME);

        $path = "videos/completed/$video->id/$videoName" . "_translated_audio_fixed.mp4";

        // Get file name
        $video = Storage::disk('local')->get($path);

        // If video exists
        if ($video) {
            // Fix fo chrome browser, ability to rewind video
            // Get file size in bytes
            $size = Storage::size($path);
            $start = 0;
            $end = $size - 1;
            $bytes = $start - $end / $size;
            $length = $end - $start + 1;

            return response($video, 200, [
                'Content-Type' => 'video/mp4',
                'Accept-Ranges' => 'bytes',
                'Content-Range' => "bytes $bytes",
                "Content-Length" => $length,
            ]);
        } else {
            return redirect('/admin/videos');
        }
    }

    public function translatedDownload($id)
    {
        $video = Video::find($id);

        $videoPathFull = storage_path("/app/videos/new/$id/$video->name");

        // Get file name
        $videoName = pathinfo($videoPathFull, PATHINFO_FILENAME);

        $path = "/videos/completed/$video->id/$videoName" . "_translated_audio_fixed.mp4";

        // Get file name
        $isVideoExists = Storage::disk('local')->exists($path);

        // If video exists
        if ($isVideoExists) {
            // Fix fo chrome browser
            // Get file size in bytes
            $size = Storage::size($path);
            $start = 0;
            $end = $size - 1;
            $bytes = $start - $end / $size;
            $length = $end - $start + 1;

            $headers = [
                'Content-Type' => 'video/mp4',
                'Accept-Ranges' => 'bytes',
                'Content-Range' => "bytes $bytes",
                "Content-Length" => $length,
            ];

            // Path to download video from
            $path = storage_path("/app/videos/completed/$video->id/$videoName" . "_translated_audio_fixed.mp4");

            return response()->download($path, $videoName . '_translated.mp4', $headers);
        } else {
            return redirect('/admin/videos');
        }
    }
}
