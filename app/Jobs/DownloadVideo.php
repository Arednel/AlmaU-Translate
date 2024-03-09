<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use DateTime;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

use App\Models\Video;

class DownloadVideo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $videoID;
    protected $videoURL;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 5;

    //pcntl PHP extension must be installed to infinite (0) timeout to work
    public $timeout = 0;

    /**
     * Create a new job instance.
     */
    public function __construct($videoID, $videoURL)
    {
        $this->videoID = $videoID;
        $this->videoURL = $videoURL;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $video = Video::find($this->videoID);

        // Check if video is still needed processing (or deleted by user)
        if ($video) {
            $this->downloadVideo($this->videoID, $this->videoURL);

            $videoName = $this->getVideoName($this->videoID);

            $video = Video::find($this->videoID);
            $video->name = $videoName;
            $video->save();
            TranslateVideo::dispatch($this->videoID, $videoName);
        } else {
            // Log that video is deleted
            Log::channel('translation')->info(
                "Video ID: $this->videoID \n" .
                    "This video is deleted from database by user request or error happenned \n" .
                    "Processing stopped, cleaning up folders"
            );

            // Delete folders
            $this->removeFolders();
        }
    }

    public function retryUntil(): DateTime
    {
        return now()->addMinutes(600);
    }

    private function downloadVideo($videoID, $videoURL)
    {
        //TODO Check if it is a single video or a playlist
        if (true) {
            $storageDir = storage_path();

            // Run yt-dlp download
            $path = base_path('python/VideoDownload.py');
            $modulesPath = base_path('python\modules');

            $process = new Process(['py', $path, $modulesPath, $videoID, $videoURL, $storageDir]);

            // Set infinite timeout
            $process->setTimeout(0);
            $process->run();

            // Show any errors
            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }
        } else {
        }
    }

    private function getVideoName($videoID)
    {
        // Get video file name (should be only one video file)
        $videoPath = storage_path("app/videos/new/$videoID");
        $files = File::files($videoPath);
        $videoName = $files[0]->getFilename();

        return $videoName;
    }

    private function removeFolders()
    {
        $videoID = $this->videoID;
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
