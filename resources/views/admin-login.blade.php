@extends('layouts.base')

@section('title', 'Admin Login — Dnipro Map')

@section('styles')
    .login-wrapper {
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 100vh;
        padding: 20px;
    }

    .login-card {
        background: var(--tg-theme-secondary-bg-color, #f5f5f5);
        border-radius: 12px;
        padding: 32px;
        width: 100%;
        max-width: 360px;
    }

    .login-card h1 {
        font-size: 20px;
        margin-bottom: 8px;
    }

    .login-card p {
        font-size: 13px;
        color: #666;
        margin-bottom: 20px;
    }

    .login-card input {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid #ccc;
        border-radius: 8px;
        font-size: 14px;
        margin-bottom: 12px;
        background: var(--tg-theme-bg-color, #fff);
        color: var(--tg-theme-text-color, #000);
    }

    .login-card .btn {
        width: 100%;
    }

    .login-error {
        color: #e53935;
        font-size: 13px;
        margin-bottom: 12px;
    }
@endsection

@section('content')
    <div class="login-wrapper">
        <div class="login-card">
            <h1>Admin Login</h1>
            <p>Dnipro Map administration panel</p>

            @if ($errors->has('password'))
                <div class="login-error">{{ $errors->first('password') }}</div>
            @endif

            <form method="POST" action="/admin/login">
                @csrf
                <input type="password" name="password" placeholder="Password" required autofocus />
                <button type="submit" class="btn">Sign In</button>
            </form>
        </div>
    </div>
@endsection
