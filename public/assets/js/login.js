(() => {
  let csrf = '';
  const form = document.querySelector('#loginPageForm');
  const email = document.querySelector('#loginEmail');
  const password = document.querySelector('#loginPassword');
  const message = document.querySelector('#loginPageMessage');
  const params = new URLSearchParams(window.location.search);

  if (params.get('email')) {
    email.value = params.get('email');
  }
  if (params.get('verified') === '1') {
    document.querySelector('#loginIntro').textContent = 'Your email has been verified. Sign in to access your account.';
  }

  function show(text, success) {
    message.style.color = success ? '#127451' : '#b93b4f';
    message.textContent = text;
  }

  document.querySelector('#loginPasswordToggle').addEventListener('click', () => {
    const reveal = password.type === 'password';
    password.type = reveal ? 'text' : 'password';
    document.querySelector('#loginPasswordToggle').textContent = reveal ? 'Hide' : 'Show';
  });

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    try {
      const response = await fetch('api/v1/index.php?route=auth/login', {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'X-CSRF-Token': csrf},
        body: JSON.stringify({email: email.value, password: password.value}),
      });
      const result = await response.json().catch(() => null);
      if (!response.ok || !result?.success) throw new Error(result?.message || 'Unable to sign in.');
      show('Signed in successfully. Opening your account...', true);
      window.setTimeout(() => { window.location.href = 'index.php'; }, 500);
    } catch (error) {
      show(error.message || 'Unable to sign in.', false);
    }
  });

  fetch('api/v1/index.php?route=csrf')
    .then((response) => response.json())
    .then((result) => { csrf = result?.data?.token || ''; })
    .catch(() => show('Your session could not be verified. Refresh and try again.', false));
})();
