@extends('layouts.base')

@section('title', 'Dnipro Map')

@section('styles')
    .subscribe-bar {
        padding: 12px;
        text-align: center;
    }

    .legend {
        padding: 8px 12px;
        display: flex;
        gap: 12px;
        font-size: 13px;
        justify-content: center;
        flex-wrap: wrap;
    }

    .legend-item {
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .legend-dot {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        display: inline-block;
    }
@endsection

@section('content')
    <div id="map"></div>

    <div class="legend">
        <span class="legend-item"><span class="legend-dot" style="background:#e53935"></span> &lt;1h</span>
        <span class="legend-item"><span class="legend-dot" style="background:#fb8c00"></span> 1-2h</span>
        <span class="legend-item"><span class="legend-dot" style="background:#43a047"></span> 2-3h</span>
        <span class="legend-item"><span class="legend-dot" style="background:#9e9e9e"></span> &gt;3h</span>
    </div>

    <div class="subscribe-bar">
        <button class="btn" onclick="subscribe()">🔔 Subscribe to alerts near me</button>
    </div>
@endsection

@section('scripts')
<script>
    const COLOR_MAP = {
        red: '#e53935',
        yellow: '#fb8c00',
        green: '#43a047',
        gray: '#9e9e9e'
    };

    const map = L.map('map').setView([48.4647, 35.0461], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    async function loadPoints() {
        try {
            const res = await fetch('/api/points');
            const json = await res.json();
            if (!json.success) return;

            json.data.forEach(point => {
                const color = COLOR_MAP[point.color] || COLOR_MAP.gray;
                const marker = L.circleMarker([point.latitude, point.longitude], {
                    radius: 10,
                    fillColor: color,
                    color: '#333',
                    weight: 1,
                    fillOpacity: 0.85
                }).addTo(map);

                const createdAt = new Date(point.created_at);
                const timeAgo = getTimeAgo(createdAt);

                let popupHtml = `<div class="popup-content">
                    <strong>${escapeHtml(point.description)}</strong>
                    <div class="time-ago">${timeAgo} — ${point.user.first_name}</div>`;

                if (point.photo_url) {
                    popupHtml += `<img src="${escapeHtml(point.photo_url)}" alt="Photo" />`;
                }

                popupHtml += `</div>`;
                marker.bindPopup(popupHtml);
            });
        } catch (e) {
            console.error('Failed to load points', e);
        }
    }

    function getTimeAgo(date) {
        const seconds = Math.floor((Date.now() - date.getTime()) / 1000);
        const minutes = Math.floor(seconds / 60);
        const hours = Math.floor(minutes / 60);

        if (hours > 0) return `${hours}h ${minutes % 60}m ago`;
        if (minutes > 0) return `${minutes}m ago`;
        return 'just now';
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    async function subscribe() {
        const tgId = getTelegramId();
        if (!tgId) {
            alert('Please open this app from Telegram.');
            return;
        }

        if (!navigator.geolocation) {
            alert('Geolocation is not supported by your browser.');
            return;
        }

        navigator.geolocation.getCurrentPosition(async (pos) => {
            try {
                const res = await fetch('/api/subscriptions?telegram_id=' + tgId, {
                    method: 'POST',
                    headers: apiHeaders(),
                    body: JSON.stringify({
                        latitude: pos.coords.latitude,
                        longitude: pos.coords.longitude,
                        radius_km: 5
                    })
                });

                const json = await res.json();
                alert(json.success ? 'Subscribed!' : (json.message || 'Error'));
            } catch (e) {
                alert('Failed to subscribe.');
            }
        }, () => {
            alert('Unable to get your location.');
        });
    }

    loadPoints();
</script>
@endsection
