import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
    Link,
    Navigate,
    NavLink,
    Route,
    Routes,
    useLocation,
    useNavigate,
    useSearchParams,
} from 'react-router-dom';
import {
    Activity, ArrowLeft, ArrowRight, CalendarDays, Camera, Check,
    CheckCircle2, Clock3, Download, Fingerprint, LayoutDashboard,
    LogOut, Menu, ScanFace, Search, Settings, ShieldCheck, Users,
} from 'lucide-react';
import { api, getData, queryString, send } from './api.js';

const tokenKey = 'hadirku.token';
const userKey = 'hadirku.user';
const dashboardPath = (user) => user?.role === 'admin' ? '/admin/dashboard' : '/employee/dashboard';

const readUser = () => {
    try {
        return sessionStorage.getItem(tokenKey)
            ? JSON.parse(sessionStorage.getItem(userKey) || 'null')
            : null;
    } catch {
        return null;
    }
};

function App() {
    const [user, setUser] = useState(readUser);
    const [authReady, setAuthReady] = useState(() => !sessionStorage.getItem(tokenKey));
    const [authError, setAuthError] = useState('');
    const [toast, setToast] = useState('');
    const location = useLocation();
    const navigate = useNavigate();
    const timer = useRef();

    const notify = useCallback((message) => {
        setToast(message);
        window.clearTimeout(timer.current);
        timer.current = window.setTimeout(() => setToast(''), 3500);
    }, []);

    useEffect(() => {
        const unauthorized = () => {
            setUser(null);
            setAuthError('');
            setAuthReady(true);
            navigate('/login', { replace: true, state: { message: 'Sesi berakhir. Silakan masuk kembali.' } });
        };
        window.addEventListener('hadirku:unauthorized', unauthorized);
        return () => window.removeEventListener('hadirku:unauthorized', unauthorized);
    }, [navigate]);

    useEffect(() => {
        if (!sessionStorage.getItem(tokenKey)) {
            setUser(null);
            setAuthReady(true);
            return undefined;
        }

        let active = true;
        setAuthReady(false);
        getData('/api/me')
            .then((data) => {
                if (!active) return;
                sessionStorage.setItem(userKey, JSON.stringify(data.user));
                setUser(data.user);
                setAuthError('');
                setAuthReady(true);
            })
            .catch((error) => {
                if (!active || error.status === 401) return;
                setAuthError(error.message);
                setAuthReady(true);
            });

        return () => {
            active = false;
        };
    }, []);

    const onAuthenticated = (data) => {
        sessionStorage.setItem(tokenKey, data.token);
        sessionStorage.setItem(userKey, JSON.stringify(data.user));
        setUser(data.user);
        navigate(dashboardPath(data.user), { replace: true });
    };

    const onUserUpdated = (updatedUser) => {
        sessionStorage.setItem(userKey, JSON.stringify(updatedUser));
        setUser(updatedUser);
    };

    const logout = async () => {
        try {
            await send('/api/logout', 'POST');
        } catch (error) {
            if (error.status !== 401 && error.status !== 0) notify(error.message);
        } finally {
            sessionStorage.removeItem(tokenKey);
            sessionStorage.removeItem(userKey);
            setUser(null);
            setAuthError('');
            setAuthReady(true);
            navigate('/login', { replace: true });
        }
    };

    const authRoutes = ['/login', '/register', '/forgot-password', '/reset-password'];
    const isAuthPage = authRoutes.includes(location.pathname);

    return (
        <>
            {!authReady ? (
                <Loading />
            ) : authError ? (
                <ErrorPanel title="Sesi belum dapat diverifikasi" message={authError} />
            ) : isAuthPage ? (
                <Routes>
                    <Route path="/login" element={user ? <Navigate to={dashboardPath(user)} replace /> : <AuthPage mode="login" onAuthenticated={onAuthenticated} notify={notify} />} />
                    <Route path="/register" element={user ? <Navigate to={dashboardPath(user)} replace /> : <AuthPage mode="register" onAuthenticated={onAuthenticated} notify={notify} />} />
                    <Route path="/forgot-password" element={<AuthPage mode="forgot" notify={notify} />} />
                    <Route path="/reset-password" element={<AuthPage mode="reset" notify={notify} />} />
                </Routes>
            ) : !user ? (
                <Navigate to="/login" replace state={{ from: location.pathname }} />
            ) : (
                <AppShell user={user} onLogout={logout}>
                    <Routes>
                        <Route path="/dashboard" element={<Navigate to={dashboardPath(user)} replace />} />
                        <Route path="/admin/dashboard" element={<RolePage user={user} role="admin"><AdminDashboard user={user} /></RolePage>} />
                        <Route path="/employee/dashboard" element={<RolePage user={user} role="karyawan"><EmployeeDashboard user={user} /></RolePage>} />
                        <Route path="/attendance" element={<Attendance user={user} />} />
                        <Route path="/face" element={<FaceTerminal user={user} notify={notify} onUserUpdated={onUserUpdated} />} />
                        <Route path="/employees" element={<RolePage user={user} role="admin"><Employees notify={notify} /></RolePage>} />
                        <Route path="/employees/new" element={<RolePage user={user} role="admin"><EmployeeCreate notify={notify} /></RolePage>} />
                        <Route path="/settings" element={<RolePage user={user} role="admin"><SettingsPage notify={notify} /></RolePage>} />
                        <Route path="*" element={<Navigate to={dashboardPath(user)} replace />} />
                    </Routes>
                </AppShell>
            )}
            {toast && <div className="toast show" role="status">{toast}</div>}
        </>
    );
}

function RolePage({ user, role, children }) {
    return user.role === role ? children : <Navigate to={dashboardPath(user)} replace />;
}

function AuthPage({ mode, onAuthenticated, notify }) {
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const [search] = useSearchParams();
    const navigate = useNavigate();
    const heading = {
        login: 'Selamat datang kembali',
        register: 'Buat akun karyawan',
        forgot: 'Lupa kata sandi?',
        reset: 'Atur ulang kata sandi',
    }[mode];

    async function submit(event) {
        event.preventDefault();
        setBusy(true);
        setError('');
        const form = Object.fromEntries(new FormData(event.currentTarget));
        try {
            if (mode === 'login' || mode === 'register') {
                const path = mode === 'login' ? '/api/login' : '/api/register';
                const { data } = await send(path, 'POST', form);
                onAuthenticated(data);
                notify(mode === 'login' ? 'Berhasil masuk.' : 'Akun karyawan berhasil dibuat.');
                return;
            }
            if (mode === 'forgot') {
                const result = await send('/api/password/forgot', 'POST', form);
                notify(result.message);
                return;
            }
            await send('/api/password/reset', 'POST', form);
            notify('Kata sandi berhasil diperbarui.');
            navigate('/login', { replace: true });
        } catch (caught) {
            setError(caught.message);
        } finally {
            setBusy(false);
        }
    }

    return (
        <main className="login-page">
            <section className="login-visual">
                <Link className="brand" to="/login"><Brand /></Link>
                <div className="login-pitch">
                    <div className="eyebrow"><span className="live-dot" /> ABSENSI WAJAH DIGITAL</div>
                    <h1>Presensi lebih mudah,<br />hadir lebih pasti.</h1>
                    <p>Catat kehadiran dengan aman dan praktis. Cukup gunakan wajahmu, tanpa kartu dan tanpa antre.</p>
                    <div className="login-illustration" aria-hidden="true">
                        <span className="illustration-orbit" />
                        <div className="phone-device">
                            <span className="phone-camera" /><span className="phone-greeting">Presensi Hari Ini</span>
                            <span className="phone-time">08:00</span>
                            <span className="phone-face"><ScanFace size={52} strokeWidth={1.3} /></span>
                            <span className="phone-status"><span className="live-dot" /> Wajah terverifikasi</span>
                        </div>
                        <span className="illustration-check"><Check size={14} /> Presensi tercatat aman</span>
                    </div>
                </div>
                <div className="login-footer"><span className="live-dot" /> Data terlindungi dengan verifikasi wajah</div>
            </section>
            <section className="login-side">
                <div className="login-box">
                    <Link className="brand mobile-brand" to="/login"><Brand /></Link>
                    {mode !== 'login' && <Link className="back-link" to="/login"><ArrowLeft size={15} /> Kembali ke masuk</Link>}
                    <h2>{heading}</h2>
                    <p>{mode === 'login' ? 'Masuk untuk melanjutkan ke dashboard HadirKu.' : mode === 'register' ? 'Daftarkan akun karyawan untuk mulai menggunakan HadirKu.' : mode === 'forgot' ? 'Masukkan email akunmu untuk menerima tautan pemulihan.' : 'Gunakan kata sandi baru minimal 10 karakter.'}</p>
                    {error && <div className="alert alert-error" role="alert">{error}</div>}
                    <form onSubmit={submit}>
                        {mode === 'login' && <>
                            <Field label="Username / NIP"><input className="form-input" name="username" autoComplete="username" required autoFocus placeholder="Masukkan username atau NIP" /></Field>
                            <Field label="Kata sandi"><input className="form-input" name="password" type="password" autoComplete="current-password" required placeholder="Masukkan kata sandi" /></Field>
                            <div className="auth-inline"><span /><Link to="/forgot-password">Lupa kata sandi?</Link></div>
                        </>}
                        {mode === 'register' && <>
                            <Field label="NIP / Username"><input className="form-input" name="nip" required minLength="2" /></Field>
                            <Field label="Nama lengkap"><input className="form-input" name="name" required /></Field>
                            <Field label="Email aktif"><input className="form-input" name="email" type="email" required /></Field>
                            <Field label="Departemen"><input className="form-input" name="department" required /></Field>
                            <Field label="Jabatan (opsional)"><input className="form-input" name="position" /></Field>
                            <Field label="Kata sandi (minimal 10 karakter)"><input className="form-input" name="password" type="password" minLength="10" required autoComplete="new-password" /></Field>
                            <Field label="Ulangi kata sandi"><input className="form-input" name="password_confirmation" type="password" minLength="10" required autoComplete="new-password" /></Field>
                        </>}
                        {mode === 'forgot' && <Field label="Email akun"><input className="form-input" name="email" type="email" required autoFocus /></Field>}
                        {mode === 'reset' && <>
                            <input type="hidden" name="token" value={search.get('token') || ''} />
                            <Field label="Email akun"><input className="form-input" name="email" type="email" required defaultValue={search.get('email') || ''} /></Field>
                            <Field label="Kata sandi baru"><input className="form-input" name="password" type="password" minLength="10" required autoComplete="new-password" /></Field>
                            <Field label="Ulangi kata sandi"><input className="form-input" name="password_confirmation" type="password" minLength="10" required autoComplete="new-password" /></Field>
                        </>}
                        <button className="button button-primary auth-submit" type="submit" disabled={busy}>{busy ? 'Mohon tunggu…' : mode === 'login' ? 'Masuk ke HadirKu' : mode === 'register' ? 'Buat akun karyawan' : mode === 'forgot' ? 'Kirim tautan pemulihan' : 'Simpan kata sandi baru'} <ArrowRight size={15} /></button>
                    </form>
                    {mode === 'login' && <div className="auth-switch">Belum punya akun? <Link to="/register">Daftar sekarang</Link></div>}
                    {mode === 'register' && <div className="auth-switch">Sudah terdaftar? <Link to="/login">Masuk</Link></div>}
                    <div className="security-note"><ShieldCheck size={14} /> Data akun dan presensi dilindungi dengan aman.</div>
                </div>
            </section>
        </main>
    );
}

function Brand() {
    return <><span className="brand-mark"><Check size={19} /></span><span className="brand-name">Hadir<span>Ku</span></span></>;
}

function Field({ label, children }) {
    return <label className="form-group"><span>{label}</span>{children}</label>;
}

function AppShell({ user, onLogout, children }) {
    const [menuOpen, setMenuOpen] = useState(false);
    const location = useLocation();
    const admin = user.role === 'admin';
    const dashboard = dashboardPath(user);
    const items = [
        { to: dashboard, label: 'Dashboard', icon: LayoutDashboard },
        { to: '/attendance', label: 'Data Absensi', icon: CalendarDays },
        { to: '/face', label: admin ? 'Terminal Scan Wajah' : 'Scan Wajah', icon: ScanFace },
        ...(admin ? [
            { to: '/employees', label: 'Data Karyawan', icon: Users },
            { to: '/settings', label: 'Pengaturan Sistem', icon: Settings },
        ] : []),
    ];
    const page = items.find((item) => location.pathname.startsWith(item.to));

    useEffect(() => {
        const clock = document.getElementById('live-clock');
        if (!clock) return undefined;
        const update = () => {
            clock.textContent = new Intl.DateTimeFormat('id-ID', {
                weekday: 'short', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit',
                second: '2-digit', timeZone: 'Asia/Jakarta',
            }).format(new Date());
        };
        update();
        const interval = window.setInterval(update, 1000);
        return () => window.clearInterval(interval);
    }, []);

    useEffect(() => setMenuOpen(false), [location.pathname]);

    return (
        <div className="shell">
            <aside className={`sidebar ${menuOpen ? 'sidebar-open' : ''}`}>
                <Link className="brand" to={dashboard}><Brand /></Link>
                <div className="nav-label">MENU UTAMA</div>
                <nav className="nav-list" aria-label="Navigasi utama">
                    {items.map(({ to, label, icon: Icon }) => <NavLink key={to} to={to} className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}><Icon className="nav-icon" size={17} />{label}</NavLink>)}
                </nav>
                <div className="sidebar-spacer" />
                <div className="help-card"><strong>Butuh bantuan?</strong><p>Hubungi administrator jika kamu mengalami kendala pada akun atau presensi.</p></div>
                <UserProfile user={user} onLogout={onLogout} />
            </aside>
            {menuOpen && <button className="menu-backdrop" aria-label="Tutup menu" onClick={() => setMenuOpen(false)} />}
            <main className="main">
                <header className="topbar">
                    <div className="topbar-left"><button className="menu-toggle" aria-label="Buka menu" onClick={() => setMenuOpen(!menuOpen)}><Menu size={19} /></button><div className="breadcrumbs">HadirKu&nbsp; / &nbsp;<b>{page?.label || 'Dashboard'}</b></div></div>
                    <div className="top-actions"><span className="top-date"><Clock3 size={14} /> <span id="live-clock" /></span><button className="logout top-logout" onClick={onLogout} aria-label="Keluar"><LogOut size={17} /></button><div className="avatar">{user.name?.[0]?.toUpperCase() || 'H'}</div></div>
                </header>
                <section className="content">{children}</section>
            </main>
            <nav className="mobile-bottom" aria-label="Navigasi mobile" style={{ gridTemplateColumns: `repeat(${items.length}, minmax(0, 1fr))` }}>
                {items.map(({ to, label, icon: Icon }) => <NavLink key={to} to={to} className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}><Icon className="nav-icon" size={17} /><span>{label}</span></NavLink>)}
            </nav>
        </div>
    );
}

function UserProfile({ user, onLogout }) {
    return <div className="profile"><div className="avatar">{user.name?.[0]?.toUpperCase() || 'H'}</div><div className="profile-info"><strong>{user.name}</strong><small>{user.role === 'admin' ? 'Administrator' : 'Karyawan'}</small></div><button className="logout" onClick={onLogout} title="Keluar" aria-label="Keluar"><LogOut size={16} /></button></div>;
}

function PageHeading({ title, description, action }) {
    return <div className="page-heading"><div><h1>{title}</h1><p>{description}</p></div>{action && <div className="heading-actions">{action}</div>}</div>;
}

function useDashboardData() {
    const [data, setData] = useState(null);
    const [error, setError] = useState('');

    useEffect(() => {
        getData('/api/dashboard').then(setData).catch((caught) => setError(caught.message));
    }, []);

    return { data, error };
}

function EmployeeDashboard({ user }) {
    const { data, error } = useDashboardData();
    const [now, setNow] = useState(() => new Date());

    useEffect(() => {
        const clock = window.setInterval(() => setNow(new Date()), 1000);
        return () => window.clearInterval(clock);
    }, []);

    if (error) return <ErrorPanel title="Dashboard belum dapat dimuat" message={error} />;
    if (!data) return <Loading />;
    const today = data.today || {};
    const stats = data.stats || {};
    const time = new Intl.DateTimeFormat('id-ID', {
        timeZone: 'Asia/Jakarta',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false,
    }).format(now);
    const todayLabel = new Intl.DateTimeFormat('id-ID', {
        timeZone: 'Asia/Jakarta',
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }).format(now);
    const nextAction = today.check_in ? 'Pulang' : 'Masuk';
    const completed = today.check_in && today.check_out;

    return <div className="employee-dashboard-page">
        <PageHeading title="Dashboard Pegawai" description="Pantau jadwal dan catat presensimu hari ini." action={<span className="date-pill"><CalendarDays size={14} /> {formatDate(data.date)}</span>} />
        <section className="employee-welcome">
            <div><div className="eyebrow"><span className="live-dot" /> AREA PRESENSI PRIBADI</div><h2>Halo, {data.user?.name || user.name}</h2><p>{data.user?.department || 'Pegawai'} <span>·</span> NIP {data.user?.username || user.username}</p></div>
            <div className="employee-welcome-date"><CalendarDays size={16} /><span>{todayLabel}</span></div>
        </section>
        <div className="employee-main-grid">
            <section className="employee-clock-card">
                <div className="employee-clock-top"><span><Clock3 size={15} /> WAKTU INDONESIA BARAT</span><span className="employee-live"><i /> LIVE</span></div>
                <div className="employee-clock">{time}<small>WIB</small></div>
                <div className="employee-checks">
                    <div><span>Presensi masuk</span><strong>{today.check_in ? `${today.check_in} WIB` : 'Belum tercatat'}</strong></div>
                    <div><span>Presensi pulang</span><strong>{today.check_out ? `${today.check_out} WIB` : 'Belum tercatat'}</strong></div>
                </div>
            </section>
            <section className="employee-action-card">
                <span className={`employee-action-icon ${completed ? 'complete' : ''}`}><ScanFace size={22} /></span>
                <div><span className="employee-action-label">STATUS HARI INI</span><h3>{completed ? 'Presensi lengkap' : today.status || 'Belum hadir'}</h3><p>{completed ? 'Terima kasih, sampai jumpa besok.' : `Lakukan verifikasi wajah untuk presensi ${nextAction.toLowerCase()}.`}</p></div>
                {!completed && <Link className="button button-primary" to={`/face?type=${nextAction}`}><ScanFace size={15} /> Presensi {nextAction}</Link>}
                {completed && <span className="employee-complete-badge"><CheckCircle2 size={15} /> Selesai hari ini</span>}
            </section>
        </div>
        <section className="employee-month-summary">
            <div className="employee-section-title"><div><h3>Ringkasan Bulan Ini</h3><p>Rekap kehadiran pribadimu</p></div><CalendarDays size={18} /></div>
            <div className="employee-month-stats">
                <div><span className="employee-metric-icon present"><CheckCircle2 size={17} /></span><span><small>Total kehadiran</small><strong>{stats.present_this_month || 0}<em> hari</em></strong></span></div>
                <div><span className="employee-metric-icon late"><Clock3 size={17} /></span><span><small>Datang terlambat</small><strong>{stats.late_this_month || 0}<em> kali</em></strong></span></div>
                <div><span className="employee-metric-icon face"><Fingerprint size={17} /></span><span><small>Verifikasi wajah</small><strong>{data.user?.face_registered ? 'Aktif' : 'Belum aktif'}</strong></span></div>
            </div>
        </section>
        <RecentTable records={data.recent || []} employee />
    </div>;
}

function AdminDashboard({ user }) {
    const { data, error } = useDashboardData();

    if (error) return <ErrorPanel title="Dashboard belum dapat dimuat" message={error} />;
    if (!data) return <Loading />;
    const date = formatDate(data.date);
    const counts = data.counts || {};
    const total = Math.max(counts.total || 0, 1);
    return <div className="admin-dashboard-page">
        <PageHeading title="Dashboard Admin" description="Pantau kehadiran dan aktivitas seluruh pegawai." action={<span className="date-pill"><CalendarDays size={14} /> {date}</span>} />
        <section className="welcome"><div className="welcome-copy"><div className="eyebrow"><span className="live-dot" /> RINGKASAN HARI INI</div><h2>Selamat datang, {user.name} 👋</h2><p>Berikut ringkasan kehadiran tim hari ini.</p></div><Link className="button button-primary" to="/face"><ScanFace size={15} /> Buka Terminal Scan</Link></section>
        <div className="stats-grid">
            <Stat label="Total Pegawai" value={counts.total || 0} foot="Pegawai aktif terdaftar" icon={Users} blue />
            <Stat label="Hadir Hari Ini" value={counts.present || 0} foot={`${Math.round(100 * (counts.present || 0) / total)}% dari pegawai aktif`} icon={CheckCircle2} />
            <Stat label="Terlambat" value={counts.late || 0} foot="Perlu perhatian hari ini" icon={Clock3} orange />
            <Stat label="Belum Hadir" value={counts.absent || 0} foot="Belum melakukan presensi masuk" icon={Activity} red />
        </div>
        <div className="dashboard-grid"><RecentTable records={data.recent || []} /><aside className="side-panel"><section className="quick-card"><h3>Terminal Presensi</h3><p>Catat kehadiran dengan verifikasi wajah dan liveness.</p><Link className="button button-primary" to="/face"><ScanFace size={15} /> Mulai Scan Wajah</Link></section><section className="quick-card"><h3>Presensi Mingguan</h3><p>Jumlah pegawai yang hadir setiap hari.</p><div className="weekly-chart" role="img" aria-label="Grafik kehadiran tujuh hari terakhir">{(data.trend || []).map((day) => <div className="chart-column" key={day.date}><span>{day.present}</span><div className="chart-bar" style={{ height: `${Math.max(8, Math.min(100, 100 * (day.present || 0) / total))}%` }} /><small>{day.label}</small></div>)}</div></section></aside></div>
    </div>;
}

function Stat({ label, value, foot, icon: Icon, blue, orange, red }) {
    return <article className="stat-card"><div className="stat-top"><span>{label}</span><span className={`stat-icon ${blue ? 'blue' : ''} ${orange ? 'orange' : ''} ${red ? 'red' : ''}`}><Icon size={16} /></span></div><div className="stat-number">{value}</div><div className="stat-foot">{foot}</div></article>;
}

function RecentTable({ records, employee = false }) {
    return <section className="panel"><div className="panel-heading"><div><h3>{employee ? 'Riwayat Presensi Terbaru' : 'Aktivitas Presensi Terbaru'}</h3><p>{employee ? 'Aktivitas presensimu' : 'Aktivitas pegawai yang paling baru'}</p></div><Link className="text-link" to="/attendance">Lihat semua <ArrowRight size={12} /></Link></div><div className="table-wrap"><table><thead><tr>{employee ? <><th>TANGGAL</th><th>WAKTU</th><th>TIPE</th><th>STATUS</th></> : <><th>NAMA PEGAWAI</th><th>WAKTU</th><th>TIPE</th><th>STATUS</th><th>KECOCOKAN</th></>}</tr></thead><tbody>{records.length ? records.map((record) => <tr key={record.id}>{!employee && <td><Person name={record.employee_name} id={record.employee_id} /></td>}{employee && <td>{formatDate(record.date)}</td>}<td>{record.time} WIB</td>{!employee && <td>{record.type}</td>}{employee && <td>{record.type}</td>}<td><StatusBadge value={record.status} /></td>{!employee && <td>{record.match_score == null ? '—' : `${Number(record.match_score).toFixed(1)}%`}</td>}</tr>) : <tr><td colSpan={employee ? 4 : 5}><div className="empty-state">Belum ada catatan presensi terbaru.</div></td></tr>}</tbody></table></div></section>;
}

function Person({ name = 'Karyawan', id }) {
    return <div className="person-cell"><span className="mini-avatar">{name[0]?.toUpperCase()}</span><span><strong>{name}</strong><small>{id}</small></span></div>;
}

function StatusBadge({ value }) {
    return <span className={`badge ${value === 'Terlambat' ? 'warning' : ''}`}>{value}</span>;
}

function Attendance({ user }) {
    const admin = user.role === 'admin';
    const [params, setParams] = useSearchParams();
    const [filters, setFilters] = useState({ date_from: params.get('date_from') || '', date_to: params.get('date_to') || '', employee_id: params.get('employee_id') || '', type: params.get('type') || '', q: params.get('q') || '', page: params.get('page') || '1' });
    const [result, setResult] = useState(null);
    const [error, setError] = useState('');
    const endpoint = admin ? '/api/reports/attendance' : '/api/attendance';
    const query = useMemo(() => Object.fromEntries(params.entries()), [params]);

    useEffect(() => {
        getData(`${endpoint}${queryString({ ...query, per_page: 20 })}`).then(setResult).catch((caught) => setError(caught.message));
    }, [endpoint, query]);

    const applyFilters = (event) => {
        event.preventDefault();
        setParams(Object.fromEntries(Object.entries(filters).filter(([key, value]) => value && key !== 'page')));
    };
    const changePage = (page) => setParams({ ...query, page: String(page) });
    const exportFile = async (format) => {
        try {
            const response = await api(`/api/reports/attendance/export/${format}${queryString(query)}`);
            const blob = await response.blob();
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = `rekap-presensi.${format}`;
            link.click();
            URL.revokeObjectURL(url);
        } catch (caught) {
            setError(caught.message);
        }
    };

    return <>
        <PageHeading title="Data Absensi Karyawan" description={admin ? 'Telusuri, filter, dan ekspor seluruh riwayat presensi.' : 'Riwayat dan rekap kehadiranmu.'} action={admin && <><button className="button button-secondary" onClick={() => exportFile('xlsx')}><Download size={14} /> Excel</button><button className="button button-primary" onClick={() => exportFile('pdf')}><Download size={14} /> PDF</button></>} />
        {error ? <ErrorPanel title="Riwayat belum dapat dimuat" message={error} /> : <section className="panel">
            <form className="toolbar-filter attendance-filters" onSubmit={applyFilters}>
                <Field label="Dari tanggal"><input className="form-input" type="date" value={filters.date_from} onChange={(e) => setFilters({ ...filters, date_from: e.target.value })} /></Field>
                <Field label="Sampai tanggal"><input className="form-input" type="date" value={filters.date_to} onChange={(e) => setFilters({ ...filters, date_to: e.target.value })} /></Field>
                {admin && <Field label="NIP"><input className="form-input" value={filters.employee_id} onChange={(e) => setFilters({ ...filters, employee_id: e.target.value })} placeholder="Semua karyawan" /></Field>}
                <Field label="Tipe"><select className="form-input" value={filters.type} onChange={(e) => setFilters({ ...filters, type: e.target.value })}><option value="">Semua</option><option>Masuk</option><option>Pulang</option></select></Field>
                <Field label="Pencarian"><input className="form-input" value={filters.q} onChange={(e) => setFilters({ ...filters, q: e.target.value })} placeholder="Nama atau departemen" /></Field>
                <button className="button button-secondary filter-submit">Terapkan filter</button>
            </form>
            <div className="panel-heading"><div><h3>Riwayat Presensi</h3><p>{result?.total ?? 'Memuat…'} catatan ditemukan</p></div></div>
            <div className="table-wrap"><table><thead><tr><th>TANGGAL</th>{admin && <><th>KARYAWAN</th><th>DEPARTEMEN</th></>}<th>WAKTU</th><th>TIPE</th><th>STATUS</th><th>KECOCOKAN WAJAH</th></tr></thead><tbody>{result?.data?.length ? result.data.map((record) => <tr key={record.id}><td>{formatDate(record.date)}</td>{admin && <><td><Person name={record.employee_name} id={record.employee_id} /></td><td>{record.department}</td></>}<td>{record.time} WIB</td><td><span className="badge neutral">{record.type}</span></td><td><StatusBadge value={record.status} /></td><td>{record.match_score == null ? '—' : `${Number(record.match_score).toFixed(1)}%`}</td></tr>) : <tr><td colSpan={admin ? 7 : 5}><div className="empty-state">{result ? 'Belum ada catatan presensi pada periode ini.' : 'Memuat data…'}</div></td></tr>}</tbody></table></div>
            {result && result.last_page > 1 && <div className="pagination-bar"><span>Halaman {result.current_page} dari {result.last_page}</span><div><button className="button button-secondary" disabled={result.current_page <= 1} onClick={() => changePage(result.current_page - 1)}>Sebelumnya</button><button className="button button-secondary" disabled={result.current_page >= result.last_page} onClick={() => changePage(result.current_page + 1)}>Berikutnya</button></div></div>}
        </section>}
    </>;
}

function Employees({ notify }) {
    const [params, setParams] = useSearchParams();
    const [search, setSearch] = useState(params.get('q') || '');
    const [department, setDepartment] = useState(params.get('department') || '');
    const [active, setActive] = useState(params.get('active') || '');
    const [data, setData] = useState(null);
    const [error, setError] = useState('');
    const query = useMemo(() => Object.fromEntries(params.entries()), [params]);

    useEffect(() => {
        getData(`/api/employees${queryString({ ...query, per_page: 20 })}`).then(setData).catch((caught) => setError(caught.message));
    }, [query]);
    const apply = (event) => {
        event.preventDefault();
        setParams(Object.fromEntries(Object.entries({ q: search, department, active }).filter(([, value]) => value)));
    };
    const toggleActive = async (employee) => {
        try {
            await send(`/api/employees/${encodeURIComponent(employee.employee_id)}`, 'PATCH', { active: !employee.active });
            notify(employee.active ? 'Karyawan dinonaktifkan.' : 'Karyawan diaktifkan.');
            setData(await getData(`/api/employees${queryString({ ...query, per_page: 20 })}`));
        } catch (caught) {
            setError(caught.message);
        }
    };

    return <>
        <PageHeading title="Data Karyawan" description="Kelola akun, data, dan status seluruh karyawan." action={<Link className="button button-primary" to="/employees/new"><Users size={15} /> Tambah Karyawan</Link>} />
        {error ? <ErrorPanel title="Data karyawan belum dapat dimuat" message={error} /> : <section className="panel">
            <form className="toolbar-filter" onSubmit={apply}><label className="search-field"><Search size={15} /><input placeholder="Cari nama, NIP, atau email" value={search} onChange={(e) => setSearch(e.target.value)} /></label><input className="form-input filter-input" placeholder="Departemen" value={department} onChange={(e) => setDepartment(e.target.value)} /><select className="form-input filter-input" value={active} onChange={(e) => setActive(e.target.value)}><option value="">Semua status</option><option value="1">Aktif</option><option value="0">Nonaktif</option></select><button className="button button-secondary">Terapkan</button></form>
            <div className="panel-heading"><div><h3>Daftar Karyawan</h3><p>{data?.total ?? 'Memuat…'} akun terdaftar</p></div></div>
            <div className="table-wrap"><table><thead><tr><th>KARYAWAN</th><th>JABATAN</th><th>DEPARTEMEN</th><th>VERIFIKASI WAJAH</th><th>STATUS</th><th>AKSI</th></tr></thead><tbody>{data?.data?.length ? data.data.map((employee) => <tr key={employee.employee_id}><td><Person name={employee.name} id={`${employee.employee_id} · ${employee.email}`} /></td><td>{employee.position}</td><td>{employee.department}</td><td><span className={`badge ${employee.face_registered ? '' : 'neutral'}`}>{employee.face_registered ? 'Terdaftar' : 'Belum terdaftar'}</span></td><td><span className={`badge ${employee.active ? '' : 'warning'}`}>{employee.active ? 'Aktif' : 'Nonaktif'}</span></td><td className="table-actions"><button className="text-link" onClick={() => toggleActive(employee)}>{employee.active ? 'Nonaktifkan' : 'Aktifkan'}</button>{employee.active && <Link className="text-link" to={`/face?employee_id=${encodeURIComponent(employee.employee_id)}`}>{employee.face_registered ? 'Perbarui wajah' : 'Daftarkan wajah'}</Link>}</td></tr>) : <tr><td colSpan="6"><div className="empty-state">{data ? 'Belum ada karyawan yang cocok.' : 'Memuat data…'}</div></td></tr>}</tbody></table></div>
            {data?.last_page > 1 && <div className="pagination-bar"><span>Halaman {data.current_page} dari {data.last_page}</span><div><button className="button button-secondary" disabled={data.current_page <= 1} onClick={() => setParams({ ...query, page: String(data.current_page - 1) })}>Sebelumnya</button><button className="button button-secondary" disabled={data.current_page >= data.last_page} onClick={() => setParams({ ...query, page: String(data.current_page + 1) })}>Berikutnya</button></div></div>}
        </section>}
    </>;
}

function EmployeeCreate({ notify }) {
    const navigate = useNavigate();
    const [error, setError] = useState('');
    const [busy, setBusy] = useState(false);
    const submit = async (event) => {
        event.preventDefault();
        setBusy(true);
        setError('');
        try {
            const form = Object.fromEntries(new FormData(event.currentTarget));
            const result = await send('/api/employees', 'POST', form);
            notify('Akun karyawan berhasil dibuat. Silakan daftarkan wajah.');
            navigate(`/face?employee_id=${encodeURIComponent(result.data.employee_id)}`);
        } catch (caught) {
            setError(caught.message);
        } finally {
            setBusy(false);
        }
    };
    return <>
        <PageHeading title="Tambah Karyawan" description="Buat akun pegawai. Setelah tersimpan, daftarkan wajahnya." action={<Link className="button button-secondary" to="/employees"><ArrowLeft size={14} /> Kembali</Link>} />
        {error && <div className="alert alert-error">{error}</div>}
        <section className="panel form-panel"><form onSubmit={submit}><div className="form-grid"><Field label="NIP / Username"><input className="form-input" name="employee_id" minLength="2" required /></Field><Field label="Nama lengkap"><input className="form-input" name="name" required /></Field><Field label="Email"><input className="form-input" name="email" type="email" required /></Field><Field label="Departemen"><input className="form-input" name="department" required /></Field><Field label="Jabatan"><input className="form-input" name="position" /></Field><Field label="Kata sandi awal (minimal 10 karakter)"><input className="form-input" name="password" type="password" minLength="10" required autoComplete="new-password" /></Field><Field label="Konfirmasi kata sandi"><input className="form-input" name="password_confirmation" type="password" minLength="10" required autoComplete="new-password" /></Field></div><div className="form-actions"><button className="button button-primary" disabled={busy}>{busy ? 'Menyimpan…' : 'Buat akun karyawan'}</button></div></form></section>
    </>;
}

function SettingsPage({ notify }) {
    const [settings, setSettings] = useState(null);
    const [error, setError] = useState('');
    const [busy, setBusy] = useState(false);
    useEffect(() => {
        getData('/api/settings').then(setSettings).catch((caught) => setError(caught.message));
    }, []);
    const submit = async (event) => {
        event.preventDefault();
        setBusy(true);
        setError('');
        const form = new FormData(event.currentTarget);
        const payload = Object.fromEntries(form);
        payload.geofence_enabled = form.has('geofence_enabled');
        try {
            setSettings(await getData('/api/settings', { method: 'PUT', body: payload }));
            notify('Pengaturan berhasil disimpan.');
        } catch (caught) {
            setError(caught.message);
        } finally {
            setBusy(false);
        }
    };
    if (error && !settings) return <ErrorPanel title="Pengaturan belum dapat dimuat" message={error} />;
    if (!settings) return <Loading />;
    return <>
        <PageHeading title="Pengaturan Sistem HadirKu" description="Atur jam operasional, lokasi presensi, dan ambang verifikasi wajah." />
        {error && <div className="alert alert-error">{error}</div>}
        <form className="settings-layout" onSubmit={submit}>
            <section className="panel settings-panel"><PanelTitle title="Jam operasional" text="Waktu kerja yang dipakai untuk menentukan status keterlambatan." icon={Clock3} /><div className="form-grid"><Field label="Mulai operasional"><input className="form-input" type="time" name="work_start" defaultValue={settings.work_start} required /></Field><Field label="Batas tepat waktu"><input className="form-input" type="time" name="late_after" defaultValue={settings.late_after} required /></Field><Field label="Selesai operasional"><input className="form-input" type="time" name="work_end" defaultValue={settings.work_end} required /></Field></div></section>
            <section className="panel settings-panel"><PanelTitle title="Geofencing kantor" text="Batasi presensi pada radius lokasi kantor yang ditentukan." icon={Activity} /><label className="toggle-setting"><span><strong>Aktifkan geofencing</strong><small>Presensi di luar radius kantor akan ditolak.</small></span><input type="checkbox" name="geofence_enabled" value="1" defaultChecked={settings.geofence_enabled === true || settings.geofence_enabled === 1 || settings.geofence_enabled === '1'} /></label><div className="form-grid"><Field label="Latitude kantor"><input className="form-input" name="office_latitude" type="number" step="any" min="-90" max="90" defaultValue={settings.office_latitude ?? ''} /></Field><Field label="Longitude kantor"><input className="form-input" name="office_longitude" type="number" step="any" min="-180" max="180" defaultValue={settings.office_longitude ?? ''} /></Field><Field label="Radius (meter)"><input className="form-input" name="geofence_radius_meters" type="number" min="10" max="5000" defaultValue={settings.geofence_radius_meters} required /></Field></div></section>
            <section className="panel settings-panel"><PanelTitle title="Verifikasi dan keamanan" text="Semakin kecil jarak wajah yang diizinkan, semakin ketat pencocokan." icon={ShieldCheck} /><div className="form-grid"><Field label="Ambang jarak wajah"><input className="form-input" name="face_distance_threshold" type="number" step="0.01" min="0.1" max="1" defaultValue={settings.face_distance_threshold} required /></Field><Field label="Batas percobaan gagal"><input className="form-input" name="face_failure_limit" type="number" min="1" max="10" defaultValue={settings.face_failure_limit} required /></Field><Field label="Periode pembatasan (menit)"><input className="form-input" name="face_failure_window_minutes" type="number" min="1" max="60" defaultValue={settings.face_failure_window_minutes} required /></Field></div></section>
            <div className="form-actions"><button className="button button-primary" disabled={busy}>{busy ? 'Menyimpan…' : 'Simpan Pengaturan'}</button></div>
        </form>
    </>;
}

function PanelTitle({ title, text, icon: Icon }) {
    return <div className="panel-heading"><div><h3>{title}</h3><p>{text}</p></div><span className="stat-icon"><Icon size={15} /></span></div>;
}

function FaceTerminal({ user, notify, onUserUpdated }) {
    const isAdmin = user.role === 'admin';
    const [params] = useSearchParams();
    const [employees, setEmployees] = useState([]);
    const [selected, setSelected] = useState('');
    const [type, setType] = useState(() => params.get('type') === 'Pulang' ? 'Pulang' : 'Masuk');
    const [status, setStatus] = useState('Kamera mati');
    const [instruction, setInstruction] = useState('Tantangan kedip dan gerakan kepala akan dimulai setelah kamera aktif.');
    const [error, setError] = useState('');
    const [result, setResult] = useState('');
    const [busy, setBusy] = useState(false);
    const [cameraOn, setCameraOn] = useState(false);
    const [ready, setReady] = useState(false);
    const [ownFaceRegistered, setOwnFaceRegistered] = useState(Boolean(user.face_registered));
    const videoRef = useRef(null);
    const streamRef = useRef(null);
    const challengeRef = useRef(null);
    const descriptorRef = useRef(null);
    const eventsRef = useRef({});
    const baselineRef = useRef(null);
    const startedRef = useRef(0);
    const eyeClosedRef = useRef(false);
    const detectingRef = useRef(false);
    const searchParams = new URLSearchParams(window.location.search);
    const currentEmployee = employees.find((employee) => employee.employee_id === selected);
    const faceRegistered = isAdmin ? Boolean(currentEmployee?.face_registered) : ownFaceRegistered;

    useEffect(() => {
        if (!isAdmin) return undefined;
        let active = true;
        getData('/api/employees?active=1&per_page=100').then((resultData) => {
            if (!active) return;
            const list = resultData.data || [];
            setEmployees(list);
            const requested = searchParams.get('employee_id');
            setSelected(list.some((item) => item.employee_id === requested) ? requested : list[0]?.employee_id || '');
        }).catch((caught) => setError(caught.message));
        return () => { active = false; };
    }, [isAdmin]);

    const makeChallenge = async () => {
        const data = await getData('/api/face/challenge', { method: 'POST' });
        challengeRef.current = data;
        eventsRef.current = {};
        descriptorRef.current = null;
        baselineRef.current = null;
        startedRef.current = performance.now();
        setReady(false);
        setInstruction('Kedipkan mata perlahan, lalu buka kembali.');
    };

    const startCamera = async () => {
        setError('');
        setResult('');
        setStatus('Memuat model');
        try {
            if (!window.faceapi) throw new Error('Model face-api.js lokal belum tersedia.');
            const faceapi = window.faceapi;
            await Promise.all([
                faceapi.nets.tinyFaceDetector.loadFromUri('/models'),
                faceapi.nets.faceLandmark68Net.loadFromUri('/models'),
                faceapi.nets.faceRecognitionNet.loadFromUri('/models'),
            ]);
            await makeChallenge();
            const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 720 } }, audio: false });
            streamRef.current = stream;
            videoRef.current.srcObject = stream;
            await videoRef.current.play();
            detectingRef.current = true;
            setCameraOn(true);
            setStatus('Kamera aktif');
            monitorFace();
        } catch (caught) {
            stopCamera();
            setError(caught.message || 'Kamera tidak dapat diaktifkan.');
            setStatus('Kamera mati');
        }
    };

    const eyeRatio = (points) => {
        const distance = (a, b) => Math.hypot(a.x - b.x, a.y - b.y);
        return (distance(points[1], points[5]) + distance(points[2], points[4])) / (2 * Math.max(distance(points[0], points[3]), 1));
    };

    const monitorFace = async () => {
        if (!detectingRef.current) return;
        const video = videoRef.current;
        if (!video?.videoWidth) {
            window.setTimeout(monitorFace, 250);
            return;
        }
        try {
            const faceapi = window.faceapi;
            const detections = await faceapi.detectAllFaces(video, new faceapi.TinyFaceDetectorOptions({ inputSize: 320, scoreThreshold: 0.55 })).withFaceLandmarks().withFaceDescriptors();
            if (detections.length !== 1) {
                descriptorRef.current = null;
                setReady(false);
                setInstruction(detections.length ? 'Pastikan hanya satu wajah terlihat di kamera.' : 'Posisikan satu wajah di tengah bingkai.');
            } else {
                const face = detections[0];
                descriptorRef.current = Array.from(face.descriptor);
                const center = face.detection.box.x + face.detection.box.width / 2;
                if (baselineRef.current === null) baselineRef.current = center;
                const eyes = [...face.landmarks.getLeftEye(), ...face.landmarks.getRightEye()];
                const middle = Math.floor(eyes.length / 2);
                const ratio = (eyeRatio(eyes.slice(0, middle)) + eyeRatio(eyes.slice(middle))) / 2;
                if (ratio < 0.19) eyeClosedRef.current = true;
                if (eyeClosedRef.current && ratio > 0.24) {
                    eventsRef.current.blink = true;
                    eyeClosedRef.current = false;
                }
                if (challengeRef.current?.actions.includes('head_turn') && Math.abs(center - baselineRef.current) > face.detection.box.width * 0.045) {
                    eventsRef.current.head_turn = true;
                }
                const remaining = challengeRef.current.actions.filter((action) => !eventsRef.current[action]);
                const complete = remaining.length === 0 && performance.now() - startedRef.current >= 2200;
                setReady(complete);
                setInstruction(complete ? 'Liveness berhasil. Tekan tombol untuk menyimpan presensi atau mendaftarkan wajah.' : remaining[0] === 'head_turn' ? 'Gerakkan kepala sedikit ke kiri atau ke kanan.' : 'Kedipkan mata perlahan, lalu buka kembali.');
            }
        } catch (caught) {
            setError(caught.message || 'Deteksi wajah gagal.');
            detectingRef.current = false;
            return;
        }
        if (detectingRef.current) window.setTimeout(monitorFace, 220);
    };

    const stopCamera = () => {
        detectingRef.current = false;
        streamRef.current?.getTracks().forEach((track) => track.stop());
        streamRef.current = null;
        if (videoRef.current) videoRef.current.srcObject = null;
        setCameraOn(false);
        setReady(false);
        setStatus('Kamera mati');
    };
    useEffect(() => () => {
        detectingRef.current = false;
        streamRef.current?.getTracks().forEach((track) => track.stop());
    }, []);

    const liveness = () => ({
        challenge_id: challengeRef.current.challenge_id,
        events: eventsRef.current,
        elapsed_ms: Math.max(2200, Math.round(performance.now() - startedRef.current)),
    });

    const registerFace = async () => {
        if (!descriptorRef.current || !challengeRef.current) throw new Error('Selesaikan verifikasi liveness terlebih dahulu.');
        const employeeId = isAdmin ? selected : user.employee_id;
        if (!employeeId) throw new Error('Pilih karyawan terlebih dahulu.');
        await send(`/api/employees/${encodeURIComponent(employeeId)}/face`, 'POST', { descriptor: descriptorRef.current, liveness: liveness() });
        setEmployees((current) => current.map((item) => item.employee_id === employeeId ? { ...item, face_registered: true } : item));
        if (!isAdmin) {
            setOwnFaceRegistered(true);
            onUserUpdated({ ...user, face_registered: true });
        }
        setResult('Wajah berhasil didaftarkan. Descriptor wajah tersimpan dengan aman.');
        notify('Pendaftaran wajah berhasil.');
        await makeChallenge();
    };

    const takePhoto = async () => {
        const video = videoRef.current;
        const canvas = document.createElement('canvas');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
        const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.88));
        if (!blob) throw new Error('Foto verifikasi tidak dapat dibuat.');
        return new File([blob], `hadirku-${Date.now()}.jpg`, { type: 'image/jpeg' });
    };

    const submitAttendance = async () => {
        if (!descriptorRef.current || !challengeRef.current) throw new Error('Selesaikan verifikasi liveness terlebih dahulu.');
        if (!faceRegistered) throw new Error('Daftarkan wajah terlebih dahulu sebelum melakukan presensi.');
        const position = await new Promise((resolve, reject) => {
            if (!navigator.geolocation) return reject(new Error('Browser ini tidak mendukung layanan lokasi.'));
            navigator.geolocation.getCurrentPosition(resolve, () => reject(new Error('Lokasi tidak tersedia. Izinkan akses lokasi dan coba kembali.')), { enableHighAccuracy: true, timeout: 12000, maximumAge: 0 });
        });
        const form = new FormData();
        form.append('type', type);
        form.append('latitude', String(position.coords.latitude));
        form.append('longitude', String(position.coords.longitude));
        form.append('photo', await takePhoto());
        if (isAdmin) form.append('employee_id', selected);
        descriptorRef.current.forEach((value, index) => form.append(`descriptor[${index}]`, String(value)));
        const live = liveness();
        form.append('liveness[challenge_id]', live.challenge_id);
        form.append('liveness[elapsed_ms]', String(live.elapsed_ms));
        Object.entries(live.events).forEach(([key, value]) => form.append(`liveness[events][${key}]`, value ? '1' : '0'));
        const response = await api('/api/attendance', { method: 'POST', body: form });
        const payload = await response.json();
        setResult(`${payload.message} Skor kecocokan wajah: ${Number(payload.data?.match_score ?? 0).toFixed(1)}%.`);
        notify('Presensi berhasil dicatat.');
        await makeChallenge();
    };

    const withBusy = async (operation) => {
        setBusy(true);
        setError('');
        try {
            await operation();
        } catch (caught) {
            setError(caught.message);
        } finally {
            setBusy(false);
        }
    };

    return <>
        <PageHeading title={isAdmin ? 'Terminal Scan Wajah' : 'Presensi Wajah'} description={isAdmin ? 'Pintu masuk utama · verifikasi wajah dan liveness.' : 'Daftarkan wajah atau catat presensi dengan verifikasi kamera.'} action={<span className="badge neutral"><span className="live-dot" /> Terminal aman</span>} />
        <div className="face-layout">
            <section className="panel camera-panel">
                <div className="panel-heading"><div><h3>Verifikasi Kamera</h3><p>Izinkan kamera dan ikuti instruksi gerakan di layar.</p></div><span className="badge neutral">{status}</span></div>
                <div className={`camera-stage ${cameraOn ? 'camera-on' : ''}`}><video ref={videoRef} autoPlay playsInline muted /><div className="camera-placeholder"><div className="camera-symbol"><ScanFace size={31} /></div><strong>Siap untuk verifikasi?</strong><p>Pastikan wajah terlihat jelas, kamera tidak tertutup, dan pencahayaan cukup.</p></div><div className="face-guide" /><div className="camera-hint">{instruction}</div></div>
                <div className="camera-actions"><button className="button button-secondary" onClick={cameraOn ? stopCamera : startCamera}><Camera size={15} /> {cameraOn ? 'Matikan Kamera' : 'Aktifkan Kamera'}</button><button className="button button-primary" disabled={!ready || busy} onClick={() => withBusy(submitAttendance)}><Fingerprint size={15} /> {busy ? 'Memverifikasi…' : 'Verifikasi Wajah'}</button></div>
                <div className={`liveness-progress ${ready ? 'complete' : ''}`}><span className="live-dot" />{instruction}</div>
                {error && <div className="face-error" role="alert">{error}</div>}
            </section>
            <aside className="side-panel face-side-panel">
                <section className="quick-card"><h3>{isAdmin ? 'Pendaftaran wajah pegawai' : 'Jenis presensi'}</h3><p>{isAdmin ? 'Pilih pegawai, lalu daftarkan wajahnya melalui kamera.' : 'Pilih masuk atau pulang setelah verifikasi wajah selesai.'}</p>{isAdmin ? <Field label="Karyawan"><select className="form-input" value={selected} onChange={(event) => setSelected(event.target.value)}>{employees.map((employee) => <option key={employee.employee_id} value={employee.employee_id}>{employee.name} · {employee.employee_id}</option>)}</select></Field> : <div className="selected-person"><span className="mini-avatar">{user.name?.[0]}</span><span><strong>{user.name}</strong><small>{user.employee_id || user.username}</small></span></div>}<Field label="Jenis tindakan"><select className="form-input" value={type} onChange={(event) => setType(event.target.value)}><option value="Masuk">Presensi masuk</option><option value="Pulang">Presensi pulang</option></select></Field><button className="button button-secondary face-enroll" disabled={!ready || busy || (isAdmin && !selected)} onClick={() => withBusy(registerFace)}>{isAdmin ? (faceRegistered ? 'Perbarui wajah pegawai' : 'Daftarkan wajah pegawai') : (faceRegistered ? 'Perbarui data wajah' : 'Daftarkan wajah')}</button></section>
                <section className="quick-card"><h3>Proses verifikasi</h3><ul className="verification-steps"><li><span>1</span>Deteksi satu wajah secara lokal</li><li><span>2</span>Ikuti tantangan kedip dan gerakkan kepala</li><li><span>3</span>Descriptor 128 angka dicocokkan secara aman</li><li><span>4</span>Koordinat, skor, dan foto verifikasi dikirim</li></ul></section>
                <div className="verification-note"><strong>Privasi kamera</strong><p>Foto disimpan sebagai bukti presensi pada penyimpanan privat. Descriptor wajah disimpan terenkripsi di backend.</p></div>
                {result && <div className="face-result"><strong>Verifikasi berhasil</strong>{result}</div>}
            </aside>
        </div>
    </>;
}

function ErrorPanel({ title, message }) {
    return <div className="panel error-state"><strong>{title}</strong><p>{message}</p><button className="button button-secondary" onClick={() => window.location.reload()}>Coba lagi</button></div>;
}

function Loading() {
    return <div className="panel loading-panel"><span className="loading-spinner" /> Memuat data HadirKu…</div>;
}

function formatDate(value) {
    if (!value) return '—';
    return new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short', year: 'numeric', timeZone: 'Asia/Jakarta' }).format(new Date(`${value}T00:00:00`));
}

export default App;
