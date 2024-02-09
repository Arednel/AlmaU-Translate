<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use DateTime;

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
    public $tries = 3;

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
        app()->call('App\Http\Controllers\VideoProcessingController@processVideo', ['videoID' => $this->videoID, 'videoNameWithExtension' => $this->videoNameWithExtension]);
    }

    public function retryUntil(): DateTime
    {
        return now()->addMinutes(600);
    }
}
