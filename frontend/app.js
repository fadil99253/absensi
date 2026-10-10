const app = document.querySelector("#app");
const toast = document.querySelector("#toast");
const state = {
  page: "dashboard",
  attendanceType: "Masuk",
  employees: [],
  dashboard: null,
  date: new Date().toLocaleDateString("sv-SE", { timeZone: "Asia/Jakarta" }),
  query: "",
  cameraStream: null,
  captured: false,
};

const icons = {
  brand: '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 3.75h10a2.25 2.25 0 0 1 2.25 2.25v12A2.25 2.25 0 0 1 17 20.25H7A2.25 2.25 0 0 1 4.75 18V6A2.25 2.25 0 0 1 7 3.75Z" stroke="currentColor" stroke-width="1.8"/><path d="m8 12.2 2.4 2.4 5.6-5.6M8.25 7.5h2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
};
const navItems = [
  { id: "dashboard", label: "Dashboard", icon: "◫" },
  { id: "attendance", label: "Data Absensi", icon: "▤" },
  { id: "employees", label: "Data Karyawan", icon: "♙" },
  { id: "kiosk", label: "Presensi Wajah", icon: "◎" },
];

function getUser() {
  try {
    const user = JSON.parse(sessionStorage.getItem("hadirkuUser") || "null");
    return user && typeof user.token === "string" ? user : null;
  }
  catch { return null; }
}

async function api(url, options = {}) {
  const token = getUser()?.token;
  const response = await fetch(url, {
    ...options,
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...(options.headers || {}),
    },
  });
  const data = await response.json();
  if (!response.ok) {
    const validationMessage = Object.values(data.errors || {}).flat()[0];
    if (response.status === 401 && token) {
      sessionStorage.removeItem("hadirkuUser");
      stopCamera();
      app.innerHTML = loginPage();
      showToast("Sesi berakhir. Silakan masuk kembali.");
    }
    const error = new Error(data.error || validationMessage || data.message || "Permintaan tidak berhasil.");
    error.status = response.status;
    throw error;
  }
  return data;
}

function showToast(message) {
  toast.textContent = message;
  toast.classList.add("show");
  clearTimeout(showToast.timer);
  showToast.timer = setTimeout(() => toast.classList.remove("show"), 3000);
}

function dateLabel(value = state.date) {
  return new Intl.DateTimeFormat("id-ID", { weekday: "long", day: "numeric", month: "long", year: "numeric", timeZone: "Asia/Jakarta" })
    .format(new Date(`${value}T12:00:00+07:00`));
}

function brand(light = false) {
  return `<div class="brand"><div class="brand-mark">${icons.brand}</div><div class="brand-name">Hadir<span>Ku</span></div></div>`;
}

function navButton(item, mobile = false) {
  return `<button class="nav-link ${state.page === item.id ? "active" : ""}" data-page="${item.id}"><span class="nav-icon">${item.icon}</span>${item.label}</button>`;
}

function shell(content) {
  const user = getUser();
  return `<div class="shell">
    <aside class="sidebar">
      ${brand()}
      <div class="nav-label">MENU UTAMA</div>
      <nav class="nav-list">${navItems.map((item) => navButton(item)).join("")}</nav>
      <div class="sidebar-spacer"></div>
      <div class="help-card"><strong>Butuh bantuan?</strong><p>Hubungi tim support kami jika kamu mengalami kendala.</p></div>
      <div class="profile"><div class="avatar">AD</div><div class="profile-info"><strong>${escapeHtml(user?.name || "Admin HadirKu")}</strong><small>Administrator</small></div><button class="logout" data-action="logout" title="Keluar">↪</button></div>
    </aside>
    <main class="main">
      <header class="topbar"><div class="breadcrumbs">HadirKu&nbsp; / &nbsp;<b>${pageTitle()}</b></div><div class="top-actions"><span class="top-date">◷ &nbsp;${dateLabel()}</span><button class="icon-button" data-action="refresh" title="Muat ulang">↻</button><button class="logout top-logout" data-action="logout" title="Keluar" aria-label="Keluar">↪</button><div class="avatar">AD</div></div></header>
      <section class="content">${content}</section>
    </main>
    <nav class="mobile-bottom">${navItems.map((item) => navButton(item, true)).join("")}</nav>
  </div>`;
}

function pageTitle() {
  return navItems.find((item) => item.id === state.page)?.label || "Dashboard";
}

function escapeHtml(value = "") {
  return String(value).replace(/[&<>"']/g, (char) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[char]);
}

function initials(name = "") {
  return name.split(/\s+/).slice(0, 2).map((part) => part[0] || "").join("").toUpperCase();
}

function pageHeading(title, description, actions = "") {
  return `<div class="page-heading"><div><h1>${title}</h1><p>${description}</p></div>${actions ? `<div class="heading-actions">${actions}</div>` : ""}</div>`;
}

function statCard(label, number, caption, icon, color = "") {
  return `<article class="stat-card"><div class="stat-top"><span>${label}</span><span class="stat-icon ${color}">${icon}</span></div><div class="stat-number">${number}</div><div class="stat-foot">${caption}</div></article>`;
}

function personCell(name, id) {
  return `<div class="person-cell"><span class="mini-avatar">${initials(name)}</span><span><strong>${escapeHtml(name)}</strong><small>${escapeHtml(id)}</small></span></div>`;
}

function attendanceRows(records) {
  if (!records.length) return `<tr><td colspan="5"><div class="empty-state">Belum ada catatan presensi untuk tanggal ini.</div></td></tr>`;
  return records.map((record) => `<tr>
    <td>${personCell(record.employeeName, record.employeeId)}</td>
    <td>${escapeHtml(record.department)}</td><td>${escapeHtml(record.time)} WIB</td>
    <td><span class="badge">${escapeHtml(record.type)}</span></td>
    <td><span class="badge ${record.status === "Terlambat" ? "warning" : ""}">${escapeHtml(record.status)}</span></td>
  </tr>`).join("");
}

async function loadDashboard() {
  const [dashboard, employees] = await Promise.all([api("/api/dashboard"), api("/api/employees")]);
  state.dashboard = dashboard;
  state.employees = employees;
}

async function renderDashboard() {
  await loadDashboard();
  const { counts, recent } = state.dashboard;
  const percentage = counts.total ? Math.round((counts.present / counts.total) * 100) : 0;
  return `${pageHeading("Dashboard", "Pantau kehadiran dan aktivitas karyawan hari ini.", `<button class="button button-secondary" data-action="refresh">↻ &nbsp; Segarkan</button><button class="button button-primary" data-page="kiosk">＋ &nbsp; Catat Presensi</button>`)}
    <div class="welcome"><div class="welcome-copy"><div class="eyebrow"><span class="live-dot"></span> HARI INI · ${dateLabel().toLocaleUpperCase("id-ID")}</div><h2>Selamat datang di HadirKu 👋</h2><p>Semoga harimu produktif! Berikut ringkasan kehadiran tim kamu.</p></div><div class="date-pill"><span>▦</span> ${dateLabel()}</div></div>
    <div class="stats-grid">
      ${statCard("Hadir Hari Ini", counts.present, `<b>●</b> Karyawan sudah presensi`, "✓")}
      ${statCard("Terlambat", counts.late, "Perlu perhatian hari ini", "◷", "orange")}
      ${statCard("Belum Hadir", counts.absent, "Belum melakukan presensi masuk", "↗", "red")}
      ${statCard("Total Karyawan", counts.total, "Karyawan aktif terdaftar", "♙", "blue")}
    </div>
    <div class="dashboard-grid">
      <section class="panel"><div class="panel-heading"><div><h3>Aktivitas Presensi Hari Ini</h3><p>Catatan kehadiran terbaru</p></div><button class="text-link" data-page="attendance">Lihat semua →</button></div><div class="table-wrap"><table><thead><tr><th>NAMA KARYAWAN</th><th>DEPARTEMEN</th><th>WAKTU</th><th>TIPE</th><th>STATUS</th></tr></thead><tbody>${attendanceRows(recent)}</tbody></table></div></section>
      <aside class="side-panel"><section class="quick-card"><h3>Presensi Karyawan</h3><p>Catat kehadiran dengan verifikasi kamera.</p><button class="button button-primary" data-page="kiosk">◎ &nbsp; Mulai Presensi Wajah</button></section><section class="quick-card"><h3>Ringkasan Kehadiran</h3><p>Persentase karyawan yang sudah hadir hari ini.</p><div class="progress-row"><span>Tingkat kehadiran</span><b>${percentage}%</b></div><div class="progress"><span style="width:${percentage}%"></span></div><div class="progress-row"><span>${counts.present} dari ${counts.total} karyawan</span><span>Hari ini</span></div></section></aside>
    </div>`;
}

async function renderAttendance() {
  const result = await api(`/api/attendance?date=${encodeURIComponent(state.date)}&q=${encodeURIComponent(state.query)}`);
  return `${pageHeading("Data Absensi", "Kelola dan pantau catatan kehadiran karyawan.", `<button class="button button-primary" data-page="kiosk">＋ &nbsp; Catat Presensi</button>`)}
    <div class="filter-bar"><input class="search-input" id="search-records" type="search" placeholder="⌕  Cari nama atau ID karyawan..." value="${escapeHtml(state.query)}"><input class="select-input" id="date-filter" type="date" value="${escapeHtml(state.date)}"><button class="button button-secondary" data-action="export">⇩ &nbsp; Ekspor CSV</button></div>
    <section class="panel records-panel"><div class="table-wrap"><table><thead><tr><th>NAMA KARYAWAN</th><th>DEPARTEMEN</th><th>WAKTU</th><th>TIPE</th><th>STATUS</th></tr></thead><tbody>${attendanceRows(result.records)}</tbody></table></div><div class="records-footer"><span>Menampilkan ${result.records.length} catatan · ${dateLabel(result.date)}</span><span>Zona waktu: WIB</span></div></section>`;
}

async function renderEmployees() {
  state.employees = await api("/api/employees");
  const cards = state.employees.map((employee) => `<article class="employee-card"><div class="employee-card-top"><div class="avatar">${initials(employee.name)}</div><div><strong>${escapeHtml(employee.name)}</strong><small>${escapeHtml(employee.role)}</small></div></div><div class="employee-meta"><span>${escapeHtml(employee.id)} · ${escapeHtml(employee.department)}</span><span class="badge">Aktif</span></div></article>`).join("");
  return `${pageHeading("Data Karyawan", "Daftar karyawan yang terdaftar di sistem HadirKu.", `<button class="button button-primary" data-action="add-employee">＋ &nbsp; Tambah Karyawan</button>`)}
    <div class="filter-bar"><input class="search-input" id="search-employees" type="search" placeholder="⌕  Cari nama, departemen, atau ID..."></div>
    <div class="employee-grid" id="employee-grid">${cards || '<div class="empty-state">Belum ada data karyawan.</div>'}</div>`;
}

async function renderKiosk() {
  state.captured = false;
  state.employees = await api("/api/employees");
  const options = state.employees.filter((employee) => employee.active).map((employee) => `<option value="${escapeHtml(employee.id)}">${escapeHtml(employee.name)} · ${escapeHtml(employee.id)}</option>`).join("");
  return `${pageHeading("Presensi Wajah", "Arahkan wajah ke kamera untuk mencatat kehadiran.", `<button class="button button-secondary" data-page="dashboard">← &nbsp; Kembali</button>`)}
    <div class="kiosk-layout">
      <section class="panel camera-panel"><div class="panel-heading"><div><h3>Verifikasi Kamera</h3><p>Posisikan wajah di dalam bingkai</p></div><span class="badge neutral" id="camera-status">Kamera mati</span></div>
        <div class="camera-stage" id="camera-stage"><video id="camera-video" autoplay playsinline muted></video><div class="camera-placeholder"><div class="camera-symbol">◎</div><strong>Siap untuk presensi?</strong><p>Aktifkan kamera dan pastikan wajah terlihat jelas di area yang terang.</p><button class="button button-primary" data-action="start-camera">Aktifkan Kamera</button></div><div class="face-guide"></div><div class="camera-hint">Posisikan wajah di tengah bingkai</div></div>
        <div class="camera-actions"><button class="button button-secondary" data-action="start-camera">▣ &nbsp; Buka Kamera</button><button class="button button-primary" data-action="capture" disabled>◎ &nbsp; Ambil Foto</button></div>
      </section>
      <form class="panel attendance-form" id="attendance-form"><h3>Detail Presensi</h3><p>Lengkapi detail di bawah sebelum mencatat kehadiran.</p>
        <div class="form-group"><label for="employee-select">PILIH KARYAWAN</label><select class="select-input" id="employee-select" required><option value="">Pilih karyawan</option>${options}</select></div>
        <div class="form-group"><label>JENIS PRESENSI</label><div class="attendance-types"><button type="button" class="type-option selected" data-type="Masuk">↘ &nbsp; Masuk</button><button type="button" class="type-option" data-type="Pulang">↗ &nbsp; Pulang</button></div></div>
        <div class="form-group"><label>WAKTU PRESENSI</label><input class="form-input" id="current-time" value="Memuat waktu..." readonly></div>
        <div class="note">Foto hanya menjadi konfirmasi dari kamera di browser dan tidak disimpan. Pencocokan biometrik wajah belum diaktifkan. Pastikan data karyawan sudah benar sebelum melanjutkan.</div>
        <button class="button button-primary submit-button" type="submit">✓ &nbsp; Konfirmasi Presensi</button>
      </form>
    </div>`;
}

function loginPage() {
  return `<main class="login-page">
    <section class="login-visual">${brand(true)}<div class="login-pitch"><h1>Kerja lebih nyaman, presensi lebih mudah.</h1><p>Kelola kehadiran tim dengan praktis. Satu platform untuk absensi yang lebih tertib dan transparan.</p><div class="visual-stats"><div><strong>Real-time</strong><small>Pantau kehadiran</small></div><div><strong>Lebih mudah</strong><small>Presensi wajah</small></div></div></div><div class="login-footer">© 2024 HadirKu · Solusi absensi modern</div></section>
    <section class="login-side"><div class="login-box">${brand()}<h2>Selamat datang 👋</h2><p>Masuk ke akun HadirKu untuk melanjutkan.</p><form id="login-form"><div class="form-group"><label for="login-email">EMAIL</label><input class="form-input" id="login-email" type="email" placeholder="nama@perusahaan.com" autocomplete="username" required></div><div class="form-group"><label for="login-password">KATA SANDI</label><input class="form-input" id="login-password" type="password" placeholder="Masukkan kata sandi" autocomplete="current-password" required></div><button class="button button-primary" type="submit">Masuk ke Dashboard &nbsp; →</button></form><div class="demo-login"><strong>Akun demo</strong><br>Email: admin@hadirku.id<br>Kata sandi: hadirku123</div></div></section>
  </main>`;
}

async function render() {
  if (!getUser()) {
    stopCamera();
    app.innerHTML = loginPage();
    return;
  }
  app.innerHTML = shell('<div class="loading">Memuat data...</div>');
  try {
    let content;
    if (state.page === "attendance") content = await renderAttendance();
    else if (state.page === "employees") content = await renderEmployees();
    else if (state.page === "kiosk") content = await renderKiosk();
    else content = await renderDashboard();
    if (getUser()) app.innerHTML = shell(content);
    if (state.page === "kiosk") startClock();
  } catch (error) {
    if (!getUser()) {
      app.innerHTML = loginPage();
      return;
    }
    app.innerHTML = shell(`<section class="panel"><div class="empty-state">${escapeHtml(error.message)}<br><br><button class="button button-primary" data-action="refresh">Coba lagi</button></div></section>`);
  }
}

function stopCamera() {
  if (state.cameraStream) {
    state.cameraStream.getTracks().forEach((track) => track.stop());
    state.cameraStream = null;
  }
}

async function startCamera() {
  if (!navigator.mediaDevices?.getUserMedia) {
    showToast("Kamera memerlukan HTTPS atau localhost dan browser yang mendukung.");
    return;
  }
  try {
    stopCamera();
    state.cameraStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: "user", width: { ideal: 1280 }, height: { ideal: 720 } }, audio: false });
    const video = document.querySelector("#camera-video");
    const stage = document.querySelector("#camera-stage");
    if (!video || !stage) return;
    video.srcObject = state.cameraStream;
    stage.classList.add("camera-on");
    document.querySelector("#camera-status").textContent = "Kamera aktif";
    document.querySelector('[data-action="capture"]').disabled = false;
    showToast("Kamera aktif. Posisikan wajah lalu ambil foto.");
  } catch (error) {
    showToast(error.name === "NotAllowedError" ? "Izin kamera ditolak. Aktifkan izin kamera di browser." : `Kamera tidak dapat dibuka: ${error.message}`);
  }
}

async function capturePhoto() {
  const video = document.querySelector("#camera-video");
  if (!video || !video.videoWidth) {
    showToast("Kamera belum siap. Coba aktifkan kamera kembali.");
    return;
  }
  const canvas = document.createElement("canvas");
  canvas.width = video.videoWidth;
  canvas.height = video.videoHeight;
  const context = canvas.getContext("2d");
  context.translate(canvas.width, 0);
  context.scale(-1, 1);
  context.drawImage(video, 0, 0);
  state.captured = true;
  document.querySelector('[data-action="capture"]').textContent = "✓ Foto diambil";
  showToast("Foto berhasil diambil. Silakan konfirmasi presensi.");
}

function startClock() {
  clearInterval(startClock.timer);
  const update = () => {
    const input = document.querySelector("#current-time");
    if (input) input.value = `${new Intl.DateTimeFormat("id-ID", { hour: "2-digit", minute: "2-digit", second: "2-digit", timeZone: "Asia/Jakarta" }).format(new Date())} WIB · ${dateLabel()}`;
  };
  update();
  startClock.timer = setInterval(update, 1000);
}

function showEmployeeModal() {
  const backdrop = document.createElement("div");
  backdrop.className = "modal-backdrop";
  backdrop.innerHTML = `<section class="modal"><div class="modal-head"><h3>Tambah Karyawan</h3><button type="button" class="close-modal" data-action="close-modal">×</button></div><form id="employee-form"><div class="form-group"><label for="employee-id">ID KARYAWAN</label><input class="form-input" id="employee-id" name="id" placeholder="Contoh: EMP-007" required></div><div class="form-group"><label for="employee-name">NAMA LENGKAP</label><input class="form-input" id="employee-name" name="name" placeholder="Nama karyawan" required></div><div class="form-group"><label for="employee-department">DEPARTEMEN</label><input class="form-input" id="employee-department" name="department" placeholder="Contoh: Engineering" required></div><div class="form-group"><label for="employee-role">JABATAN</label><input class="form-input" id="employee-role" name="role" placeholder="Contoh: Developer"></div><button class="button button-primary" type="submit">Simpan Karyawan</button></form></section>`;
  app.append(backdrop);
  backdrop.addEventListener("click", (event) => { if (event.target === backdrop) backdrop.remove(); });
}

function exportCsv() {
  const rows = [...document.querySelectorAll(".records-panel tbody tr")];
  const headers = ["Nama Karyawan", "ID Karyawan", "Departemen", "Waktu", "Jenis", "Status"];
  const records = rows.filter((row) => row.querySelector(".person-cell")).map((row) => {
    const person = row.querySelector(".person-cell");
    return [person.querySelector("strong").textContent, person.querySelector("small").textContent, ...Array.from(row.querySelectorAll("td")).slice(1).map((cell) => cell.textContent.trim())];
  });
  const csv = [headers, ...records].map((row) => row.map((value) => `"${String(value).replace(/"/g, '""')}"`).join(",")).join("\r\n");
  const link = document.createElement("a");
  link.href = URL.createObjectURL(new Blob(["\ufeff", csv], { type: "text/csv;charset=utf-8" }));
  link.download = `absensi-${state.date}.csv`;
  link.click();
  URL.revokeObjectURL(link.href);
}

app.addEventListener("click", async (event) => {
  const target = event.target.closest("button");
  if (!target) return;
  if (target.dataset.page) {
    if (state.page === "kiosk" && target.dataset.page !== "kiosk") {
      stopCamera();
      state.captured = false;
    }
    state.page = target.dataset.page;
    await render();
  } else if (target.dataset.action === "logout") {
    try {
      await api("/api/logout", { method: "POST" });
    } catch (error) {
      showToast(error.message);
    } finally {
      sessionStorage.removeItem("hadirkuUser");
      state.page = "dashboard";
      await render();
    }
  } else if (target.dataset.action === "refresh") {
    await render();
  } else if (target.dataset.action === "start-camera") {
    await startCamera();
  } else if (target.dataset.action === "capture") {
    await capturePhoto();
  } else if (target.dataset.action === "add-employee") {
    showEmployeeModal();
  } else if (target.dataset.action === "close-modal") {
    target.closest(".modal-backdrop").remove();
  } else if (target.dataset.action === "export") {
    exportCsv();
  } else if (target.dataset.type) {
    state.attendanceType = target.dataset.type;
    document.querySelectorAll(".type-option").forEach((button) => button.classList.toggle("selected", button.dataset.type === state.attendanceType));
  }
});

app.addEventListener("submit", async (event) => {
  event.preventDefault();
  const form = event.target;
  try {
    if (form.id === "login-form") {
      const result = await api("/api/login", { method: "POST", body: JSON.stringify({ email: form.elements["login-email"].value.trim(), password: form.elements["login-password"].value }) });
      sessionStorage.setItem("hadirkuUser", JSON.stringify({ ...result.user, token: result.token }));
      state.page = "dashboard";
      showToast("Berhasil masuk. Selamat datang di HadirKu!");
      await render();
    } else if (form.id === "attendance-form") {
      const employeeId = document.querySelector("#employee-select").value;
      if (!employeeId) throw new Error("Pilih karyawan terlebih dahulu.");
      if (!state.captured) throw new Error("Ambil foto melalui kamera sebelum konfirmasi presensi.");
      const result = await api("/api/attendance", { method: "POST", body: JSON.stringify({ employeeId, type: state.attendanceType }) });
      showToast(`Presensi ${result.type.toLowerCase()} berhasil dicatat pukul ${result.time} WIB.`);
      state.captured = false;
      stopCamera();
      state.page = "dashboard";
      await render();
    } else if (form.id === "employee-form") {
      const data = Object.fromEntries(new FormData(form).entries());
      await api("/api/employees", { method: "POST", body: JSON.stringify(data) });
      showToast("Karyawan berhasil ditambahkan.");
      state.page = "employees";
      await render();
    }
  } catch (error) {
    showToast(error.message);
  }
});

app.addEventListener("change", async (event) => {
  if (event.target.id === "date-filter") {
    state.date = event.target.value || new Date().toLocaleDateString("sv-SE", { timeZone: "Asia/Jakarta" });
    await render();
  }
});

app.addEventListener("input", async (event) => {
  if (event.target.id === "search-records") {
    state.query = event.target.value;
    clearTimeout(app.searchTimer);
    app.searchTimer = setTimeout(async () => {
      try {
        const result = await api(`/api/attendance?date=${encodeURIComponent(state.date)}&q=${encodeURIComponent(state.query)}`);
        const body = document.querySelector(".records-panel tbody");
        const footer = document.querySelector(".records-footer span");
        if (body) body.innerHTML = attendanceRows(result.records);
        if (footer) footer.textContent = `Menampilkan ${result.records.length} catatan · ${dateLabel(result.date)}`;
      } catch (error) {
        showToast(error.message);
      }
    }, 250);
  }
  if (event.target.id === "search-employees") {
    const query = event.target.value.toLocaleLowerCase("id");
    document.querySelectorAll(".employee-card").forEach((card) => { card.hidden = !card.textContent.toLocaleLowerCase("id").includes(query); });
  }
});

window.addEventListener("beforeunload", stopCamera);
render();
