<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AdminAuthController extends Controller
{
    public function showLogin()
    {
        return view('admin-login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        if ($request->password !== config('app.admin_password')) {
            return back()->withErrors(['password' => 'Invalid password.']);
        }

        $adminIds = array_filter(array_map('trim', explode(',', config('services.telegram.admin_id') ?? '')));
        session(['admin_telegram_id' => $adminIds[0] ?? null]);

        return redirect('/admin');
    }

    public function logout()
    {
        session()->forget('admin_telegram_id');

        return redirect('/admin/login');
    }
}
