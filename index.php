<?php
$version = 'RDL Prototype 2 — Research Mode';
$cssVersion = filemtime(__DIR__ . '/styles.css');
$jsVersion = filemtime(__DIR__ . '/app.js');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RoadDiscover Laboratory</title>
    <link rel="stylesheet" href="styles.css?v=<?= $cssVersion ?>">
</head>
<body>
<header class="topbar">
    <div><p class="eyebrow">RoadDiscover</p><h1>POI Selection Laboratory</h1></div>
    <span class="version"><?= htmlspecialchars($version) ?></span>
</header>
<main class="layout">
    <aside class="panel controls">
        <h2>Journey</h2>
        <div class="grid two">
            <label>Latitude<input id="latitude" type="number" step="0.000001" value="-26.3911"></label>
            <label>Longitude<input id="longitude" type="number" step="0.000001" value="153.0317"></label>
            <label>Heading °<input id="heading" type="number" min="0" max="359" value="90"></label>
            <label>Search radius km<input id="radius" type="number" min="1" max="50" value="15"></label>
        </div>
        <button id="use-location" class="secondary" type="button">Use my current location</button>
        <h2>Interests</h2>
        <p class="hint">High interests rank ahead of Normal. Within each level, forward distance ranks candidates. Prominence is research evidence, not a quality judgement.</p>
        <div id="interests" class="interest-list">
        <?php
        $interestRows = [
            ['history','History & heritage','normal'], ['churches','Churches & religious sites','normal'],
            ['nature','Nature & scenery','normal'], ['gardens','Gardens','normal'],
            ['architecture','Architecture','normal'], ['museums','Museums & galleries','normal'],
            ['coast','Beaches & coast','normal'], ['wildlife','Wildlife','normal'],
            ['engineering','Engineering & infrastructure','normal'], ['accommodation','Hotels & accommodation','off'],
            ['retail','Shops & retail','off'], ['food','Restaurants & cafes','off'], ['business','Businesses & services','off'],
        ];
        foreach ($interestRows as [$key,$label,$defaultLevel]): ?>
            <label class="interest-row"><span><?= htmlspecialchars($label) ?></span><select data-interest="<?= htmlspecialchars($key) ?>">
                <option value="off"<?= $defaultLevel === 'off' ? ' selected' : '' ?>>Off</option>
                <option value="normal"<?= $defaultLevel === 'normal' ? ' selected' : '' ?>>Normal</option>
                <option value="high"<?= $defaultLevel === 'high' ? ' selected' : '' ?>>High</option>
            </select></label>
        <?php endforeach; ?>
        </div>
        <div class="actions"><button id="search" type="button">Find POIs ahead</button><button id="demo" class="secondary" type="button">Load demo candidates</button></div>
        <p id="status" class="status">Ready.</p>
    </aside>

    <section class="workspace">
        <div class="summary-row">
            <article class="stat"><strong id="candidate-count">0</strong><span>Candidates</span></article>
            <article class="stat"><strong id="eligible-count">0</strong><span>Eligible</span></article>
            <article class="stat"><strong id="shortlist-count">0</strong><span>Shortlisted</span></article>
            <article class="stat"><strong id="active-distance">—</strong><span>Next POI</span></article>
        </div>
        <article id="active-card" class="active-card empty">
            <div><p class="eyebrow">Next active POI</p><h2 id="active-name">No POI selected</h2><p id="active-meta">Run a search to create a shortlist.</p><p id="active-reason" class="reason"></p></div>
            <div class="active-actions"><button id="accept" type="button" disabled>Accept</button><button id="reject" class="danger" type="button" disabled>Reject / next</button></div>
        </article>

        <article class="research-panel">
            <div class="research-heading"><div><p class="eyebrow">Research mode</p><h2>Google types returned</h2></div><button id="clear-type-filter" class="secondary small" type="button" hidden>Show all candidates</button></div>
            <p class="hint">Counts are from the current candidate set. Click a type to inspect only candidates carrying that Google type.</p>
            <div id="type-summary" class="type-summary"><span class="empty-note">Run a search to inspect Google's returned types.</span></div>
        </article>

        <div class="table-wrap">
            <table class="research-table">
                <thead><tr><th>Rank</th><th>Place</th><th>Primary Google type</th><th>All Google types</th><th>RD interest</th><th>Prominence</th><th>Distance</th><th>Direction</th><th>Decision</th><th>Info</th></tr></thead>
                <tbody id="results"><tr><td colspan="10" class="empty-cell">No candidates yet.</td></tr></tbody>
            </table>
        </div>
    </section>
</main>
<div id="details-modal" class="modal" hidden>
    <div class="modal-backdrop" data-close-details></div>
    <section class="modal-card" role="dialog" aria-modal="true" aria-labelledby="details-name">
        <button class="modal-close" type="button" data-close-details aria-label="Close">×</button>
        <p class="eyebrow">Google Places details</p><h2 id="details-name">Place details</h2><p id="details-type" class="details-type"></p><p id="details-summary" class="details-summary"></p>
        <div id="details-grid" class="details-grid"></div><div id="details-hours" class="details-hours"></div><div id="details-links" class="details-links"></div><p id="details-status" class="status"></p>
    </section>
</div>
<script>window.addEventListener('error',function(event){var status=document.getElementById('status');if(status)status.textContent='JavaScript error: '+(event.message||'unknown error')+(event.lineno?' (line '+event.lineno+')':'');});</script>
<script src="app.js?v=<?= $jsVersion ?>" onerror="document.getElementById('status').textContent='Could not load app.js.'"></script>
</body>
</html>