(() => {
  let csrf = '';
  const form = document.querySelector('#verificationPageForm');
  const message = document.querySelector('#verificationPageMessage');
  const email = form.elements.email;
  const code = form.elements.code;
  const suppliedEmail = new URLSearchParams(window.location.search).get('email');

  if (suppliedEmail) {
    email.value = suppliedEmail;
  }

  async function request(route, payload) {
    const response = await fetch(`api/v1/index.php?route=${route}`, {
      method: 'POST',
      headers: {'Content-Type': 'application/json', 'X-CSRF-Token': csrf},
      body: JSON.stringify(payload),
    });
    const result = await response.json().catch(() => null);
    if (!response.ok || !result?.success) throw new Error(result?.message || 'The request could not be completed.');
    return result;
  }

  function show(text, success) {
    message.style.color = success ? '#127451' : '#b93b4f';
    message.textContent = text;
  }

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    try {
      const result = await request('auth/verify-email', {email: email.value, code: code.value});
      show(result.message, true);
      window.setTimeout(() => { window.location.href = `login.php?verified=1&email=${encodeURIComponent(email.value)}`; }, 800);
    } catch (error) {
      show(error.message || 'Unable to verify this email address.', false);
    }
  });

  document.querySelector('#resendPageCode').addEventListener('click', async () => {
    try {
      const result = await request('auth/resend-verification', {email: email.value});
      show(result.message, true);
    } catch (error) {
      show(error.message || 'Unable to send a new code.', false);
    }
  });

  fetch('api/v1/index.php?route=csrf')
    .then((response) => response.json())
    .then((result) => { csrf = result?.data?.token || ''; })
    .catch(() => show('Your session could not be verified. Refresh and try again.', false));
})();
