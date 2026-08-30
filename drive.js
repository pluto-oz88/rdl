(() => {
  const q = id => document.getElementById(id);
  const SEARCH_AFTER_KM = 0.5;
  const SEARCH_AFTER_MS = 90 * 1000;
  const HEADING_MIN_KM = 0.025;
  const RELAY_POLL_MS = 2000;
  const RELAY_STALE_MS = 15000;

  const drive = {
    watchId: null,
    relayTimer: null,
    running: false,
    previousPosition: null,
    searchPosition: null,
    lastSearchAt: 0,
    searchRunning: false,
    headingReady: false,
    lastAnnouncedId: null,
    relayTimestamp: null
  };

  function rad(v) { return v * Math.PI / 180; }
  function deg(v) { return v * 180 / Math.PI; }

  function kmBetween(a, b) {
    const R = 6371;
    const dLat = rad(b.lat - a.lat);
    const dLng = rad(b.lng - a.lng);
    const x = Math.sin(dLat / 2) ** 2 + Math.cos(rad(a.lat)) * Math.cos(rad(b.lat)) * Math.sin(dLng / 2) ** 2;
    return 2 * R * Math.asin(Math.sqrt(x));
  }

  function bearingBetween(a, b) {
    const p1 = rad(a.lat);
    const p2 = rad(b.lat);
    const dl = rad(b.lng - a.lng);
    return (deg(Math.atan2(
      Math.sin(dl) * Math.cos(p2),
      Math.cos(p1) * Math.sin(p2) - Math.sin(p1) * Math.cos(p2) * Math.cos(dl)
    )) + 360) % 360;
  }

  function setIndicator(text, active = false) {
    const indicator = q('drive-indicator');
    indicator.textContent = text;
    indicator.classList.toggle('active', active);
  }

  function setDriveStatus(text) {
    q('drive-status').textContent = text;
  }

  function updateReadouts(position, current) {
    const speed = Number.isFinite(position.coords.speed) ? position.coords.speed * 3.6 : null;
    q('drive-speed').textContent = speed === null ? '—' : `${speed.toFixed(1)} km/h`;
    q('drive-accuracy').textContent = Number.isFinite(position.coords.accuracy) ? `±${Math.round(position.coords.accuracy)} m` : '—';
    q('drive-heading-value').textContent = drive.headingReady ? `${Math.round(Number(q('heading').value))}°` : 'Waiting';
    q('drive-distance').textContent = drive.searchPosition ? `${kmBetween(drive.searchPosition, current).toFixed(2)} km` : '—';
  }

  function speakActivePoi() {
    if (!q('speak-pois').checked || !('speechSynthesis' in window)) return;
    const active = state.shortlist[state.activeIndex];
    if (!active || active.id === drive.lastAnnouncedId) return;

    drive.lastAnnouncedId = active.id;
    window.speechSynthesis.cancel();
    const distance = active.km < 1
      ? `${Math.max(1, Math.round(active.km * 1000))} metres`
      : `${active.km.toFixed(1)} kilometres`;
    const utterance = new SpeechSynthesisUtterance(`Coming up in ${distance} is ${active.name}.`);
    utterance.rate = 0.95;
    window.speechSynthesis.speak(utterance);
  }

  async function runDriveSearch(current) {
    if (!drive.running || drive.searchRunning || !drive.headingReady) return;

    drive.searchRunning = true;
    setIndicator('Searching', true);
    setDriveStatus('Refreshing places of interest ahead…');

    try {
      await searchGoogle();
      drive.searchPosition = current;
      drive.lastSearchAt = Date.now();
      q('drive-distance').textContent = '0.00 km';
      speakActivePoi();
      const active = state.shortlist[state.activeIndex];
      setDriveStatus(active ? `Tracking. Current active POI: ${active.name}.` : 'Tracking. No eligible POI currently ahead.');
    } catch (error) {
      setDriveStatus(`Automatic POI search failed: ${error.message}`);
    } finally {
      drive.searchRunning = false;
      if (drive.running) setIndicator('Driving', true);
    }
  }

  function maybeSearch(current) {
    if (!drive.headingReady) {
      setDriveStatus('GPS is live. Move about 25 m so RDL can establish direction of travel.');
      return;
    }

    if (!drive.searchPosition) {
      runDriveSearch(current);
      return;
    }

    const movedKm = kmBetween(drive.searchPosition, current);
    const elapsed = Date.now() - drive.lastSearchAt;
    if (movedKm >= SEARCH_AFTER_KM || elapsed >= SEARCH_AFTER_MS) {
      runDriveSearch(current);
    } else {
      const remaining = Math.max(0, SEARCH_AFTER_KM - movedKm);
      setDriveStatus(`Tracking. Next POI refresh after another ${remaining.toFixed(2)} km or 90 seconds.`);
    }
  }

  function onPosition(position) {
    if (!drive.running) return;

    const current = {
      lat: position.coords.latitude,
      lng: position.coords.longitude
    };

    q('latitude').value = current.lat.toFixed(6);
    q('longitude').value = current.lng.toFixed(6);

    const gpsHeading = Number(position.coords.heading);
    const gpsSpeed = Number(position.coords.speed);
    if (Number.isFinite(gpsHeading) && gpsHeading >= 0 && (!Number.isFinite(gpsSpeed) || gpsSpeed > 1.5)) {
      q('heading').value = Math.round(gpsHeading);
      drive.headingReady = true;
      drive.previousPosition = current;
    } else if (drive.previousPosition) {
      const moved = kmBetween(drive.previousPosition, current);
      if (moved >= HEADING_MIN_KM) {
        q('heading').value = Math.round(bearingBetween(drive.previousPosition, current));
        drive.headingReady = true;
        drive.previousPosition = current;
      }
    } else {
      drive.previousPosition = current;
    }

    updateReadouts(position, current);
    maybeSearch(current);
  }

  function onPositionError(error) {
    const messages = {
      1: 'Location permission was denied.',
      2: 'Current location is unavailable.',
      3: 'Location request timed out.'
    };
    setDriveStatus(messages[error.code] || `Location error: ${error.message || 'unknown error'}`);
  }

  async function pollRelay() {
    if (!drive.running || q('gps-source').value !== 'iphone') return;
    const session = q('gps-session').value.trim().toLowerCase();
    if (!/^[a-z0-9_-]{3,32}$/.test(session)) {
      setDriveStatus('GPS session code must be 3–32 letters, numbers, dashes or underscores.');
      return;
    }

    try {
      const response = await fetch(`api/gps.php?session=${encodeURIComponent(session)}&_=${Date.now()}`, {cache:'no-store'});
      const data = await response.json();
      if (!response.ok) throw new Error(data.error || `HTTP ${response.status}`);

      const age = Date.now() - Number(data.timestamp || 0);
      if (!Number.isFinite(age) || age > RELAY_STALE_MS) {
        setDriveStatus('Waiting for a fresh iPhone GPS update. Keep the GPS page open on the phone.');
        setIndicator('Waiting for iPhone', true);
        return;
      }

      if (drive.relayTimestamp === data.timestamp) return;
      drive.relayTimestamp = data.timestamp;

      onPosition({
        coords: {
          latitude: Number(data.latitude),
          longitude: Number(data.longitude),
          accuracy: data.accuracy === null ? null : Number(data.accuracy),
          speed: data.speed === null ? null : Number(data.speed),
          heading: data.heading === null ? null : Number(data.heading)
        },
        timestamp: Number(data.timestamp)
      });
      setIndicator('iPhone GPS live', true);
    } catch (error) {
      setIndicator('Waiting for iPhone', true);
      setDriveStatus(`Waiting for iPhone GPS: ${error.message}`);
    }
  }

  function startDrive() {
    if (drive.running) return;
    if (!window.isSecureContext) {
      setDriveStatus('Drive Mode requires HTTPS (or localhost).');
      return;
    }

    const source = q('gps-source').value;
    if (source === 'laptop' && !navigator.geolocation) {
      setDriveStatus('This browser does not support geolocation.');
      return;
    }

    if (source === 'iphone') {
      const session = q('gps-session').value.trim().toLowerCase();
      if (!/^[a-z0-9_-]{3,32}$/.test(session)) {
        setDriveStatus('GPS session code must be 3–32 letters, numbers, dashes or underscores.');
        return;
      }
    }

    drive.running = true;
    drive.previousPosition = null;
    drive.searchPosition = null;
    drive.lastSearchAt = 0;
    drive.headingReady = false;
    drive.lastAnnouncedId = null;
    drive.relayTimestamp = null;
    q('start-drive').disabled = true;
    q('stop-drive').disabled = false;
    q('gps-source').disabled = true;
    q('gps-session').disabled = true;

    if (source === 'iphone') {
      setIndicator('Waiting for iPhone', true);
      setDriveStatus('Connecting to the iPhone GPS relay…');
      pollRelay();
      drive.relayTimer = window.setInterval(pollRelay, RELAY_POLL_MS);
      return;
    }

    setIndicator('Starting', true);
    setDriveStatus('Starting laptop location tracking…');
    drive.watchId = navigator.geolocation.watchPosition(
      onPosition,
      onPositionError,
      { enableHighAccuracy: true, timeout: 20000, maximumAge: 2000 }
    );
  }

  function stopDrive() {
    if (drive.watchId !== null) navigator.geolocation.clearWatch(drive.watchId);
    if (drive.relayTimer !== null) window.clearInterval(drive.relayTimer);
    drive.watchId = null;
    drive.relayTimer = null;
    drive.running = false;
    drive.searchRunning = false;
    q('start-drive').disabled = false;
    q('stop-drive').disabled = true;
    q('gps-source').disabled = false;
    q('gps-session').disabled = false;
    setIndicator('Stopped', false);
    setDriveStatus('Drive Mode stopped.');
    if ('speechSynthesis' in window) window.speechSynthesis.cancel();
  }

  function updateSourceUi() {
    q('gps-session-row').hidden = q('gps-source').value !== 'iphone';
  }

  q('start-drive').addEventListener('click', startDrive);
  q('stop-drive').addEventListener('click', stopDrive);
  q('gps-source').addEventListener('change', updateSourceUi);
  window.addEventListener('beforeunload', stopDrive);
  updateSourceUi();
})();
