<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class PageController extends Controller
{
    public function about(): View
    {
        return $this->render('pages.about', 'about');
    }

    public function shipping(): View
    {
        return $this->render('pages.shipping', 'shipping');
    }

    public function returns(): View
    {
        return $this->render('pages.returns', 'returns');
    }

    public function faq(): View
    {
        return $this->render('pages.faq', 'faq');
    }

    public function warranty(): View
    {
        return $this->render('pages.warranty', 'warranty');
    }

    public function popia(): View
    {
        return $this->render('pages.popia', 'popia');
    }

    public function careers(): View
    {
        return $this->render('pages.careers', 'careers');
    }

    public function privacy(): View
    {
        return $this->render('pages.privacy', 'privacy');
    }

    public function terms(): View
    {
        return $this->render('pages.terms', 'terms');
    }

    protected function render(string $view, string $key): View
    {
        return view($view, [
            'pageSeo' => config("page_seo.{$key}", []),
        ]);
    }
}
