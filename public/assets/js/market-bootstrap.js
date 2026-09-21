(() => {
  const encoded = document.body?.dataset.instrument || '';
  if (!encoded || typeof window.fetch !== 'function') return;

  let data;
  try {
    data = JSON.parse(atob(encoded));
  } catch {
    return;
  }

  if (!data || !data.instrument) return;
  const fetchFromNetwork = window.fetch.bind(window);
  window.fetch = (input, options) => {
    const url = typeof input === 'string' ? input : input instanceof Request ? input.url : '';
    if (url.includes('route=market/instrument')) {
      return Promise.resolve(new Response(JSON.stringify({success: true, message: 'Request completed successfully', data}), {
        status: 200,
        headers: {'Content-Type': 'application/json'},
      }));
    }
    return fetchFromNetwork(input, options);
  };
})();
