(() => {
  const body = document.body;
  const openButton = document.querySelector('[data-tl-menu-open]');
  const closeButtons = document.querySelectorAll('[data-tl-menu-close]');
  const nav = document.getElementById('tl-primary-nav');
  const setOpen = (open) => {
    body.classList.toggle('tl-nav-open', open);
    if (openButton) openButton.setAttribute('aria-expanded', open ? 'true' : 'false');
  };
  if (openButton) openButton.addEventListener('click', () => setOpen(true));
  closeButtons.forEach((button) => button.addEventListener('click', () => setOpen(false)));
  if (nav) nav.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => setOpen(false)));
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') setOpen(false);
  });
})();
