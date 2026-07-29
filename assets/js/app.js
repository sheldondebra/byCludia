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

  const heroVideo = document.querySelector('[data-hero-video]');
  if (heroVideo) {
    const iframe = heroVideo.querySelector('iframe[data-src]');
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      heroVideo.remove();
    } else if (iframe) {
      // Portrait Vimeo source (~240×376) — size iframe to cover the hero
      const VIDEO_ASPECT = 240 / 376;
      const coverHeroVideo = () => {
        const { width: cw, height: ch } = heroVideo.getBoundingClientRect();
        if (!cw || !ch) return;
        let w;
        let h;
        if (cw / ch > VIDEO_ASPECT) {
          w = cw;
          h = cw / VIDEO_ASPECT;
        } else {
          h = ch;
          w = ch * VIDEO_ASPECT;
        }
        const pad = 1.08; // crop player chrome / rounding gaps
        iframe.style.width = `${Math.ceil(w * pad)}px`;
        iframe.style.height = `${Math.ceil(h * pad)}px`;
      };

      iframe.src = iframe.dataset.src;
      coverHeroVideo();
      window.addEventListener('resize', coverHeroVideo);
      if (typeof ResizeObserver !== 'undefined') {
        new ResizeObserver(coverHeroVideo).observe(heroVideo);
      }
    }
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
    const previewBody = document.getElementById('cart-preview-body');
    if (previewBody && typeof data.preview_html === 'string') {
      previewBody.innerHTML = data.preview_html;
    }
  };

  document.querySelectorAll('[data-add-to-cart]').forEach((form) => {
    const addToCart = async (buyNow = false) => {
      const fd = new FormData(form);
      fd.append('csrf_token', window.APP.csrf);
      const submitBtn = form.querySelector('button[type="submit"]');
      const buyBtn = form.querySelector('[data-product-buy-now]');
      const activeBtn = buyNow ? buyBtn : submitBtn;
      const idleLabel = buyNow ? 'Buy Now' : 'Add to Cart';
      if (activeBtn) {
        activeBtn.disabled = true;
        activeBtn.textContent = buyNow ? 'Please wait…' : 'Adding…';
      }
      if (submitBtn && buyNow) submitBtn.disabled = true;
      if (buyBtn && !buyNow) buyBtn.disabled = true;
      try {
        const res = await fetch(`${window.APP.baseUrl}/api/cart.php`, {
          method: 'POST',
          body: fd,
          credentials: 'same-origin'
        });
        const data = await res.json();
        if (!data.ok) throw new Error(data.error || 'Could not add to cart');
        bumpCart(data);
        if (buyNow) {
          window.toast.success('Taking you to checkout…', { title: 'Cart' });
          setTimeout(() => {
            window.location = `${window.APP.baseUrl}/checkout`;
          }, 350);
          return;
        }
        window.toast.success('Added to your bag', { title: 'Cart' });
        if (activeBtn) activeBtn.textContent = 'Added ✓';
        setTimeout(() => {
          if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Add to Cart';
          }
          if (buyBtn) {
            buyBtn.disabled = false;
            buyBtn.textContent = 'Buy Now';
          }
        }, 1200);
      } catch (err) {
        window.toast.error(err.message || 'Could not add to cart');
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = 'Add to Cart';
        }
        if (buyBtn) {
          buyBtn.disabled = false;
          buyBtn.textContent = 'Buy Now';
        }
      }
    };

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      await addToCart(false);
    });

    const buyNowBtn = form.querySelector('[data-product-buy-now]');
    if (buyNowBtn) {
      buyNowBtn.addEventListener('click', async (e) => {
        e.preventDefault();
        await addToCart(true);
      });
    }
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

  const setBtnLabel = (btn, text) => {
    const label = btn.querySelector('[data-btn-label]');
    if (label) label.textContent = text;
    else btn.textContent = text;
  };
  const getBtnLabel = (btn) => {
    const label = btn.querySelector('[data-btn-label]');
    return label ? label.textContent : btn.textContent;
  };

  document.querySelectorAll('[data-quick-add]').forEach((btn) => {
    btn.addEventListener('click', async (e) => {
      e.preventDefault();
      e.stopPropagation();
      const original = getBtnLabel(btn);
      btn.disabled = true;
      setBtnLabel(btn, 'Adding…');
      try {
        await quickAdd(btn.getAttribute('data-quick-add'), btn.getAttribute('data-variant'));
        window.toast.success('Added to your bag', { title: 'Cart' });
        setBtnLabel(btn, 'Added ✓');
        setTimeout(() => { btn.disabled = false; setBtnLabel(btn, original); }, 1200);
      } catch (err) {
        window.toast.error(err.message || 'Could not add to cart');
        btn.disabled = false;
        setBtnLabel(btn, original);
      }
    });
  });

  document.querySelectorAll('[data-buy-now]').forEach((btn) => {
    btn.addEventListener('click', async (e) => {
      e.preventDefault();
      e.stopPropagation();
      const original = getBtnLabel(btn) || 'Buy Now';
      btn.disabled = true;
      setBtnLabel(btn, 'Please wait…');
      try {
        await quickAdd(btn.getAttribute('data-buy-now'), btn.getAttribute('data-variant'));
        window.toast.success('Taking you to checkout…', { title: 'Cart' });
        setTimeout(() => {
          window.location = `${window.APP.baseUrl}/checkout`;
        }, 450);
      } catch (err) {
        window.toast.error(err.message || 'Could not add to cart');
        btn.disabled = false;
        setBtnLabel(btn, original);
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
        if (!data.ok) throw new Error(data.error || 'Could not update favourites');
        const svg = btn.querySelector('svg');
        const label = btn.querySelector('[data-wishlist-label]');
        btn.setAttribute('aria-pressed', data.active ? 'true' : 'false');
        btn.classList.toggle('text-rose-500', data.active);
        btn.classList.toggle('border-rose-200', data.active);
        btn.classList.toggle('text-brand-ink', !data.active);
        if (svg) svg.setAttribute('fill', data.active ? 'currentColor' : 'none');
        if (label) label.textContent = data.active ? 'Wishlisted' : 'Wishlist';
        btn.title = data.active ? 'Remove from wishlist' : 'Add to wishlist';
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
      const label = btn.querySelector('[data-compare-label]');
      btn.classList.toggle('bg-brand-ink', active);
      btn.classList.toggle('text-white', active);
      btn.classList.toggle('bg-white/90', !active);
      btn.classList.toggle('border-brand-ink', active);
      btn.title = active ? 'Remove from compare' : 'Add to compare';
      btn.setAttribute('aria-pressed', active ? 'true' : 'false');
      if (label) label.textContent = active ? 'In compare' : 'Compare';
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

  // Auth email / phone toggle
  document.querySelectorAll('[data-auth-form]').forEach((root) => {
    const modeInput = root.querySelector('[data-auth-mode-input]');
    const buttons = root.querySelectorAll('[data-auth-mode]');
    const panels = {
      email: root.querySelector('[data-auth-panel="email"]'),
      phone: root.querySelector('[data-auth-panel="phone"]'),
    };

    const setMode = (mode) => {
      const next = mode === 'phone' ? 'phone' : 'email';
      if (modeInput) modeInput.value = next;
      buttons.forEach((btn) => {
        const active = btn.getAttribute('data-auth-mode') === next;
        btn.classList.toggle('is-active', active);
        btn.setAttribute('aria-selected', active ? 'true' : 'false');
      });
      Object.entries(panels).forEach(([key, panel]) => {
        if (!panel) return;
        const show = key === next;
        panel.hidden = !show;
        const input = panel.querySelector('input');
        if (input) {
          if (show) input.setAttribute('required', 'required');
          else input.removeAttribute('required');
        }
      });
      const focusEl = panels[next]?.querySelector('input');
      if (focusEl && document.activeElement?.hasAttribute?.('data-auth-mode')) {
        focusEl.focus();
      }
    };

    buttons.forEach((btn) => {
      btn.addEventListener('click', () => setMode(btn.getAttribute('data-auth-mode')));
    });
  });

  // Password show / hide
  document.querySelectorAll('[data-password-toggle]').forEach((btn) => {
    const wrap = btn.closest('.password-field');
    const input = wrap?.querySelector('[data-password-input], input[type="password"], input[type="text"]');
    if (!wrap || !input) return;

    btn.addEventListener('click', () => {
      const show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      wrap.classList.toggle('is-visible', show);
      btn.setAttribute('aria-pressed', show ? 'true' : 'false');
      btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    });
  });

  // Password strength + confirm match
  const strengthSource = document.querySelector('[data-password-strength-source]');
  const strengthBox = document.querySelector('[data-password-strength]');
  const strengthLabel = document.querySelector('[data-password-strength-label]');
  const confirmInput = document.querySelector('[data-password-confirm]');
  const matchMsg = document.querySelector('[data-password-match-msg]');

  const scorePassword = (value) => {
    let score = 0;
    if (value.length >= 8) score += 1;
    if (value.length >= 12) score += 1;
    if (/[a-z]/.test(value) && /[A-Z]/.test(value)) score += 1;
    if (/\d/.test(value)) score += 1;
    if (/[^A-Za-z0-9]/.test(value)) score += 1;

    if (score <= 1) return { level: 'weak', label: 'Weak — add length and variety' };
    if (score === 2) return { level: 'fair', label: 'Fair — getting better' };
    if (score === 3) return { level: 'good', label: 'Good — almost there' };
    return { level: 'strong', label: 'Strong password' };
  };

  const updateStrength = () => {
    if (!strengthSource || !strengthBox || !strengthLabel) return;
    const value = strengthSource.value || '';
    if (!value) {
      strengthBox.hidden = true;
      strengthBox.removeAttribute('data-level');
      strengthLabel.textContent = '';
      return;
    }
    const result = scorePassword(value);
    strengthBox.hidden = false;
    strengthBox.setAttribute('data-level', result.level);
    strengthLabel.textContent = result.label;
  };

  const updateMatch = () => {
    if (!strengthSource || !confirmInput || !matchMsg) return;
    const confirmValue = confirmInput.value || '';
    if (!confirmValue) {
      matchMsg.hidden = true;
      confirmInput.setCustomValidity('');
      return;
    }
    const matches = strengthSource.value === confirmValue;
    matchMsg.hidden = matches;
    confirmInput.setCustomValidity(matches ? '' : 'Passwords do not match');
  };

  if (strengthSource) {
    strengthSource.addEventListener('input', () => {
      updateStrength();
      updateMatch();
    });
    updateStrength();
  }
  if (confirmInput) {
    confirmInput.addEventListener('input', updateMatch);
  }

  // Account tabs + expandable orders
  const accountTabs = document.querySelector('[data-account-tabs]');
  if (accountTabs) {
    const tabButtons = accountTabs.querySelectorAll('[data-account-tab]');
    const panels = accountTabs.querySelectorAll('[data-account-panel]');

    const activateTab = (name) => {
      tabButtons.forEach((btn) => {
        const active = btn.getAttribute('data-account-tab') === name;
        btn.classList.toggle('is-active', active);
        btn.setAttribute('aria-selected', active ? 'true' : 'false');
      });
      panels.forEach((panel) => {
        const active = panel.getAttribute('data-account-panel') === name;
        panel.classList.toggle('is-active', active);
        panel.hidden = !active;
      });
      const url = new URL(window.location.href);
      url.searchParams.set('tab', name);
      window.history.replaceState({}, '', url);
    };

    tabButtons.forEach((btn) => {
      btn.addEventListener('click', () => activateTab(btn.getAttribute('data-account-tab')));
    });
  }

  document.querySelectorAll('[data-account-order]').forEach((order) => {
    const toggle = order.querySelector('[data-account-order-toggle]');
    const details = order.querySelector('.account-order__details');
    if (!toggle || !details) return;

    toggle.addEventListener('click', () => {
      const open = !order.classList.contains('is-open');
      order.classList.toggle('is-open', open);
      details.hidden = !open;
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  });

  // Lookbook: slow auto-scroll + arrow controls
  document.querySelectorAll('[data-lookbook]').forEach((root) => {
    const rail = root.querySelector('[data-lookbook-rail]');
    const prev = root.querySelector('[data-lookbook-prev]');
    const next = root.querySelector('[data-lookbook-next]');
    if (!rail) return;

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const speed = 0.35; // px per frame — slow crawl
    let paused = false;
    let resumeTimer = null;
    let raf = 0;

    const maxScroll = () => Math.max(0, rail.scrollWidth - rail.clientWidth);

    const syncArrows = () => {
      const max = maxScroll();
      const atStart = rail.scrollLeft <= 2;
      const atEnd = rail.scrollLeft >= max - 2;
      if (prev) prev.disabled = atStart;
      if (next) next.disabled = atEnd || max <= 0;
    };

    const pauseTemporarily = (ms = 2800) => {
      paused = true;
      if (resumeTimer) window.clearTimeout(resumeTimer);
      resumeTimer = window.setTimeout(() => {
        paused = false;
      }, ms);
    };

    const step = (amount) => {
      rail.scrollBy({ left: amount, behavior: 'smooth' });
      pauseTemporarily();
    };

    if (prev) prev.addEventListener('click', () => step(-Math.max(220, rail.clientWidth * 0.7)));
    if (next) next.addEventListener('click', () => step(Math.max(220, rail.clientWidth * 0.7)));

    root.addEventListener('mouseenter', () => { paused = true; });
    root.addEventListener('mouseleave', () => { paused = false; });
    root.addEventListener('focusin', () => { paused = true; });
    root.addEventListener('focusout', (e) => {
      if (!root.contains(e.relatedTarget)) paused = false;
    });

    rail.addEventListener('pointerdown', () => pauseTemporarily(4000));
    rail.addEventListener('wheel', () => pauseTemporarily(4000), { passive: true });
    rail.addEventListener('scroll', syncArrows, { passive: true });
    window.addEventListener('resize', syncArrows);

    const tick = () => {
      if (!reduceMotion && !paused && maxScroll() > 0) {
        const max = maxScroll();
        if (rail.scrollLeft >= max - 0.5) {
          rail.scrollLeft = 0;
        } else {
          rail.scrollLeft += speed;
        }
      }
      syncArrows();
      raf = window.requestAnimationFrame(tick);
    };

    syncArrows();
    if (!reduceMotion) raf = window.requestAnimationFrame(tick);

    // Cleanup if needed when navigating away in SPA-like contexts
    root.addEventListener('lookbook:destroy', () => {
      if (raf) window.cancelAnimationFrame(raf);
      if (resumeTimer) window.clearTimeout(resumeTimer);
    }, { once: true });
  });

  // Product card gallery: slide through images on hover when multiple exist
  document.querySelectorAll('[data-card-slider]').forEach((card) => {
    const slides = Array.from(card.querySelectorAll('.product-card__slide'));
    const dots = Array.from(card.querySelectorAll('.product-card__dot'));
    if (slides.length < 2) return;

    let index = 0;
    let timer = null;
    const intervalMs = 1100;

    const show = (next) => {
      const prev = index;
      index = (next + slides.length) % slides.length;
      if (prev === index) return;
      slides[prev].classList.remove('is-active');
      slides[prev].classList.add('is-exit');
      slides[index].classList.add('is-active');
      slides[index].classList.remove('is-exit');
      window.setTimeout(() => slides[prev].classList.remove('is-exit'), 480);
      dots.forEach((dot, i) => dot.classList.toggle('is-active', i === index));
    };

    const start = () => {
      if (timer || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
      timer = window.setInterval(() => show(index + 1), intervalMs);
    };

    const stop = () => {
      if (timer) {
        window.clearInterval(timer);
        timer = null;
      }
      // Reset to primary image when hover ends
      if (index !== 0) {
        slides.forEach((s, i) => {
          s.classList.toggle('is-active', i === 0);
          s.classList.remove('is-exit');
        });
        dots.forEach((dot, i) => dot.classList.toggle('is-active', i === 0));
        index = 0;
      }
    };

    card.addEventListener('mouseenter', start);
    card.addEventListener('mouseleave', stop);
    card.addEventListener('focusin', start);
    card.addEventListener('focusout', (e) => {
      if (!card.contains(e.relatedTarget)) stop();
    });
  });
});
