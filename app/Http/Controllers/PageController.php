<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class PageController extends Controller
{
    public function home(): View
    {
        return view('site.home');
    }

    public function docs(): View
    {
        return view('site.docs');
    }

    public function authPage(): View
    {
        return view('auth.login');
    }

    public function dashboard(): View
    {
        return view('app.dashboard');
    }

    public function console(): View
    {
        return view('admin.console');
    }
}
