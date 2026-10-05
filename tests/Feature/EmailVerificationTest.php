<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_sends_verification_email(): void
    {
        Notification::fake();

        $response = $this->post('/register', [
            'name' => 'メール認証ユーザー',
            'email' => 'verify@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $user = User::where('email', 'verify@example.com')->firstOrFail();

        $response->assertRedirect(route('verification.notice'));

        Notification::assertSentTo(
            $user,
            VerifyEmail::class
        );
    }

    public function test_verification_page_has_mailpit_link(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)
            ->get('/email/verify');

        $response->assertOk();
        $response->assertSee('認証はこちらから');
        $response->assertSee('http://localhost:8025', false);
    }

    public function test_user_can_verify_email_and_is_redirected_to_attendance(): void
    {
        Event::fake();

        $user = User::factory()->unverified()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $user->id,
                'hash' => sha1($user->email),
            ]
        );

        $response = $this->actingAs($user)
            ->get($verificationUrl);

        Event::assertDispatched(Verified::class);

        $this->assertTrue(
            $user->fresh()->hasVerifiedEmail()
        );

        $response->assertRedirect('/attendance?verified=1');

        $attendanceResponse = $this->actingAs($user->fresh())
            ->get('/attendance');

        $attendanceResponse->assertOk();
    }
}