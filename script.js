(() => {
  const header = document.querySelector('[data-header]');
  const menuToggle = document.querySelector('[data-menu-toggle]');
  const nav = document.querySelector('[data-nav]');
  const form = document.querySelector('[data-request-form]');
  const status = document.querySelector('[data-form-status]');
  const interest = document.querySelector('[data-interest]');
  const year = document.querySelector('[data-year]');

  if (year) {
    year.textContent = new Date().getFullYear();
  }

  const updateHeader = () => {
    if (header) {
      header.classList.toggle('scrolled', window.scrollY > 12);
    }
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

  if (form && status) {
    form.addEventListener('submit', (event) => {
      event.preventDefault();
      status.textContent = '';
      status.classList.remove('error');

      const requiredFields = [...form.querySelectorAll('[required]')];
      let firstInvalid = null;

      requiredFields.forEach((field) => {
        const valid = field.type === 'checkbox' ? field.checked : field.value.trim() !== '' && field.checkValidity();
        field.setAttribute('aria-invalid', String(!valid));
        if (!valid && !firstInvalid) firstInvalid = field;
      });

      if (firstInvalid) {
        status.textContent = 'Please complete the required fields before previewing your request.';
        status.classList.add('error');
        firstInvalid.focus();
        return;
      }

      const name = form.elements.name.value.trim();
      const location = form.elements.location.value.trim();
      status.textContent = `Thank you, ${name}. This preview request for ${location} is complete, but no information has been transmitted.`;
      form.reset();
      requiredFields.forEach((field) => field.removeAttribute('aria-invalid'));
    });
  }
})();
