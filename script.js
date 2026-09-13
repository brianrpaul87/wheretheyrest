(() => {
  const header = document.querySelector('[data-header]');
  const menuToggle = document.querySelector('[data-menu-toggle]');
  const nav = document.querySelector('[data-nav]');
  const form = document.querySelector('[data-request-form]');
  const status = document.querySelector('[data-form-status]');
  const interest = document.querySelector('[data-interest]');
  const submitButton = document.querySelector('[data-submit-button]') || form?.querySelector('button[type="submit"]');
  const year = document.querySelector('[data-year]');

  if (year) year.textContent = new Date().getFullYear();

  const heroEyebrow = document.querySelector('.hero-copy > .eyebrow');
  if (heroEyebrow) heroEyebrow.textContent = 'Now accepting requests in Greater Victoria, BC';

  const updateHeader = () => {
    if (header) header.classList.toggle('scrolled', window.scrollY > 12);
  };
  updateHeader();
  window.addEventListener('scroll', updateHeader, { passive: true });

  const closeMenu = () => {
    if (!menuToggle || !nav) return;
    menuToggle.setAttribute('aria-expanded', 'false');
    nav.classList.remove('open');
  };

  if (menuToggle && nav) {
    menuToggle.addEventListener('click', () => {
      const willOpen = menuToggle.getAttribute('aria-expanded') !== 'true';
      menuToggle.setAttribute('aria-expanded', String(willOpen));
      nav.classList.toggle('open', willOpen);
    });

    nav.addEventListener('click', (event) => {
      if (event.target.closest('a')) closeMenu();
    });

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') closeMenu();
    });

    window.addEventListener('resize', () => {
      if (window.innerWidth > 820) closeMenu();
    });
  }

  document.querySelectorAll('[data-intent]').forEach((link) => {
    link.addEventListener('click', () => {
      const value = link.getAttribute('data-intent');
      if (interest && value) interest.value = value;
    });
  });

  const heroImage = document.querySelector('.hero-visual img');
  if (heroImage && heroImage.getAttribute('src')?.includes('memorial-before-after-side-by-side.jpg')) {
    heroImage.setAttribute('src', 'assets/grave-memorial-cleaning-before-after.jpg');
  }

  if (!form || !status || !submitButton) return;

  form.action = 'api/submit.php';
  form.method = 'post';

  const ensureHiddenInput = (name, datasetKey) => {
    let input = form.querySelector(`input[name="${name}"]`);
    if (!input) {
      input = document.createElement('input');
      input.type = 'hidden';
      input.name = name;
      form.appendChild(input);
    }
    if (datasetKey) input.dataset[datasetKey] = '';
    return input;
  };

  const startedAt = ensureHiddenInput('started_at', 'startedAt');
  const page = ensureHiddenInput('page', 'page');

  let trap = form.querySelector('input[name="website"]');
  if (!trap) {
    trap = document.createElement('input');
    trap.type = 'text';
    trap.name = 'website';
    trap.tabIndex = -1;
    trap.autocomplete = 'off';
    trap.setAttribute('aria-hidden', 'true');
    trap.style.position = 'absolute';
    trap.style.left = '-9999px';
    trap.style.width = '1px';
    trap.style.height = '1px';
    trap.style.overflow = 'hidden';
    form.appendChild(trap);
  }

  const resetRuntimeFields = () => {
    startedAt.value = String(Date.now());
    page.value = window.location.href.slice(0, 300);
    trap.value = '';
  };
  resetRuntimeFields();

  let submitting = false;

  const setStatus = (message, type = '') => {
    status.textContent = message;
    status.classList.toggle('error', type === 'error');
    status.classList.toggle('success', type === 'success');
  };

  const validate = () => {
    const requiredFields = [...form.querySelectorAll('[required]')];
    let firstInvalid = null;

    requiredFields.forEach((field) => {
      const valid = field.type === 'checkbox'
        ? field.checked
        : field.value.trim() !== '' && field.checkValidity();
      field.setAttribute('aria-invalid', String(!valid));
      if (!valid && !firstInvalid) firstInvalid = field;
    });

    if (firstInvalid) {
      setStatus('Please complete the required fields before sending your request.', 'error');
      firstInvalid.focus();
      return false;
    }

    return true;
  };

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (submitting) return;

    setStatus('');
    if (!validate()) return;

    submitting = true;
    submitButton.disabled = true;
    submitButton.textContent = 'Sending…';

    try {
      const body = new URLSearchParams(new FormData(form));
      body.set('consent', 'on');
      if (body.get('interest') === 'memorial-care') body.set('interest', 'single-visit');

      const response = await fetch(form.action, {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
        },
        body,
        credentials: 'same-origin',
      });

      let payload = null;
      try {
        payload = await response.json();
      } catch {
        payload = null;
      }

      if (!response.ok || !payload?.ok) {
        const message = response.status === 429
          ? 'Please wait a little before sending another request.'
          : payload?.message || 'We could not send your request right now. Please email care@wheretheyrest.ca instead.';
        throw new Error(message);
      }

      const name = form.elements.name.value.trim();
      setStatus(`Thank you, ${name}. Your request was received. Reference: ${payload.reference}.`, 'success');
      form.reset();
      resetRuntimeFields();
      form.querySelectorAll('[aria-invalid]').forEach((field) => field.removeAttribute('aria-invalid'));
    } catch (error) {
      setStatus(
        error instanceof Error
          ? error.message
          : 'We could not send your request right now. Please email care@wheretheyrest.ca instead.',
        'error',
      );
    } finally {
      submitting = false;
      submitButton.disabled = false;
      submitButton.textContent = 'Send request';
    }
  });
})();
