(() => {
  'use strict';
  const body = document.body;
  const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  const main = document.querySelector('main');
  if (main && !main.id) main.id = 'main-content';
  if (main && !main.hasAttribute('tabindex')) main.setAttribute('tabindex', '-1');

  if (token) {
    document.querySelectorAll('form').forEach((form) => {
      const method = (form.getAttribute('method') || 'get').toLowerCase();
      if (method !== 'post' || form.querySelector('input[name="_csrf"]')) return;
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = '_csrf';
      input.value = token;
      form.prepend(input);
    });
  }

  const openButton = document.querySelector('[data-tl-menu-open]');
  const closeButtons = document.querySelectorAll('[data-tl-menu-close]');
  const nav = document.getElementById('tl-primary-nav');
  const setOpen = (open) => {
    body.classList.toggle('tl-nav-open', open);
    if (openButton) openButton.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (open) nav?.querySelector('a,button')?.focus();
  };
  if (openButton) openButton.addEventListener('click', () => setOpen(true));
  closeButtons.forEach((button) => button.addEventListener('click', () => setOpen(false)));
  if (nav) nav.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => setOpen(false)));
  document.addEventListener('keydown', (event) => { if (event.key === 'Escape') setOpen(false); });
})();
