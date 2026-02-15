<?php

namespace App\Console\Commands;

use App\Models\Message;
use Illuminate\Console\Command;

class CleanupExpiredMessages extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'messages:cleanup';

    /**
     * The console command description.
     */
    protected $description = 'Delete messages that have passed their expires_at timestamp';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $deleted = Message::whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->delete();

        $this->info("Cleaned up {$deleted} expired message(s).");

        return self::SUCCESS;
    }
}
