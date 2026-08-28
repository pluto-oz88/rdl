const interestDefinitions = [
  ['history', 'History & heritage', 'normal'],
  ['churches', 'Churches & religious sites', 'normal'],
  ['nature', 'Nature & scenery', 'normal'],
  ['gardens', 'Gardens', 'normal'],
  ['architecture', 'Architecture', 'normal'],
  ['museums', 'Museums & galleries', 'normal'],
  ['coast', 'Beaches & coast', 'normal'],
  ['wildlife', 'Wildlife', 'normal'],
  ['engineering', 'Engineering & infrastructure', 'normal'],
  ['accommodation', 'Hotels & accommodation', 'off'],
  ['retail', 'Shops & retail', 'off'],
  ['food', 'Restaurants & cafes', 'off'],
  ['business', 'Businesses & services', 'off']
];

const interestKeywords = {
  history: ['historic','heritage','monument','memorial','landmark','fort','ruins','cemetery'],
  churches: ['church','chapel','cathedral','basilica','temple','mosque','synagogue','religious'],
  nature: ['national park','reserve','nature','lookout','waterfall','scenic','mountain','forest','headland','park'],
  gardens: ['garden','gardens','botanic','botanical','arboretum'],
  architecture: ['architecture','architectural','tower','bridge','manor'],
  museums: ['museum','gallery','arts centre','cultural centre','visitor centre'],
  coast: ['beach','coast','coastal','headland','marina','lighthouse','jetty','pier'],
  wildlife: ['zoo','aquarium','wildlife','sanctuary','bird','koala'],
  engineering: ['dam','bridge','railway','railroad','observatory','engineering','infrastructure'],
  accommodation: ['hotel','resort','lodge','hostel','motel','accommodation','lodging'],
  retail: ['store','shop','shopping','retail','hardware','supermarket','grocery','department store','clothing store','furniture store','home goods store','electronics store','book store','convenience store','liquor store','shopping mall'],
  food: ['restaurant','cafe','coffee','bakery','bar','pub','meal takeaway','fast food','food'],
  business: ['real estate','real estate agency','agency','accounting','lawyer','insurance','finance','bank','car dealer','car rental','car repair','travel agency','business','service']
};

const state = { candidates: [], shortlist: [], activeIndex: 0 };
const el = id => document.getElementById(id);

function buildInterests() {
  const host = el('interests');
  host.innerHTML = interestDefinitions.map(([key, label, defaultLevel]) => `
    <label class="interest-row">
      <span>${label}</span>
      <select data-interest="${key}">
        <option value="off" ${defaultLevel === 'off' ? 'selected' : ''}>Off</option>
        <option value="normal" ${defaultLevel === 'normal' ? 'selected' : ''}>Normal</option>
        <option value="high" ${defaultLevel === 'high' ? 'selected' : ''}>High</option>
      </select>
    </label>`).join('');
}

function preferences() {
  return Object.fromEntries([...document.querySelectorAll('[data-interest]')].map(x => [x.dataset.interest, x.value]));
}

function toRad(v) { return v * Math.PI / 180; }
function toDeg(v) { return v * 180 / Math.PI; }
function distanceKm(a, b) {
  const R = 6371;
  const dLat = toRad(b.lat - a.lat), dLng = toRad(b.lng - a.lng);
  const x = Math.sin(dLat/2) ** 2 + Math.cos(toRad(a.lat)) * Math.cos(toRad(b.lat)) * Math.sin(dLng/2) ** 2;
  return 2 * R * Math.asin(Math.sqrt(x));
}
function bearing(a, b) {
  const p1 = toRad(a.lat), p2 = toRad(b.lat), dl = toRad(b.lng-a.lng);
  return (toDeg(Math.atan2(Math.sin(dl)*Math.cos(p2), Math.cos(p1)*Math.sin(p2)-Math.sin(p1)*Math.cos(p2)*Math.cos(dl))) + 360) % 360;
}
function angleDifference(a,b) { return Math.abs(((a-b+540)%360)-180); }
function compass(diff) {
  if (diff <= 25) return 'Ahead';
  if (diff <= 60) return 'Ahead / side';
  if (diff <= 100) return 'Side';
  return 'Behind';
}

function matchingCategories(place) {
  const haystack = `${place.name} ${place.primaryType || ''} ${(place.types || []).join(' ')}`.toLowerCase().replaceAll('_',' ');
  return interestDefinitions
    .filter(([key]) => interestKeywords[key].some(k => haystack.includes(k)))
    .map(([key]) => key);
}

function matchInterests(place, prefs) {
  return matchingCategories(place)
    .filter(key => prefs[key] !== 'off')
    .map(key => ({ key, level: prefs[key] }));
}

function qualityScore(place) {
  let score = 0;
  if ((place.rating || 0) >= 4.3) score += 2;
  if ((place.userRatingCount || 0) >= 100) score += 2;
  if ((place.userRatingCount || 0) >= 1000) score += 2;
  if (place.photos?.length) score += 1;
  return score;
}

function rankCandidates(candidates) {
  const origin = { lat: Number(el('latitude').value), lng: Number(el('longitude').value) };
  const heading = Number(el('heading').value);
  const prefs = preferences();

  state.candidates = candidates.map(place => {
    const km = distanceKm(origin, place.location);
    const b = bearing(origin, place.location);
    const diff = angleDifference(heading, b);
    const categories = matchingCategories(place);
    const blockedCategories = categories.filter(key => prefs[key] === 'off');
    const matches = matchInterests(place, prefs);
    const maxLevel = matches.some(m => m.level === 'high') ? 'high' : matches.length ? 'normal' : 'none';
    const quality = qualityScore(place);
    const forward = diff <= 100;
    const eligible = forward && blockedCategories.length === 0 && maxLevel !== 'none';
    const blockedLabels = blockedCategories.map(key => interestDefinitions.find(x => x[0] === key)?.[1] || key).join(', ');
    const reason = !forward
      ? 'Outside forward corridor'
      : blockedCategories.length
        ? `Blocked by Off interest: ${blockedLabels}`
        : maxLevel === 'none'
          ? 'No enabled interest match'
          : `${maxLevel === 'high' ? 'High' : 'Normal'} interest match`;
    return { ...place, km, bearing: b, directionDiff: diff, direction: compass(diff), categories, blockedCategories, matches, maxLevel, quality, eligible, reason };
  });

  const eligible = state.candidates.filter(x => x.eligible);
  const high = eligible.filter(x => x.maxLevel === 'high');
  const pool = high.length ? high : eligible;
  state.shortlist = pool
    .sort((a,b) => (b.quality-a.quality) || (a.km-b.km))
    .slice(0, 10)
    .sort((a,b) => a.km-b.km);
  state.activeIndex = 0;
  render();
}

function render() {
  el('candidate-count').textContent = state.candidates.length;
  el('shortlist-count').textContent = state.shortlist.length;
  const active = state.shortlist[state.activeIndex];
  el('active-distance').textContent = active ? `${active.km.toFixed(1)} km` : '—';

  const card = el('active-card');
  if (active) {
    card.classList.remove('empty');
    el('active-name').textContent = active.name;
    el('active-meta').textContent = `${active.primaryType || 'Place'} • ${active.km.toFixed(2)} km • ${active.direction}`;
    el('active-reason').textContent = `Selected because: ${active.reason}. Ranked ${state.activeIndex + 1} of ${state.shortlist.length} by forward distance.`;
    el('accept').disabled = false;
    el('reject').disabled = false;
  } else {
    card.classList.add('empty');
    el('active-name').textContent = state.shortlist.length ? 'End of shortlist' : 'No POI selected';
    el('active-meta').textContent = state.shortlist.length ? 'All shortlisted POIs have been reviewed.' : 'No candidate survived the current filters.';
    el('active-reason').textContent = '';
    el('accept').disabled = true;
    el('reject').disabled = true;
  }

  const ordered = [...state.candidates].sort((a,b) => {
    const ai = state.shortlist.findIndex(x => x.id === a.id), bi = state.shortlist.findIndex(x => x.id === b.id);
    if (ai >= 0 && bi < 0) return -1;
    if (bi >= 0 && ai < 0) return 1;
    if (ai >= 0 && bi >= 0) return ai-bi;
    return a.km-b.km;
  });

  el('results').innerHTML = ordered.length ? ordered.map(p => {
    const shortlistIndex = state.shortlist.findIndex(x => x.id === p.id);
    const isActive = state.shortlist[state.activeIndex]?.id === p.id;
    const interest = p.matches.length ? p.matches.map(m => interestDefinitions.find(x => x[0] === m.key)[1]).join(', ') : '—';
    const decision = shortlistIndex >= 0 ? `Shortlist #${shortlistIndex+1}` : p.reason;
    const infoButton = p.id.startsWith('demo') ? '<button class="small secondary" type="button" disabled>More info</button>' : `<button class="small secondary" type="button" data-more-info="${escapeHtml(p.id)}">More info</button>`;
    return `<tr class="${isActive ? 'active-row' : ''}">
      <td>${shortlistIndex >= 0 ? shortlistIndex+1 : '—'}</td>
      <td><strong>${escapeHtml(p.name)}</strong><br><small>${escapeHtml(p.address || '')}</small></td>
      <td>${escapeHtml((p.primaryType || 'unknown').replaceAll('_',' '))}</td>
      <td><span class="badge ${p.maxLevel === 'high' ? 'high' : ''}">${p.maxLevel}</span><br>${escapeHtml(interest)}</td>
      <td>${p.quality}</td><td>${p.km.toFixed(2)} km</td><td>${p.direction}<br><small>${Math.round(p.directionDiff)}° off heading</small></td>
      <td><span class="badge ${shortlistIndex < 0 ? 'reject' : ''}">${escapeHtml(decision)}</span></td>
      <td>${infoButton}</td>
    </tr>`;
  }).join('') : '<tr><td colspan="9" class="empty-cell">No candidates yet.</td></tr>';
}

function escapeHtml(value='') { return String(value).replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c])); }

function closeDetails() { el('details-modal').hidden = true; }

async function showDetails(placeId) {
  const place = state.candidates.find(p => p.id === placeId);
  el('details-modal').hidden = false;
  el('details-name').textContent = place?.name || 'Place details';
  el('details-type').textContent = place ? (place.primaryType || 'Place').replaceAll('_', ' ') : '';
  el('details-summary').textContent = '';
  el('details-grid').innerHTML = '';
  el('details-hours').innerHTML = '';
  el('details-links').innerHTML = '';
  el('details-status').textContent = 'Loading additional Google Places information…';
  try {
    const response = await fetch(`api/details.php?id=${encodeURIComponent(placeId)}`);
    const data = await response.json();
    if (!response.ok) throw new Error(data.error || `HTTP ${response.status}`);
    el('details-name').textContent = data.name || place?.name || 'Place details';
    el('details-type').textContent = (data.primaryType || 'Place').replaceAll('_', ' ');
    const summaryParts = [];
    if (data.summary) summaryParts.push(data.summary);
    if (data.reviewSummary) summaryParts.push(`Visitors often mention: ${data.reviewSummary}`);
    el('details-summary').textContent = summaryParts.join(' ');
    const facts = [];
    if (data.address) facts.push(['Address', data.address]);
    if (data.rating) facts.push(['Google rating', `${Number(data.rating).toFixed(1)} from ${data.userRatingCount || 0} ratings`]);
    if (data.phone) facts.push(['Phone', data.phone]);
    if (data.types?.length) facts.push(['Google types', data.types.map(t => t.replaceAll('_',' ')).join(', ')]);
    if (data.photoCount) facts.push(['Photos', `${data.photoCount} available from Google Places`]);
    el('details-grid').innerHTML = facts.map(([label, value]) => `<div><strong>${escapeHtml(label)}</strong><span>${escapeHtml(value)}</span></div>`).join('');
    if (data.hours?.length) el('details-hours').innerHTML = `<h3>Opening hours</h3><ul>${data.hours.map(h => `<li>${escapeHtml(h)}</li>`).join('')}</ul>`;
    const links = [];
    if (data.website) links.push(`<a href="${escapeHtml(data.website)}" target="_blank" rel="noopener">Official website</a>`);
    if (data.googleMapsUri) links.push(`<a href="${escapeHtml(data.googleMapsUri)}" target="_blank" rel="noopener">Open in Google Maps</a>`);
    el('details-links').innerHTML = links.join('');
    el('details-status').textContent = summaryParts.length ? 'Live Google Places details loaded.' : 'Google Places details loaded; no editorial summary was available.';
  } catch (err) { el('details-status').textContent = `Could not load more information: ${err.message}`; }
}

async function searchGoogle() {
  el('status').textContent = 'Requesting nearby places from Google Places…';
  el('search').disabled = true;
  try {
    const response = await fetch('api/search.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ latitude: Number(el('latitude').value), longitude: Number(el('longitude').value), radiusKm: Number(el('radius').value) })});
    const data = await response.json();
    if (!response.ok) throw new Error(data.error || `HTTP ${response.status}`);
    rankCandidates(data.places || []);
    el('status').textContent = `Google returned ${data.places?.length || 0} candidates. Selection pipeline complete.`;
  } catch (err) { el('status').textContent = `Search failed: ${err.message}`; }
  finally { el('search').disabled = false; }
}

function loadDemo() {
  const o = { lat:Number(el('latitude').value), lng:Number(el('longitude').value) };
  const demo = [
    ['demo1','Summer Salt Real Estate','real_estate_agency',0.0017,0.0010,4.8,18],
    ['demo2','St Marys Church','church',0.015,0.020,4.6,83],
    ['demo3','Regional Museum','museum',0.028,0.035,4.7,241],
    ['demo4','Warrack Park','park',0.002,0.003,4.1,12],
    ['demo5','Coastal Lookout','scenic_spot',0.038,0.048,4.8,1300],
    ['demo6','Botanic Gardens','botanical_garden',-0.020,0.010,4.7,550],
    ['demo7','Bunnings Warehouse','hardware_store',0.003,0.006,4.4,1200],
    ['demo8','Beachside Cafe','cafe',0.004,0.008,4.6,650],
    ['demo9','Noosa Resort','hotel',0.005,0.010,4.5,900]
  ].map(([id,name,primaryType,dlat,dlng,rating,userRatingCount]) => ({id,name,primaryType,types:[primaryType],location:{lat:o.lat+dlat,lng:o.lng+dlng},rating,userRatingCount,address:'Demo candidate',photos: rating > 4.5 ? [{}] : []}));
  rankCandidates(demo);
  el('status').textContent = 'Demo candidates loaded. Change interests or heading and reload to test the pipeline.';
}

el('reject').addEventListener('click', () => { if (state.activeIndex < state.shortlist.length) state.activeIndex++; render(); });
el('accept').addEventListener('click', () => { const p=state.shortlist[state.activeIndex]; if (p) el('status').textContent = `Accepted: ${p.name}`; });
el('search').addEventListener('click', searchGoogle);
el('demo').addEventListener('click', loadDemo);
el('results').addEventListener('click', event => { const button = event.target.closest('[data-more-info]'); if (button) showDetails(button.dataset.moreInfo); });
document.querySelectorAll('[data-close-details]').forEach(button => button.addEventListener('click', closeDetails));
document.addEventListener('keydown', event => { if (event.key === 'Escape' && !el('details-modal').hidden) closeDetails(); });
el('use-location').addEventListener('click', () => {
  if (!navigator.geolocation) return el('status').textContent = 'Browser geolocation is not available.';
  el('status').textContent = 'Getting browser location…';
  navigator.geolocation.getCurrentPosition(pos => {
    el('latitude').value = pos.coords.latitude.toFixed(6); el('longitude').value = pos.coords.longitude.toFixed(6); el('status').textContent = 'Current location loaded.';
  }, err => el('status').textContent = `Location failed: ${err.message}`);
});

buildInterests();
