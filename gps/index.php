<?php
$version = 'RDL GPS Relay';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <title>RoadDiscover GPS Relay</title>
    <style>
        :root{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color-scheme:light dark}
        body{margin:0;background:#0d1f24;color:#fff;min-height:100vh;display:grid;place-items:center;padding:1rem}
        main{width:min(520px,100%);background:#153039;border:1px solid rgba(255,255,255,.12);border-radius:18px;padding:1.25rem;box-shadow:0 20px 60px rgba(0,0,0,.25)}
        h1{margin:.2rem 0 1rem;font-size:1.6rem}p{line-height:1.45}.muted{color:#b8c8cd;font-size:.9rem}
        label{display:grid;gap:.4rem;margin:1rem 0;font-weight:700}input{font:inherit;padding:.8rem;border-radius:10px;border:1px solid #587078;background:#fff;color:#111}
        .actions{display:grid;grid-template-columns:1fr 1fr;gap:.7rem;margin:1rem 0}button{font:inherit;border:0;border-radius:10px;padding:.9rem 1rem;font-weight:800;cursor:pointer}#start{background:#17a69f;color:#fff}#stop{background:#b84a4a;color:#fff}button:disabled{opacity:.45}
        .status{padding:.9rem;background:rgba(255,255,255,.08);border-radius:10px;min-height:2.8rem}.grid{display:grid;grid-template-columns:1fr 1fr;gap:.7rem;margin-top:1rem}.readout{background:rgba(255,255,255,.06);border-radius:10px;padding:.8rem}.readout span{display:block;color:#b8c8cd;font-size:.75rem}.readout strong{display:block;margin-top:.2rem;font-size:1rem}.ok{color:#8ee8c3}.bad{color:#ffb3b3}
    </style>
</head>
<body>
<main>
    <div class="muted">RoadDiscover</div>
    <h1><?= htmlspecialchars($version) ?></h1>
    <p class="muted">Leave this page open on the iPhone during the road test. It sends the phone GPS position to RDL on the laptop.</p>

    <label>Session code
        <input id="session" value="roadtest" maxlength="32" autocapitalize="none" autocomplete="off">
    </label>

    <div class="actions">
        <button id="start" type="button">Start GPS</button>
        <button id="stop" type="button" disabled>Stop</button>
    </div>

    <div id="status" class="status">Ready.</div>

    <div class="grid">
        <div class="readout"><span>Latitude</span><strong id="lat">—</strong></div>
        <div class="readout"><span>Longitude</span><strong id="lng">—</strong></div>
        <div class="readout"><span>Accuracy</span><strong id="accuracy">—</strong></div>
        <div class="readout"><span>Speed</span><strong id="speed">—</strong></div>
        <div class="readout"><span>Heading</span><strong id="heading">—</strong></div>
        <div class="readout"><span>Last sent</span><strong id="sent">—</strong></div>
    </div>
</main>
<script>
(() => {
    const q = id => document.getElementById(id);
    let watchId = null;
    let sending = false;

    function cleanSession() {
        return q('session').value.trim().toLowerCase();
    }

    async function sendPosition(position) {
        if (sending) return;
        sending = true;
        const c = position.coords;
        q('lat').textContent = c.latitude.toFixed(6);
        q('lng').textContent = c.longitude.toFixed(6);
        q('accuracy').textContent = Number.isFinite(c.accuracy) ? `±${Math.round(c.accuracy)} m` : '—';
        q('speed').textContent = Number.isFinite(c.speed) ? `${(c.speed * 3.6).toFixed(1)} km/h` : '—';
        q('heading').textContent = Number.isFinite(c.heading) && c.heading >= 0 ? `${Math.round(c.heading)}°` : '—';
        try {
            const response = await fetch('../api/gps.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                cache: 'no-store',
                body: JSON.stringify({
                    session: cleanSession(),
                    latitude: c.latitude,
                    longitude: c.longitude,
                    accuracy: c.accuracy,
                    speed: c.speed,
                    heading: c.heading
                })
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.error || `HTTP ${response.status}`);
            q('sent').textContent = new Date().toLocaleTimeString();
            q('status').innerHTML = '<span class="ok">GPS live — position sent to RDL.</span>';
        } catch (error) {
            q('status').innerHTML = `<span class="bad">Could not send GPS: ${String(error.message || error)}</span>`;
        } finally {
            sending = false;
        }
    }

    function start() {
        if (watchId !== null) return;
        const session = cleanSession();
        if (!/^[a-z0-9_-]{3,32}$/.test(session)) {
            q('status').textContent = 'Session code must be 3–32 letters, numbers, dashes or underscores.';
            return;
        }
        if (!window.isSecureContext) {
            q('status').textContent = 'GPS requires HTTPS.';
            return;
        }
        if (!navigator.geolocation) {
            q('status').textContent = 'This browser does not provide geolocation.';
            return;
        }
        q('status').textContent = 'Requesting iPhone GPS…';
        q('start').disabled = true;
        q('stop').disabled = false;
        q('session').disabled = true;
        watchId = navigator.geolocation.watchPosition(
            sendPosition,
            error => {
                const messages = {1:'Location permission denied.',2:'Position unavailable.',3:'Location request timed out.'};
                q('status').innerHTML = `<span class="bad">${messages[error.code] || error.message || 'GPS error'}</span>`;
            },
            {enableHighAccuracy:true, timeout:20000, maximumAge:1000}
        );
    }

    function stop() {
        if (watchId !== null) navigator.geolocation.clearWatch(watchId);
        watchId = null;
        q('start').disabled = false;
        q('stop').disabled = true;
        q('session').disabled = false;
        q('status').textContent = 'GPS stopped.';
    }

    q('start').addEventListener('click', start);
    q('stop').addEventListener('click', stop);
})();
</script>
</body>
</html>
