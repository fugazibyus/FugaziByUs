<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_url_opens_the_login_modal_on_the_homepage(): void
    {
        $this->get(route('login'))->assertRedirect(route('home', ['auth' => 'login']));
    }

    public function test_users_can_log_in(): void
    {
        $user = User::factory()->create();

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'Fugazi-Test-123!',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('account.show', absolute: false));
        $response->assertInertiaFlash('toast.message', 'Welcome back!');
    }

    public function test_guests_are_sent_back_to_the_page_they_wanted_after_logging_in(): void
    {
        $user = User::factory()->create();

        $this->get(route('account.show'))->assertRedirect(route('login'));

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'Fugazi-Test-123!',
        ])->assertRedirect(route('account.show'));
    }

    public function test_users_can_not_log_in_with_a_wrong_password(): void
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_users_can_log_out(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect('/');
        $response->assertInertiaFlash('toast.type', 'info');
        $this->assertGuest();
    }

    public function test_logins_are_rate_limited(): void
    {
        $user = User::factory()->create();

        RateLimiter::increment(md5('login'.implode('|', [$user->email, '127.0.0.1'])), amount: 5);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertTooManyRequests();
    }
}
