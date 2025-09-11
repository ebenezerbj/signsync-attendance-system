<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>SignSync – Where Every Check‑In Connects</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet" />
  <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyAWeXcDwE21q90SP_ADvll7D7gEYPN30TU"></script>
  <style>
    body { font-family: 'Inter', sans-serif; }
    #webcam { transform: scaleX(-1); }
    .logo { max-width: 120px; }
  </style>
</head>
<body class="bg-gradient-to-br from-indigo-50 via-white to-blue-100 flex items-center justify-center min-h-screen">
  <main class="w-full max-w-md p-8 bg-white rounded-2xl shadow-2xl space-y-6 border border-indigo-200">
    <header class="flex flex-col items-center">
      <img src="images/SignSync.png" alt="Logo" class="logo mb-2" />
      <h1 class="text-3xl font-extrabold text-indigo-700 flex items-center gap-2">
        <svg class="w-8 h-8 text-indigo-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path d="M12 11c0-1.657-1.343-3-3-3s-3 1.343-3 3 1.343 3 3 3 3-1.343 3-3zm0 0c0-1.657 1.343-3 3-3s3 1.343 3 3-1.343 3-3 3-3-1.343-3-3zm0 0v8"/>
        </svg>
        SignSync
      </h1>
      <p class="text-sm text-indigo-400 font-semibold">Automated Clock In/Out &bull; v1.0</p>
    </header>

    <div>
      <label for="employee_id" class="block text-sm font-semibold text-indigo-700">Employee ID</label>
      <input id="employee_id" type="text" minlength="16" maxlength="30" pattern="\d{16,}" required
             placeholder="Enter your 16+ digit ID"
             class="w-full px-4 py-2 mt-1 border-2 border-indigo-200 rounded-lg focus:ring-2 focus:ring-indigo-400 focus:border-indigo-400 transition" />
      <p class="text-xs text-gray-500 mt-1">Must be at least 16 digits.</p>
    </div>

    <div id="status-area" class="text-center text-lg font-semibold text-indigo-700 min-h-[2rem]"></div>
    <div id="countdown-area" class="text-center text-4xl font-bold text-indigo-600"></div>

    <div id="reason-area" class="hidden">
      <label class="block text-sm font-semibold text-indigo-700">Reason</label>
      <textarea id="reason" rows="2" placeholder="Why?" 
                class="w-full px-3 py-2 mt-1 border-2 border-red-200 rounded-lg focus:ring-2 focus:ring-red-400 focus:border-red-400"></textarea>
    </div>

    <video id="webcam" class="hidden w-full rounded-lg shadow-inner"></video>
    <canvas id="canvas" class="hidden"></canvas>
    <input type="hidden" id="snapshot" />
    <input type="hidden" id="latitude" />
    <input type="hidden" id="longitude" />

    <div id="photo-preview-area" class="hidden text-center">
      <p class="text-sm font-semibold text-indigo-700">Photo Preview</p>
      <img id="photo-preview" class="mx-auto rounded-lg border border-indigo-200 mt-1" style="max-width:180px;max-height:180px;" />
    </div>

    <div id="location-map" class="w-full h-56 rounded-lg shadow-inner overflow-hidden"></div>

    <button id="reset-btn" class="w-full py-2 bg-gray-300 text-gray-700 font-semibold rounded-lg hidden">RESET</button>
  </main>

  <script>
    // -- DOM refs
    const employeeID = document.getElementById('employee_id');
    const statusArea = document.getElementById('status-area');
    const countdown = document.getElementById('countdown-area');
    const webcamEl = document.getElementById('webcam');
    const canvasEl = document.getElementById('canvas');
    const snapshotInput = document.getElementById('snapshot');
    const latInput = document.getElementById('latitude');
    const lngInput = document.getElementById('longitude');
    const reasonArea = document.getElementById('reason-area');
    const reasonInput = document.getElementById('reason');
    const previewArea = document.getElementById('photo-preview-area');
    const previewImg = document.getElementById('photo-preview');
    const mapContainer = document.getElementById('location-map');
    const resetBtn = document.getElementById('reset-btn');
    let stream;
    let map, circle, branchMarker, userMarker;

    // -- Helpers
    const sleep = ms => new Promise(r => setTimeout(r, ms));
    const showStatus = (msg, error=false) => {
      statusArea.textContent = msg;
      statusArea.className = `text-center text-lg font-semibold ${error?'text-red-600':'text-indigo-700'}`;
    };
    const showReset = () => resetBtn.classList.remove('hidden');

    // -- IndexedDB for offline
    const DB = 'signsync_offline', STORE = 'entries';
    async function openDB(){ const r=indexedDB.open(DB,1);
      r.onupgradeneeded = ()=> r.result.createObjectStore(STORE,{keyPath:'timestamp'});
      return new Promise((res,rej)=>{r.onsuccess=()=>res(r.result);r.onerror=()=>rej(r.error)});
    }
    async function saveEntry(o){ const db=await openDB(); const tx=db.transaction(STORE,'readwrite'); tx.objectStore(STORE).add(o); return tx.complete; }
    async function syncOffline(){ const db=await openDB(); const all=await new Promise((res,rej)=>{const s=db.transaction(STORE).objectStore(STORE);const q=s.getAll();q.onsuccess=()=>res(q.result);q.onerror=()=>rej(q.error)});
      for(const e of all){ try{ const fd=new FormData(); Object.entries(e).forEach(([k,v])=>fd.append(k,v)); let res=await fetch('clockinout.php',{method:'POST',body:fd}); if(res.ok){ await new Promise(r=>{const db2=request=db.transaction(STORE,'readwrite');db2.objectStore(STORE).delete(e.timestamp);db2.oncomplete=r}); showStatus('✅ Synced offline entry');}}catch{} }
    }
    window.addEventListener('online', ()=>{ showStatus('Online. Syncing...'); syncOffline(); });
    window.addEventListener('offline', ()=> showStatus('Offline. Saving entry for later.', true));

    // -- Webcam functions
    async function startWebcam(){ try{ stream=await navigator.mediaDevices.getUserMedia({video:true}); webcamEl.srcObject=stream; webcamEl.play(); webcamEl.classList.remove('hidden'); return true;}catch{return showStatus('Cannot access camera',true),false;} }
    function stopWebcam(){ if(stream) stream.getTracks().forEach(t=>t.stop()); webcamEl.classList.add('hidden'); }
    function capture(){ canvasEl.width=webcamEl.videoWidth;canvasEl.height=webcamEl.videoHeight;
      const ctx=canvasEl.getContext('2d'); ctx.setTransform(-1,0,0,1,canvasEl.width,0); ctx.drawImage(webcamEl,0,0); ctx.setTransform(1,0,0,1,0,0);
      const d=canvasEl.toDataURL(); snapshotInput.value=d; previewImg.src=d; previewArea.classList.remove('hidden'); }

    // -- Geolocation
    async function getPosition(){ return new Promise((res,rej)=>navigator.geolocation.getCurrentPosition(res,rej,{timeout:60000})); }

    // -- Map
    function initMap(){ map = new google.maps.Map(mapContainer, { zoom: 14, center: {lat:0,lng:0}, disableDefaultUI:true }); }
    function updateMap(blat,blng,radius,ulat,ulng,inRange){ if(!map) initMap(); const center={lat:blat,lng:blng}; map.setCenter(center);
      const zoomLvl = radius<100?17: radius<500?16:14; map.setZoom(zoomLvl);
      if(circle) circle.setMap(null);
      circle=new google.maps.Circle({ map, center, radius, strokeColor:'#3B82F6', fillColor:'#3B82F6', fillOpacity:0.1 });
      if(branchMarker) branchMarker.setMap(null);
      branchMarker=new google.maps.Marker({ position:center, map, title:'Branch', icon:'https://maps.google.com/mapfiles/ms/icons/blue-dot.png'});
      const userPos={lat:ulat,lng:ulng}; if(userMarker) userMarker.setMap(null);
      userMarker=new google.maps.Marker({ position:userPos, map, title:'You', icon: inRange? 'https://maps.google.com/mapfiles/ms/icons/green-dot.png':'https://maps.google.com/mapfiles/ms/icons/red-dot.png' }); }

    // -- Main flow
    employeeID.addEventListener('keydown', async e=>{
      if(e.key!=='Enter' || employeeID.readOnly) return;
      const id=employeeID.value.trim(); if(!id) return showStatus('Enter your Employee ID',true);
      showStatus('Checking ID...');
      try{
        let r=await fetch(`get_employee.php?id=${encodeURIComponent(id)}`);
        let d=await r.json(); if(!d.FullName) throw 'Not found';
        showStatus(`Welcome ${d.FullName}`);
        // Countdown
        for(let i=3;i>0;i--){ countdown.textContent=i; await sleep(800); }
        countdown.textContent='';
        if(!(await startWebcam())) return;
        showStatus('Capturing photo...'); await sleep(800);
        capture(); stopWebcam();
        // Location & range check
        showStatus('Checking location...'); const pos=await getPosition(); latInput.value=pos.coords.latitude; lngInput.value=pos.coords.longitude;
        let locRes=await fetch(`check_location.php?employee_id=${encodeURIComponent(id)}&latitude=${latInput.value}&longitude=${lngInput.value}`);
        let loc=await locRes.json(); updateMap(parseFloat(loc.branch_lat),parseFloat(loc.branch_lng),parseFloat(loc.allowed_radius),pos.coords.latitude,pos.coords.longitude,loc.in_range);
        if(!loc.in_range){ showStatus('❌ Out of range!',true); showReset(); return; }
        showStatus(`In range: ${loc.branch} (${loc.distance}m)`);
        // Late/Early logic
        const now=new Date(); const shiftStart=new Date(loc.shift_start); const shiftEnd=new Date(loc.shift_end);
        if(now>shiftStart && now-shiftStart>5*60000){ reasonArea.classList.remove('hidden'); showStatus('You are late. Please provide a reason.',true); showReset(); return; }
        if(now<shiftEnd && shiftEnd-now>5*60000 && e.type==='logout'){ reasonArea.classList.remove('hidden'); showStatus('Leaving early? Provide reason.',true); showReset(); return; }
        // Submit
        await submitEntry({employee_id:id});
      }catch(err){ console.error(err); showStatus('Error: '+err,true); showReset(); }
    });

    // -- Submit clock in/out
    async function submitEntry(data){ showStatus('Processing...');
      const payload={...data, snapshot: snapshotInput.value, latitude: latInput.value, longitude: lngInput.value, timestamp: Date.now(), reason: reasonInput.value};
      if(!navigator.onLine){ await saveEntry(payload); showStatus('Offline saved.'); showReset(); return; }
      try{
        const fd=new FormData(); Object.entries(payload).forEach(([k,v])=>fd.append(k,v));
        let res=await fetch('clockinout.php',{method:'POST',body:fd}); let txt=await res.text();
        if(res.ok){ showStatus(`✅ ${txt}`);} else showStatus(`❌ ${txt}`,true);
      }catch{ await saveEntry(payload); showStatus('Network error, saved offline.',true); }
      showReset(); }

    resetBtn.addEventListener('click',()=> location.reload());
    if(navigator.onLine) syncOffline();
  </script>
</body>
</html>
