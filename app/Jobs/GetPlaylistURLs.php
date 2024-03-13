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

use App\Http\Controllers\VideoController;

class GetPlaylistURLs implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $translateAudio;
    protected $playlistURL;

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
    public function __construct($translateAudio, $playlistURL)
    {
        $this->translateAudio = $translateAudio;
        $this->playlistURL = $playlistURL;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $storageDir = storage_path();

        // Get all video links from playlist
        $path = base_path('python/GetLinks.py');
        $modulesPath = base_path('python/modules');

        $process = new Process(['py', $path, $modulesPath, $this->playlistURL, $storageDir]);

        // Set infinite timeout
        $process->setTimeout(0);
        $process->run();

        // Show any errors
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        $json = File::get("$storageDir/app/playlists/playlist.json");
        $playlistURLs = json_decode($json, true);
        foreach ($playlistURLs as $videoURL) {
            VideoController::downloadVideo($this->translateAudio, $videoURL);
        }
    }

    public function retryUntil(): DateTime
    {
        return now()->addMinutes(600);
    }
}
