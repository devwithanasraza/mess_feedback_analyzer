/**
 * Admin Shared Controller & Authentication
 * Mess Menu Feedback Analyzer - B.Tech Data Science Project
 */

let currentAdminUser = null;

document.addEventListener('DOMContentLoaded', async () => {
  // If not on login.html, verify session
  if (!window.location.pathname.includes('login.html')) {
    await verifyAdminSession();
  }

  // Setup logout button
  const logoutBtn = document.getElementById('admin-logout-btn');
  if (logoutBtn) {
    logoutBtn.addEventListener('click', handleAdminLogout);
  }

  // Highlight active sidebar item
  highlightActiveSidebar();
});

async function verifyAdminSession() {
  const res = await apiFetch('../api/auth.php?action=check');
  if (!res.authenticated) {
    // Redirect to admin login
    window.location.href = 'login.html';
    return;
  }

  currentAdminUser = res.user;

  // Update admin name & initials in topbar / sidebar
  const adminNameDisplays = document.querySelectorAll('.admin-display-name');
  adminNameDisplays.forEach(el => {
    el.textContent = res.user.name || res.user.username;
  });

  const adminAvatarDisplays = document.querySelectorAll('.admin-avatar');
  adminAvatarDisplays.forEach(el => {
    const initials = (res.user.name || res.user.username).substring(0, 2).toUpperCase();
    el.textContent = initials;
  });
}

async function handleAdminLogout(e) {
  if (e) e.preventDefault();
  if (confirm('Are you sure you want to sign out of the Admin Portal?')) {
    const res = await apiFetch('../api/auth.php?action=logout');
    if (res.success) {
      window.location.href = 'login.html';
    } else {
      showToast('Logout failed. Please try again.', 'error');
    }
  }
}

function highlightActiveSidebar() {
  const currentPath = window.location.pathname;
  const links = document.querySelectorAll('.sidebar-link');
  links.forEach(link => {
    const href = link.getAttribute('href');
    if (href && currentPath.endsWith(href)) {
      link.classList.add('active');
    } else {
      link.classList.remove('active');
    }
  });
}

// Modal helper functions
function openModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) {
    modal.classList.add('active');
  }
}

function closeModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) {
    modal.classList.remove('active');
  }
}
