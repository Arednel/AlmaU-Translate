<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use DateTime;

use Illuminate\Support\Facades\File;

use App\Models\Video;

class TranslateVideo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $videoID;
    protected $videoNameWithExtension;

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
    public function __construct($videoID, $videoNameWithExtension)
    {
        $this->videoID = $videoID;
        $this->videoNameWithExtension = $videoNameWithExtension;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $video = Video::find($this->videoID);

        // Check if video is still needed processing (or deleted by user)
        if ($video) {
            app()->call('App\Http\Controllers\VideoProcessingController@processVideo', ['videoID' => $this->videoID, 'videoNameWithExtension' => $this->videoNameWithExtension]);
        } else {
            // TO DO Log that video is deleted

            // Delete folders
            $this->removeFolders();
        }
    }

    public function retryUntil(): DateTime
    {
        return now()->addMinutes(600);
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
