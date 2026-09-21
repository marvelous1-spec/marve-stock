(() => {
  const $ = (selector) => document.querySelector(selector);
  const money = (value) => value === null || value === undefined ? '--' : `NGN ${Number(value).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
  const menu = $('.instrument-nav');
  $('#menuToggle').addEventListener('click', () => { const open = menu.classList.toggle('menu-open'); $('#menuToggle').setAttribute('aria-expanded', String(open)); });
  const draw = (points) => {
    const canvas = $('#priceChart'); const box = canvas.getBoundingClientRect(); const ratio = window.devicePixelRatio || 1;
    canvas.width = Math.max(1, Math.floor(box.width * ratio)); canvas.height = Math.max(1, Math.floor(box.height * ratio));
    const ctx = canvas.getContext('2d'); ctx.scale(ratio, ratio); const width = box.width; const height = box.height;
    ctx.clearRect(0, 0, width, height); const values = points.map((point) => Number(point.last_price)).filter(Number.isFinite);
    if (values.length < 2) return;
    const low = Math.min(...values); const high = Math.max(...values); const range = high - low || Math.max(high * .02, 1); const padding = {top: 30, right: 22, bottom: 32, left: 56};
    ctx.strokeStyle = '#e3eaf1'; ctx.lineWidth = 1; ctx.fillStyle = '#718198'; ctx.font = '11px Segoe UI, Arial';
    for (let index = 0; index < 4; index += 1) { const y = padding.top + ((height - padding.top - padding.bottom) * index / 3); const value = high - (range * index / 3); ctx.beginPath(); ctx.moveTo(padding.left, y); ctx.lineTo(width - padding.right, y); ctx.stroke(); ctx.fillText(Number(value).toLocaleString(undefined, {maximumFractionDigits: 2}), 8, y + 4); }
    ctx.strokeStyle = '#0b66d4'; ctx.lineWidth = 2.5; ctx.beginPath(); values.forEach((value, index) => { const x = padding.left + ((width - padding.left - padding.right) * index / (values.length - 1)); const y = padding.top + ((high - value) / range * (height - padding.top - padding.bottom)); if (index === 0) ctx.moveTo(x, y); else ctx.lineTo(x, y); }); ctx.stroke();
  };
  const call = async () => { const response = await fetch(`api/v1/index.php?route=market/instrument&symbol=${encodeURIComponent(window.MARVE_SYMBOL)}`); const result = await response.json(); if (!response.ok || !result.success) throw new Error(result.message || 'Market detail is unavailable.'); return result.data; };
  const encodedData = document.body.dataset.instrument || '';
  let initialData = null;
  try { initialData = encodedData ? JSON.parse(atob(encodedData)) : null; } catch { initialData = null; }
  const initialError = document.body.dataset.instrumentError || '';
  const request = initialData ? Promise.resolve(initialData) : initialError ? Promise.reject(new Error(initialError)) : call();
  request.then((data) => { const item = data.instrument; $('#instrumentName').textContent = item.company_name || item.symbol; $('#instrumentSymbol').textContent = item.symbol; $('#lastPrice').textContent = money(item.last_price); const change = item.change_percent === null || item.change_percent === undefined ? 'Change unavailable' : `${Number(item.change_percent).toFixed(2)}% today`; $('#quoteChange').textContent = change; $('#quoteChange').className = Number(item.change_percent) < 0 ? 'negative' : 'positive'; $('#dataSource').textContent = data.source || '--'; $('#dataStatus').textContent = data.status || '--'; $('#observedAt').textContent = item.observed_at ? new Date(item.observed_at).toLocaleString() : '--'; $('#quoteStatus').textContent = `${data.history.length} stored point${data.history.length === 1 ? '' : 's'}`; $('#chartMeta').textContent = data.history.length > 1 ? 'Last 90 days' : 'Awaiting history'; if (data.history.length < 2) { $('#chartEmpty').hidden = false; $('#chartEmpty').textContent = data.notice || 'No authorised historical quotes are available yet.'; } draw(data.history); window.addEventListener('resize', () => draw(data.history)); }).catch((error) => { $('#instrumentName').textContent = 'Market detail unavailable'; $('#chartEmpty').hidden = false; $('#chartEmpty').textContent = error.message; });
})();
