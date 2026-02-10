@extends('layouts.base')

@section('title', 'Admin — Dnipro Map')

@section('styles')
    .admin-panel {
        padding: 12px;
    }

    .form-group {
        margin-bottom: 10px;
    }

    .form-group label {
        display: block;
        font-size: 13px;
        font-weight: 600;
        margin-bottom: 4px;
    }

    .form-group input,
    .form-group textarea {
        width: 100%;
        padding: 8px;
        border: 1px solid #ccc;
        border-radius: 6px;
        font-size: 14px;
        background: var(--tg-theme-secondary-bg-color, #f0f0f0);
        color: var(--tg-theme-text-color, #000);
    }

    .form-group textarea {
        min-height: 60px;
        resize: vertical;
    }

    .points-list {
        margin-top: 16px;
    }

    .point-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px;
        border-bottom: 1px solid #eee;
        gap: 8px;
    }

    .point-item .info {
        flex: 1;
        font-size: 13px;
    }

    .point-item .actions {
        display: flex;
        gap: 4px;
    }

    .btn-sm {
        padding: 4px 10px;
        font-size: 12px;
    }

    #status-msg {
        padding: 8px;
        font-size: 13px;
        text-align: center;
    }
@endsection

@section('content')
    <div id="map" style="height: 50vh;"></div>

    <div class="admin-panel">
        <div id="status-msg"></div>

        <form id="point-form" onsubmit="return savePoint(event)">
            <div class="form-group">
                <label>Latitude</label>
                <input type="text" id="lat" required />
            </div>
            <div class="form-group">
                <label>Longitude</label>
                <input type="text" id="lng" required />
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea id="desc" required maxlength="1000"></textarea>
            </div>
            <div class="form-group">
                <label>Photo URL (optional)</label>
                <input type="url" id="photo" />
            </div>
            <button type="submit" class="btn" id="save-btn">Add Point</button>
            <button type="button" class="btn btn-secondary" onclick="cancelEdit()" id="cancel-btn" style="display:none;">Cancel</button>
        </form>

        <div class="points-list" id="points-list"></div>
    </div>
@endsection

@section('scripts')
<script>
    let editingPointId = null;

    const map = L.map('map').setView([48.4647, 35.0461], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    let clickMarker = null;

    map.on('click', (e) => {
        document.getElementById('lat').value = e.latlng.lat.toFixed(7);
        document.getElementById('lng').value = e.latlng.lng.toFixed(7);

        if (clickMarker) map.removeLayer(clickMarker);
        clickMarker = L.marker(e.latlng).addTo(map);
    });

    function showStatus(msg, isError = false) {
        const el = document.getElementById('status-msg');
        el.textContent = msg;
        el.style.color = isError ? '#e53935' : '#43a047';
        setTimeout(() => el.textContent = '', 3000);
    }

    async function savePoint(e) {
        e.preventDefault();
        const tgId = getTelegramId();
        if (!tgId) { alert('Open from Telegram.'); return false; }

        const payload = {
            latitude: parseFloat(document.getElementById('lat').value),
            longitude: parseFloat(document.getElementById('lng').value),
            description: document.getElementById('desc').value,
            photo_url: document.getElementById('photo').value || null,
        };

        const url = editingPointId ? `/api/points/${editingPointId}` : '/api/points';
        const method = editingPointId ? 'PUT' : 'POST';

        try {
            const res = await fetch(url + '?telegram_id=' + tgId, {
                method,
                headers: apiHeaders(),
                body: JSON.stringify(payload)
            });

            const json = await res.json();

            if (json.success) {
                showStatus(editingPointId ? 'Point updated!' : 'Point added!');
                cancelEdit();
                loadPoints();
            } else {
                showStatus(json.message || 'Error', true);
            }
        } catch (err) {
            showStatus('Request failed.', true);
        }

        return false;
    }

    function editPoint(point) {
        editingPointId = point.id;
        document.getElementById('lat').value = point.latitude;
        document.getElementById('lng').value = point.longitude;
        document.getElementById('desc').value = point.description;
        document.getElementById('photo').value = point.photo_url || '';
        document.getElementById('save-btn').textContent = 'Update Point';
        document.getElementById('cancel-btn').style.display = 'inline-block';
    }

    function cancelEdit() {
        editingPointId = null;
        document.getElementById('point-form').reset();
        document.getElementById('save-btn').textContent = 'Add Point';
        document.getElementById('cancel-btn').style.display = 'none';
        if (clickMarker) { map.removeLayer(clickMarker); clickMarker = null; }
    }

    async function deletePoint(id) {
        if (!confirm('Delete this point?')) return;

        const tgId = getTelegramId();
        try {
            const res = await fetch(`/api/points/${id}?telegram_id=${tgId}`, {
                method: 'DELETE',
                headers: apiHeaders()
            });

            const json = await res.json();
            if (json.success) {
                showStatus('Point deleted.');
                loadPoints();
            } else {
                showStatus(json.message || 'Error', true);
            }
        } catch (err) {
            showStatus('Request failed.', true);
        }
    }

    let markers = [];

    async function loadPoints() {
        markers.forEach(m => map.removeLayer(m));
        markers = [];

        try {
            const res = await fetch('/api/points');
            const json = await res.json();
            if (!json.success) return;

            const listEl = document.getElementById('points-list');
            listEl.innerHTML = '';

            const COLOR_MAP = { red: '#e53935', yellow: '#fb8c00', green: '#43a047', gray: '#9e9e9e' };

            json.data.forEach(point => {
                const color = COLOR_MAP[point.color] || COLOR_MAP.gray;
                const m = L.circleMarker([point.latitude, point.longitude], {
                    radius: 8, fillColor: color, color: '#333', weight: 1, fillOpacity: 0.85
                }).addTo(map);
                markers.push(m);

                const div = document.createElement('div');
                div.className = 'point-item';
                div.innerHTML = `
                    <span class="legend-dot" style="background:${color};width:10px;height:10px;border-radius:50%;flex-shrink:0;"></span>
                    <div class="info">${escapeHtml(point.description)}</div>
                    <div class="actions">
                        <button class="btn btn-sm" onclick='editPoint(${JSON.stringify(point)})'>Edit</button>
                        <button class="btn btn-sm btn-danger" onclick="deletePoint(${point.id})">Del</button>
                    </div>`;
                listEl.appendChild(div);
            });
        } catch (e) {
            console.error('Failed to load points', e);
        }
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    loadPoints();
</script>
@endsection
