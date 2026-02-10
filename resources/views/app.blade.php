@extends('layouts.base')

@section('title', 'Dnipro Map')

@section('styles')
    .subscribe-bar {
        padding: 12px;
        text-align: center;
    }

    .sub-card {
        background: var(--tg-theme-secondary-bg-color, #f5f5f5);
        border-radius: 12px;
        padding: 16px;
        max-width: 400px;
        margin: 0 auto;
    }

    .sub-heading {
        font-size: 14px;
        font-weight: 600;
        margin-bottom: 12px;
    }

    .radius-slider {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 12px;
        font-size: 13px;
    }

    .radius-slider input[type="range"] {
        flex: 1;
        accent-color: var(--tg-theme-button-color, #3390ec);
    }

    .radius-value {
        font-weight: 600;
        min-width: 45px;
        text-align: right;
    }

    .sub-info {
        font-size: 13px;
        color: #666;
        margin-bottom: 12px;
    }

    .sub-actions {
        display: flex;
        gap: 8px;
        justify-content: center;
    }

    .hidden { display: none !important; }

    .modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.4);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1000;
        padding: 20px;
    }

    .modal-overlay .sub-card {
        max-width: 520px;
        width: 100%;
        margin: 0 auto;
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

    <div class="subscribe-bar" id="subscribe-loading">
        <div class="sub-card">Loading subscription...</div>
    </div>

    <div class="subscribe-bar hidden modal-overlay" id="subscribe-form">
        <div class="sub-card">
            <div class="sub-heading">Get notified about nearby danger points</div>
            <div class="radius-slider">
                <label for="radius">Notification radius:</label>
                <input type="range" id="radius" min="1" max="10" value="2" oninput="updateRadiusLabel(this.value)" />
                <span id="radius-label" class="radius-value">2 km</span>
            </div>
            <div class="sub-actions">
                <button class="btn" onclick="subscribe()">Subscribe</button>
                <button class="btn btn-secondary" onclick="closeSubscribeModal()">Cancel</button>
            </div>
        </div>
    </div>

    <div class="subscribe-bar hidden" id="subscribe-active">
        <div class="sub-card">
            <div class="sub-info" id="sub-info-text"></div>
            <div class="sub-actions">
                <button class="btn btn-danger" onclick="unsubscribe()">Unsubscribe</button>
                <button class="btn btn-secondary btn-sm" onclick="showChangeRadius()">Change radius</button>
            </div>
        </div>
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

    setTimeout(() => map.invalidateSize(), 150);
    if (window.TG) {
        window.TG.onEvent('viewportChanged', () => map.invalidateSize());
    }

    function buildPopupHtml(point) {
        const createdAt = new Date(point.created_at);
        const timeAgo = getTimeAgo(createdAt);
        const likeActive = point.user_vote === 'like' ? ' active-like' : '';
        const dislikeActive = point.user_vote === 'dislike' ? ' active-dislike' : '';

        let html = `<div class="popup-content" id="popup-point-${point.id}">
            <strong>${escapeHtml(point.description)}</strong>
            <div class="time-ago">${timeAgo} &mdash; ${escapeHtml(point.user.first_name)}</div>`;

        if (point.photo_url) {
            html += `<img src="${escapeHtml(point.photo_url)}" alt="Photo" />`;
        }

        html += `<div class="vote-bar">
            <button class="vote-btn${likeActive}" onclick="vote(${point.id}, 'like')">
                &#x1F44D; <span id="likes-${point.id}">${point.likes || 0}</span>
            </button>
            <button class="vote-btn${dislikeActive}" onclick="vote(${point.id}, 'dislike')">
                &#x1F44E; <span id="dislikes-${point.id}">${point.dislikes || 0}</span>
            </button>
        </div></div>`;

        return html;
    }

    const pointsCache = {};

    async function vote(pointId, type) {
        const tgId = getTelegramId();
        if (!tgId) {
            alert('Please open this app from Telegram to vote.');
            return;
        }

        try {
            const res = await fetch(`/api/points/${pointId}/vote`, {
                method: 'POST',
                headers: apiHeaders(),
                body: JSON.stringify({ type })
            });

            const json = await res.json();
            if (!json.success) return;

            const point = pointsCache[pointId];
            if (point) {
                point.likes = json.data.likes;
                point.dislikes = json.data.dislikes;
                point.user_vote = json.data.user_vote;
            }

            const likesEl = document.getElementById(`likes-${pointId}`);
            const dislikesEl = document.getElementById(`dislikes-${pointId}`);
            if (likesEl) likesEl.textContent = json.data.likes;
            if (dislikesEl) dislikesEl.textContent = json.data.dislikes;

            const popup = document.getElementById(`popup-point-${pointId}`);
            if (popup) {
                const buttons = popup.querySelectorAll('.vote-btn');
                buttons.forEach(btn => btn.classList.remove('active-like', 'active-dislike'));
                if (json.data.user_vote === 'like') {
                    buttons[0].classList.add('active-like');
                } else if (json.data.user_vote === 'dislike') {
                    buttons[1].classList.add('active-dislike');
                }
            }
        } catch (e) {
            console.error('Failed to vote', e);
        }
    }

    async function loadPoints() {
        try {
            const res = await fetch('/api/points');
            const json = await res.json();
            if (!json.success) return;

            json.data.forEach(point => {
                pointsCache[point.id] = point;
                const color = COLOR_MAP[point.color] || COLOR_MAP.gray;
                const marker = L.circleMarker([point.latitude, point.longitude], {
                    radius: 10,
                    fillColor: color,
                    color: '#333',
                    weight: 1,
                    fillOpacity: 0.85
                }).addTo(map);

                marker.bindPopup(() => buildPopupHtml(point), { maxWidth: 280 });
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
        if (isNaN(radiusKm) || radiusKm < 1 || radiusKm > 10) {
            alert('Radius must be between 1 and 10 km.');
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

    function updateRadiusLabel(value) {
        document.getElementById('radius-label').textContent = value + ' km';
    }

    function showChangeRadius() {
        showPanel('subscribe-form');
    }

    function closeSubscribeModal() {
        document.getElementById('subscribe-form').classList.add('hidden');
    }

    loadPoints();
    loadSubscription();
</script>
@endsection
