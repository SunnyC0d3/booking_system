<?php

namespace Tests\Feature\App\Http\Controllers\V1;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Notification;
use Illuminate\Auth\Notifications\VerifyEmail;

class EmailVerificationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_verifies_email_when_valid_signature_is_provided()
    {
        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        $signedUrl = URL::signedRoute('verification.verify', ['id' => $user->id, 'hash' => sha1($user->email)]);

        $response = $this->get($signedUrl);

        $response->assertRedirect(env('APP_URL_FRONTEND') . env('APP_URL_FRONTEND_EMAIL_VERIFIED'));
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_fails_to_verify_email_with_invalid_signature()
    {
        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        $invalidUrl = route('verification.verify', ['id' => $user->id, 'hash' => 'invalidhash']);

        $response = $this->get($invalidUrl);

        $response->assertStatus(403);
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_resends_verification_email_to_unverified_user()
    {
        Notification::fake();

        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        $this->actingAs($user, 'api');

        $response = $this->getJson(route('verification.resend'));

        $response->assertJson([
            'message' => 'Email verification link sent on your email id.',
            'status' => 200,
        ]);

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_does_not_resend_email_to_verified_user()
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user, 'api');

        $response = $this->getJson(route('verification.resend'));

        $response->assertJson([
            'message' => 'Email already verified.',
            'status' => 400,
        ]);
    }
}