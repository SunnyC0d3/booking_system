<?php

namespace App\Console\Commands\V1;

use Illuminate\Console\Command;
use App\Models\PersonalAccessToken;

class DeleteExpiredTokens extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tokens:delete-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete expired access and refresh tokens';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $expiredTokens = PersonalAccessToken::expired()->get();

        $count = $expiredTokens->count();

        $expiredTokens->each->delete();

        $this->info("$count expired tokens have been deleted.");
    }
}
