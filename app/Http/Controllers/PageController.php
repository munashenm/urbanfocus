<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class PageController extends Controller
{
    public function about(): View
    {
        return view('pages.about');
    }

    public function shipping(): View
    {
        return view('pages.shipping');
    }

    public function returns(): View
    {
        return view('pages.returns');
    }

    public function faq(): View
    {
        return view('pages.faq');
    }

    public function warranty(): View
    {
        return view('pages.warranty');
    }

    public function popia(): View
    {
        return view('pages.popia');
    }

    public function careers(): View
    {
        return view('pages.careers');
    }

    public function privacy(): View
    {
        return view('pages.privacy');
    }

    public function terms(): View
    {
        return view('pages.terms');
    }
}
