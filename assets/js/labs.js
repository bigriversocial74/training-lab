(() => {
  'use strict';

  const body = document.body;
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  const ensureMainTarget = () => {
    const main = document.querySelector('main');
    if (main && !main.id) main.id = 'main-content';
    if (main && !main.hasAttribute('tabindex')) main.setAttribute('tabindex', '-1');
  };
  const securePostForms = () => {
    if (!csrfToken) return;
    document.querySelectorAll('form').forEach((form) => {
      const method = (form.getAttribute('method') || 'get').toLowerCase();
      if (method !== 'post' || form.querySelector('input[name="_csrf"]')) return;
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = '_csrf';
      input.value = csrfToken;
      form.prepend(input);
    });
  };
  const secureFetch = () => {
    if (!window.fetch || !csrfToken || window.__trainingLabFetchSecured) return;
    const nativeFetch = window.fetch.bind(window);
    window.fetch = (input, init = {}) => {
      const requestUrl = typeof input === 'string' ? input : input?.url;
      const url = new URL(requestUrl || window.location.href, window.location.href);
      const method = String(init.method || (input instanceof Request ? input.method : 'GET')).toUpperCase();
      const next = { ...init, credentials: init.credentials || 'same-origin' };
      if (url.origin === window.location.origin && !['GET', 'HEAD', 'OPTIONS'].includes(method)) {
        const headers = new Headers(input instanceof Request ? input.headers : undefined);
        new Headers(init.headers || {}).forEach((value, key) => headers.set(key, value));
        headers.set('X-CSRF-Token', csrfToken);
        next.headers = headers;
      }
      return nativeFetch(input, next);
    };
    window.__trainingLabFetchSecured = true;
  };

  ensureMainTarget();
  securePostForms();
  secureFetch();

  document.querySelectorAll('[data-labs-toggle]').forEach((toggle) => toggle.addEventListener('click', () => {
    const selector = toggle.getAttribute('data-labs-toggle');
    const target = selector ? document.querySelector(selector) : null;
    if (target) target.classList.toggle('is-open');
  }));

  const mainMenu = document.querySelector('#labs-primary-nav');
  const openMenu = document.querySelector('[data-labs-menu-open]');
  const closeMenu = () => {
    body.classList.remove('labs-nav-open');
    if (openMenu) openMenu.setAttribute('aria-expanded', 'false');
  };
  if (openMenu && mainMenu) {
    openMenu.addEventListener('click', () => {
      body.classList.add('labs-nav-open');
      openMenu.setAttribute('aria-expanded', 'true');
      mainMenu.querySelector('a,button')?.focus();
    });
  }
  document.querySelectorAll('[data-labs-menu-close]').forEach((node) => node.addEventListener('click', closeMenu));

  const openWorkspace = document.querySelector('[data-labs-workspace-open]');
  const workspace = document.querySelector('#labs-workspace-nav');
  const closeWorkspace = () => {
    body.classList.remove('labs-workspace-open');
    if (openWorkspace) openWorkspace.setAttribute('aria-expanded', 'false');
  };
  if (openWorkspace) {
    openWorkspace.addEventListener('click', () => {
      body.classList.add('labs-workspace-open');
      openWorkspace.setAttribute('aria-expanded', 'true');
      workspace?.querySelector('a,button')?.focus();
    });
  }
  document.querySelectorAll('[data-labs-workspace-close]').forEach((node) => node.addEventListener('click', closeWorkspace));

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      closeMenu();
      closeWorkspace();
    }
  });

  const storageKey = 'trainingLabDemoStateV1';
  const defaultState = { proofStatus: 'not_submitted', reviewStatus: 'not_submitted', completedActions: 4, streakDays: 4, rewardStatus: 'pending', updatedAt: '' };
  const labelMap = { not_submitted: 'Not submitted', submitted: 'Submitted', in_review: 'In review', approved: 'Approved', pending: 'Pending', unlocked: 'Unlocked' };
  const pretty = (value) => labelMap[value] || value;
  const readState = () => {
    try { return { ...defaultState, ...(JSON.parse(localStorage.getItem(storageKey)) || {}) }; }
    catch { return { ...defaultState }; }
  };
  const setText = (selector, value) => document.querySelectorAll(selector).forEach((node) => { node.textContent = value; });
  const setStatusTone = (selector, stateValue) => document.querySelectorAll(selector).forEach((node) => { node.dataset.statusTone = stateValue; });
  const renderState = (state = readState()) => {
    const progress = Math.min(100, Math.max(0, Number(state.completedActions || 0) * 20));
    setText('[data-demo-proof-status]', pretty(state.proofStatus));
    setText('[data-demo-review-status]', pretty(state.reviewStatus));
    setText('[data-demo-reward-status]', pretty(state.rewardStatus));
    setText('[data-demo-streak-days]', String(state.streakDays || 0));
    setText('[data-demo-completed-actions]', String(state.completedActions || 0));
    setText('[data-demo-progress-label]', `${progress}% complete`);
    setText('[data-demo-updated-at]', state.updatedAt || 'Not updated yet');
    setStatusTone('[data-demo-proof-status]', state.proofStatus);
    setStatusTone('[data-demo-review-status]', state.reviewStatus);
    setStatusTone('[data-demo-reward-status]', state.rewardStatus);
    document.querySelectorAll('[data-demo-progress-fill]').forEach((node) => { node.style.width = `${progress}%`; });
  };
  const writeState = (nextState) => {
    const state = { ...readState(), ...nextState, updatedAt: new Date().toLocaleString() };
    localStorage.setItem(storageKey, JSON.stringify(state));
    renderState(state);
  };
  document.querySelectorAll('[data-demo-action]').forEach((button) => button.addEventListener('click', () => {
    const action = button.getAttribute('data-demo-action');
    if (action === 'submit-proof') writeState({ proofStatus: 'submitted', reviewStatus: 'in_review', completedActions: 5, rewardStatus: 'pending' });
    if (action === 'approve-proof') writeState({ proofStatus: 'approved', reviewStatus: 'approved', completedActions: 5, rewardStatus: 'unlocked' });
    if (action === 'reset-demo') { localStorage.removeItem(storageKey); renderState({ ...defaultState }); }
  }));
  renderState();

  document.querySelectorAll('.labs-stage30-form').forEach((form) => { form.dataset.trainingLabFunctional = 'true'; });
})();
