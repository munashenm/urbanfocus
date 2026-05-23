<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function index(): View
    {
        return view('contact');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:30',
            'message' => 'required|string|max:2000',
        ]);

        Mail::raw(
            "Name: {$validated['name']}\nEmail: {$validated['email']}\nPhone: ".($validated['phone'] ?? 'N/A')."\n\n{$validated['message']}",
            function ($message) use ($validated) {
                $message->to(config('app.email'))
                    ->replyTo($validated['email'], $validated['name'])
                    ->subject('Urban Focus Contact Form: '.$validated['name']);
            }
        );

        return back()->with('success', 'Thank you for your message. We will respond shortly.');
    }
}
