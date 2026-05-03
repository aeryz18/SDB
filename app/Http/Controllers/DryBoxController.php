<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DryBoxController extends Controller
{
    public function dashboard()
    {
        return view('dashboard');
    }

    public function equipment()
    {
        return view('equipment');
    }

    public function analytics()
    {
        return view('analytics');
    }

    public function settings()
    {
        return view('settings');
    }
}
