(() => {
  let csrf = '';
  const $ = (selector) => document.querySelector(selector);
  const landingNav = $('.landing-nav');
  const menuToggle = $('#landingMenuToggle');
  menuToggle?.addEventListener('click', () => { const open = landingNav.classList.toggle('menu-open'); menuToggle.setAttribute('aria-expanded', String(open)); });
  const enhancementStyles = document.createElement('link');
  enhancementStyles.rel = 'stylesheet'; enhancementStyles.href = 'public/assets/css/presentation-enhancements.css'; document.head.append(enhancementStyles);
  const welcomeBanner = document.createElement('p'); welcomeBanner.className = 'welcome-banner'; welcomeBanner.hidden = true; document.querySelector('.landing-nav')?.append(welcomeBanner);
  const welcomeStyle = document.createElement('style'); welcomeStyle.textContent = '.welcome-banner{margin:0 0 0 auto;color:#0b4c8b;font:800 13px/1 Inter,Segoe UI,Arial,sans-serif}'; document.head.append(welcomeStyle);
  const accountDock = document.createElement('div'); accountDock.className = 'account-dock'; accountDock.innerHTML = '<div id="accountDockLeft"></div><div id="accountDockRight"></div>'; document.body.append(accountDock);
  const dockStyle = document.createElement('style'); dockStyle.textContent = '.account-dock{opacity:0;transform:translateY(18px);transition:opacity .22s ease,transform .22s ease}.account-dock.dock-visible{opacity:1;transform:none}'; document.head.append(dockStyle);
  let lastScrollY = window.scrollY;
  window.addEventListener('scroll', () => { const currentScrollY = window.scrollY; if (currentScrollY > lastScrollY && currentScrollY > 40) accountDock.classList.add('dock-visible'); else if (currentScrollY < lastScrollY) accountDock.classList.remove('dock-visible'); lastScrollY = currentScrollY; }, {passive: true});
  const dockLeft = () => $('#accountDockLeft'); const dockRight = () => $('#accountDockRight');
  function showTopWelcome(firstName) { const name = String(firstName || '').trim(); welcomeBanner.textContent = name ? `Welcome, ${name}` : ''; welcomeBanner.hidden = !name; }
  function updateAccountActions() {
    if (signedIn) {
      dockLeft().innerHTML = `${isAdmin ? '<a class="nav-action admin-link" href="admin.php">Admin</a>' : ''}<button class="nav-action" id="settingsButton" type="button">Settings</button>`;
      dockRight().innerHTML = '<button class="logout-button" id="logoutButton" type="button">Log out</button>';
      $('#logoutButton').addEventListener('click', logout);
      $('#settingsButton').addEventListener('click', showSettings);
    } else {
      dockLeft().innerHTML = '';
      dockRight().innerHTML = '<button class="nav-action" id="loginButton" type="button">Log in</button><button class="signup-button" id="signupButton" type="button">Sign up</button>';
      $('#loginButton').addEventListener('click', () => showAuth(false));
      $('#signupButton').addEventListener('click', () => showAuth(true));
    }
  }
  const call = async (route, options = {}) => {
    const controller = new AbortController();
    const timeout = window.setTimeout(() => controller.abort(), 15000);
    try {
      const response = await fetch(`api/v1/index.php?route=${route}`, {headers: {'Content-Type': 'application/json', ...(csrf ? {'X-CSRF-Token': csrf} : {}), ...(options.headers || {})}, ...options, signal: options.signal || controller.signal});
      const result = await response.json().catch(() => null);
      if (!response.ok || !result?.success) throw new Error(result?.message || 'The server could not complete the request.');
      return result;
    } finally {
      window.clearTimeout(timeout);
    }
  };
  const money = (amount) => amount === null ? '—' : `NGN ${Number(amount).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
  let signedIn = false; let isAdmin = false; let accountName = '';
  function welcome(firstName, detail = '') { const name = String(firstName || 'there').trim() || 'there'; $('#notice').textContent = `Welcome, ${name}!${detail ? ` ${detail}` : ''}`; $('#notice').hidden = false; }
  async function loadSession() { try { const result = await call('me'); signedIn = true; isAdmin = result.data?.is_admin === true; accountName = result.data?.first_name || ''; showTopWelcome(accountName); $('#memberMessage').hidden = false; } catch { signedIn = false; isAdmin = false; accountName = ''; showTopWelcome(''); } finally { updateAccountActions(); } }
  function beginTrade(symbol) {
    if (!signedIn) { showAuth(false, symbol); return; }
    $('#notice').textContent = 'Trade execution is unavailable until an approved broker is configured.';
    $('#notice').hidden = false;
  }
  async function loadConnections() {
    try {
      const result = await call('platform/connections');
      $('#executionStatus').textContent = result.data.execution?.status === 'CONNECTED' ? 'Connected' : 'Not connected';
      $('#paymentsStatus').textContent = result.data.payments?.status === 'CONFIGURED' ? 'Configured' : 'Not configured';
    } catch {
      $('#executionStatus').textContent = 'Unavailable';
      $('#paymentsStatus').textContent = 'Unavailable';
    }
  }
  async function platformStatus() {
    try {
      const result = await call('market/overview'); const data = result.data;
      if (!data || typeof data !== 'object') throw new Error('Market-data response is invalid.');
      $('#dataStatus').textContent = data.status || 'UNAVAILABLE'; $('#marketState').textContent = data.status || 'UNAVAILABLE'; $('#landingMarketState').textContent = `Market data: ${data.status || 'UNAVAILABLE'}`;
      $('#asOf').textContent = data.as_of ? `As of ${new Date(data.as_of).toLocaleString()}` : '';
      if (data.notice) { $('#notice').textContent = data.notice; $('#notice').hidden = false; }
      if (!data.items?.length) return;
      $('#marketRows').className = ''; $('#marketRows').innerHTML = data.items.map((item) => { const change = item.change_percent === null ? '—' : `${Number(item.change_percent).toFixed(2)}%`; const changeClass = Number(item.change_percent) < 0 ? 'negative' : 'positive'; return `<div class="market-row" data-symbol="${escapeHtml(item.symbol)}" role="link" tabindex="0"><span><strong>${escapeHtml(item.symbol)}</strong><br><small>${escapeHtml(item.company_name || '')}</small></span><span>${money(item.last_price)}</span><span class="${changeClass}">${change}</span><span>${escapeHtml(item.data_status || data.status)}</span><button class="trade-button" type="button" data-symbol="${escapeHtml(item.symbol)}">Trade</button></div>`; }).join('');
      document.querySelectorAll('.trade-button').forEach((button) => button.addEventListener('click', () => beginTrade(button.dataset.symbol)));
      document.querySelectorAll('.market-row').forEach((row) => row.addEventListener('click', (event) => { if (event.target.closest('.trade-button')) return; window.location.href = `market.php?symbol=${encodeURIComponent(row.dataset.symbol)}`; }));
      document.querySelectorAll('.market-row').forEach((row) => row.addEventListener('keydown', (event) => { if (event.target.closest('.trade-button')) return; if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); row.click(); } }));
      document.querySelectorAll('.market-row').forEach((row, index) => {
        const item = data.items[index]; const company = row.firstElementChild; if (!item || !company) return;
        company.classList.add('instrument'); const logo = document.createElement('span'); logo.className = 'stock-logo'; logo.setAttribute('aria-hidden', 'true');
        const initials = String(item.symbol || 'ST').replace(/[^a-z0-9]/gi, '').slice(0, 2).toUpperCase() || 'ST';
        if (item.logo_url) { const image = document.createElement('img'); image.src = item.logo_url; image.alt = ''; image.addEventListener('error', () => { logo.textContent = initials; }); logo.append(image); } else logo.textContent = initials;
        company.prepend(logo);
      });
    } catch {
      $('#dataStatus').textContent = 'UNAVAILABLE';
      $('#marketState').textContent = 'UNAVAILABLE'; $('#landingMarketState').textContent = 'Market data unavailable';
      $('#notice').textContent = 'Platform status could not be loaded. Refresh after local services are available.'; $('#notice').hidden = false;
    }
  }
  function formatSessionTime(value) {
    if (!value) return '';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleString([], {weekday: 'short', hour: 'numeric', minute: '2-digit'});
  }
  async function loadMarketSession() {
    try {
      const result = await call('market/status'); const data = result.data || {}; const open = data.is_open === true || data.status === 'OPEN';
      $('#dataStatus').textContent = open ? 'NGX market open' : data.status === 'CLOSED' ? 'NGX market closed' : 'NGX session unavailable';
      $('#marketSessionState').textContent = open ? 'Open' : data.status === 'CLOSED' ? 'Closed' : 'Unavailable';
      const time = open ? formatSessionTime(data.closes_at) : formatSessionTime(data.next_opens_at);
      $('#asOf').textContent = time
        ? (open ? `Closes ${time}` : `Opens ${time}`)
        : (open ? 'Regular close: 4:00pm WAT' : data.status === 'CLOSED' ? 'Regular session: 9:00am–4:00pm WAT' : (data.notice || ''));
      $('#marketSession').classList.toggle('market-closed', !open);
    } catch {
      $('#dataStatus').textContent = 'NGX session unavailable'; $('#marketSessionState').textContent = 'Unavailable'; $('#asOf').textContent = '';
    }
  }
  function escapeHtml(value) { const element = document.createElement('span'); element.textContent = String(value ?? ''); return element.innerHTML; }
  function revealMarketBoard() {
    const board = $('#markets'); if (!board) return; board.classList.add('market-reveal');
    if (!('IntersectionObserver' in window)) { board.classList.add('is-visible'); return; }
    const observer = new IntersectionObserver(([entry]) => { if (entry.isIntersecting) { board.classList.add('is-visible'); observer.disconnect(); } }, {threshold: 0.16}); observer.observe(board);
    window.setTimeout(() => { board.classList.add('is-visible'); observer.disconnect(); }, 900);
  }
  function showSettings() {
    const settings = document.createElement('dialog'); settings.className = 'settings-dialog';
    settings.innerHTML = '<form method="dialog"><button class="close" value="cancel" aria-label="Close">×</button><p class="caption">SETTINGS</p><h2>Your account settings</h2><p>Account, notification, and security preferences will be available after you sign in.</p><button class="outline" value="cancel">Close</button></form>';
    document.body.append(settings); settings.addEventListener('close', () => settings.remove()); settings.showModal();
  }
  async function setupCsrf() { const result = await call('csrf'); csrf = result.data.token; }
  const dialog = $('#authDialog'); let register = false;
  function showAuth(isRegister, symbol = '') { register = isRegister; $('#authTitle').textContent = register ? 'Create your account' : 'Sign in to continue'; $('#authIntro').textContent = register ? 'Create an account to join Marve.' : `Sign in to continue with ${symbol || 'your account'}.`; $('#registrationFields').hidden = !register; $('input[name=password]').autocomplete = register ? 'new-password' : 'current-password'; $('#switchAuth').hidden = true; $('#authMessage').textContent = ''; dialog.showModal(); }
  $('.close').type = 'button';
  $('.close').addEventListener('click', (event) => { event.preventDefault(); dialog.close(); });
  $('#togglePassword').addEventListener('click', () => { const input = $('input[name=password]'); const reveal = input.type === 'password'; input.type = reveal ? 'text' : 'password'; $('#togglePassword').textContent = reveal ? 'Hide' : 'Show'; $('#togglePassword').setAttribute('aria-label', reveal ? 'Hide password' : 'Show password'); $('#togglePassword').setAttribute('aria-pressed', String(reveal)); });
  $('#authForm').addEventListener('submit', async (event) => {
    event.preventDefault(); const form = new FormData(event.currentTarget); const payload = Object.fromEntries(form.entries());
    try { const result = await call(register ? 'auth/register' : 'auth/login', {method: 'POST', body: JSON.stringify(payload)}); if (!result.success) throw new Error(result.message); const name = result.data?.first_name || payload.first_name || ''; $('#authMessage').style.color = '#127451'; $('#authMessage').textContent = register ? `Welcome, ${name || 'there'}! Your account is ready — please sign in.` : `Welcome, ${name || 'there'}!`; if (register) { showTopWelcome(name); welcome(name, 'Your account has been created. Please sign in to continue.'); } if (!register) setTimeout(() => { signedIn = true; accountName = name; $('#memberMessage').hidden = false; showTopWelcome(name); welcome(name, 'You are signed in.'); updateAccountActions(); dialog.close(); }, 700); } catch (error) { $('#authMessage').style.color = '#b93b4f'; $('#authMessage').textContent = error.message || 'Unable to complete request.'; }
  });
  async function logout() {
    try { await call('auth/logout', {method: 'POST', body: '{}'}); csrf = ''; signedIn = false; isAdmin = false; accountName = ''; showTopWelcome(''); window.location.reload(); }
    catch (error) { $('#notice').textContent = error.message || 'Unable to sign out.'; $('#notice').hidden = false; }
  }
  // The public market board does not depend on a user session, so load it first.
  revealMarketBoard(); platformStatus(); loadMarketSession();
  loadConnections();
  updateAccountActions(); setupCsrf().then(loadSession).catch(() => { signedIn = false; updateAccountActions(); });
})();
