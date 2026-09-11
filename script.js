(() => {
  const header = document.querySelector('[data-header]');
  const menuToggle = document.querySelector('[data-menu-toggle]');
  const nav = document.querySelector('[data-nav]');
  const form = document.querySelector('[data-request-form]');
  const status = document.querySelector('[data-form-status]');
  const interest = document.querySelector('[data-interest]');
  const submitButton = document.querySelector('[data-submit-button]');
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

  const params = new URLSearchParams(window.location.search);
  const success = params.get('status') === 'success' || params.get('success') === '1' || params.get('sent') === '1' || params.get('request') === 'sent';
  const error = params.get('status') === 'error' || params.get('error') === '1' || params.get('request') === 'error';

  if (status && success) {
    status.textContent = 'Thank you. Your request was sent successfully. We will review the cemetery, local coverage, and the next step.';
    status.classList.remove('error');
    document.querySelector('#request-care')?.scrollIntoView({ block: 'start' });
  } else if (status && error) {
    status.textContent = 'Your request could not be sent. Please try again or email hello@wheretheyrest.ca.';
    status.classList.add('error');
    document.querySelector('#request-care')?.scrollIntoView({ block: 'start' });
  }

  if (form && submitButton) {
    form.addEventListener('submit', () => {
      if (!form.checkValidity()) return;
      submitButton.disabled = true;
      submitButton.textContent = 'Sending…';
    });
  }
})();
