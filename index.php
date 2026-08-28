<?php
$version = 'RDL Prototype 1';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RoadDiscover Laboratory</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<header class="topbar">
    <div>
        <p class="eyebrow">RoadDiscover</p>
        <h1>POI Selection Laboratory</h1>
    </div>
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
        <p class="hint">High interests determine the shortlist. Distance orders the survivors.</p>
        <div id="interests" class="interest-list"></div>

        <div class="actions">
            <button id="search" type="button">Find POIs ahead</button>
            <button id="demo" class="secondary" type="button">Load demo candidates</button>
        </div>
        <p id="status" class="status">Ready.</p>
    </aside>

    <section class="workspace">
        <div class="summary-row">
            <article class="stat"><strong id="candidate-count">0</strong><span>Candidates</span></article>
            <article class="stat"><strong id="shortlist-count">0</strong><span>Shortlisted</span></article>
            <article class="stat"><strong id="active-distance">—</strong><span>Next POI</span></article>
        </div>

        <article id="active-card" class="active-card empty">
            <div>
                <p class="eyebrow">Next active POI</p>
                <h2 id="active-name">No POI selected</h2>
                <p id="active-meta">Run a search to create a shortlist.</p>
                <p id="active-reason" class="reason"></p>
            </div>
            <div class="active-actions">
                <button id="accept" type="button" disabled>Accept</button>
                <button id="reject" class="danger" type="button" disabled>Reject / next</button>
            </div>
        </article>

        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>#</th><th>Place</th><th>Type</th><th>Interest</th><th>Quality</th><th>Distance</th><th>Direction</th><th>Decision</th>
                </tr>
                </thead>
                <tbody id="results">
                <tr><td colspan="8" class="empty-cell">No candidates yet.</td></tr>
                </tbody>
            </table>
        </div>
    </section>
</main>

<script src="app.js"></script>
</body>
</html>
