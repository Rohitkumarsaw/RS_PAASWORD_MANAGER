(function() {
  'use strict';

  document.addEventListener('DOMContentLoaded', function() {
    initTheme();
    initSidebar();
    initUserDropdown();
    initGlobalSearch();
    initToasts();
    initPasswordToggle();
    initPasswordGenerator();
    initPasswordStrength();
    initCopyButtons();
    initModals();
    initConfirmDialogs();
    initKeyboardShortcuts();
    initEntrySearch();
    initFavButtons();
    initBulkActions();
    initTabNavigation();
    initAutoLogout();
  });

  function initTheme() {
    const toggle = document.getElementById('themeToggle');
    if (!toggle) return;
    const saved = localStorage.getItem('pm_theme') || 'dark';
    document.documentElement.setAttribute('data-theme', saved);
    updateThemeIcon(toggle, saved);
    toggle.addEventListener('click', toggleTheme);
  }

  function toggleTheme() {
    const current = document.documentElement.getAttribute('data-theme');
    const next = current === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    localStorage.setItem('pm_theme', next);
    var toggle = document.getElementById('themeToggle');
    if (toggle) updateThemeIcon(toggle, next);
    var meta = document.querySelector('meta[name=theme-color]');
    if (meta) meta.content = next === 'dark' ? 'rgb(10, 14, 28)' : 'rgb(248, 250, 252)';
    var cs = document.querySelector('meta[name=color-scheme]');
    if (cs) cs.content = next;
  }

  function updateThemeIcon(el, theme) {
    const icon = el.querySelector('i');
    if (icon) {
      icon.className = theme === 'dark' ? 'fas fa-moon' : 'fas fa-sun';
    }
  }

  function initSidebar() {
    const toggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    if (!toggle || !sidebar) return;
    toggle.addEventListener('click', function() {
      sidebar.classList.toggle('open');
      if (overlay) overlay.classList.toggle('active');
    });
    if (overlay) {
      overlay.addEventListener('click', function() {
        sidebar.classList.remove('open');
        overlay.classList.remove('active');
      });
    }
    const main = document.querySelector('.main-content');
    if (main) {
      window.addEventListener('resize', function() {
        if (window.innerWidth > 992) {
          sidebar.classList.remove('open');
          if (overlay) overlay.classList.remove('active');
        }
      });
    }
  }

  function initUserDropdown() {
    const btn = document.getElementById('userDropdownBtn');
    const menu = document.getElementById('userDropdownMenu');
    if (!btn || !menu) return;
    btn.addEventListener('click', function(e) {
      e.stopPropagation();
      menu.classList.toggle('active');
    });
    document.addEventListener('click', function(e) {
      if (!btn.contains(e.target) && !menu.contains(e.target)) {
        menu.classList.remove('active');
      }
    });
  }

  function initGlobalSearch() {
    const search = document.getElementById('globalSearch');
    if (!search) return;
    search.addEventListener('keydown', function(e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        const q = this.value.trim();
        if (q) {
          window.location.href = 'vault.php?search=' + encodeURIComponent(q);
        }
      }
    });
  }

  function initToasts() {
    const container = document.createElement('div');
    container.className = 'toast-container';
    container.id = 'toastContainer';
    document.body.appendChild(container);

    window.showToast = function(message, type, duration) {
      type = type || 'info';
      duration = duration || 4000;
      const icons = {
        success: 'fa-check-circle',
        error: 'fa-exclamation-circle',
        info: 'fa-info-circle',
        warning: 'fa-exclamation-triangle'
      };
      const toast = document.createElement('div');
      toast.className = 'toast toast-' + type;
      toast.innerHTML =
        '<i class="fas ' + (icons[type] || icons.info) + ' toast-icon"></i>' +
        '<span class="toast-message">' + escapeHtml(message) + '</span>' +
        '<button class="toast-close" onclick="this.parentElement.classList.add(\'toast-out\');setTimeout(function(){this.parentElement.remove()}.bind(this),350)"><i class="fas fa-times"></i></button>';
      container.appendChild(toast);
      setTimeout(function() {
        toast.classList.add('toast-out');
        setTimeout(function() { if (toast.parentNode) toast.remove(); }, 350);
      }, duration);
    };
  }

  function initPasswordToggle() {
    document.addEventListener('click', function(e) {
      const btn = e.target.closest('.pw-toggle');
      if (!btn) return;
      const input = btn.closest('.input-group') ? btn.closest('.input-group').querySelector('.form-input') : null;
      if (!input) return;
      if (input.type === 'password') {
        input.type = 'text';
        btn.innerHTML = '<i class="fas fa-eye-slash"></i>';
      } else {
        input.type = 'password';
        btn.innerHTML = '<i class="fas fa-eye"></i>';
      }
    });
  }

  function initPasswordGenerator() {
    document.addEventListener('click', function(e) {
      const btn = e.target.closest('.pw-generate-btn');
      if (!btn) return;
      const targetId = btn.getAttribute('data-target');
      const target = document.getElementById(targetId);
      if (!target) return;

      const length = parseInt(document.getElementById('genLength') ? document.getElementById('genLength').value : 20);
      const useUpper = document.getElementById('genUpper') ? document.getElementById('genUpper').checked : true;
      const useLower = document.getElementById('genLower') ? document.getElementById('genLower').checked : true;
      const useDigits = document.getElementById('genDigits') ? document.getElementById('genDigits').checked : true;
      const useSymbols = document.getElementById('genSymbols') ? document.getElementById('genSymbols').checked : true;

      const chars = [];
      if (useUpper) chars.push('ABCDEFGHIJKLMNOPQRSTUVWXYZ');
      if (useLower) chars.push('abcdefghijklmnopqrstuvwxyz');
      if (useDigits) chars.push('0123456789');
      if (useSymbols) chars.push('!@#$%^&*()-_=+[]{}|;:,.<>?');

      if (chars.length === 0) {
        showToast('Select at least one character type', 'warning');
        return;
      }

      const allChars = chars.join('');
      let password = '';
      for (let i = 0; i < length; i++) {
        password += allChars.charAt(Math.floor(Math.random() * allChars.length));
      }

      if (useUpper) {
        const idx = Math.floor(Math.random() * length);
        const uc = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        password = password.substring(0, idx) + uc.charAt(Math.floor(Math.random() * 26)) + password.substring(idx + 1);
      }

      password = password.split('').sort(function() { return Math.random() - 0.5; }).join('');

      target.value = password;
      target.type = 'text';
      if (typeof updatePasswordStrength === 'function') {
        updatePasswordStrength(password);
      }
      if (typeof updateStrengthDisplay === 'function') {
        updateStrengthDisplay(password);
      }
    });

    const lengthSlider = document.getElementById('genLength');
    const lengthDisplay = document.getElementById('genLengthDisplay');
    if (lengthSlider && lengthDisplay) {
      lengthSlider.addEventListener('input', function() {
        lengthDisplay.textContent = this.value;
      });
    }
  }

  function initPasswordStrength() {
    document.addEventListener('input', function(e) {
      const el = e.target.closest('[data-strength="true"]');
      if (!el) return;
      const password = el.value;
      updateStrengthDisplay(password, el);
    });
  }

  window.updateStrengthDisplay = function(password, input) {
    const container = input ? input.closest('.form-group') || input.parentElement : document;
    const bar = container.querySelector ? container.querySelector('.strength-bar-fill') : null;
    const label = container.querySelector ? container.querySelector('.strength-label') : null;
    if (!bar && !label) return;

    const result = evaluatePasswordStrength(password);
    const score = result.score;
    const feedback = result.feedback[0] || '';

    const colors = {
      veryweak: 'rgb(239, 68, 68)',
      weak: 'rgb(251, 146, 60)',
      fair: 'rgb(234, 179, 8)',
      strong: 'rgb(34, 197, 94)',
      verystrong: 'rgb(34, 197, 94)'
    };

    let level = 'veryweak';
    let color = colors.veryweak;
    if (score >= 90) { level = 'verystrong'; color = colors.verystrong; }
    else if (score >= 70) { level = 'strong'; color = colors.strong; }
    else if (score >= 50) { level = 'fair'; color = colors.fair; }
    else if (score >= 30) { level = 'weak'; color = colors.weak; }

    if (bar) {
      bar.style.width = score + '%';
      bar.style.background = color;
    }
    if (label) {
      const names = { veryweak: 'Very Weak', weak: 'Weak', fair: 'Fair', strong: 'Strong', verystrong: 'Very Strong' };
      label.textContent = names[level] + ' (' + score + '/100)';
      label.style.color = color;
    }
  };

  function evaluatePasswordStrength(password) {
    let score = 0;
    const feedback = [];
    if (password.length >= 8) score += 20;
    if (password.length >= 12) score += 10;
    if (password.length >= 16) score += 10;
    if (/[A-Z]/.test(password)) score += 15;
    if (/[a-z]/.test(password)) score += 15;
    if (/[0-9]/.test(password)) score += 15;
    if (/[^a-zA-Z0-9]/.test(password)) score += 15;
    if (score < 30) feedback.push('Very weak - add more characters and variety');
    else if (score < 50) feedback.push('Weak - include uppercase, numbers, and symbols');
    else if (score < 70) feedback.push('Fair - consider making it longer');
    else if (score < 90) feedback.push('Strong');
    else feedback.push('Very strong');
    return { score: Math.min(100, score), feedback: feedback };
  }

  function initCopyButtons() {
    document.addEventListener('click', function(e) {
      const btn = e.target.closest('.copy-btn');
      if (!btn) return;
      const targetId = btn.getAttribute('data-copy');
      const target = document.getElementById(targetId);
      if (!target) return;
      const text = target.value || target.textContent || '';
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(function() {
          showToast('Copied to clipboard!', 'success');
        }).catch(function() {
          fallbackCopy(text);
        });
      } else {
        fallbackCopy(text);
      }
    });
  }

  function fallbackCopy(text) {
    const ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.left = '-9999px';
    document.body.appendChild(ta);
    ta.select();
    try {
      document.execCommand('copy');
      showToast('Copied to clipboard!', 'success');
    } catch (e) {
      showToast('Failed to copy', 'error');
    }
    document.body.removeChild(ta);
  }

  function initModals() {
    document.addEventListener('click', function(e) {
      const btn = e.target.closest('[data-modal]');
      if (!btn) return;
      const modalId = btn.getAttribute('data-modal');
      const modal = document.getElementById(modalId);
      if (modal) openModal(modal);
    });

    document.addEventListener('click', function(e) {
      const btn = e.target.closest('.modal-close');
      if (!btn) return;
      const modal = btn.closest('.modal-overlay');
      if (modal) closeModal(modal);
    });

    document.addEventListener('click', function(e) {
      const overlay = e.target.closest('.modal-overlay');
      if (overlay && e.target === overlay) {
        closeModal(overlay);
      }
    });

    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
        const activeModal = document.querySelector('.modal-overlay.active');
        if (activeModal) closeModal(activeModal);
      }
    });
  }

  window.openModal = function(modal) {
    if (typeof modal === 'string') modal = document.getElementById(modal);
    if (!modal) return;
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
  };

  window.closeModal = function(modal) {
    if (typeof modal === 'string') modal = document.getElementById(modal);
    if (!modal) return;
    modal.classList.remove('active');
    document.body.style.overflow = '';
  };

  function initConfirmDialogs() {
    document.addEventListener('click', function(e) {
      const btn = e.target.closest('[data-confirm]');
      if (!btn) return;
      e.preventDefault();
      const message = btn.getAttribute('data-confirm') || 'Are you sure?';
      const confirmText = btn.getAttribute('data-confirm-text') || 'Confirm';
      const cancelText = btn.getAttribute('data-cancel-text') || 'Cancel';
      const action = btn.getAttribute('data-action') || btn.getAttribute('href') || '';
      const title = btn.getAttribute('data-confirm-title') || 'Confirm Action';

      showConfirmDialog(message, title, confirmText, cancelText, function() {
        if (action) {
          if (btn.tagName === 'A') {
            window.location.href = action;
          } else if (btn.tagName === 'BUTTON' || btn.tagName === 'INPUT') {
            if (btn.form) btn.form.submit();
          }
        }
      });
    });
  }

  window.showConfirmDialog = function(message, title, confirmText, cancelText, onConfirm) {
    title = title || 'Confirm Action';
    confirmText = confirmText || 'Confirm';
    cancelText = cancelText || 'Cancel';

    const overlay = document.createElement('div');
    overlay.className = 'modal-overlay active';
    overlay.innerHTML =
      '<div class="modal" style="max-width:400px">' +
        '<div class="modal-body">' +
          '<div class="confirm-dialog">' +
            '<div class="confirm-dialog-icon danger"><i class="fas fa-exclamation-triangle"></i></div>' +
            '<h3>' + escapeHtml(title) + '</h3>' +
            '<p>' + escapeHtml(message) + '</p>' +
            '<div style="display:flex;gap:10px;justify-content:center">' +
              '<button class="btn btn-secondary" id="confirmCancel">' + escapeHtml(cancelText) + '</button>' +
              '<button class="btn btn-danger" id="confirmOk">' + escapeHtml(confirmText) + '</button>' +
            '</div>' +
          '</div>' +
        '</div>' +
      '</div>';
    document.body.appendChild(overlay);

    document.getElementById('confirmOk').addEventListener('click', function() {
      overlay.remove();
      if (typeof onConfirm === 'function') onConfirm();
    });
    document.getElementById('confirmCancel').addEventListener('click', function() {
      overlay.remove();
    });
    overlay.addEventListener('click', function(e) {
      if (e.target === overlay) overlay.remove();
    });
  };

  function initKeyboardShortcuts() {
    document.addEventListener('keydown', function(e) {
      if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        const search = document.getElementById('globalSearch');
        if (search) search.focus();
      }
      if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
        e.preventDefault();
        const addBtn = document.querySelector('[data-modal="addEntryModal"], .add-entry-btn');
        if (addBtn) addBtn.click();
      }
    });
  }

  function initEntrySearch() {
    const searchInput = document.getElementById('entrySearch');
    if (!searchInput) return;
    let timer = null;
    searchInput.addEventListener('input', function() {
      clearTimeout(timer);
      timer = setTimeout(function() {
        const q = searchInput.value.trim();
        if (q.length >= 2 || q.length === 0) {
          const url = new URL(window.location.href);
          if (q) url.searchParams.set('search', q);
          else url.searchParams.delete('search');
          url.searchParams.set('page', '1');
          window.location.href = url.toString();
        }
      }, 400);
    });
  }

  function initFavButtons() {
    document.addEventListener('click', function(e) {
      const btn = e.target.closest('.fav-btn');
      if (!btn) return;
      e.preventDefault();
      const entryId = btn.getAttribute('data-id');
      if (!entryId) return;
      const isFav = btn.getAttribute('data-fav') === '1';
      toggleFavorite(entryId, !isFav, btn);
    });
  }

  function toggleFavorite(id, fav, btn) {
    fetch('api/entries.php?action=toggle_favorite', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: id, favorite: fav })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (data.success) {
        btn.setAttribute('data-fav', fav ? '1' : '0');
        btn.innerHTML = fav ? '<i class="fas fa-star" style="color:rgb(251,191,36)"></i>' : '<i class="far fa-star"></i>';
        showToast(fav ? 'Added to favorites' : 'Removed from favorites', 'success');
      }
    })
    .catch(function(err) {
      showToast('Failed to update favorite', 'error');
    });
  }

  function initBulkActions() {
    const selectAll = document.getElementById('selectAll');
    if (selectAll) {
      selectAll.addEventListener('change', function() {
        const cbs = document.querySelectorAll('.entry-checkbox');
        cbs.forEach(function(cb) { cb.checked = selectAll.checked; });
      });
    }
  }

  function initTabNavigation() {
    document.addEventListener('click', function(e) {
      const tab = e.target.closest('[data-tab]');
      if (!tab) return;
      const tabId = tab.getAttribute('data-tab');
      const container = tab.closest('[data-tabs]') || tab.parentElement;
      container.querySelectorAll('.tab').forEach(function(t) { t.classList.remove('active'); });
      tab.classList.add('active');
      const content = document.getElementById(tabId);
      if (content) {
        const parent = content.parentElement;
        parent.querySelectorAll('.tab-content').forEach(function(c) { c.style.display = 'none'; });
        content.style.display = 'block';
      }
    });
  }

  function initAutoLogout() {
    let timeout = null;
    const resetTimer = function() {
      if (timeout) clearTimeout(timeout);
      timeout = setTimeout(function() {
        fetch('api/auth.php?action=ping').then(function(r) { return r.json(); }).then(function(d) {
          if (!d.logged_in) {
            window.location.href = 'login.php?timeout=1';
          }
        });
      }, 60000);
    };
    document.addEventListener('mousemove', resetTimer);
    document.addEventListener('keydown', resetTimer);
    document.addEventListener('click', resetTimer);
    resetTimer();
  }

  function escapeHtml(str) {
    if (!str) return '';
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
  }
})();
