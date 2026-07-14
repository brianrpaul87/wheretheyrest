(() => {
  const header = document.querySelector('[data-header]');
  const menuToggle = document.querySelector('[data-menu-toggle]');
  const nav = document.querySelector('[data-nav]');
  const form = document.querySelector('[data-request-form]');
  const status = document.querySelector('[data-form-status]');
  const interest = document.querySelector('[data-interest]');
  const year = document.querySelector('[data-year]');

  if (year) year.textContent = new Date().getFullYear();

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

  const addRuntimeStyles = () => {
    const style = document.createElement('style');
    style.textContent = `
      .form-trap{position:absolute!important;left:-10000px!important;width:1px!important;height:1px!important;overflow:hidden!important}
      .button[disabled]{cursor:wait;opacity:.68;transform:none!important}
      .contact-channel{margin-top:18px;color:inherit;font-size:.93rem}
      .contact-channel a{font-weight:800;text-decoration:underline;text-underline-offset:3px}
      .site-footer .contact-channel{margin:12px 0 0;color:rgba(255,255,255,.72)}
      .form-status.success{color:var(--evergreen-700);font-weight:700}
    `;
    document.head.appendChild(style);
  };

  const addContactLine = (container, label, email) => {
    if (!container || container.querySelector(`[href="mailto:${email}"]`)) return;
    const line = document.createElement('p');
    line.className = 'contact-channel';
    line.append(`${label} `);
    const link = document.createElement('a');
    link.href = `mailto:${email}`;
    link.textContent = email;
    line.appendChild(link);
    container.appendChild(line);
  };

  const prepareLiveContactContent = () => {
    addRuntimeStyles();

    const requestCopy = document.querySelector('.request-copy');
    const requestDescription = requestCopy
      ? [...requestCopy.querySelectorAll('p')].find((paragraph) => !paragraph.classList.contains('eyebrow'))
      : null;
    if (requestDescription) {
      requestDescription.textContent =
        'Share the cemetery community and the kind of care you are considering. We will review availability, access, and the appropriate next step.';
    }

    const privacyNote = document.querySelector('.privacy-note');
    if (privacyNote) {
      const heading = privacyNote.querySelector('strong');
      const copy = privacyNote.querySelector('span');
      if (heading) heading.textContent = 'Privacy-conscious request form';
      if (copy) {
        copy.textContent =
          'Your request will be emailed securely to Where They Rest. Please do not include medical, financial, identification, or other highly sensitive information. Documents and photographs can be requested later through an appropriate process.';
      }
    }

    addContactLine(requestCopy, 'Prefer email?', 'care@wheretheyrest.ca');

    const stewardCopy = document.querySelector('#stewards .steward-layout > div');
    addContactLine(stewardCopy, 'Steward inquiries:', 'stewards@wheretheyrest.ca');

    const footer = document.querySelector('.site-footer .container') || document.querySelector('.site-footer');
    addContactLine(footer, 'General inquiries:', 'hello@wheretheyrest.ca');
  };

  prepareLiveContactContent();

  if (!form || !status) return;

  form.action = 'api/submit.php';
  form.method = 'post';

  const submitButton = form.querySelector('button[type="submit"]');
  if (submitButton) submitButton.textContent = 'Send request';

  const consentText = form.querySelector('.consent-row span');
  if (consentText) {
    consentText.textContent =
      'I consent to Where They Rest using this information to review and respond to my request.';
  }

  const startedAt = document.createElement('input');
  startedAt.type = 'hidden';
  startedAt.name = 'started_at';
  startedAt.value = String(Date.now());
  form.appendChild(startedAt);

  const page = document.createElement('input');
  page.type = 'hidden';
  page.name = 'page';
  page.value = window.location.href.slice(0, 300);
  form.appendChild(page);

  const trap = document.createElement('label');
  trap.className = 'form-trap';
  trap.setAttribute('aria-hidden', 'true');
  trap.textContent = 'Leave this field empty ';
  const trapInput = document.createElement('input');
  trapInput.type = 'text';
  trapInput.name = 'website';
  trapInput.tabIndex = -1;
  trapInput.autocomplete = 'off';
  trap.appendChild(trapInput);
  form.appendChild(trap);

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
      const valid =
        field.type === 'checkbox'
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
    if (submitButton) {
      submitButton.disabled = true;
      submitButton.textContent = 'Sending…';
    }

    try {
      const response = await fetch(form.action, {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
        },
        body: new URLSearchParams(new FormData(form)),
        credentials: 'same-origin',
      });

      let payload = null;
      try {
        payload = await response.json();
      } catch {
        payload = null;
      }

      if (!response.ok || !payload?.ok) {
        const message =
          response.status === 429
            ? 'Please wait a little before sending another request.'
            : payload?.message ||
              'We could not send your request right now. Please email care@wheretheyrest.ca instead.';
        throw new Error(message);
      }

      const name = form.elements.name.value.trim();
      setStatus(
        `Thank you, ${name}. Your request was received. Reference: ${payload.reference}.`,
        'success',
      );
      form.reset();
      startedAt.value = String(Date.now());
      page.value = window.location.href.slice(0, 300);
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
      if (submitButton) {
        submitButton.disabled = false;
        submitButton.textContent = 'Send request';
      }
    }
  });
})();
