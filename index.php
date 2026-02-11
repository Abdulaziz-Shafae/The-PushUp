<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Pushup Tracker</title>
  <style>
    :root { --bg:#0b0c10; --card:#12141a; --text:#e8eaed; --muted:#9aa0a6; --line:#222631; --ok:#1db954; }
    body { margin:0; font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial; background:var(--bg); color:var(--text); }
    .wrap { max-width: 560px; margin: 0 auto; padding: 16px; }
    .card { background:var(--card); border:1px solid var(--line); border-radius: 16px; padding: 14px; }
    .row { display:flex; align-items:center; justify-content:space-between; gap:12px; }
    .h1 { font-size: 20px; font-weight: 800; }
    .muted { color: var(--muted); font-size: 13px; }
    .stats { display:grid; grid-template-columns: 1fr 1fr 1fr; gap:10px; margin-top: 10px; }
    .stat { padding: 10px; border:1px solid var(--line); border-radius: 14px; background: #0f1117; }
    .stat .v { font-size: 18px; font-weight: 800; }
    .tabs { display:flex; gap:10px; margin: 12px 0; }
    .tab { flex:1; padding: 10px; border-radius: 14px; border:1px solid var(--line); background:#0f1117; color:var(--text); font-weight: 800; }
    .tab.active { border-color: #3a3f52; background:#151927; }
    .hint { margin-top: 10px; font-size: 12px; color: var(--muted); }
    .list { margin-top: 10px; }
    .item { padding: 12px; border:1px solid var(--line); border-radius: 14px; background:#0f1117; margin-bottom: 10px; }
    .badge { font-size: 12px; padding: 4px 10px; border-radius: 999px; border:1px solid var(--line); color: var(--muted); }
    .badge.ok { color: #0b0c10; background: var(--ok); border-color: var(--ok); font-weight: 900; }
    .grid { display:grid; grid-template-columns: repeat(7, 1fr); gap: 8px; margin-top: 10px; }
    .cell { aspect-ratio: 1/1; border-radius: 14px; border:1px solid var(--line); background:#0f1117; display:flex; align-items:center; justify-content:center; flex-direction:column; gap:4px; padding: 6px; user-select:none; }
    .cell.ok { border-color: var(--ok); }
    .d { font-weight: 900; font-size: 13px; }
    .t { font-size: 12px; color: var(--muted); }
    .topline { display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom: 8px; }
    .navbtn { padding:8px 12px; border-radius: 12px; border:1px solid var(--line); background:#0f1117; color:var(--text); font-weight:900; }
    .danger { margin-top: 12px; width:100%; padding: 10px 12px; border-radius: 14px; border:1px solid #442; background:#1a0f11; color:#ffb4b4; font-weight: 900; }

    .actions { display:flex; gap:8px; }
    .small { padding:8px 10px; border-radius: 14px; border:1px solid var(--line); background:#0f1117; color:var(--text); font-weight:900; }
    select { border-radius: 14px; border:1px solid var(--line); background:#0f1117; color:var(--text); padding:8px 10px; font-weight:900; max-width: 220px; }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <div class="row">
        <div>
          <div class="h1">Pushup Tracker</div>
          <div class="muted" id="subtitle">Loading…</div>
        </div>
        <div class="badge" id="todayBadge">—</div>
      </div>

      <div class="row" style="margin-top:12px;">
        <div style="display:flex; gap:10px; align-items:center;">
          <div class="muted" style="font-weight:900;">Profile</div>
          <select id="profileSelect"></select>
        </div>
        <div class="actions">
          <button class="small" id="addProfileBtn">+ Add</button>
          <button class="small" id="renameProfileBtn">Rename</button>
          <button class="small" id="deleteProfileBtn">Delete</button>
        </div>
      </div>

      <div class="stats">
        <div class="stat"><div class="muted">Streak</div><div class="v" id="streak">—</div></div>
        <div class="stat"><div class="muted">Completed Days</div><div class="v" id="doneDays">—</div></div>
        <div class="stat"><div class="muted">Total Pushups</div><div class="v" id="totalPushups">—</div></div>
      </div>

      <div class="hint">Double-tap a day to toggle ✅</div>
    </div>

    <div class="tabs">
      <button class="tab active" id="tabList">List</button>
      <button class="tab" id="tabCal">Calendar</button>
    </div>

    <div class="card" id="panel">
      <div class="topline">
        <button class="navbtn" id="prevMonth">◀</button>
        <div class="h1" id="monthTitle" style="font-size:16px;">—</div>
        <button class="navbtn" id="nextMonth">▶</button>
      </div>

      <div id="listView" class="list"></div>
      <div id="calView" style="display:none;">
        <div class="grid" id="calGrid"></div>
      </div>

      <button class="danger" id="resetBtn">Reset tracker on this device (new device id)</button>
    </div>
  </div>

<script>
  // device id (no account)
  function generateDeviceId() {
    if (window.crypto && typeof window.crypto.randomUUID === "function") {
      return window.crypto.randomUUID().replace(/-/g, "");
    }

    // Fallback for older browsers without crypto.randomUUID.
    const rand = Math.random().toString(36).slice(2);
    return `${Date.now().toString(36)}${rand}${rand}`.slice(0, 32);
  }

  function getDeviceId() {
    const key = "pushup_device_id_v3";
    let id = localStorage.getItem(key);
    if (!id) {
      id = generateDeviceId();
      localStorage.setItem(key, id);
    }
    return id;
  }
  const deviceId = getDeviceId();

  // selected profile
  const PROFILE_KEY = "pushup_selected_profile_v3";
  function getSelectedProfile() { return localStorage.getItem(PROFILE_KEY) || ""; }
  function setSelectedProfile(id) { localStorage.setItem(PROFILE_KEY, id); }

  // double-tap helper
  function attachDoubleTap(el, onDouble) {
    let last = 0;
    const threshold = 320;

    el.addEventListener("touchend", (e) => {
      const now = Date.now();
      if (now - last < threshold) { last = 0; onDouble(e); }
      else { last = now; }
    }, {passive:true});

    el.addEventListener("dblclick", (e) => onDouble(e));
  }

  // month state
  let current = new Date();
  current.setDate(1);

  function yyyymm(d) {
    const y = d.getFullYear();
    const m = String(d.getMonth()+1).padStart(2,'0');
    return `${y}-${m}`;
  }
  function monthTitle(ym) {
    const [y,m] = ym.split("-");
    const date = new Date(Number(y), Number(m)-1, 1);
    return date.toLocaleString(undefined, {month:"long", year:"numeric"});
  }

  async function apiProfilesList() {
    const res = await fetch(`api.php?action=profiles_list&device_id=${encodeURIComponent(deviceId)}`);
    const data = await res.json();
    if (!res.ok) throw new Error(data.error || "Failed to load profiles");
    return data.profiles || [];
  }
  async function apiProfilesCreate(name) {
    const res = await fetch(`api.php?action=profiles_create`, {
      method:"POST",
      headers: {"Content-Type":"application/json"},
      body: JSON.stringify({device_id: deviceId, name})
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.error || "Create failed");
    return data.profile_id;
  }
  async function apiProfilesRename(profile_id, name) {
    const res = await fetch(`api.php?action=profiles_rename`, {
      method:"POST",
      headers: {"Content-Type":"application/json"},
      body: JSON.stringify({device_id: deviceId, profile_id, name})
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.error || "Rename failed");
  }
  async function apiProfilesDelete(profile_id) {
    const res = await fetch(`api.php?action=profiles_delete`, {
      method:"POST",
      headers: {"Content-Type":"application/json"},
      body: JSON.stringify({device_id: deviceId, profile_id})
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.error || "Delete failed");
  }

  async function apiState(profile_id, ym) {
    const res = await fetch(`api.php?action=state&device_id=${encodeURIComponent(deviceId)}&profile_id=${encodeURIComponent(profile_id)}&month=${encodeURIComponent(ym)}`);
    const data = await res.json();
    if (!res.ok) throw new Error(data.error || "Failed to load");
    return data;
  }
  async function apiToggle(profile_id, dateStr) {
    const res = await fetch(`api.php?action=toggle`, {
      method:"POST",
      headers: {"Content-Type":"application/json"},
      body: JSON.stringify({device_id: deviceId, profile_id, date: dateStr})
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.error || "Toggle failed");
  }

  function fmtDate(dateStr) {
    const d = new Date(dateStr + "T00:00:00");
    return d.toLocaleDateString(undefined, {weekday:"short", month:"2-digit", day:"2-digit"});
  }
  function dayNumber(dateStr) { return Number(dateStr.slice(-2)); }

  function renderHeader(profileName, stats) {
    // Next target = completedDays + 1
    document.getElementById("subtitle").textContent =
      `${profileName} • Next target: ${stats.nextTarget} pushup${stats.nextTarget===1?'':'s'}`;

    const badge = document.getElementById("todayBadge");
    badge.textContent = stats.todayCompleted ? "✅ Today done" : "Today";
    badge.className = "badge" + (stats.todayCompleted ? " ok" : "");

    document.getElementById("streak").textContent = stats.currentStreak;
    document.getElementById("doneDays").textContent = stats.totalCompletedDays;
    document.getElementById("totalPushups").textContent = stats.totalPushupsCompleted;
  }

  function renderList(profile_id, days) {
    const wrap = document.getElementById("listView");
    wrap.innerHTML = "";

    for (const d of days) {
      const div = document.createElement("div");
      div.className = "item";

      const left = document.createElement("div");
      left.innerHTML = `<div style="font-weight:900">${fmtDate(d.date)}</div>
                        <div class="muted">Target: ${d.target}</div>`;

      const right = document.createElement("div");
      const badge = document.createElement("div");
      badge.className = "badge" + (d.completed ? " ok" : "");
      badge.textContent = d.completed ? "✅" : "—";
      right.appendChild(badge);

      const row = document.createElement("div");
      row.className = "row";
      row.appendChild(left);
      row.appendChild(right);

      div.appendChild(row);

      attachDoubleTap(div, async () => {
        try { await apiToggle(profile_id, d.date); await refresh(); }
        catch (e) { alert(e.message); }
      });

      wrap.appendChild(div);
    }
  }

  function renderCalendar(profile_id, ym, days) {
    const grid = document.getElementById("calGrid");
    grid.innerHTML = "";

    const [y,m] = ym.split("-").map(Number);
    const first = new Date(y, m-1, 1);
    const startDow = (first.getDay() + 6) % 7; // Monday=0

    const names = ["M","T","W","T","F","S","S"];
    for (const n of names) {
      const h = document.createElement("div");
      h.className = "muted";
      h.style.textAlign = "center";
      h.style.fontWeight = "900";
      h.textContent = n;
      grid.appendChild(h);
    }

    for (let i=0; i<startDow; i++) {
      const blank = document.createElement("div");
      blank.className = "cell";
      blank.style.visibility = "hidden";
      grid.appendChild(blank);
    }

    for (const d of days) {
      const cell = document.createElement("div");
      cell.className = "cell" + (d.completed ? " ok" : "");
      cell.innerHTML = `<div class="d">${dayNumber(d.date)}</div>
                        <div class="t">${d.target}</div>`;

      attachDoubleTap(cell, async () => {
        try { await apiToggle(profile_id, d.date); await refresh(); }
        catch (e) { alert(e.message); }
      });

      grid.appendChild(cell);
    }
  }

  function setActiveTab(which) {
    const listBtn = document.getElementById("tabList");
    const calBtn = document.getElementById("tabCal");
    const listView = document.getElementById("listView");
    const calView = document.getElementById("calView");

    if (which === "list") {
      listBtn.classList.add("active"); calBtn.classList.remove("active");
      listView.style.display = ""; calView.style.display = "none";
    } else {
      calBtn.classList.add("active"); listBtn.classList.remove("active");
      calView.style.display = ""; listView.style.display = "none";
    }
  }

  // profiles UI
  let profiles = [];
  function fillProfileSelect(selectedId) {
    const sel = document.getElementById("profileSelect");
    sel.innerHTML = "";
    for (const p of profiles) {
      const opt = document.createElement("option");
      opt.value = p.profile_id;
      opt.textContent = p.name;
      sel.appendChild(opt);
    }
    if (selectedId) sel.value = selectedId;
  }

  async function ensureProfile() {
    profiles = await apiProfilesList();

    if (profiles.length === 0) {
      const name = prompt("Create first profile name:");
      if (!name) throw new Error("Profile name required");
      const newId = await apiProfilesCreate(name.trim());
      profiles = await apiProfilesList();
      setSelectedProfile(newId);
    }

    const saved = getSelectedProfile();
    const exists = profiles.some(p => p.profile_id === saved);
    const useId = exists ? saved : profiles[0].profile_id;

    setSelectedProfile(useId);
    fillProfileSelect(useId);
    return useId;
  }

  async function refresh() {
    const profile_id = await ensureProfile();
    const ym = yyyymm(current);

    document.getElementById("monthTitle").textContent = monthTitle(ym);

    const data = await apiState(profile_id, ym);
    const profName = (profiles.find(p => p.profile_id === profile_id) || {}).name || "Profile";
    renderHeader(profName, data.stats);
    renderList(profile_id, data.days);
    renderCalendar(profile_id, ym, data.days);
  }

  // handlers
  document.getElementById("tabList").addEventListener("click", () => setActiveTab("list"));
  document.getElementById("tabCal").addEventListener("click", () => setActiveTab("cal"));

  document.getElementById("prevMonth").addEventListener("click", async () => {
    current = new Date(current.getFullYear(), current.getMonth()-1, 1);
    await refresh();
  });
  document.getElementById("nextMonth").addEventListener("click", async () => {
    current = new Date(current.getFullYear(), current.getMonth()+1, 1);
    await refresh();
  });

  document.getElementById("profileSelect").addEventListener("change", async (e) => {
    setSelectedProfile(e.target.value);
    await refresh();
  });

  document.getElementById("addProfileBtn").addEventListener("click", async () => {
    try {
      const name = prompt("Profile name:");
      if (!name) return;
      const newId = await apiProfilesCreate(name.trim());
      profiles = await apiProfilesList();
      setSelectedProfile(newId);
      fillProfileSelect(newId);
      await refresh();
    } catch (e) { alert(e.message); }
  });

  document.getElementById("renameProfileBtn").addEventListener("click", async () => {
    try {
      const profile_id = getSelectedProfile();
      const currentName = (profiles.find(p => p.profile_id === profile_id) || {}).name || "";
      const name = prompt("New name:", currentName);
      if (!name) return;
      await apiProfilesRename(profile_id, name.trim());
      profiles = await apiProfilesList();
      fillProfileSelect(profile_id);
      await refresh();
    } catch (e) { alert(e.message); }
  });

  document.getElementById("deleteProfileBtn").addEventListener("click", async () => {
    try {
      const profile_id = getSelectedProfile();
      const p = profiles.find(x => x.profile_id === profile_id);
      if (!p) return;

      const ok = confirm(`Delete profile "${p.name}"? This will remove ALL its history.`);
      if (!ok) return;

      await apiProfilesDelete(profile_id);
      profiles = await apiProfilesList();

      if (profiles.length === 0) localStorage.removeItem(PROFILE_KEY);
      else setSelectedProfile(profiles[0].profile_id);

      await refresh();
    } catch (e) { alert(e.message); }
  });

  document.getElementById("resetBtn").addEventListener("click", () => {
    const ok = confirm("Reset tracker on this device? (Creates a new device id)");
    if (!ok) return;
    localStorage.removeItem("pushup_device_id_v3");
    localStorage.removeItem(PROFILE_KEY);
    location.reload();
  });

  refresh().catch(e => alert(e.message));
</script>
</body>
</html>
