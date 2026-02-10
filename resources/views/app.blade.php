@extends('layouts.base')

@section('title', 'Dnipro Map')

@section('styles')
    .subscribe-bar {
        padding: 12px;
        text-align: center;
    }

    .subscribe-bar .radius-input {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin-right: 8px;
    }

    .subscribe-bar .radius-input input {
        width: 60px;
        padding: 6px 8px;
        border: 1px solid #ccc;
        border-radius: 6px;
        font-size: 14px;
        text-align: center;
    }

    .subscribe-bar .sub-info {
        font-size: 13px;
        color: #666;
        margin-bottom: 8px;
    }

    .hidden { display: none !important; }

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

    <div class="subscribe-bar" id="subscribe-loading">
        Loading subscription...
    </div>

    <div class="subscribe-bar hidden" id="subscribe-form">
        <span class="radius-input">
            <label for="radius">Radius:</label>
            <input type="number" id="radius" min="1" max="50" value="5" />
            <span>km</span>
        </span>
        <button class="btn" onclick="subscribe()">🔔 Subscribe</button>
    </div>

    <div class="subscribe-bar hidden" id="subscribe-active">
        <div class="sub-info" id="sub-info-text"></div>
        <button class="btn btn-danger" onclick="unsubscribe()">Unsubscribe</button>
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

    let subscriptionCircle = null;

    function showPanel(id) {
        ['subscribe-loading', 'subscribe-form', 'subscribe-active'].forEach(panelId => {
            document.getElementById(panelId).classList.toggle('hidden', panelId !== id);
        });
    }

    function showActiveSubscription(sub) {
        document.getElementById('sub-info-text').textContent =
            `Subscribed within ${sub.radius_km} km of [${sub.latitude.toFixed(4)}, ${sub.longitude.toFixed(4)}]`;

        if (subscriptionCircle) {
            map.removeLayer(subscriptionCircle);
        }
        subscriptionCircle = L.circle([sub.latitude, sub.longitude], {
            radius: sub.radius_km * 1000,
            color: '#3390ec',
            fillColor: '#3390ec',
            fillOpacity: 0.1,
            weight: 1
        }).addTo(map);

        showPanel('subscribe-active');
    }

    function removeCircle() {
        if (subscriptionCircle) {
            map.removeLayer(subscriptionCircle);
            subscriptionCircle = null;
        }
    }

    async function loadSubscription() {
        const tgId = getTelegramId();
        if (!tgId) {
            showPanel('subscribe-form');
            return;
        }

        try {
            const res = await fetch('/api/subscriptions', { headers: apiHeaders() });
            const json = await res.json();

            if (json.success && json.data) {
                showActiveSubscription(json.data);
            } else {
                showPanel('subscribe-form');
            }
        } catch {
            showPanel('subscribe-form');
        }
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

        const radiusKm = parseInt(document.getElementById('radius').value, 10);
        if (isNaN(radiusKm) || radiusKm < 1 || radiusKm > 50) {
            alert('Radius must be between 1 and 50 km.');
            return;
        }

        navigator.geolocation.getCurrentPosition(async (pos) => {
            try {
                const res = await fetch('/api/subscriptions', {
                    method: 'POST',
                    headers: apiHeaders(),
                    body: JSON.stringify({
                        latitude: pos.coords.latitude,
                        longitude: pos.coords.longitude,
                        radius_km: radiusKm
                    })
                });

                const json = await res.json();
                if (json.success && json.data) {
                    showActiveSubscription(json.data);
                } else {
                    alert(json.message || 'Failed to subscribe.');
                }
            } catch {
                alert('Failed to subscribe.');
            }
        }, () => {
            alert('Unable to get your location.');
        });
    }

    async function unsubscribe() {
        try {
            const res = await fetch('/api/subscriptions', {
                method: 'DELETE',
                headers: apiHeaders()
            });

            const json = await res.json();
            if (json.success) {
                removeCircle();
                showPanel('subscribe-form');
            } else {
                alert(json.message || 'Failed to unsubscribe.');
            }
        } catch {
            alert('Failed to unsubscribe.');
        }
    }

    loadPoints();
    loadSubscription();
</script>
@endsection
