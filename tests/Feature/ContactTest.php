<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ContactTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function validData(array $overrides = []): array
    {
        return [
            'name' => 'Jane Smith',
            'email' => 'Jane@Example.com',
            'subject' => 'Hello there',
            'message' => 'I would love to know more about the AI workflow.',
            ...$overrides,
        ];
    }

    public function test_the_contact_page_renders(): void
    {
        $this->get(route('contact.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Contact'));
    }

    public function test_a_valid_message_is_saved(): void
    {
        $this->post(route('contact.store'), $this->validData())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('contact.show'))
            ->assertInertiaFlash('toast.type', 'success');

        $message = ContactMessage::sole();
        $this->assertSame('jane@example.com', $message->email);
        $this->assertSame('Hello there', $message->subject);
        $this->assertNull($message->user_id);
        $this->assertNotNull($message->ip_address);
    }

    public function test_logged_in_users_are_linked_to_their_message(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('contact.store'), $this->validData());

        $this->assertSame($user->id, ContactMessage::sole()->user_id);
    }

    public function test_all_fields_are_required(): void
    {
        $this->post(route('contact.store'), [])
            ->assertSessionHasErrors(['name', 'email', 'subject', 'message']);

        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_fields_are_validated(): void
    {
        $this->post(route('contact.store'), $this->validData([
            'email' => 'nope',
            'message' => 'too short',
            'subject' => str_repeat('x', 151),
        ]))->assertSessionHasErrors(['email', 'message', 'subject']);
    }

    public function test_bots_filling_the_honeypot_are_rejected(): void
    {
        $this->post(route('contact.store'), $this->validData(['website' => 'http://spam.example']))
            ->assertSessionHasErrors('website');

        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_the_form_is_rate_limited(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->post(route('contact.store'), $this->validData())->assertRedirect();
        }

        $this->post(route('contact.store'), $this->validData())->assertTooManyRequests();
        $this->assertDatabaseCount('contact_messages', 3);
    }
}
