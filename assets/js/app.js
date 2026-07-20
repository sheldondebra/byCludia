(() => {
  const ICONS = {
    success: '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 13l4 4L19 7"/></svg>',
    error: '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 18L18 6M6 6l12 12"/></svg>',
    info: '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M13 16h-1v-4h-1m1-4h.01M12 20a8 8 0 100-16 8 8 0 000 16z"/></svg>',
  };

  const TITLES = {
    success: 'Done',
    error: 'Oops',
    info: 'Note',
  };

  function ensureRoot() {
    let root = document.getElementById('toast-root');
    if (!root) {
      root = document.createElement('div');
      root.id = 'toast-root';
      root.className = 'toast-root';
      root.setAttribute('aria-live', 'polite');
      document.body.appendChild(root);
    }
    return root;
  }

  window.toast = function toast(message, options = {}) {
    const type = ['success', 'error', 'info'].includes(options.type) ? options.type : 'success';
    const title = options.title || TITLES[type];
    const duration = typeof options.duration === 'number' ? options.duration : (type === 'error' ? 4200 : 3200);
    const root = ensureRoot();

    const el = document.createElement('div');
    el.className = `toast toast--${type}`;
    el.setAttribute('role', type === 'error' ? 'alert' : 'status');
    el.innerHTML = `
      <span class="toast__icon">${ICONS[type]}</span>
      <div class="toast__body">
        <p class="toast__title">${title}</p>
        <p class="toast__msg"></p>
      </div>
      <button type="button" class="toast__close" aria-label="Dismiss">
        <svg class="w-3.5 h-3.5 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 6l12 12M18 6L6 18"/></svg>
      </button>
    `;
    el.querySelector('.toast__msg').textContent = message || '';

    const dismiss = () => {
      if (el.classList.contains('is-leaving')) return;
      el.classList.add('is-leaving');
      setTimeout(() => el.remove(), 280);
    };

    el.querySelector('.toast__close').addEventListener('click', dismiss);
    root.appendChild(el);

    // Keep stack tidy
    while (root.children.length > 4) {
      root.firstElementChild.remove();
    }

    if (duration > 0) {
      setTimeout(dismiss, duration);
    }

    return { dismiss };
  };

  window.toast.success = (message, opts = {}) => toast(message, { ...opts, type: 'success' });
  window.toast.error = (message, opts = {}) => toast(message, { ...opts, type: 'error' });
  window.toast.info = (message, opts = {}) => toast(message, { ...opts, type: 'info' });
})();

document.addEventListener('DOMContentLoaded', () => {
  // Server flash → toast
  if (window.APP && Array.isArray(window.APP.toasts)) {
    window.APP.toasts.forEach((t, i) => {
      setTimeout(() => {
        window.toast(t.message, { type: t.type || 'info' });
      }, i * 120);
    });
  }

  const menuBtn = document.getElementById('mobile-menu-btn');
  const menu = document.getElementById('mobile-menu');
  if (menuBtn && menu) {
    menuBtn.addEventListener('click', () => menu.classList.toggle('hidden'));
  }

  const reveals = document.querySelectorAll('.reveal');
  if ('IntersectionObserver' in window) {
    const io = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add('visible');
          io.unobserve(entry.target);
        }
      });
    }, { threshold: 0.15 });
    reveals.forEach((el) => io.observe(el));
  } else {
    reveals.forEach((el) => el.classList.add('visible'));
  }

  const bumpCart = (data) => {
    const badge = document.getElementById('cart-count');
    if (badge && typeof data.count !== 'undefined') badge.textContent = data.count;
    const totalEl = document.getElementById('cart-total');
    if (totalEl && data.subtotal) totalEl.textContent = data.subtotal;
  };

  document.querySelectorAll('[data-add-to-cart]').forEach((form) => {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(form);
      fd.append('csrf_token', window.APP.csrf);
      const btn = form.querySelector('button[type="submit"]');
      if (btn) {
        btn.disabled = true;
        btn.textContent = 'Adding…';
      }
      try {
        const res = await fetch(`${window.APP.baseUrl}/api/cart.php`, {
          method: 'POST',
          body: fd,
          credentials: 'same-origin'
        });
        const data = await res.json();
        if (!data.ok) throw new Error(data.error || 'Could not add to cart');
        bumpCart(data);
        window.toast.success('Added to your bag', { title: 'Cart' });
        if (btn) btn.textContent = 'Added ✓';
        setTimeout(() => {
          if (btn) {
            btn.disabled = false;
            btn.textContent = 'Add to Cart';
          }
        }, 1200);
      } catch (err) {
        window.toast.error(err.message || 'Could not add to cart');
        if (btn) {
          btn.disabled = false;
          btn.textContent = 'Add to Cart';
        }
      }
    });
  });

  const newsletter = document.getElementById('footer-newsletter');
  if (newsletter) {
    newsletter.addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(newsletter);
      try {
        const res = await fetch(newsletter.action, { method: 'POST', body: fd, credentials: 'same-origin' });
        const data = await res.json();
        if (!data.ok) throw new Error(data.message || data.error || 'Something went wrong');
        window.toast.success(data.message || "You're on the list", { title: 'Subscribed' });
        newsletter.reset();
      } catch (err) {
        window.toast.error(err.message || 'Something went wrong');
      }
    });
  }

  const quickAdd = async (productId, variantId) => {
    const fd = new FormData();
    fd.append('csrf_token', window.APP.csrf);
    fd.append('action', 'add');
    fd.append('product_id', productId);
    fd.append('variant_id', variantId);
    fd.append('quantity', 1);
    const res = await fetch(`${window.APP.baseUrl}/api/cart.php`, { method: 'POST', body: fd, credentials: 'same-origin' });
    const data = await res.json();
    if (!data.ok) throw new Error(data.error || 'Could not add to cart');
    bumpCart(data);
    return data;
  };

  document.querySelectorAll('[data-quick-add]').forEach((btn) => {
    btn.addEventListener('click', async (e) => {
      e.preventDefault();
      e.stopPropagation();
      const original = btn.textContent;
      btn.disabled = true;
      btn.textContent = 'Adding…';
      try {
        await quickAdd(btn.getAttribute('data-quick-add'), btn.getAttribute('data-variant'));
        window.toast.success('Added to your bag', { title: 'Cart' });
        btn.textContent = 'Added ✓';
        setTimeout(() => { btn.disabled = false; btn.textContent = original; }, 1200);
      } catch (err) {
        window.toast.error(err.message || 'Could not add to cart');
        btn.disabled = false;
        btn.textContent = original;
      }
    });
  });

  document.querySelectorAll('[data-buy-now]').forEach((btn) => {
    btn.addEventListener('click', async (e) => {
      e.preventDefault();
      e.stopPropagation();
      btn.disabled = true;
      btn.textContent = 'Please wait…';
      try {
        await quickAdd(btn.getAttribute('data-buy-now'), btn.getAttribute('data-variant'));
        window.toast.success('Taking you to checkout…', { title: 'Cart' });
        setTimeout(() => {
          window.location = `${window.APP.baseUrl}/index.php?page=cart`;
        }, 450);
      } catch (err) {
        window.toast.error(err.message || 'Could not add to cart');
        btn.disabled = false;
        btn.textContent = 'Buy Now';
      }
    });
  });

  document.querySelectorAll('[data-wishlist-toggle]').forEach((btn) => {
    btn.addEventListener('click', async (e) => {
      e.preventDefault();
      e.stopPropagation();
      const fd = new FormData();
      fd.append('csrf_token', window.APP.csrf);
      fd.append('product_id', btn.getAttribute('data-wishlist-toggle'));
      try {
        const res = await fetch(`${window.APP.baseUrl}/api/wishlist.php`, { method: 'POST', body: fd, credentials: 'same-origin' });
        const data = await res.json();
        if (data.login_required) {
          window.toast.info('Sign in to save favourites', { title: 'Wishlist' });
          setTimeout(() => {
            window.location = `${window.APP.baseUrl}/index.php?page=login`;
          }, 700);
          return;
        }
        if (!data.ok) throw new Error(data.error || 'Could not update favourites');
        const svg = btn.querySelector('svg');
        btn.setAttribute('aria-pressed', data.active ? 'true' : 'false');
        btn.classList.toggle('text-rose-500', data.active);
        btn.classList.toggle('text-brand-ink', !data.active);
        if (svg) svg.setAttribute('fill', data.active ? 'currentColor' : 'none');
        const wlBadge = document.getElementById('wishlist-count');
        if (wlBadge && typeof data.count === 'number') {
          wlBadge.textContent = data.count;
          wlBadge.classList.toggle('hidden', data.count === 0);
        }
        window.toast.success(data.active ? 'Saved to favourites' : 'Removed from favourites', { title: 'Wishlist' });
      } catch (err) {
        window.toast.error(err.message || 'Could not update favourites');
      }
    });
  });

  const COMPARE_KEY = 'cd_compare';
  const COMPARE_MAX = 4;
  const getCompare = () => {
    try { return JSON.parse(localStorage.getItem(COMPARE_KEY) || '[]').map(Number).filter(Boolean); }
    catch (e) { return []; }
  };
  const setCompare = (ids) => localStorage.setItem(COMPARE_KEY, JSON.stringify(ids.slice(0, COMPARE_MAX)));

  const refreshCompareBadge = () => {
    const badge = document.getElementById('compare-count');
    if (!badge) return;
    const n = getCompare().length;
    badge.textContent = n;
    badge.classList.toggle('hidden', n === 0);
  };

  const refreshCompareButtons = () => {
    const ids = getCompare();
    document.querySelectorAll('[data-compare-toggle]').forEach((btn) => {
      const id = Number(btn.getAttribute('data-compare-toggle'));
      const active = ids.includes(id);
      btn.classList.toggle('bg-brand-ink', active);
      btn.classList.toggle('text-white', active);
      btn.classList.toggle('bg-white/90', !active);
      btn.title = active ? 'Remove from compare' : 'Add to compare';
    });
  };

  document.querySelectorAll('[data-compare-toggle]').forEach((btn) => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      const id = Number(btn.getAttribute('data-compare-toggle'));
      let ids = getCompare();
      if (ids.includes(id)) {
        ids = ids.filter((x) => x !== id);
        setCompare(ids);
        refreshCompareBadge();
        refreshCompareButtons();
        window.toast.info('Removed from compare', { title: 'Compare' });
      } else {
        if (ids.length >= COMPARE_MAX) {
          window.toast.error(`You can compare up to ${COMPARE_MAX} items`, { title: 'Compare' });
          return;
        }
        ids.push(id);
        setCompare(ids);
        refreshCompareBadge();
        refreshCompareButtons();
        window.toast.success('Added to compare', { title: 'Compare' });
      }
    });
  });

  refreshCompareBadge();
  refreshCompareButtons();

  const comparePage = document.querySelector('[data-compare-page]');
  if (comparePage) {
    const ids = getCompare();
    const params = new URLSearchParams(window.location.search);
    const urlIds = (params.get('ids') || '').split(',').map(Number).filter(Boolean);
    const sameSet = urlIds.length === ids.length && urlIds.every((x) => ids.includes(x));
    if (!sameSet) {
      if (ids.length) {
        params.set('ids', ids.join(','));
        window.location.search = params.toString();
      } else if (urlIds.length) {
        params.delete('ids');
        window.location.search = params.toString();
      }
    }
    comparePage.querySelectorAll('[data-compare-remove]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const id = Number(btn.getAttribute('data-compare-remove'));
        setCompare(getCompare().filter((x) => x !== id));
        const left = getCompare();
        const p = new URLSearchParams(window.location.search);
        if (left.length) { p.set('ids', left.join(',')); } else { p.delete('ids'); }
        window.location.search = p.toString();
      });
    });
  }

  const slides = document.querySelectorAll('[data-testimonial]');
  const dots = document.querySelectorAll('[data-testimonial-dot]');
  if (slides.length > 1) {
    let i = 0;
    const show = (n) => {
      slides.forEach((s, idx) => s.classList.toggle('hidden', idx !== n));
      dots.forEach((d, idx) => d.classList.toggle('bg-brand-ink', idx === n));
      dots.forEach((d, idx) => d.classList.toggle('bg-brand-ink/20', idx !== n));
    };
    dots.forEach((d, idx) => d.addEventListener('click', () => { i = idx; show(i); }));
    setInterval(() => { i = (i + 1) % slides.length; show(i); }, 5500);
  }

  // Homepage newsletter form
  const homeNewsletter = document.getElementById('home-newsletter');
  if (homeNewsletter) {
    homeNewsletter.addEventListener('submit', async (e) => {
      e.preventDefault();
      const form = e.target;
      const btn = form.querySelector('button[type="submit"], button:not([type])');
      if (btn) { btn.disabled = true; btn.textContent = 'Joining…'; }
      try {
        const res = await fetch(form.action, { method: 'POST', body: new FormData(form), credentials: 'same-origin' });
        const data = await res.json();
        if (!data.ok) throw new Error(data.message || data.error || 'Something went wrong');
        window.toast.success(data.message || "You're on the list", { title: 'Subscribed' });
        form.reset();
      } catch (err) {
        window.toast.error(err.message || 'Something went wrong');
      } finally {
        if (btn) { btn.disabled = false; btn.textContent = 'Subscribe'; }
      }
    });
  }
});
