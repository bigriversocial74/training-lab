(() => {
  const toggles = document.querySelectorAll('[data-labs-toggle]');
  toggles.forEach((toggle) => toggle.addEventListener('click', () => {
    const target = document.querySelector(toggle.getAttribute('data-labs-toggle'));
    if (target) target.classList.toggle('is-open');
  }));

  const body = document.body;
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
    });
  }
  document.querySelectorAll('[data-labs-menu-close]').forEach((node) => node.addEventListener('click', closeMenu));

  const openWorkspace = document.querySelector('[data-labs-workspace-open]');
  const closeWorkspace = () => body.classList.remove('labs-workspace-open');
  if (openWorkspace) {
    openWorkspace.addEventListener('click', () => body.classList.add('labs-workspace-open'));
  }
  document.querySelectorAll('[data-labs-workspace-close]').forEach((node) => node.addEventListener('click', closeWorkspace));

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      closeMenu();
      closeWorkspace();
    }
  });

  const storageKey = 'trainingLabDemoStateV1';
  const defaultState = {
    proofStatus: 'not_submitted',
    reviewStatus: 'not_submitted',
    completedActions: 4,
    streakDays: 4,
    rewardStatus: 'pending',
    updatedAt: ''
  };
  const labelMap = { not_submitted: 'Not submitted', submitted: 'Submitted', in_review: 'In review', approved: 'Approved', pending: 'Pending', unlocked: 'Unlocked' };
  const pretty = (value) => labelMap[value] || value;
  const readState = () => {
    try {
      return { ...defaultState, ...(JSON.parse(localStorage.getItem(storageKey)) || {}) };
    } catch {
      return { ...defaultState };
    }
  };
  const setText = (selector, value) => document.querySelectorAll(selector).forEach((node) => { node.textContent = value; });
  const setStatusTone = (selector, stateValue) => {
    document.querySelectorAll(selector).forEach((node) => {
      node.dataset.statusTone = stateValue;
    });
  };
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
    if (action === 'reset-demo') {
      localStorage.removeItem(storageKey);
      renderState({ ...defaultState });
    }
  }));
  renderState();
})();

// Stage 30 helper: mark functional Training Lab write forms for styling only.
document.querySelectorAll('.labs-stage30-form').forEach((form) => {
  form.dataset.trainingLabFunctional = 'true';
});
