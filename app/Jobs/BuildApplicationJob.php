<?php

namespace App\Jobs;

use App\Models\AppBuild;
use App\Services\BuildService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class BuildApplicationJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    /**
     * Create a new job instance.
     */
    public function __construct(public AppBuild $build)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(BuildService $buildService): void
    {
        $buildService->executeBuild($this->build);
    }
}
