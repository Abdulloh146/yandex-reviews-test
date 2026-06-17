<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Yandex Reviews Parser - DESIGN FIX ACTIVE</title>
    <!-- DESIGN_FIX_ACTIVE_INLINE_CSS -->
    <meta name="viewport" content="width=device-width, initial-scale=1">

    {{-- CDN kerak emas. Dizayn 100% shu fayl ichidagi CSS bilan ishlaydi. --}}
    <style>
        *{box-sizing:border-box}
        body{
            margin:0;
            font-family:Inter,Arial,sans-serif;
            background:#f1f5f9;
            color:#0f172a;
        }
        .hidden{display:none!important}
        .topbar{
            background:#fff;
            border-bottom:1px solid #e2e8f0;
        }
        .topbar-inner{
            max-width:1180px;
            margin:0 auto;
            padding:18px 22px;
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:16px;
        }
        .brand-title{
            font-size:22px;
            font-weight:900;
            margin:0;
            color:#020617;
        }
        .brand-sub{
            margin:3px 0 0;
            color:#64748b;
            font-size:13px;
        }
        .page{
            max-width:1180px;
            margin:0 auto;
            padding:44px 22px 70px;
        }
        .card{
            background:#fff;
            border:1px solid #e2e8f0;
            border-radius:24px;
            box-shadow:0 8px 24px rgba(15,23,42,.06);
            padding:24px;
            margin-bottom:26px;
        }
        .card-title{
            margin:0 0 18px;
            font-size:18px;
            font-weight:900;
            color:#020617;
        }
        .input{
            width:100%;
            height:58px;
            border:1px solid #cbd5e1;
            background:#f8fafc;
            border-radius:18px;
            padding:0 18px;
            font-size:15px;
            font-weight:800;
            outline:none;
            color:#020617;
        }
        .input:focus{
            border-color:#2563eb;
            background:#fff;
            box-shadow:0 0 0 4px rgba(37,99,235,.12);
        }
        .alert{
            margin-top:16px;
            padding:14px 16px;
            border-radius:16px;
            font-size:14px;
            font-weight:800;
            line-height:1.45;
            white-space:pre-line;
        }
        .alert-success{
            background:#ecfdf5;
            color:#047857;
        }
        .alert-error{
            background:#fef2f2;
            color:#b91c1c;
        }
        .alert-info{
            background:#eff6ff;
            color:#1d4ed8;
        }
        .alert-warning{
            background:#fffbeb;
            color:#b45309;
        }
        .actions{
            display:flex;
            flex-wrap:wrap;
            gap:12px;
            margin-top:18px;
        }
        .btn{
            height:50px;
            border:0;
            border-radius:16px;
            padding:0 24px;
            font-weight:900;
            font-size:15px;
            cursor:pointer;
            transition:.15s;
        }
        .btn:disabled{
            opacity:.55;
            cursor:not-allowed;
        }
        .btn-primary{
            background:#2563eb;
            color:#fff;
        }
        .btn-primary:hover{background:#1d4ed8}
        .btn-dark{
            background:#0f172a;
            color:#fff;
        }
        .btn-dark:hover{background:#020617}
        .btn-light{
            background:#e2e8f0;
            color:#0f172a;
        }
        .btn-light:hover{background:#cbd5e1}
        .stats{
            display:grid;
            grid-template-columns:repeat(4,minmax(0,1fr));
            gap:16px;
        }
        .stat{
            background:#f1f5f9;
            border-radius:18px;
            padding:18px;
            min-height:90px;
        }
        .stat-label{
            color:#64748b;
            font-size:13px;
            font-weight:700;
            margin-bottom:10px;
        }
        .stat-value{
            font-size:18px;
            font-weight:900;
            color:#020617;
            line-height:1.35;
        }
        .status-line{
            margin-top:18px;
            font-size:14px;
            color:#64748b;
        }
        .status-line b{color:#0f172a}
        .reviews-head{
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:16px;
            margin-bottom:18px;
        }
        .reviews-title{
            font-size:18px;
            font-weight:900;
            margin:0;
        }
        .reviews-count{
            color:#64748b;
            font-size:14px;
        }
        .empty{
            background:#f8fafc;
            border-radius:18px;
            padding:18px;
            color:#64748b;
            font-size:15px;
        }
        .review-card{
            border:1px solid #dbe3ee;
            border-radius:18px;
            padding:18px;
            margin-bottom:14px;
            background:#fff;
        }
        .review-top{
            display:flex;
            align-items:flex-start;
            justify-content:space-between;
            gap:14px;
            margin-bottom:8px;
        }
        .review-author{
            font-size:16px;
            font-weight:900;
            color:#020617;
        }
        .review-date{
            font-size:13px;
            color:#64748b;
            white-space:nowrap;
        }
        .rating{
            font-size:14px;
            color:#d97706;
            font-weight:900;
            margin-bottom:10px;
        }
        .review-text{
            margin:0;
            color:#334155;
            line-height:1.55;
            white-space:pre-line;
            font-size:15px;
        }
        .pagination{
            margin-top:22px;
            display:flex;
            align-items:center;
            gap:12px;
            flex-wrap:wrap;
        }
        .page-info{
            color:#64748b;
            font-weight:800;
            font-size:14px;
        }
        .login-box{
            max-width:460px;
            margin:0 auto;
        }
        .form-group{margin-bottom:14px}
        .label{
            display:block;
            margin-bottom:7px;
            font-size:12px;
            text-transform:uppercase;
            letter-spacing:.08em;
            font-weight:900;
            color:#64748b;
        }
        .overlay{
            position:fixed;
            inset:0;
            z-index:99999;
            background:rgba(2,6,23,.72);
            backdrop-filter:blur(7px);
            display:flex;
            align-items:center;
            justify-content:center;
            padding:18px;
        }
        .overlay-box{
            width:100%;
            max-width:430px;
            background:#fff;
            border-radius:28px;
            padding:34px;
            text-align:center;
            box-shadow:0 25px 80px rgba(0,0,0,.30);
        }
        .spinner{
            width:58px;
            height:58px;
            border-radius:999px;
            border:5px solid #e2e8f0;
            border-top-color:#2563eb;
            margin:0 auto 18px;
            animation:spin .9s linear infinite;
        }
        @keyframes spin{to{transform:rotate(360deg)}}
        .overlay-title{
            margin:0;
            font-size:22px;
            font-weight:900;
        }
        .overlay-text{
            margin:12px 0 0;
            color:#64748b;
            line-height:1.5;
        }
        .overlay-note{
            margin:18px 0 0;
            background:#eff6ff;
            color:#1d4ed8;
            border-radius:16px;
            padding:13px;
            font-weight:900;
        }
        @media(max-width:850px){
            .stats{grid-template-columns:1fr 1fr}
        }
        @media(max-width:560px){
            .topbar-inner{padding:14px}
            .page{padding:24px 14px 50px}
            .card{padding:18px;border-radius:20px}
            .stats{grid-template-columns:1fr}
            .actions{flex-direction:column}
            .btn{width:100%}
            .reviews-head{align-items:flex-start;flex-direction:column}
            .review-top{flex-direction:column}
        }
    </style>
</head>
<body>
<div id="loadingOverlay" class="overlay hidden">
    <div class="overlay-box">
        <div class="spinner"></div>
        <h2 class="overlay-title">Парсинг выполняется...</h2>
        <p class="overlay-text">Yandex Maps’dan reviewlar olinmoqda. Parser tugaguncha oynani yopmang.</p>
        <p id="loadingText" class="overlay-note">1–5 minut kuting...</p>
    </div>
</div>

<header class="topbar">
    <div class="topbar-inner">
        <div>
            <h1 class="brand-title">Yandex Reviews Parser</h1>
            <p class="brand-sub">Настройки организации и отзывы</p>
        </div>
        <button id="logoutBtn" type="button" class="btn btn-dark hidden">Выйти</button>
    </div>
</header>

<main class="page">
    <section id="loginSection" class="card login-box">
        <h2 class="card-title">Вход</h2>
        <p class="brand-sub" style="margin-bottom:18px">Seed user: admin@example.com / password</p>
        <div id="loginError" class="alert alert-error hidden"></div>

        <form id="loginForm">
            <div class="form-group">
                <label class="label">Email</label>
                <input id="email" type="email" required class="input" value="admin@example.com">
            </div>
            <div class="form-group">
                <label class="label">Password</label>
                <input id="password" type="password" required class="input" value="password">
            </div>
            <button id="loginBtn" type="submit" class="btn btn-primary" style="width:100%">Войти</button>
        </form>
    </section>

    <section id="dashboardSection" class="hidden">
        <section class="card">
            <h2 class="card-title">Ссылка на карточку организации</h2>
            <form id="organizationForm">
                <input id="yandexUrl" type="url" required class="input" placeholder="https://yandex.ru/maps/org/...">
                <div id="errorBox" class="alert alert-error hidden"></div>
                <div id="successBox" class="alert alert-success hidden"></div>
                <div class="actions">
                    <button id="saveBtn" type="submit" class="btn btn-primary">Сохранить</button>
                    <button id="refreshBtn" type="button" class="btn btn-dark" disabled>Обновить данные</button>
                </div>
            </form>
        </section>

        <section id="organizationCard" class="card hidden">
            <h2 class="card-title">Данные организации</h2>
            <div class="stats">
                <div class="stat">
                    <div class="stat-label">Название</div>
                    <div id="orgName" class="stat-value">—</div>
                </div>
                <div class="stat">
                    <div class="stat-label">Средний рейтинг</div>
                    <div id="orgRating" class="stat-value">—</div>
                </div>
                <div class="stat">
                    <div class="stat-label">Оценок</div>
                    <div id="orgRatingsCount" class="stat-value">0</div>
                </div>
                <div class="stat">
                    <div class="stat-label">Отзывов</div>
                    <div id="orgReviewsCount" class="stat-value">0 / 0</div>
                </div>
            </div>
            <div class="status-line">Статус парсинга: <b id="parseStatus">—</b></div>
            <div id="statusBox" class="alert hidden"></div>
        </section>

        <section class="card">
            <div class="reviews-head">
                <h2 class="reviews-title">Отзывы <span id="reviewsTotalBadge" class="reviews-count"></span></h2>
                <button id="reloadReviewsBtn" type="button" class="btn btn-light">Обновить список</button>
            </div>

            <div id="reviewsLoading" class="empty hidden">Загружаем отзывы...</div>
            <div id="emptyReviews" class="empty">Пока отзывов нет.</div>
            <div id="reviewsList"></div>

            <div id="pagination" class="pagination hidden">
                <button id="prevBtn" type="button" class="btn btn-light">Назад</button>
                <span id="pageInfo" class="page-info">Страница 1 из 1</span>
                <button id="nextBtn" type="button" class="btn btn-light">Вперёд</button>
            </div>
        </section>
    </section>
</main>

<script>
    const TOKEN_KEY = 'auth_token';

    const state = {
        token: localStorage.getItem(TOKEN_KEY),
        organization: null,
        currentPage: 1,
        lastPage: 1,
        total: 0,
    };

    const $ = (id) => document.getElementById(id);

    const el = {
        loadingOverlay: $('loadingOverlay'), loadingText: $('loadingText'),
        loginSection: $('loginSection'), dashboardSection: $('dashboardSection'), logoutBtn: $('logoutBtn'),
        loginForm: $('loginForm'), loginBtn: $('loginBtn'), loginError: $('loginError'), email: $('email'), password: $('password'),
        organizationForm: $('organizationForm'), yandexUrl: $('yandexUrl'), saveBtn: $('saveBtn'), refreshBtn: $('refreshBtn'),
        errorBox: $('errorBox'), successBox: $('successBox'), organizationCard: $('organizationCard'),
        orgName: $('orgName'), orgRating: $('orgRating'), orgRatingsCount: $('orgRatingsCount'), orgReviewsCount: $('orgReviewsCount'),
        parseStatus: $('parseStatus'), statusBox: $('statusBox'), reloadReviewsBtn: $('reloadReviewsBtn'), reviewsLoading: $('reviewsLoading'),
        emptyReviews: $('emptyReviews'), reviewsList: $('reviewsList'), reviewsTotalBadge: $('reviewsTotalBadge'), pagination: $('pagination'),
        prevBtn: $('prevBtn'), nextBtn: $('nextBtn'), pageInfo: $('pageInfo'),
    };

    function setScreen(authenticated) {
        el.loginSection.classList.toggle('hidden', authenticated);
        el.dashboardSection.classList.toggle('hidden', !authenticated);
        el.logoutBtn.classList.toggle('hidden', !authenticated);
    }

    function resetAuth(message = 'Sessiya tugagan. Qayta login qiling.') {
        localStorage.removeItem(TOKEN_KEY);
        state.token = null;
        state.organization = null;
        setScreen(false);
        el.loginError.textContent = message;
        el.loginError.classList.remove('hidden');
    }

    function showLoading(text = '1–5 minut kuting...') {
        el.loadingText.textContent = text;
        el.loadingOverlay.classList.remove('hidden');
    }

    function hideLoading() {
        el.loadingOverlay.classList.add('hidden');
    }

    function showError(message) {
        el.errorBox.textContent = message || 'Error';
        el.errorBox.classList.remove('hidden');
        el.successBox.classList.add('hidden');
    }

    function showSuccess(message) {
        el.successBox.textContent = message || 'Success';
        el.successBox.classList.remove('hidden');
        el.errorBox.classList.add('hidden');
    }

    function clearAlerts() {
        el.errorBox.classList.add('hidden');
        el.successBox.classList.add('hidden');
        el.loginError.classList.add('hidden');
    }

    function getBackendMessage(error, fallback = 'Ошибка') {
        return error?.data?.organization?.parse_error || error?.data?.message || error?.data?.error || fallback;
    }

    async function api(path, options = {}) {
        const headers = {'Accept': 'application/json', 'Content-Type': 'application/json', ...(options.headers || {})};
        if (state.token) headers.Authorization = `Bearer ${state.token}`;

        const response = await fetch(`/api${path}`, {...options, headers});
        const text = await response.text();

        let data = {};
        try { data = text ? JSON.parse(text) : {}; } catch (e) { data = {message: text || 'Invalid JSON response'}; }

        if (response.status === 401) {
            resetAuth(data.message || 'Unauthenticated. Qayta login qiling.');
            throw {status: response.status, data};
        }

        if (!response.ok) throw {status: response.status, data};
        return data;
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;').replaceAll("'", '&#039;');
    }

    function renderOrganization(organization) {
        state.organization = organization;

        if (!organization) {
            el.organizationCard.classList.add('hidden');
            el.refreshBtn.disabled = true;
            return;
        }

        el.organizationCard.classList.remove('hidden');
        el.refreshBtn.disabled = false;

        el.yandexUrl.value = organization.yandex_url || '';
        el.orgName.textContent = organization.name || '—';
        el.orgRating.textContent = organization.average_rating || '—';
        el.orgRatingsCount.textContent = organization.ratings_count ?? 0;

        const parsed = organization.parsed_reviews_count ?? state.total ?? 0;
        const yandexCount = organization.reviews_count ?? 0;

        el.orgReviewsCount.textContent = `${parsed} / ${yandexCount}`;
        el.parseStatus.textContent = organization.parse_status || '—';

        el.statusBox.className = 'alert';

        if (organization.parse_status === 'success') {
            if (organization.parse_error) {
                el.statusBox.textContent = `Парсинг завершён с предупреждением: ${organization.parse_error}`;
                el.statusBox.classList.add('alert-warning');
            } else {
                el.statusBox.textContent = `Парсинг завершён. Загружено ${parsed} из ${yandexCount} отзывов.`;
                el.statusBox.classList.add('alert-success');
            }
            el.statusBox.classList.remove('hidden');
        } else if (organization.parse_status === 'failed') {
            el.statusBox.textContent = `Ошибка парсинга: ${organization.parse_error || 'Parsing failed.'}`;
            el.statusBox.classList.add('alert-error');
            el.statusBox.classList.remove('hidden');
        } else if (organization.parse_status === 'processing' || organization.parse_status === 'pending') {
            el.statusBox.textContent = 'Парсинг выполняется...';
            el.statusBox.classList.add('alert-info');
            el.statusBox.classList.remove('hidden');
        } else {
            el.statusBox.classList.add('hidden');
        }
    }

    function renderReviews(data) {
        const reviews = data.data || [];
        state.currentPage = data.current_page || 1;
        state.lastPage = data.last_page || 1;
        state.total = data.total || 0;

        el.reviewsTotalBadge.textContent = state.total ? `(${state.total})` : '';
        el.reviewsList.innerHTML = '';
        el.emptyReviews.classList.toggle('hidden', reviews.length > 0);

        for (const review of reviews) {
            const card = document.createElement('div');
            card.className = 'review-card';
            card.innerHTML = `
                <div class="review-top">
                    <div class="review-author">${escapeHtml(review.author_name || 'Аноним')}</div>
                    <div class="review-date">${escapeHtml(review.review_date_text || review.review_date || '—')}</div>
                </div>
                <div class="rating">Оценка: ${escapeHtml(review.rating || '—')}</div>
                <p class="review-text">${escapeHtml(review.text || 'Без текста')}</p>
            `;
            el.reviewsList.appendChild(card);
        }

        el.pagination.classList.toggle('hidden', state.lastPage <= 1);
        el.pageInfo.textContent = `Страница ${state.currentPage} из ${state.lastPage}`;
        el.prevBtn.disabled = state.currentPage <= 1;
        el.nextBtn.disabled = state.currentPage >= state.lastPage;
    }

    async function loadOrganization() {
        const data = await api('/organization');
        renderOrganization(data.organization || null);
    }

    async function loadReviews(page = 1) {
        el.reviewsLoading.classList.remove('hidden');
        try {
            const data = await api(`/organization/reviews?page=${page}&per_page=50`);
            renderReviews(data);
        } finally {
            el.reviewsLoading.classList.add('hidden');
        }
    }

    async function initDashboard() {
        if (!state.token) {
            setScreen(false);
            return;
        }

        setScreen(false);

        try {
            await loadOrganization();
            await loadReviews(1);
            setScreen(true);
        } catch (error) {
            if (error?.status !== 401) {
                setScreen(true);
                showError(getBackendMessage(error, 'Dashboard yuklanmadi.'));
            }
        }
    }

    el.loginForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        el.loginBtn.disabled = true;
        el.loginBtn.textContent = 'Входим...';
        el.loginError.classList.add('hidden');

        try {
            const oldToken = state.token;
            state.token = null;

            const data = await api('/login', {
                method: 'POST',
                body: JSON.stringify({email: el.email.value, password: el.password.value}),
            });

            state.token = oldToken;

            const token = data.token || data.access_token;
            if (!token) throw {data: {message: 'Login response ichida token yo‘q.'}};

            localStorage.setItem(TOKEN_KEY, token);
            state.token = token;

            clearAlerts();
            await initDashboard();
        } catch (error) {
            el.loginError.textContent = getBackendMessage(error, 'Login yoki password xato.');
            el.loginError.classList.remove('hidden');
        } finally {
            el.loginBtn.disabled = false;
            el.loginBtn.textContent = 'Войти';
        }
    });

    async function directParse(endpoint, button, loadingText) {
        clearAlerts();
        showLoading(loadingText);
        button.disabled = true;

        try {
            const data = await api(endpoint, {
                method: 'POST',
                body: endpoint === '/organization'
                    ? JSON.stringify({yandex_url: el.yandexUrl.value})
                    : undefined,
            });

            renderOrganization(data.organization);
            showSuccess(data.message || 'Done.');
            await loadReviews(1);
        } catch (error) {
            if (error?.status !== 401) {
                showError(getBackendMessage(error, 'Ошибка парсинга'));
                await loadOrganization().catch(() => {});
                await loadReviews(1).catch(() => {});
            }
        } finally {
            button.disabled = false;
            hideLoading();
        }
    }

    el.organizationForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        await directParse('/organization', el.saveBtn, 'Сохраняем и парсим... 1–5 minut kuting.');
    });

    el.refreshBtn.addEventListener('click', async () => {
        await directParse('/organization/refresh', el.refreshBtn, 'Обновляем данные... 1–5 minut kuting.');
    });

    el.reloadReviewsBtn.addEventListener('click', () => {
        loadReviews(state.currentPage).catch((error) => {
            if (error?.status !== 401) showError(getBackendMessage(error, 'Reviewlar yuklanmadi.'));
        });
    });

    el.prevBtn.addEventListener('click', () => {
        if (state.currentPage > 1) loadReviews(state.currentPage - 1);
    });

    el.nextBtn.addEventListener('click', () => {
        if (state.currentPage < state.lastPage) loadReviews(state.currentPage + 1);
    });

    el.logoutBtn.addEventListener('click', async () => {
        try { await api('/logout', {method: 'POST'}); } catch (e) {}
        resetAuth('Siz chiqdingiz. Qayta login qilishingiz mumkin.');
    });

    initDashboard();
</script>
</body>
</html>
