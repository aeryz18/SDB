<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DemoController extends Controller
{
    /**
     * Enter demo mode — store a flag in the session and redirect to the demo dashboard.
     */
    public function enter(Request $request)
    {
        $request->session()->put('demo_mode', true);
        return redirect()->route('demo.dashboard');
    }

    /**
     * Exit demo mode — flush the flag and redirect to landing.
     */
    public function exit(Request $request)
    {
        $request->session()->forget('demo_mode');
        return redirect()->route('landing');
    }

    public function dashboard()
    {
        return view('demo.dashboard');
    }

    public function equipment()
    {
        return view('demo.equipment');
    }

    public function analytics()
    {
        return view('demo.analytics');
    }

    public function settings()
    {
        return view('demo.settings');
    }
}
