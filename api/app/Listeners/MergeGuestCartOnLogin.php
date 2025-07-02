<?php

namespace App\Listeners;

use App\Services\V1\Cart\CartService;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MergeGuestCartOnLogin
{
    private CartService $cartService;
    private Request $request;

    public function __construct(CartService $cartService, Request $request)
    {
        $this->cartService = $cartService;
        $this->request = $request;
    }

    public function handle(Login $event): void
    {
        try {
            $user = $event->user;
            $sessionId = $this->request->session()->getId();

            if ($user && $sessionId) {
                $this->cartService->mergeGuestCart($sessionId, $user->id);

                Log::info('Guest cart merge attempted on login', [
                    'user_id' => $user->id,
                    'session_id' => $sessionId,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to merge guest cart on login', [
                'user_id' => $event->user->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
