<?php

namespace App\Jobs;

use App\Services\Remote\CommandDispatcherService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExpireStaleCommandsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function handle(CommandDispatcherService $dispatcher): void
    {
        $dispatcher->expireStaleCommands();
    }
}
