<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Laravel\Passport\Token;

class RevokeExpiredTokens extends Command
{
    protected $signature = 'tokens:revoke-expired';
    protected $description = 'Revokes all expired access tokens';

    public function handle()
    {
        $expiredTokens = Token::where('expires_at', '<', now())
            ->where('revoked', false)
            ->get();

        foreach ($expiredTokens as $token) {
            $token->revoke();
        }

        $this->info("Revoked {$expiredTokens->count()} expired tokens.");
    }
}
