/**
 * TaskFlow — Enterprise Task Manager  |  app.js
 * Complete clean rewrite — no duplicates, all bugs fixed.
 */
'use strict';

/* ══════════════════════════════════════════════════
   HELPERS (defined first — used everywhere below)
══════════════════════════════════════════════════ */
function $(id) { return document.getElementById(id); }

function escHtml(s) {
  return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;')
    .replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}

function initials(name) {
  return (name || '?').split(' ').slice(0,2).map(w => w[0]).join('').toUpperCase();
}

function formatDate(d) {
  if (!d) return null;
  return new Date(d + 'T00:00:00').toLocaleDateString('en-GB', { day:'numeric', month:'short', year:'numeric' });
}

function isOverdue(d) {
  if (!d) return false;
  return new Date(d + 'T00:00:00') < new Date(new Date().toDateString());
}

function timeAgo(d) {
  const s = Math.floor((Date.now() - new Date(d)) / 1000);
  if (s < 60)    return 'just now';
  if (s < 3600)  return `${Math.floor(s/60)}m ago`;
  if (s < 86400) return `${Math.floor(s/3600)}h ago`;
  return `${Math.floor(s/86400)}d ago`;
}

function setLoading(btn, on) {
  if (!btn) return;
  btn.classList.toggle('loading', on);
  btn.disabled = on;
}

function showBanner(el, msg, type) {
  if (!el) return;
  el.textContent = msg;
  el.className = `auth-banner auth-banner--${type}`;
}

function clearBanner(el) {
  if (!el) return;
  el.textContent = '';
  el.className = 'auth-banner';
}

/* ══════════════════════════════════════════════════
   CONFIG & STATE
══════════════════════════════════════════════════ */
// ⚠️  PRODUCTION: Replace this with your Railway backend URL before deploying.
// Example: const API_BASE = 'https://taskflow-backend.up.railway.app/api';
const API_BASE = 'REPLACE_WITH_RAILWAY_BACKEND_URL/api';

const state = {
  token:         null,
  user:          null,
  projects:      [],
  activeProject: null,
  tasks:         [],
  filters:       { status: '', priority: '', search: '' },
  dragTaskId:    null,
  pendingDeleteId: null,
};

/* ══════════════════════════════════════════════════
   API
══════════════════════════════════════════════════ */
async function api(method, path, body = null) {
  const headers = { 'Content-Type': 'application/json' };
  if (state.token) headers['Authorization'] = `Bearer ${state.token}`;
  const opts = { method, headers };
  if (body) opts.body = JSON.stringify(body);
  const res  = await fetch(`${API_BASE}${path}`, opts);
  const json = await res.json().catch(() => ({ success:false, message:'Server error.' }));
  if (!res.ok) throw Object.assign(new Error(json.message || 'Request failed'), { status: res.status });
  return json;
}

/* ══════════════════════════════════════════════════
   TOKEN
══════════════════════════════════════════════════ */
function saveToken(t)  { localStorage.setItem('tf_token', t); state.token = t; }
function loadToken()   { state.token = localStorage.getItem('tf_token') || null; }
function clearToken()  { localStorage.removeItem('tf_token'); state.token = null; }

/* ══════════════════════════════════════════════════
   TOAST
══════════════════════════════════════════════════ */
const TOAST_ICONS = {
  success: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>`,
  error:   `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>`,
  info:    `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>`,
  warning: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/></svg>`,
};

function toast(msg, type = 'info', dur = 3800) {
  const el = document.createElement('div');
  el.className = `toast toast--${type}`;
  el.setAttribute('role', 'status');
  el.innerHTML = `${TOAST_ICONS[type] || TOAST_ICONS.info}<span>${escHtml(msg)}</span>
    <button class="toast-close" aria-label="Dismiss">✕</button>`;
  $('toast-container').appendChild(el);
  el.querySelector('.toast-close').onclick = () => el.remove();
  setTimeout(() => { if (el.parentNode) el.remove(); }, dur);
}

/* ══════════════════════════════════════════════════
   MODAL
══════════════════════════════════════════════════ */
function openModal(id) {
  const el = $(id);
  if (!el) return;
  el.style.display = 'flex';
  el.classList.add('is-open');
  // Focus first interactive element
  setTimeout(() => {
    const f = el.querySelector('input:not([type="hidden"]), textarea, select, button:not(.modal-close)');
    if (f) f.focus();
  }, 60);
}

function closeModal(id) {
  const el = $(id);
  if (!el) return;
  el.style.display = 'none';
  el.classList.remove('is-open');
}

/* ══════════════════════════════════════════════════
   VIEW
══════════════════════════════════════════════════ */
function showApp() {
  $('auth-screen').classList.add('auth-screen--hidden');
  $('app').classList.remove('app-shell--hidden');
}

function showAuth() {
  // Hide app, show auth
  $('auth-screen').classList.remove('auth-screen--hidden');
  $('app').classList.add('app-shell--hidden');

  // Make sure board view is not in active state for next login
  $('view-board').classList.remove('view--active');
  $('view-activity').classList.add('view--hidden');
  $('view-activity').classList.remove('view--active');
  $('empty-state').classList.add('empty-state--hidden');

  // Reset nav to Board tab
  document.querySelectorAll('.nav-item').forEach(n => {
    const isBoard = n.dataset.view === 'board';
    n.classList.toggle('active', isBoard);
    n.setAttribute('aria-current', isBoard ? 'page' : 'false');
  });

  // Switch back to login tab
  $('tab-login').classList.add('active');
  $('tab-login').setAttribute('aria-selected', 'true');
  $('tab-register').classList.remove('active');
  $('tab-register').setAttribute('aria-selected', 'false');
  $('panel-login').classList.remove('auth-panel--hidden');
  $('panel-register').classList.add('auth-panel--hidden');
}

function showView(view) {
  // Hide all views
  $('view-board').classList.remove('view--active');
  $('view-activity').classList.add('view--hidden');
  $('empty-state').classList.add('empty-state--hidden');

  // Update nav
  document.querySelectorAll('.nav-item').forEach(n => {
    const active = n.dataset.view === view;
    n.classList.toggle('active', active);
    n.setAttribute('aria-current', active ? 'page' : 'false');
  });

  if (view === 'board') {
    if (state.activeProject) {
      $('view-board').classList.add('view--active');
    } else {
      $('empty-state').classList.remove('empty-state--hidden');
    }
  } else if (view === 'activity') {
    $('view-activity').classList.remove('view--hidden');
    $('view-activity').classList.add('view--active');
    loadActivity();
  }
}

function showEmptyState() {
  $('view-board').classList.remove('view--active');
  $('view-activity').classList.add('view--hidden');
  $('empty-state').classList.remove('empty-state--hidden');
}

/* ══════════════════════════════════════════════════
   AUTH
══════════════════════════════════════════════════ */
async function initAuth() {
  loadToken();
  if (!state.token) { showAuth(); return; }
  try {
    const res  = await api('GET', '/auth/me');
    state.user = res.data.user;
    showApp();
    renderSidebarUser();
    await loadProjects();
  } catch {
    clearToken();
    showAuth();
  }
}

// Login form
$('login-form').addEventListener('submit', async e => {
  e.preventDefault();
  const btn    = $('login-submit');
  const banner = $('login-banner');
  clearBanner(banner);

  const email = $('login-email').value.trim();
  const pw    = $('login-password').value;
  let valid   = true;

  if (!email) {
    $('login-email-err').textContent = 'Email is required.'; valid = false;
  } else if (!/\S+@\S+\.\S+/.test(email)) {
    $('login-email-err').textContent = 'Enter a valid email address.'; valid = false;
  } else {
    $('login-email-err').textContent = '';
  }

  if (!pw) {
    $('login-pw-err').textContent = 'Password is required.'; valid = false;
  } else {
    $('login-pw-err').textContent = '';
  }
  if (!valid) return;

  setLoading(btn, true);
  try {
    const res  = await api('POST', '/auth/login', { email, password: pw });
    saveToken(res.data.token);
    state.user = res.data.user;
    showApp();
    renderSidebarUser();
    await loadProjects();
  } catch(err) {
    showBanner(banner, err.message, 'error');
  } finally {
    setLoading(btn, false);
  }
});

// Register form
$('register-form').addEventListener('submit', async e => {
  e.preventDefault();
  const btn    = $('register-submit');
  const banner = $('reg-banner');
  clearBanner(banner);

  const name  = $('reg-name').value.trim();
  const email = $('reg-email').value.trim();
  const pw    = $('reg-password').value;
  let valid   = true;

  if (!name)  { $('reg-name-err').textContent = 'Full name is required.'; valid = false; }
  else          $('reg-name-err').textContent = '';

  if (!email || !/\S+@\S+\.\S+/.test(email)) {
    $('reg-email-err').textContent = 'Valid email address required.'; valid = false;
  } else {
    $('reg-email-err').textContent = '';
  }

  if (pw.length < 8) {
    $('reg-pw-err').textContent = 'Password must be at least 8 characters.'; valid = false;
  } else {
    $('reg-pw-err').textContent = '';
  }
  if (!valid) return;

  setLoading(btn, true);
  try {
    const res  = await api('POST', '/auth/register', { name, email, password: pw });
    saveToken(res.data.token);
    state.user = res.data.user;
    showApp();
    renderSidebarUser();
    await loadProjects();
    toast('Welcome to TaskFlow! 🎉', 'success');
  } catch(err) {
    showBanner(banner, err.message, 'error');
  } finally {
    setLoading(btn, false);
  }
});

// Logout
$('logout-btn').addEventListener('click', () => {
  // Clear auth state
  clearToken();
  state.user          = null;
  state.projects      = [];
  state.activeProject = null;
  state.tasks         = [];
  state.filters       = { status: '', priority: '', search: '' };
  state.dragTaskId    = null;
  state.pendingDeleteId = null;

  // Reset all UI elements to initial state
  $('board').innerHTML          = '';
  $('project-list').innerHTML   = '';
  $('activity-list').innerHTML  = '';
  $('topbar-title').textContent = 'Select a project';
  $('topbar-dot').style.background = 'var(--p)';
  $('topbar-tools').classList.add('topbar-tools--hidden');
  $('stats-bar').classList.add('stats-bar--hidden');
  $('stat-todo').textContent        = '0';
  $('stat-inprogress').textContent  = '0';
  $('stat-review').textContent      = '0';
  $('stat-done').textContent        = '0';
  $('stat-total').textContent       = '0';
  $('sidebar-user-name').textContent = 'Loading…';
  $('sidebar-user-role').textContent = '';
  $('sidebar-avatar').textContent   = '';
  $('sidebar-avatar').style.background = '#6366f1';

  // Reset form fields
  $('login-email').value    = '';
  $('login-password').value = '';
  $('login-email-err').textContent = '';
  $('login-pw-err').textContent    = '';
  clearBanner($('login-banner'));

  // Close any open modals
  ['task-modal', 'project-modal', 'confirm-modal'].forEach(closeModal);
  closeSidebar();

  showAuth();
  toast('Signed out successfully.', 'info');
});

// Auth tab switching
document.querySelectorAll('.auth-tab').forEach(tab => {
  tab.addEventListener('click', () => {
    const target = tab.dataset.tab;
    document.querySelectorAll('.auth-tab').forEach(t => {
      t.classList.toggle('active', t.dataset.tab === target);
      t.setAttribute('aria-selected', String(t.dataset.tab === target));
    });
    $('panel-login').classList.toggle('auth-panel--hidden', target !== 'login');
    $('panel-register').classList.toggle('auth-panel--hidden', target !== 'register');
  });
});

// Password reveal
document.querySelectorAll('.toggle-pw').forEach(btn => {
  btn.addEventListener('click', () => {
    const inp = $(btn.dataset.target);
    if (!inp) return;
    inp.type = inp.type === 'password' ? 'text' : 'password';
    btn.setAttribute('aria-label', inp.type === 'password' ? 'Show password' : 'Hide password');
  });
});

/* ══════════════════════════════════════════════════
   SIDEBAR USER
══════════════════════════════════════════════════ */
function renderSidebarUser() {
  const u = state.user;
  if (!u) return;
  const av = $('sidebar-avatar');
  av.textContent    = initials(u.name);
  av.style.background = u.avatar_color || '#6366f1';
  $('sidebar-user-name').textContent = u.name;
  $('sidebar-user-role').textContent = u.role;
}

/* ══════════════════════════════════════════════════
   PROJECTS
══════════════════════════════════════════════════ */
async function loadProjects() {
  try {
    const res       = await api('GET', '/projects');
    state.projects  = res.data.projects || [];
    renderProjectList();
    if (state.projects.length === 0) { showEmptyState(); return; }
    const current   = state.activeProject
      ? state.projects.find(p => p.id === state.activeProject.id)
      : null;
    selectProject(current || state.projects[0]);
  } catch(err) {
    toast(err.message, 'error');
    showEmptyState();
  }
}

function renderProjectList() {
  const list = $('project-list');
  list.innerHTML = '';
  if (state.projects.length === 0) {
    list.innerHTML = '<li class="sidebar-no-projects">No projects yet</li>';
    return;
  }
  state.projects.forEach(p => {
    const li  = document.createElement('li');
    const active = state.activeProject?.id === p.id;
    li.className = 'project-item' + (active ? ' active' : '');
    li.setAttribute('role', 'button');
    li.setAttribute('tabindex', '0');
    li.setAttribute('aria-label', `Project: ${p.name}`);
    li.dataset.id = p.id;
    li.innerHTML = `
      <span class="project-dot-sm" style="background:${escHtml(p.color)}" aria-hidden="true"></span>
      <span class="project-item-name">${escHtml(p.name)}</span>
      <div class="project-item-actions">
        <span class="project-count">${p.task_count || 0}</span>
        <button class="project-edit-btn icon-btn" data-id="${p.id}" aria-label="Edit project ${escHtml(p.name)}" title="Edit">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" width="12" height="12">
            <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/>
            <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4z"/>
          </svg>
        </button>
      </div>`;
    li.addEventListener('click', ev => {
      if (ev.target.closest('.project-edit-btn')) return;
      selectProject(p);
    });
    li.querySelector('.project-edit-btn').addEventListener('click', ev => {
      ev.stopPropagation();
      openProjectModal(p);
    });
    li.addEventListener('keydown', ev => {
      if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); selectProject(p); }
    });
    list.appendChild(li);
  });
}

function selectProject(project) {
  state.activeProject = project;
  state.filters = { status: '', priority: '', search: '' };

  $('filter-status').value   = '';
  $('filter-priority').value = '';
  $('search-input').value    = '';

  $('topbar-dot').style.background = project.color;
  $('topbar-title').textContent    = project.name;
  $('topbar-tools').classList.remove('topbar-tools--hidden');
  $('stats-bar').classList.remove('stats-bar--hidden');

  document.querySelectorAll('.project-item').forEach(li => {
    li.classList.toggle('active', parseInt(li.dataset.id) === project.id);
  });

  showView('board');
  loadTasks();
}

// Project modal open/close
$('new-project-btn').addEventListener('click',   () => openProjectModal());
$('empty-new-project').addEventListener('click', () => openProjectModal());
$('project-modal-close').addEventListener('click', closeProjectModal);
$('project-cancel').addEventListener('click', closeProjectModal);

function openProjectModal(project = null) {
  $('project-modal-title').textContent = project ? 'Edit Project' : 'New Project';
  $('project-id').value                = project?.id ?? '';
  $('project-name').value              = project?.name ?? '';
  $('project-description').value       = project?.description ?? '';
  $('project-submit').querySelector('.btn-text').textContent = project ? 'Save Changes' : 'Create Project';
  $('project-name-err').textContent    = '';
  clearBanner($('project-banner'));

  const colorVal = project?.color ?? '#6366f1';
  const radio    = document.querySelector(`input[name="project-color"][value="${colorVal}"]`);
  if (radio) radio.checked = true;

  openModal('project-modal');
}

function closeProjectModal() { closeModal('project-modal'); }

$('project-form').addEventListener('submit', async e => {
  e.preventDefault();
  const btn    = $('project-submit');
  const banner = $('project-banner');
  clearBanner(banner);

  const id    = $('project-id').value;
  const name  = $('project-name').value.trim();
  const desc  = $('project-description').value.trim();
  const color = document.querySelector('input[name="project-color"]:checked')?.value ?? '#6366f1';

  if (!name) { $('project-name-err').textContent = 'Project name is required.'; return; }
  $('project-name-err').textContent = '';

  setLoading(btn, true);
  try {
    if (id) {
      await api('PUT', `/projects/${id}`, { name, description: desc, color });
      toast('Project updated.', 'success');
    } else {
      await api('POST', '/projects', { name, description: desc, color });
      toast('Project created!', 'success');
    }
    closeProjectModal();
    await loadProjects();
  } catch(err) {
    showBanner(banner, err.message, 'error');
  } finally {
    setLoading(btn, false);
  }
});

/* ══════════════════════════════════════════════════
   BOARD / TASKS
══════════════════════════════════════════════════ */
const COLUMNS = [
  { key: 'todo',        label: 'To Do',       color: '#94a3b8', icon: '📋' },
  { key: 'in_progress', label: 'In Progress',  color: '#3b82f6', icon: '🔄' },
  { key: 'review',      label: 'Review',       color: '#f59e0b', icon: '👀' },
  { key: 'done',        label: 'Done',         color: '#10b981', icon: '✅' },
];

async function loadTasks() {
  if (!state.activeProject) return;
  const board = $('board');

  // Skeleton columns on first load (empty innerHTML means first render)
  if (!board.querySelector('.board-column')) {
    board.innerHTML = COLUMNS.map(col => `
      <div class="board-column" data-status="${col.key}" role="listitem" aria-label="${col.label} column">
        <div class="column-header">
          <span class="column-indicator" style="background:${col.color}" aria-hidden="true"></span>
          <span class="column-title">${col.icon} ${col.label}</span>
          <span class="column-count" id="col-count-${col.key}">0</span>
        </div>
        <div class="column-body" id="col-body-${col.key}" role="list" aria-label="${col.label} tasks"></div>
        <div class="column-add">
          <button class="column-add-btn" data-status="${col.key}" aria-label="Add task to ${col.label}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true" width="14" height="14"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Add task
          </button>
        </div>
      </div>`).join('');

    board.querySelectorAll('.column-add-btn').forEach(btn => {
      btn.addEventListener('click', () => openTaskModal(null, btn.dataset.status));
    });
    board.querySelectorAll('.column-body').forEach(col => {
      col.addEventListener('dragover',  onDragOver);
      col.addEventListener('dragleave', onDragLeave);
      col.addEventListener('drop',      onDrop);
    });
  }

  // Show loading shimmer in each column
  COLUMNS.forEach(col => {
    const body = $(`col-body-${col.key}`);
    if (body && !body.querySelector('.task-card')) {
      body.innerHTML = '<div class="task-skeleton"></div><div class="task-skeleton task-skeleton--short"></div>';
    }
  });

  try {
    const params = new URLSearchParams();
    if (state.filters.status)   params.append('status',   state.filters.status);
    if (state.filters.priority) params.append('priority', state.filters.priority);
    if (state.filters.search)   params.append('search',   state.filters.search);
    const q = params.toString() ? `?${params}` : '';

    const res    = await api('GET', `/projects/${state.activeProject.id}/tasks${q}`);
    state.tasks  = res.data.tasks || [];
    renderBoard(state.tasks);
    renderStats(res.data.stats || {});
  } catch(err) {
    toast(err.message, 'error');
  }
}

function renderBoard(tasks) {
  COLUMNS.forEach(col => {
    const body  = $(`col-body-${col.key}`);
    const count = $(`col-count-${col.key}`);
    if (!body || !count) return;

    const colTasks = tasks.filter(t => t.status === col.key);
    count.textContent = colTasks.length;

    if (colTasks.length === 0) {
      body.innerHTML = `<div class="empty-column">Drop tasks here</div>`;
      return;
    }

    body.innerHTML = colTasks.map(t => buildTaskCard(t)).join('');

    body.querySelectorAll('.task-card').forEach(card => {
      card.draggable = true;
      card.addEventListener('dragstart', onDragStart);
      card.addEventListener('dragend',   onDragEnd);
      card.querySelector('.edit-btn')?.addEventListener('click', ev => {
        ev.stopPropagation();
        const task = state.tasks.find(t => t.id === parseInt(card.dataset.id));
        if (task) openTaskModal(task);
      });
      card.querySelector('.delete-btn')?.addEventListener('click', ev => {
        ev.stopPropagation();
        const title = card.querySelector('.task-title')?.textContent || 'this task';
        openConfirmDelete(parseInt(card.dataset.id), title.trim());
      });
    });
  });
}

function buildTaskCard(task) {
  const due      = task.due_date ? formatDate(task.due_date) : null;
  const overdue  = isOverdue(task.due_date);
  const pl       = { low: 'Low', medium: 'Medium', high: 'High', urgent: 'Urgent' };
  const piColor  = { low: '#10b981', medium: '#f59e0b', high: '#f97316', urgent: '#ef4444' };

  return `<article class="task-card" data-id="${task.id}" data-priority="${escHtml(task.priority)}"
      role="listitem" aria-label="${escHtml(task.title)}" tabindex="0">
    <div class="task-priority-stripe" style="background:${piColor[task.priority] || '#94a3b8'}" aria-hidden="true"></div>
    <div class="task-body">
      <h3 class="task-title">${escHtml(task.title)}</h3>
      ${task.description ? `<p class="task-desc">${escHtml(task.description)}</p>` : ''}
      <div class="task-meta">
        <span class="badge badge--${escHtml(task.priority)}">${pl[task.priority] || task.priority}</span>
        ${due ? `<span class="task-due${overdue ? ' task-due--overdue' : ''}">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" width="11" height="11">
            <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
          </svg>${due}${overdue ? ' ⚠️' : ''}
        </span>` : ''}
      </div>
      <div class="task-actions" role="group" aria-label="Task actions">
        <button class="task-btn edit-btn" aria-label="Edit ${escHtml(task.title)}">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" width="13" height="13"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4z"/></svg>
          Edit
        </button>
        <button class="task-btn delete-btn" aria-label="Delete ${escHtml(task.title)}">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" width="13" height="13"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/></svg>
          Delete
        </button>
      </div>
    </div>
  </article>`;
}

function renderStats(s) {
  $('stat-todo').textContent       = s.todo        ?? 0;
  $('stat-inprogress').textContent = s.in_progress ?? 0;
  $('stat-review').textContent     = s.review      ?? 0;
  $('stat-done').textContent       = s.done        ?? 0;
  $('stat-total').textContent      = s.total       ?? 0;
}

/* ══════════════════════════════════════════════════
   DRAG & DROP
══════════════════════════════════════════════════ */
function onDragStart(e) {
  state.dragTaskId = parseInt(e.currentTarget.dataset.id);
  e.currentTarget.classList.add('dragging');
  e.dataTransfer.effectAllowed = 'move';
}
function onDragEnd(e) {
  e.currentTarget.classList.remove('dragging');
  document.querySelectorAll('.column-body').forEach(c => c.classList.remove('drag-over'));
}
function onDragOver(e) {
  e.preventDefault();
  e.dataTransfer.dropEffect = 'move';
  e.currentTarget.classList.add('drag-over');
}
function onDragLeave(e) { e.currentTarget.classList.remove('drag-over'); }

async function onDrop(e) {
  e.preventDefault();
  e.currentTarget.classList.remove('drag-over');
  const newStatus = e.currentTarget.closest('.board-column')?.dataset.status;
  if (!newStatus || !state.dragTaskId) return;
  const task = state.tasks.find(t => t.id === state.dragTaskId);
  if (!task || task.status === newStatus) return;
  const oldStatus  = task.status;
  task.status      = newStatus;
  renderBoard(state.tasks);
  try {
    await api('PATCH', `/tasks/${state.dragTaskId}/status`, { status: newStatus });
    toast(`Task moved to ${newStatus.replace('_', ' ')}.`, 'success');
    await loadTasks();
  } catch(err) {
    task.status = oldStatus;
    renderBoard(state.tasks);
    toast(err.message, 'error');
  }
}

/* ══════════════════════════════════════════════════
   TASK MODAL
══════════════════════════════════════════════════ */
$('add-task-btn').addEventListener('click',       () => openTaskModal());
$('task-modal-close').addEventListener('click',   closeTaskModal);
$('task-cancel').addEventListener('click',        closeTaskModal);

// Character counter on task title
$('task-title').addEventListener('input', () => {
  const len = $('task-title').value.length;
  const counter = $('task-title-count');
  if (counter) {
    counter.textContent = `${len} / 200`;
    counter.classList.toggle('char-count--warn', len > 160);
  }
});

function openTaskModal(task = null, defaultStatus = 'todo') {
  $('task-modal-title').textContent = task ? 'Edit Task' : 'New Task';
  $('task-id').value                = task?.id ?? '';
  $('task-title').value             = task?.title ?? '';
  $('task-description').value       = task?.description ?? '';
  $('task-status').value            = task?.status ?? defaultStatus;
  $('task-priority').value          = task?.priority ?? 'medium';
  $('task-due').value               = task?.due_date ?? '';
  $('task-title-err').textContent   = '';
  $('task-submit').querySelector('.btn-text').textContent = task ? 'Save Changes' : 'Save Task';

  // Update char counter
  const len     = (task?.title || '').length;
  const counter = $('task-title-count');
  if (counter) counter.textContent = `${len} / 200`;

  clearBanner($('task-banner'));
  openModal('task-modal');
}

function closeTaskModal() { closeModal('task-modal'); }

$('task-form').addEventListener('submit', async e => {
  e.preventDefault();
  const btn    = $('task-submit');
  const banner = $('task-banner');
  clearBanner(banner);

  const id          = $('task-id').value;
  const title       = $('task-title').value.trim();
  const description = $('task-description').value.trim();
  const status      = $('task-status').value;
  const priority    = $('task-priority').value;
  const due_date    = $('task-due').value || null;

  if (!title) {
    $('task-title-err').textContent = 'Task title is required.';
    $('task-title').focus();
    return;
  }
  $('task-title-err').textContent = '';

  setLoading(btn, true);
  try {
    if (id) {
      await api('PUT', `/tasks/${id}`, { title, description, status, priority, due_date });
      toast('Task updated successfully.', 'success');
    } else {
      await api('POST', `/projects/${state.activeProject.id}/tasks`, { title, description, status, priority, due_date });
      toast('Task created!', 'success');
    }
    closeTaskModal();
    await loadTasks();
    await loadProjects();
  } catch(err) {
    showBanner(banner, err.message, 'error');
  } finally {
    setLoading(btn, false);
  }
});

/* ══════════════════════════════════════════════════
   DELETE CONFIRM
══════════════════════════════════════════════════ */
function openConfirmDelete(taskId, taskName) {
  state.pendingDeleteId = taskId;
  $('confirm-desc').textContent = `"${taskName}" will be soft-deleted. You can restore it later from the Activity log.`;
  openModal('confirm-modal');
}

$('confirm-close').addEventListener('click',  () => { closeModal('confirm-modal'); state.pendingDeleteId = null; });
$('confirm-cancel').addEventListener('click', () => { closeModal('confirm-modal'); state.pendingDeleteId = null; });

$('confirm-ok').addEventListener('click', async () => {
  if (!state.pendingDeleteId) return;
  const id = state.pendingDeleteId;
  closeModal('confirm-modal');
  state.pendingDeleteId = null;
  try {
    await api('DELETE', `/tasks/${id}`);
    toast('Task deleted.', 'success');
    await loadTasks();
    await loadProjects();
  } catch(err) {
    toast(err.message, 'error');
  }
});

/* ══════════════════════════════════════════════════
   FILTERS
══════════════════════════════════════════════════ */
let searchTimer = null;
$('search-input').addEventListener('input', e => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    state.filters.search = e.target.value.trim();
    // Reset board columns to trigger re-render
    $('board').innerHTML = '';
    loadTasks();
  }, 350);
});
$('filter-status').addEventListener('change', e => {
  state.filters.status = e.target.value;
  $('board').innerHTML = '';
  loadTasks();
});
$('filter-priority').addEventListener('change', e => {
  state.filters.priority = e.target.value;
  $('board').innerHTML = '';
  loadTasks();
});

/* ══════════════════════════════════════════════════
   ACTIVITY LOG
══════════════════════════════════════════════════ */
async function loadActivity() {
  const list = $('activity-list');
  list.innerHTML = '<li class="activity-loading"><span class="activity-spinner"></span> Loading activity…</li>';
  try {
    const endpoint = state.activeProject
      ? `/projects/${state.activeProject.id}/activity`
      : '/activity/me';
    const res  = await api('GET', endpoint);
    const logs = res.data.logs || [];

    if (!logs.length) {
      list.innerHTML = `<li class="activity-empty">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="32" height="32" aria-hidden="true"><path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"/><path d="M12 8v4M12 16h.01"/></svg>
        No activity yet for this project.
      </li>`;
      return;
    }

    const actionMap = {
      task_created:        'created task',
      task_updated:        'updated task',
      task_deleted:        'deleted task',
      task_restored:       'restored task',
      task_status_changed: 'moved task',
      project_created:     'created project',
      project_updated:     'updated project',
      project_deleted:     'deleted project',
    };

    list.innerHTML = logs.map(log => {
      const label   = actionMap[log.action] || log.action.replace(/_/g, ' ');
      const taskRef = log.task_title ? ` <strong>${escHtml(log.task_title)}</strong>` : '';
      let extra = '';
      try {
        const meta = log.meta ? JSON.parse(log.meta) : null;
        if (log.action === 'task_status_changed' && meta) {
          extra = ` <span class="activity-from">${escHtml(meta.from)}</span>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
            <span class="activity-to">${escHtml(meta.to)}</span>`;
        }
      } catch {}

      return `<li class="activity-item">
        <div class="activity-avatar" style="background:${escHtml(log.avatar_color || '#6366f1')}" aria-hidden="true">
          ${initials(log.user_name || '?')}
        </div>
        <div class="activity-body">
          <p class="activity-text">
            <strong>${escHtml(log.user_name || 'Someone')}</strong> ${label}${taskRef}${extra}
          </p>
          <time class="activity-time" datetime="${escHtml(log.created_at)}" title="${new Date(log.created_at).toLocaleString()}">
            ${timeAgo(log.created_at)}
          </time>
        </div>
      </li>`;
    }).join('');
  } catch(err) {
    list.innerHTML = `<li class="activity-error">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
      ${escHtml(err.message)}
    </li>`;
  }
}

/* ══════════════════════════════════════════════════
   SIDEBAR TOGGLE (mobile)
══════════════════════════════════════════════════ */
const sidebarEl = $('sidebar');
const sidebarOverlay = document.createElement('div');
sidebarOverlay.className = 'sidebar-overlay';
sidebarOverlay.setAttribute('aria-hidden', 'true');
document.body.appendChild(sidebarOverlay);

function openSidebar() {
  sidebarEl.classList.add('open');
  sidebarOverlay.classList.add('active');
  $('sidebar-toggle').setAttribute('aria-expanded', 'true');
}
function closeSidebar() {
  sidebarEl.classList.remove('open');
  sidebarOverlay.classList.remove('active');
  $('sidebar-toggle').setAttribute('aria-expanded', 'false');
}

$('sidebar-toggle').addEventListener('click', openSidebar);
$('sidebar-close').addEventListener('click', closeSidebar);
sidebarOverlay.addEventListener('click', closeSidebar);

/* ══════════════════════════════════════════════════
   NAV
══════════════════════════════════════════════════ */
document.querySelectorAll('.nav-item').forEach(btn => {
  btn.addEventListener('click', () => showView(btn.dataset.view));
});

/* ══════════════════════════════════════════════════
   GLOBAL KEYBOARD
══════════════════════════════════════════════════ */
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') {
    ['task-modal', 'project-modal', 'confirm-modal'].forEach(closeModal);
    closeSidebar();
  }
});

// Close modals when clicking outside
document.querySelectorAll('.modal-overlay').forEach(ov => {
  ov.addEventListener('click', e => { if (e.target === ov) closeModal(ov.id); });
});

/* ══════════════════════════════════════════════════
   BOOT
══════════════════════════════════════════════════ */
initAuth();
