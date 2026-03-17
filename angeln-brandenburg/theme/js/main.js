/**
 * Angeln Brandenburg 2026 – Main JavaScript
 * Phase 2: UX, lazy loading, mobile enhancements
 * Phase 4: Beißindex + Pegel live updates
 */

'use strict';

(function () {

  // ============================================================
  // 1. LAZY IMAGE LOADING (IntersectionObserver – CLS prevention)
  // ============================================================
  function initLazyImages() {
    const imgs = document.querySelectorAll('img[loading="lazy"]');
    if (!('IntersectionObserver' in window)) {
      imgs.forEach(img => img.classList.add('loaded'));
      return;
    }
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          const img = entry.target;
          img.addEventListener('load', () => img.classList.add('loaded'), { once: true });
          observer.unobserve(img);
        }
      });
    }, { rootMargin: '200px' });
    imgs.forEach(img => observer.observe(img));
  }

  // ============================================================
  // 2. FAQ ACCORDION
  // ============================================================
  function initFaqAccordion() {
    document.querySelectorAll('.abb-faq__question').forEach(btn => {
      btn.addEventListener('click', () => {
        const item = btn.closest('.abb-faq__item');
        const isOpen = item.classList.contains('open');
        // Close all
        document.querySelectorAll('.abb-faq__item.open').forEach(el => el.classList.remove('open'));
        if (!isOpen) item.classList.add('open');
      });
    });
  }

  // ============================================================
  // 3. MOBILE NAV – close on outside click
  // ============================================================
  function initMobileNav() {
    const toggle = document.querySelector('.ast-mobile-menu-trigger, .menu-toggle');
    if (!toggle) return;
    document.addEventListener('click', (e) => {
      const nav = document.querySelector('.main-navigation, #ast-desktop-navigation');
      if (nav && nav.classList.contains('toggled') && !nav.contains(e.target) && !toggle.contains(e.target)) {
        toggle.click();
      }
    });
  }

  // ============================================================
  // 4. BEISSINDEX – animate gauge on load
  // ============================================================
  function initBeissindexGauge() {
    const fill = document.querySelector('.abb-gauge-fill');
    if (!fill) return;
    const targetWidth = fill.dataset.score || '0';
    setTimeout(() => {
      fill.style.width = targetWidth + '%';
    }, 300);
  }

  // ============================================================
  // 5. PEGEL TICKER – fetch live data via AJAX
  // ============================================================
  async function loadPegelData() {
    const container = document.querySelector('[data-pegel-ticker]');
    if (!container || typeof abbData === 'undefined') return;

    const stations = JSON.parse(container.dataset.stations || '[]');
    if (!stations.length) return;

    const tbody = container.querySelector('.abb-pegel-tbody');
    if (!tbody) return;

    try {
      const results = await Promise.allSettled(
        stations.map(uuid =>
          fetch(`${abbData.pegelApiBase}/stations/${uuid}/W/currentmeasurement.json`)
            .then(r => r.ok ? r.json() : null)
            .then(data => ({ uuid, data }))
        )
      );

      results.forEach(result => {
        if (result.status !== 'fulfilled' || !result.value.data) return;
        const { uuid, data } = result.value;
        const row = tbody.querySelector(`tr[data-uuid="${uuid}"]`);
        if (!row) return;

        const valueCell = row.querySelector('.pegel-value');
        const trendCell = row.querySelector('.pegel-trend');
        if (valueCell) valueCell.textContent = data.value ? `${data.value} cm` : '–';
        if (trendCell) {
          const trend = data.trend;
          trendCell.textContent = trend > 0 ? '↑' : trend < 0 ? '↓' : '→';
          trendCell.className = 'pegel-trend abb-trend-' + (trend > 0 ? 'up' : trend < 0 ? 'down' : 'flat');
        }
      });

      const ts = container.querySelector('.pegel-timestamp');
      if (ts) ts.textContent = 'Aktualisiert: ' + new Date().toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });

    } catch (err) {
      console.warn('[Angeln BB] Pegel API error:', err);
    }
  }

  // ============================================================
  // 6. SMOOTH SCROLL for anchor links
  // ============================================================
  function initSmoothScroll() {
    document.querySelectorAll('a[href^="#"]').forEach(link => {
      link.addEventListener('click', (e) => {
        const id = link.getAttribute('href').slice(1);
        const target = document.getElementById(id);
        if (!target) return;
        e.preventDefault();
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        target.focus({ preventScroll: true });
      });
    });
  }

  // ============================================================
  // 7. BACK TO TOP button (show after 400px scroll)
  // ============================================================
  function initBackToTop() {
    const btn = document.querySelector('.abb-back-to-top');
    if (!btn) return;
    const toggle = () => btn.classList.toggle('visible', window.scrollY > 400);
    window.addEventListener('scroll', toggle, { passive: true });
    btn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
  }

  // ============================================================
  // 8. BEISSINDEX LIVE UPDATE (scheduled)
  // ============================================================
  function initBeissindexUpdate() {
    const widget = document.querySelector('[data-beissindex-widget]');
    if (!widget || typeof abbData === 'undefined') return;

    const update = async () => {
      try {
        const response = await fetch(
          `${abbData.ajaxUrl}?action=abb_get_beissindex&nonce=${abbData.nonce}`
        );
        if (!response.ok) return;
        const result = await response.json();
        if (!result.success) return;

        const { score, label, factors } = result.data;

        const fill = widget.querySelector('.abb-gauge-fill');
        const labelEl = widget.querySelector('.abb-gauge-label');
        if (fill) fill.style.width = score + '%';
        if (labelEl) labelEl.textContent = label;

        ['luftdruck', 'mondphase', 'temperatur'].forEach(key => {
          const el = widget.querySelector(`[data-factor="${key}"]`);
          if (el && factors[key] !== undefined) el.textContent = factors[key];
        });
      } catch (err) {
        console.warn('[Angeln BB] Beißindex update error:', err);
      }
    };

    // Update every 15 minutes
    update();
    setInterval(update, 15 * 60 * 1000);
  }

  // ============================================================
  // INIT
  // ============================================================
  document.addEventListener('DOMContentLoaded', () => {
    initLazyImages();
    initFaqAccordion();
    initMobileNav();
    initBeissindexGauge();
    initSmoothScroll();
    initBackToTop();

    // Pegel + Beißindex after small delay (non-critical)
    setTimeout(() => {
      loadPegelData();
      initBeissindexUpdate();
    }, 500);

    // Reload pegel every 10 minutes
    setInterval(loadPegelData, 10 * 60 * 1000);
  });

})();
