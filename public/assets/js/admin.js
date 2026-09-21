(() => {
  const call = async (route) => {
    const response = await fetch(`api/v1/index.php?route=${route}`, {headers: {'Accept': 'application/json'}});
    const result = await response.json().catch(() => null);
    if (!response.ok || !result?.success) throw new Error(result?.message || 'Unable to load administration data.');
    return result.data;
  };
  const label = (value) => String(value).replace(/_/g, ' ').toLowerCase().replace(/\b\w/g, (letter) => letter.toUpperCase());
  const state = (name, value) => `<div class="connection-card"><span>${name}</span><strong class="${value === 'CONNECTED' || value === 'CONFIGURED' || value === 'LIVE' ? 'connected' : 'unavailable'}">${label(value)}</strong></div>`;
  const statNames = {total_users: 'Total accounts', active_users: 'Active accounts', suspended_users: 'Suspended accounts', accounts_today: 'Accounts today', payments_pending: 'Pending payments', orders_open: 'Open orders'};
  call('admin/overview').then((overview) => {
    document.querySelector('#adminStats').innerHTML = Object.entries(statNames).map(([key, name]) => `<article class="admin-stat"><span>${name}</span><strong>${Number(overview[key] || 0).toLocaleString()}</strong></article>`).join('');
    document.querySelector('#adminUpdated').textContent = `Updated ${new Date().toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'})}`;
  }).catch((error) => {
    document.querySelector('#adminStats').innerHTML = `<p class="notice">${error.message}</p>`;
  });
  call('platform/status').then((platform) => {
    document.querySelector('#connectionCards').innerHTML = [state('Market data', platform.market?.status || 'UNAVAILABLE'), state('Trade execution', platform.execution?.status || 'NOT_CONNECTED'), state('Payments', platform.payments?.status || 'NOT_CONFIGURED')].join('');
  }).catch(() => { document.querySelector('#connectionCards').textContent = 'Service status unavailable.'; });
})();
