<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class NewsletterController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255',
        ]);

        Mail::raw(
            "Newsletter signup: {$validated['email']}\nSource: ".($request->headers->get('referer') ?? 'website'),
            fn ($message) => $message->to(config('business.email'))
                ->replyTo($validated['email'])
                ->subject('Newsletter signup — Urban Focus')
        );

        return back()->with('success', 'Thanks for subscribing. We will keep you updated on deals and new products.');
    }
}
