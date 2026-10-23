/**
 * Bachat — app.js
 * Vanilla JS helpers: toasts, AJAX with CSRF, cart animations, quantity steppers,
 * count-up counters, confirm dialogs. No frameworks; respects reduced-motion.
 */
(function () {
  'use strict';

  var CSRF = window.BACHAT_CSRF || '';
  var BASE = window.BACHAT_URL || '/';
  var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------- Toasts ---------- */
  function showToast(message, type) {
    var wrap = document.createElement('div');
    wrap.className = 'toast-container position-fixed top-0 start-50 translate-middle-x p-3 b-toasts';
    wrap.setAttribute('aria-live', 'polite');
    var el = document.createElement('div');
    el.className = 'toast b-toast align-items-center border-0 show';
    el.setAttribute('role', 'alert');
    el.setAttribute('data-flash-type', type || 'info');
    el.innerHTML = '<div class="d-flex"><div class="toast-body">' + escapeHtml(message) +
      '</div><button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button></div>';
    wrap.appendChild(el);
    document.body.appendChild(wrap);
    if (window.bootstrap && window.bootstrap.Toast) {
      var t = new bootstrap.Toast(el, { delay: 3200 });
      t.show();
      el.addEventListener('hidden.bs.toast', function () { wrap.remove(); });
    } else {
      setTimeout(function () { wrap.remove(); }, 3200);
    }
  }
  window.bachToast = showToast;

  function escapeHtml(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  /* ---------- AJAX ---------- */
  function ajax(url, data) {
    var opts = {
      method: 'POST',
      headers: { 'X-CSRF-Token': CSRF, 'X-Requested-With': 'XMLHttpRequest' }
    };
    if (data instanceof FormData) {
      opts.body = data;
    } else {
      opts.headers['Content-Type'] = 'application/x-www-form-urlencoded; charset=UTF-8';
      var body = new URLSearchParams();
      body.append('_token', CSRF);
      if (data) Object.keys(data).forEach(function (k) {
        if (data[k] !== undefined && data[k] !== null) body.append(k, data[k]);
      });
      opts.body = body.toString();
    }
    return fetch(url, opts).then(function (r) {
      return r.json().catch(function () { return { ok: false, message: 'Invalid server response' }; });
    });
  }
  window.bachAjax = ajax;

  /* ---------- Cart ---------- */
  function bumpBadge(delta) {
    var badge = document.getElementById('cartBadge');
    if (!badge) return;
    var n = parseInt(badge.textContent || '0', 10) || 0;
    n = Math.max(0, n + delta);
    badge.textContent = n > 99 ? '99+' : n;
    badge.hidden = n === 0;
    badge.classList.remove('bump');
    void badge.offsetWidth;
    badge.classList.add('bump');
  }
  window.bachCartBump = bumpBadge;

  function flyToCart(fromEl) {
    if (reduceMotion || !fromEl) return;
    var cart = document.querySelector('.bn-cart') || document.querySelector('[data-cart-target]');
    var start = fromEl.getBoundingClientRect();
    var dot = document.createElement('div');
    dot.className = 'fly-dot';
    dot.style.left = (start.left + start.width / 2) + 'px';
    dot.style.top = (start.top + start.height / 2) + 'px';
    document.body.appendChild(dot);
    var end = cart ? cart.getBoundingClientRect() : { left: window.innerWidth / 2, top: window.innerHeight - 40, width: 0, height: 0 };
    var dx = end.left + end.width / 2 - (start.left + start.width / 2);
    var dy = end.top + end.height / 2 - (start.top + start.height / 2);
    dot.animate([
      { transform: 'translate(0,0) scale(1)', opacity: 1 },
      { transform: 'translate(' + dx + 'px,' + dy + 'px) scale(.35)', opacity: .4 }
    ], { duration: 550, easing: 'cubic-bezier(.2,.8,.3,1)' }).onfinish = function () { dot.remove(); };
  }

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-add-to-cart]');
    if (btn) {
      e.preventDefault();
      var variantId = btn.getAttribute('data-add-to-cart');
      var qtyInput = btn.closest('.b-card, form, .product-buy').querySelector('[data-qty-for="' + variantId + '"]');
      var qty = qtyInput ? parseInt(qtyInput.value, 10) || 1 : 1;
      btn.disabled = true;
      flyToCart(btn);
      ajax(url('cart/add'), { variant_id: variantId, qty: qty }).then(function (res) {
        btn.disabled = false;
        if (res.ok) {
          bumpBadge(res.delta || qty);
          showToast(res.message || 'Added to cart', 'success');
          if (res.cart_subtotal_html) {
            var st = document.querySelector('[data-cart-subtotal]');
            if (st) st.innerHTML = res.cart_subtotal_html;
          }
        } else {
          showToast(res.message || 'Could not add to cart', 'error');
        }
      }).catch(function () { btn.disabled = false; showToast('Network error', 'error'); });
      return;
    }

    var fav = e.target.closest('[data-fav-toggle]');
    if (fav) {
      e.preventDefault();
      var id = fav.getAttribute('data-fav-toggle');
      ajax(url('favourites/toggle'), { variant_id: id }).then(function (res) {
        if (res.ok) {
          fav.classList.toggle('active', res.faved);
          var icon = fav.querySelector('svg');
          if (icon && res.faved) { fav.setAttribute('fill', 'currentColor'); } else if (icon) { fav.removeAttribute('fill'); }
          showToast(res.message, 'success');
        } else showToast(res.message || 'Error', 'error');
      }).catch(function () { showToast('Network error', 'error'); });
    }
  });

  function url(path) { return BASE.replace(/\/$/, '') + '/' + String(path).replace(/^\//, ''); }

  /* ---------- Quantity steppers ---------- */
  document.addEventListener('click', function (e) {
    var minus = e.target.closest('.qty-minus');
    var plus = e.target.closest('.qty-plus');
    if (!minus && !plus) return;
    var wrap = (minus || plus).closest('.qty-stepper');
    var input = wrap ? wrap.querySelector('.qty-input') : null;
    if (!input) return;
    var step = parseFloat(input.getAttribute('data-step') || '1');
    var min = parseFloat(input.getAttribute('data-min') || '1');
    var max = parseFloat(input.getAttribute('data-max') || '9999');
    var val = parseFloat(input.value) || min;
    val = minus ? val - step : val + step;
    val = Math.min(max, Math.max(min, val));
    val = Math.round(val * 1000) / 1000;
    input.value = val;
    input.dispatchEvent(new Event('change', { bubbles: true }));
    if (!reduceMotion) {
      input.classList.remove('qty-pop');
      void input.offsetWidth;
      input.classList.add('qty-pop');
    }
  });

  /* ---------- Confirm dialogs ---------- */
  window.bachConfirm = function (message) {
    if (window.bootstrap && window.bootstrap.Modal) {
      return new Promise(function (resolve) {
        var html = '<div class="modal fade" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-sm">' +
          '<div class="modal-content"><div class="modal-body text-center pt-4">' +
          '<p class="mb-4" style="font-weight:600">' + escapeHtml(message) + '</p>' +
          '<div class="d-flex gap-2 justify-content-center pb-3">' +
          '<button class="btn btn-soft flex-fill" data-bach="no">Cancel</button>' +
          '<button class="btn btn-brand flex-fill" data-bach="yes">Confirm</button>' +
          '</div></div></div></div></div>';
        var holder = document.createElement('div');
        holder.innerHTML = html;
        document.body.appendChild(holder);
        var modalEl = holder.querySelector('.modal');
        var modal = new bootstrap.Modal(modalEl);
        modalEl.addEventListener('hidden.bs.modal', function () { holder.remove(); resolve(false); });
        modalEl.addEventListener('click', function (ev) {
          var b = ev.target.closest('[data-bach]');
          if (!b) return;
          var yes = b.getAttribute('data-bach') === 'yes';
          modalEl.removeEventListener('hidden.bs.modal', arguments.callee);
          modalEl.addEventListener('hidden.bs.modal', function () { resolve(yes); }, { once: true });
          modal.hide();
          if (yes) {
            setTimeout(function () { resolve(yes); }, 50);
          }
        });
        modal.show();
      });
    }
    return Promise.resolve(window.confirm(message));
  };

  /* Auto-wire confirmable forms */
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (form.matches('form[data-confirm]') && !form.dataset.confirmed) {
      e.preventDefault();
      var msg = form.getAttribute('data-confirm') || 'Are you sure?';
      window.bachConfirm(msg).then(function (yes) {
        if (yes) { form.dataset.confirmed = '1'; form.submit(); }
      });
    }
  });

  /* ---------- Count-up counters ---------- */
  function countUp(el) {
    var target = parseInt(el.getAttribute('data-count') || '0', 10);
    if (reduceMotion) { el.textContent = target; return; }
    var start = null, dur = 1200;
    function step(ts) {
      if (!start) start = ts;
      var p = Math.min(1, (ts - start) / dur);
      el.textContent = Math.round(target * (1 - Math.pow(1 - p, 3)));
      if (p < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
  }
  function initCountUps(root) {
    (root || document).querySelectorAll('.count-up:not([data-done])').forEach(function (el) {
      el.setAttribute('data-done', '1');
      countUp(el);
    });
  }
  window.bachCountUp = initCountUps;

  /* ---------- Scroll reveal for .reveal ---------- */
  function initReveal() {
    if (!('IntersectionObserver' in window) || reduceMotion) {
      document.querySelectorAll('.fade-up').forEach(function (el) { el.style.opacity = 1; el.style.transform = 'none'; });
      return;
    }
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting) { en.target.style.animationPlayState = 'running'; io.unobserve(en.target); }
      });
    }, { threshold: 0.08 });
    document.querySelectorAll('.fade-up').forEach(function (el) {
      el.style.animationPlayState = 'paused';
      io.observe(el);
    });
  }

  /* ---------- Lucide icons ---------- */
  function initIcons(root) {
    if (window.lucide && window.lucide.createIcons) {
      window.lucide.createIcons({ attrs: { 'stroke-width': 2 } });
    }
  }

  /* ---------- Auto-dismiss server toasts ---------- */
  function initToasts() {
    document.querySelectorAll('.toast.show').forEach(function (el) {
      if (window.bootstrap && window.bootstrap.Toast) {
        bootstrap.Toast.getOrCreateInstance(el, { delay: 3800 }).show();
      }
    });
  }

  function runInit() {
    initIcons();
    initToasts();
    initCountUps();
    initReveal();
  }

  if (document.readyState === 'interactive' || document.readyState === 'complete') {
    runInit();
  } else {
    document.addEventListener('DOMContentLoaded', runInit);
  }
  window.bachInitIcons = initIcons;
})();
