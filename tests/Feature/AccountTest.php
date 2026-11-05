<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    private const CURRENT = 'Fugazi-Test-123!';

    public function test_guests_cannot_see_the_account_page(): void
    {
        $this->get(route('account.show'))->assertRedirect(route('login'));
    }

    public function test_the_account_page_renders(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('account.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Account')
                ->has('passwordRules')
                ->where('auth.user.email', $user->email));
    }

    public function test_users_can_set_their_first_and_last_name(): void
    {
        $user = User::factory()->create(['first_name' => null, 'last_name' => null]);

        $this->actingAs($user)
            ->patch(route('account.profile.update'), ['first_name' => '  Ada ', 'last_name' => 'Lovelace'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('account.show'))
            ->assertInertiaFlash('toast.type', 'success');

        $user->refresh();
        $this->assertSame('Ada', $user->first_name);
        $this->assertSame('Lovelace', $user->last_name);
        $this->assertSame('Ada Lovelace', $user->name);
    }

    public function test_names_can_be_cleared_and_fall_back_to_email(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('account.profile.update'), ['first_name' => '', 'last_name' => ''])
            ->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertNull($user->first_name);
        $this->assertSame($user->email, $user->name);
    }

    public function test_names_have_a_maximum_length(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('account.profile.update'), ['first_name' => str_repeat('a', 101)])
            ->assertSessionHasErrors('first_name');
    }

    public function test_users_can_change_their_email_with_their_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('account.email.update'), [
                'email' => 'NEW@example.com',
                'current_password' => self::CURRENT,
            ])
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('toast.type', 'success');

        $user->refresh();
        $this->assertSame('new@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_changing_email_requires_the_correct_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('account.email.update'), [
                'email' => 'new@example.com',
                'current_password' => 'wrong-password',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertNotSame('new@example.com', $user->refresh()->email);
    }

    public function test_email_cannot_be_taken_by_another_user(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('account.email.update'), [
                'email' => 'taken@example.com',
                'current_password' => self::CURRENT,
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_users_can_change_their_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('account.password.update'), [
                'current_password' => self::CURRENT,
                'password' => 'Brand-New-Pass-9#',
                'password_confirmation' => 'Brand-New-Pass-9#',
            ])
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('toast.type', 'success');

        $this->assertTrue(Hash::check('Brand-New-Pass-9#', $user->refresh()->password));
    }

    public function test_changing_password_requires_the_current_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('account.password.update'), [
                'current_password' => 'wrong-password',
                'password' => 'Brand-New-Pass-9#',
                'password_confirmation' => 'Brand-New-Pass-9#',
            ])
            ->assertSessionHasErrors('current_password');
    }

    public function test_new_password_must_be_strong_confirmed_and_different(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('account.password.update'), [
                'current_password' => self::CURRENT,
                'password' => 'weak',
                'password_confirmation' => 'weak',
            ])
            ->assertSessionHasErrors('password');

        $this->actingAs($user)
            ->put(route('account.password.update'), [
                'current_password' => self::CURRENT,
                'password' => 'Brand-New-Pass-9#',
                'password_confirmation' => 'Different-Pass-9#',
            ])
            ->assertSessionHasErrors('password');

        $this->actingAs($user)
            ->put(route('account.password.update'), [
                'current_password' => self::CURRENT,
                'password' => self::CURRENT,
                'password_confirmation' => self::CURRENT,
            ])
            ->assertSessionHasErrors('password');
    }
}
