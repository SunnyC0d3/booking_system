<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\CalendarIntegration;
use App\Constants\V1\CalendarProviders;
use Symfony\Component\HttpFoundation\Response;

class VerifyWebhookSignature
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            // Get the provider from the route
            $provider = $this->getProviderFromRoute($request);

            if (!$provider) {
                Log::warning('Webhook received without valid provider', [
                    'url' => $request->url(),
                    'headers' => $request->headers->all(),
                ]);

                return response()->json([
                    'error' => 'Invalid webhook provider'
                ], 400);
            }

            // Verify signature based on provider
            $isValid = match ($provider) {
                CalendarProviders::GOOGLE => $this->verifyGoogleWebhook($request),
                CalendarProviders::OUTLOOK => $this->verifyOutlookWebhook($request),
                default => $this->verifyGenericWebhook($request, $provider)
            };

            if (!$isValid) {
                Log::warning('Invalid webhook signature', [
                    'provider' => $provider,
                    'ip' => $request->ip(),
                    'headers' => $request->headers->all(),
                ]);

                return response()->json([
                    'error' => 'Invalid webhook signature'
                ], 401);
            }

            // Add provider to request for downstream use
            $request->merge(['webhook_provider' => $provider]);

            Log::info('Valid webhook received', [
                'provider' => $provider,
                'ip' => $request->ip(),
            ]);

            return $next($request);

        } catch (\Exception $e) {
            Log::error('Webhook signature verification failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'error' => 'Webhook verification failed'
            ], 500);
        }
    }

    /**
     * Get provider from route name or path
     */
    private function getProviderFromRoute(Request $request): ?string
    {
        $path = $request->path();

        if (str_contains($path, 'google')) {
            return CalendarProviders::GOOGLE;
        }

        if (str_contains($path, 'outlook')) {
            return CalendarProviders::OUTLOOK;
        }

        if (str_contains($path, 'ical')) {
            return CalendarProviders::ICAL;
        }

        return null;
    }

    /**
     * Verify Google Calendar webhook
     */
    private function verifyGoogleWebhook(Request $request): bool
    {
        // Google uses X-Goog-Channel-Token for verification
        $channelToken = $request->header('X-Goog-Channel-Token');
        $resourceId = $request->header('X-Goog-Resource-ID');
        $channelId = $request->header('X-Goog-Channel-ID');

        if (!$channelToken || !$resourceId) {
            return false;
        }

        // Find integration by resource ID or channel ID
        $integration = CalendarIntegration::where('provider', CalendarProviders::GOOGLE)
            ->where(function ($query) use ($resourceId, $channelId) {
                $query->whereJsonContains('sync_settings->resource_id', $resourceId)
                    ->orWhereJsonContains('sync_settings->channel_id', $channelId);
            })
            ->first();

        if (!$integration) {
            Log::warning('Google webhook integration not found', [
                'resource_id' => $resourceId,
                'channel_id' => $channelId,
            ]);
            return false;
        }

        // Verify the channel token
        $expectedToken = $integration->sync_settings['webhook_token'] ?? null;

        if (!$expectedToken) {
            return false;
        }

        return hash_equals($expectedToken, $channelToken);
    }

    /**
     * Verify Outlook webhook
     */
    private function verifyOutlookWebhook(Request $request): bool
    {
        // Handle Outlook subscription validation
        $validationToken = $request->query('validationToken');
        if ($validationToken) {
            // This is a subscription validation request
            return response($validationToken, 200)
                ->header('Content-Type', 'text/plain');
        }

        // Verify client state for regular webhooks
        $clientState = $request->header('ClientState');

        if (!$clientState) {
            return false;
        }

        // Find integration by client state
        $integration = CalendarIntegration::where('provider', CalendarProviders::OUTLOOK)
            ->whereJsonContains('sync_settings->client_state', $clientState)
            ->first();

        return $integration !== null;
    }

    /**
     * Verify generic webhook (for iCal or other providers)
     */
    private function verifyGenericWebhook(Request $request, string $provider): bool
    {
        // For iCal webhooks, verify integration exists
        if ($provider === CalendarProviders::ICAL) {
            $integrationId = $request->route('integration');

            if (!$integrationId) {
                return false;
            }

            $integration = CalendarIntegration::where('id', $integrationId)
                ->where('provider', CalendarProviders::ICAL)
                ->where('is_active', true)
                ->first();

            return $integration !== null;
        }

        // For other providers, implement specific verification logic
        return true;
    }

    /**
     * Verify webhook timestamp to prevent replay attacks
     */
    private function verifyTimestamp(Request $request, int $toleranceSeconds = 300): bool
    {
        $timestamp = $request->header('X-Webhook-Timestamp');

        if (!$timestamp) {
            return true; // Skip if no timestamp provided
        }

        $webhookTime = (int) $timestamp;
        $currentTime = time();

        // Check if webhook is within tolerance window
        return abs($currentTime - $webhookTime) <= $toleranceSeconds;
    }

    /**
     * Log webhook verification attempt
     */
    private function logVerificationAttempt(Request $request, string $provider, bool $success): void
    {
        Log::info('Webhook signature verification', [
            'provider' => $provider,
            'success' => $success,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'headers' => [
                'x-goog-channel-token' => $request->header('X-Goog-Channel-Token') ? '[PRESENT]' : '[MISSING]',
                'x-goog-resource-id' => $request->header('X-Goog-Resource-ID'),
                'client-state' => $request->header('ClientState') ? '[PRESENT]' : '[MISSING]',
            ],
        ]);
    }
}
