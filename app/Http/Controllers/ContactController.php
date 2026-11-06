<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactMessageRequest;
use App\Models\ContactMessage;
use App\Slack\Notifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('Contact');
    }

    public function store(StoreContactMessageRequest $request, Notifier $notifier): RedirectResponse
    {
        $message = ContactMessage::create([
            ...$request->safe()->only(['name', 'email', 'subject', 'message']),
            'user_id' => $request->user()?->id,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
        ]);

        $notifier->notify('contact.received', sprintf(
            "📬 *%s* from %s (%s)\n>%s",
            $message->subject,
            $message->name,
            $message->email,
            str_replace("\n", "\n>", Str::limit($message->message, 600)),
        ));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Thanks! Your message has been sent. We\'ll get back to you soon.'),
        ]);

        return to_route('contact.show');
    }
}
