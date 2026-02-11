<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>@yield('title', 'Dnipro Map')</title>

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://telegram.org/js/telegram-web-app.js"></script>

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--tg-theme-bg-color, #ffffff);
            color: var(--tg-theme-text-color, #000000);
        }

        #map {
            width: 100%;
            height: 70vh;
        }

        .btn {
            display: inline-block;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            background: var(--tg-theme-button-color, #3390ec);
            color: var(--tg-theme-button-text-color, #ffffff);
        }

        .btn:hover { opacity: 0.9; }
        .btn-danger { background: #e53935; color: #fff; }
        .btn-secondary { background: #6c757d; color: #fff; }

        .controls {
            padding: 12px;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .popup-content {
            min-width: 200px;
            max-width: 260px;
        }

        .popup-content img {
            max-width: 100%;
            border-radius: 6px;
            margin-top: 6px;
        }

        .popup-content .time-ago {
            font-size: 12px;
            color: #888;
            margin-top: 4px;
        }

        .popup-content .vote-bar {
            display: flex;
            gap: 12px;
            margin-top: 8px;
            align-items: center;
        }

        .popup-content .vote-btn {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: none;
            border: 1px solid #ccc;
            border-radius: 16px;
            padding: 4px 10px;
            font-size: 13px;
            cursor: pointer;
            color: var(--tg-theme-text-color, #333);
            transition: background 0.15s, border-color 0.15s;
        }

        .popup-content .vote-btn:hover {
            background: #f0f0f0;
        }

        .popup-content .vote-btn.active-like {
            background: #e8f5e9;
            border-color: #43a047;
            color: #2e7d32;
        }

        .popup-content .vote-btn.active-dislike {
            background: #ffebee;
            border-color: #e53935;
            color: #c62828;
        }

        @yield('styles')
    </style>
</head>
<body>
    @yield('content')

    <script>
        window.TG = window.Telegram?.WebApp;
        if (window.TG) {
            window.TG.ready();
            window.TG.expand();
        }

        function getTelegramId() {
            if (window.TG?.initDataUnsafe?.user?.id) {
                return window.TG.initDataUnsafe.user.id;
            }
            const params = new URLSearchParams(window.location.search);
            if (params.get('telegram_id')) {
                return params.get('telegram_id');
            }
            return null;
        }

        function apiHeaders() {
            const headers = { 'Content-Type': 'application/json', 'Accept': 'application/json' };
            const tgId = getTelegramId();
            if (tgId) headers['X-Telegram-Id'] = tgId;
            return headers;
        }
    </script>

    @yield('scripts')
</body>
</html>
