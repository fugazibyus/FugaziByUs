<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private const STRONG = 'Fugazi-Strong-1!';

    public function test_register_url_opens_the_register_modal_on_the_homepage(): void
    {
        $this->get(route('register'))->assertRedirect(route('home', ['auth' => 'register']));
    }

    public function test_new_users_can_register_with_just_email_and_password(): void
    {
        $response = $this->post(route('register.store'), [
            'email' => 'New.Person@Example.com',
            'password' => self::STRONG,
            'password_confirmation' => self::STRONG,
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('account.show', absolute: false));
        $response->assertInertiaFlash('toast.type', 'success');

        $user = User::firstOrFail();
        $this->assertSame('new.person@example.com', $user->email);
        $this->assertNull($user->first_name);
        $this->assertNull($user->last_name);
    }

    public function test_email_must_be_unique(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->post(route('register.store'), [
            'email' => 'taken@example.com',
            'password' => self::STRONG,
            'password_confirmation' => self::STRONG,
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_password_must_be_confirmed(): void
    {
        $this->post(route('register.store'), [
            'email' => 'someone@example.com',
            'password' => self::STRONG,
            'password_confirmation' => 'Something-Else-2!',
        ])->assertSessionHasErrors('password');

        $this->assertGuest();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function weakPasswords(): array
    {
        return [
            'too short' => ['Short-1!'],
            'no uppercase' => ['all-lowercase-123!'],
            'no lowercase' => ['ALL-UPPERCASE-123!'],
            'no number' => ['No-Numbers-Here!'],
            'no symbol' => ['NoSymbolsHere123'],
        ];
    }

    #[DataProvider('weakPasswords')]
    public function test_weak_passwords_are_rejected(string $password): void
    {
        $this->post(route('register.store'), [
            'email' => 'someone@example.com',
            'password' => $password,
            'password_confirmation' => $password,
        ])->assertSessionHasErrors('password');

        $this->assertGuest();
    }

    public function test_invalid_email_is_rejected(): void
    {
        $this->post(route('register.store'), [
            'email' => 'not-an-email',
            'password' => self::STRONG,
            'password_confirmation' => self::STRONG,
        ])->assertSessionHasErrors('email');
    }
}
