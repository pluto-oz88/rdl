(() => {
  const q = id => document.getElementById(id);
  let activeId = null;
  let detailsCache = new Map();
  let detailsRequest = 0;

  function activePoi() { return state.shortlist[state.activeIndex] || null; }
  function humanType(value='place') { return String(value || 'place').replaceAll('_',' '); }
  function distanceText(km) { return km < 1 ? `${Math.max(10, Math.round(km * 1000 / 10) * 10)} metres` : `${km.toFixed(1)} kilometres`; }
  function sideText(p) {
    const signed = ((p.bearing - Number(q('heading').value) + 540) % 360) - 180;
    if (Math.abs(signed) <= 18) return 'ahead';
    return signed > 0 ? 'on your right' : 'on your left';
  }
  function announcement(p) {
    const category = p.matches?.length ? interestLabel(p.matches[0].key).toLowerCase() : humanType(p.primaryType);
    return `Coming up ${sideText(p)} in about ${distanceText(p.km)} is ${p.name}, a ${category} place.`;
  }
  function speak(text) {
    if (!text || !('speechSynthesis' in window)) return;
    window.speechSynthesis.cancel();
    const u = new SpeechSynthesisUtterance(text); u.rate = .95; window.speechSynthesis.speak(u);
  }

  async function fetchDetails(id) {
    if (detailsCache.has(id)) return detailsCache.get(id);
    const response = await fetch(`api/details.php?id=${encodeURIComponent(id)}`);
    const data = await response.json();
    if (!response.ok) throw new Error(data.error || `HTTP ${response.status}`);
    detailsCache.set(id, data); return data;
  }

  function refreshDiscovery() {
    const p = activePoi();
    if (!p) {
      activeId = null; q('discovery-card').classList.add('empty'); q('discovery-name').textContent='Waiting for a discovery'; q('discovery-announcement').textContent='When RoadDiscover selects a POI, its driver announcement will appear here.'; ['tell-more','speak-discovery','discovery-next'].forEach(id=>q(id).disabled=true); q('tell-more-card').hidden=true; return;
    }
    q('discovery-card').classList.remove('empty'); q('discovery-name').textContent=p.name; q('discovery-announcement').textContent=announcement(p); ['tell-more','speak-discovery','discovery-next'].forEach(id=>q(id).disabled=false);
    if (activeId !== p.id) { activeId=p.id; q('tell-more-card').hidden=true; }
  }

  const originalRender = window.render;
  window.render = function(...args) { const result=originalRender.apply(this,args); refreshDiscovery(); return result; };
  refreshDiscovery();

  q('speak-discovery').addEventListener('click',()=>{const p=activePoi(); if(p)speak(announcement(p));});
  q('discovery-next').addEventListener('click',()=>q('reject').click());
  q('close-more').addEventListener('click',()=>{q('tell-more-card').hidden=true;});
  q('speak-more').addEventListener('click',()=>speak(q('tell-more-text').textContent));

  q('tell-more').addEventListener('click', async()=>{
    const p=activePoi(); if(!p)return;
    const request=++detailsRequest; q('tell-more-card').hidden=false; q('tell-more-name').textContent=p.name; q('tell-more-text').textContent=''; q('tell-more-facts').innerHTML=''; q('tell-more-status').textContent='Loading Google Places information…'; q('tell-more').disabled=true;
    if(String(p.id).startsWith('demo')) { q('tell-more-text').textContent=`${p.name} is a demonstration POI. Live Tell Me More information is available after a Google Places search.`; q('tell-more-status').textContent='Demo candidate — no Google Place Details request made.'; q('tell-more').disabled=false; return; }
    try {
      const d=await fetchDetails(p.id); if(request!==detailsRequest)return;
      const text=d.summary || d.reviewSummary || `${d.name} is listed by Google Places as ${humanType(d.primaryType)} at ${d.address || 'this location'}.`;
      q('tell-more-text').textContent=text;
      const facts=[]; if(d.rating)facts.push(`Google rating ${Number(d.rating).toFixed(1)} from ${d.userRatingCount||0} ratings`); if(d.hours?.length)facts.push(d.hours.join(' • ')); if(d.address)facts.push(d.address);
      q('tell-more-facts').innerHTML=facts.map(x=>`<div>${escapeHtml(x)}</div>`).join(''); q('tell-more-status').textContent=d.summary?'Google Places summary loaded.':'Google Places details loaded.';
      speak(text);
    } catch(e) { q('tell-more-text').textContent='More information could not be loaded right now.'; q('tell-more-status').textContent=`Tell Me More failed: ${e.message}`; }
    finally { q('tell-more').disabled=false; }
  });

  window.roadDiscoverExperience={announcement,speak,refreshDiscovery};
})();