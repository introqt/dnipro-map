@extends('layouts.base')

@section('title', 'Admin — Dnipro Map')

@section('styles')
    .admin-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 12px;
        border-bottom: 1px solid #eee;
        background: var(--tg-theme-secondary-bg-color, #f5f5f5);
    }

    .admin-header h1 {
        font-size: 16px;
        font-weight: 700;
    }

    .admin-layout {
        display: flex;
        height: calc(100vh - 45px);
    }

    .admin-sidebar {
        width: 340px;
        min-width: 280px;
        border-right: 1px solid #eee;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    .admin-main {
        flex: 1;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    #map {
        flex: 1;
        min-height: 0;
    }

    .admin-form {
        padding: 12px;
        border-top: 1px solid #eee;
        max-height: 50%;
        overflow-y: auto;
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

    .sidebar-header {
        padding: 10px 12px;
        border-bottom: 1px solid #eee;
    }

    .search-row {
        display: flex;
        gap: 6px;
        margin-bottom: 8px;
    }

    .search-row input {
        flex: 1;
        padding: 6px 10px;
        border: 1px solid #ccc;
        border-radius: 6px;
        font-size: 13px;
        background: var(--tg-theme-secondary-bg-color, #f0f0f0);
        color: var(--tg-theme-text-color, #000);
    }

    .filter-row {
        display: flex;
        gap: 4px;
        align-items: center;
        flex-wrap: wrap;
    }

    .filter-btn {
        padding: 3px 10px;
        border: 1px solid #ccc;
        border-radius: 12px;
        font-size: 12px;
        cursor: pointer;
        background: transparent;
        color: var(--tg-theme-text-color, #000);
    }

    .filter-btn.active {
        border-color: var(--tg-theme-button-color, #3390ec);
        background: var(--tg-theme-button-color, #3390ec);
        color: #fff;
    }

    .point-count {
        font-size: 12px;
        color: #888;
        margin-left: auto;
    }

    .points-list {
        flex: 1;
        overflow-y: auto;
    }

    .point-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 12px;
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

    #photo-preview {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .toast {
        position: fixed;
        top: 16px;
        right: 16px;
        padding: 10px 20px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
        color: #fff;
        z-index: 10000;
        opacity: 0;
        transform: translateY(-10px);
        transition: all 0.3s ease;
        pointer-events: none;
    }

    .toast.visible {
        opacity: 1;
        transform: translateY(0);
    }

    .toast.success { background: #43a047; }
    .toast.error { background: #e53935; }

    .logout-form button {
        background: transparent;
        border: 1px solid #ccc;
        border-radius: 6px;
        padding: 4px 12px;
        font-size: 12px;
        cursor: pointer;
        color: var(--tg-theme-text-color, #666);
    }

    @media (max-width: 768px) {
        .admin-layout {
            flex-direction: column;
            height: auto;
        }

        .admin-sidebar {
            width: 100%;
            min-width: 0;
            border-right: none;
            border-bottom: 1px solid #eee;
            max-height: 40vh;
        }

        #map {
            height: 40vh;
        }
    }
@endsection

@section('content')
    <div class="admin-header">
        <h1>Dnipro Map Admin</h1>
        @if(session('admin_telegram_id'))
            <form method="POST" action="/admin/logout" class="logout-form">
                @csrf
                <button type="submit">Logout</button>
            </form>
        @endif
    </div>

    <div class="admin-layout">
        <div class="admin-sidebar">
            <div class="sidebar-header">
                <div class="search-row">
                    <input type="text" id="search-input" placeholder="Search points..." oninput="filterPoints()" />
                </div>
                <div class="filter-row">
                    <button class="filter-btn active" data-color="all" onclick="setColorFilter('all', this)">All</button>
                    <button class="filter-btn" data-color="red" onclick="setColorFilter('red', this)" style="border-color:#e53935;color:#e53935;">Red</button>
                    <button class="filter-btn" data-color="yellow" onclick="setColorFilter('yellow', this)" style="border-color:#fb8c00;color:#fb8c00;">Yellow</button>
                    <button class="filter-btn" data-color="green" onclick="setColorFilter('green', this)" style="border-color:#43a047;color:#43a047;">Green</button>
                    <button class="filter-btn" data-color="gray" onclick="setColorFilter('gray', this)" style="border-color:#9e9e9e;color:#9e9e9e;">Gray</button>
                    <span class="point-count" id="point-count">0 points</span>
                </div>
            </div>
            <div class="points-list" id="points-list"></div>
        </div>

        <div class="admin-main">
            <div id="map"></div>

            <div class="admin-form">
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
                        <label>Photo (optional — paste from clipboard or select file)</label>
                        <input type="file" id="photo-file" accept="image/*" onchange="handleFileSelect(this)" />
                        <input type="hidden" id="photo" />
                        <div id="photo-preview" style="margin-top:8px;display:none;">
                            <img id="photo-preview-img" style="max-width:200px;max-height:150px;border-radius:6px;" />
                            <button type="button" class="btn btn-sm btn-secondary" onclick="clearPhoto()" style="margin-left:8px;">Remove</button>
                        </div>
                    </div>
                    <button type="submit" class="btn" id="save-btn">Add Point</button>
                    <button type="button" class="btn btn-secondary" onclick="cancelEdit()" id="cancel-btn" style="display:none;">Cancel</button>
                </form>
            </div>
        </div>
    </div>

    <div class="toast" id="toast"></div>
@endsection

@section('scripts')
<script>
    const telegramId = @json($telegramId ?? null) || getTelegramId();

    // Override getTelegramId for this page to use session-based ID
    function getAdminTelegramId() {
        return telegramId;
    }

    let editingPointId = null;
    let allPoints = [];
    let activeColorFilter = 'all';

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

    function showToast(msg, isError = false) {
        const toast = document.getElementById('toast');
        toast.textContent = msg;
        toast.className = 'toast ' + (isError ? 'error' : 'success') + ' visible';
        setTimeout(() => toast.classList.remove('visible'), 3000);
    }

    async function savePoint(e) {
        e.preventDefault();
        const tgId = getAdminTelegramId();
        if (!tgId) { alert('Authentication required.'); return false; }

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
                showToast(editingPointId ? 'Point updated!' : 'Point added!');
                cancelEdit();
                loadPoints();
            } else {
                showToast(json.message || 'Error', true);
            }
        } catch (err) {
            showToast('Request failed.', true);
        }

        return false;
    }

    function editPoint(point) {
        editingPointId = point.id;
        document.getElementById('lat').value = point.latitude;
        document.getElementById('lng').value = point.longitude;
        document.getElementById('desc').value = point.description;
        document.getElementById('photo').value = point.photo_url || '';
        if (point.photo_url) {
            showPhotoPreview(point.photo_url);
        } else {
            clearPhoto();
        }
        document.getElementById('save-btn').textContent = 'Update Point';
        document.getElementById('cancel-btn').style.display = 'inline-block';
    }

    function cancelEdit() {
        editingPointId = null;
        document.getElementById('point-form').reset();
        document.getElementById('save-btn').textContent = 'Add Point';
        document.getElementById('cancel-btn').style.display = 'none';
        if (clickMarker) { map.removeLayer(clickMarker); clickMarker = null; }
        clearPhoto();
    }

    async function deletePoint(id) {
        if (!confirm('Delete this point?')) return;

        const tgId = getAdminTelegramId();
        try {
            const res = await fetch(`/api/points/${id}?telegram_id=${tgId}`, {
                method: 'DELETE',
                headers: apiHeaders()
            });

            const json = await res.json();
            if (json.success) {
                showToast('Point deleted.');
                loadPoints();
            } else {
                showToast(json.message || 'Error', true);
            }
        } catch (err) {
            showToast('Request failed.', true);
        }
    }

    let markers = [];

    const COLOR_MAP = { red: '#e53935', yellow: '#fb8c00', green: '#43a047', gray: '#9e9e9e' };

    async function loadPoints() {
        markers.forEach(m => map.removeLayer(m));
        markers = [];

        try {
            const res = await fetch('/api/points');
            const json = await res.json();
            if (!json.success) return;

            allPoints = json.data;
            renderPoints();
        } catch (e) {
            console.error('Failed to load points', e);
        }
    }

    function renderPoints() {
        markers.forEach(m => map.removeLayer(m));
        markers = [];

        const listEl = document.getElementById('points-list');
        listEl.innerHTML = '';

        const searchTerm = (document.getElementById('search-input')?.value || '').toLowerCase();

        const filtered = allPoints.filter(point => {
            if (activeColorFilter !== 'all' && point.color !== activeColorFilter) return false;
            if (searchTerm && !point.description.toLowerCase().includes(searchTerm)) return false;
            return true;
        });

        document.getElementById('point-count').textContent = filtered.length + ' point' + (filtered.length !== 1 ? 's' : '');

        filtered.forEach(point => {
            const color = COLOR_MAP[point.color] || COLOR_MAP.gray;
            const m = L.circleMarker([point.latitude, point.longitude], {
                radius: 8, fillColor: color, color: '#333', weight: 1, fillOpacity: 0.85
            }).addTo(map);
            markers.push(m);

            const div = document.createElement('div');
            div.className = 'point-item';
            div.innerHTML = `
                <span style="background:${color};width:10px;height:10px;border-radius:50%;flex-shrink:0;display:inline-block;"></span>
                <div class="info">${escapeHtml(point.description)}</div>
                <div class="actions">
                    <button class="btn btn-sm" onclick='editPoint(${JSON.stringify(point)})'>Edit</button>
                    <button class="btn btn-sm btn-danger" onclick="deletePoint(${point.id})">Del</button>
                </div>`;
            listEl.appendChild(div);
        });
    }

    function filterPoints() {
        renderPoints();
    }

    function setColorFilter(color, btn) {
        activeColorFilter = color;
        document.querySelectorAll('.filter-btn').forEach(b => {
            b.classList.remove('active');
            if (b.dataset.color !== 'all') {
                b.style.background = 'transparent';
                b.style.color = b.style.borderColor;
            }
        });
        btn.classList.add('active');
        if (color !== 'all') {
            btn.style.background = btn.style.borderColor;
            btn.style.color = '#fff';
        }
        renderPoints();
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    async function handleFileSelect(input) {
        if (!input.files || !input.files[0]) return;
        await uploadFile(input.files[0]);
    }

    document.addEventListener('paste', async (e) => {
        const items = e.clipboardData?.items;
        if (!items) return;
        for (const item of items) {
            if (item.type.startsWith('image/')) {
                e.preventDefault();
                const file = item.getAsFile();
                await uploadFile(file);
                break;
            }
        }
    });

    async function uploadFile(file) {
        const tgId = getAdminTelegramId();
        if (!tgId) { alert('Authentication required.'); return; }

        const formData = new FormData();
        formData.append('file', file);

        try {
            const res = await fetch('/api/upload?telegram_id=' + tgId, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-Telegram-Id': tgId },
                body: formData
            });

            const json = await res.json();
            if (json.success) {
                document.getElementById('photo').value = json.data.url;
                showPhotoPreview(json.data.url);
                showToast('Photo uploaded!');
            } else {
                showToast(json.message || 'Upload failed.', true);
            }
        } catch (err) {
            showToast('Upload failed.', true);
        }
    }

    function showPhotoPreview(url) {
        document.getElementById('photo-preview-img').src = url;
        document.getElementById('photo-preview').style.display = 'flex';
    }

    function clearPhoto() {
        document.getElementById('photo').value = '';
        document.getElementById('photo-file').value = '';
        document.getElementById('photo-preview').style.display = 'none';
    }

    // Override apiHeaders to include session-based telegram ID
    function adminApiHeaders() {
        const headers = { 'Content-Type': 'application/json', 'Accept': 'application/json' };
        const tgId = getAdminTelegramId();
        if (tgId) headers['X-Telegram-Id'] = tgId;
        return headers;
    }

    loadPoints();
</script>
@endsection
