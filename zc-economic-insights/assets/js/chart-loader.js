/**
 * ZC DMT (merged) - Frontend Chart Loader
 * - Fetches data via nonce-protected WP AJAX (no API key exposed)
 * - Renders with Chart.js
 * - Optional fallback to backup AJAX endpoint
 * - Timeframe controls for dynamic charts
 */
(function () {
  const CFG = window.zcDmtChartsConfig || {};
  const AJAX_URL = CFG.ajaxUrl || '';
  const NONCE = CFG.nonce || '';
  const DEFAULTS = CFG.defaults || {};

  // Utility: load Chart.js on demand
  function ensureChartJs() {
    return new Promise((resolve, reject) => {
      if (window.Chart) return resolve();
      const script = document.createElement('script');
      script.src = 'https://cdn.jsdelivr.net/npm/chart.js';
      script.onload = () => resolve();
      script.onerror = () => reject(new Error('Failed to load Chart.js'));
      document.head.appendChild(script);
    });
  }

  // Utility: format series [ [date, value], ...] -> Chart.js dataset
  function toChartJsDataset(series) {
    const labels = [];
    const data = [];
    (series || []).forEach((row) => {
      labels.push(row[0]); // date string
      data.push(row[1] !== null ? Number(row[1]) : null);
    });
    return { labels, data };
  }

  // Utility: timeframe filter
  function filterByTimeframe(series, timeframe) {
    if (!series || !Array.isArray(series) || !series.length) return series;
    if (!timeframe || timeframe === 'all') return series;

    const lastDate = new Date(series[series.length - 1][0] || new Date());
    let startDate = null;
    const tf = String(timeframe).toLowerCase();
    const num = parseInt(tf, 10);
    if (tf.endsWith('m') && !isNaN(num)) {
      startDate = new Date(lastDate);
      startDate.setMonth(startDate.getMonth() - num);
    } else if (tf.endsWith('y') && !isNaN(num)) {
      startDate = new Date(lastDate);
      startDate.setFullYear(startDate.getFullYear() - num);
    }
    if (!startDate) return series;

    return series.filter(([dateStr]) => new Date(dateStr) >= startDate);
  }

  // Utility: error UI
  function renderError(container, message, details) {
    container.innerHTML = `
      <div class="zc-chart-error">
        <div class="error-icon">⚠️</div>
        <div class="error-message">${message}</div>
        ${details ? `<div class="error-details">${details}</div>` : ''}
      </div>
    `;
  }

  // Utility: ensure canvas
  function ensureCanvas(container) {
    let canvas = container.querySelector('canvas');
    if (!canvas) {
      canvas = document.createElement('canvas');
      canvas.style.width = '100%';
      canvas.style.height = '100%';
      container.appendChild(canvas);
    }
    return canvas;
  }

  // AJAX fetch helpers
  async function ajaxPost(action, payload) {
    if (!AJAX_URL || !NONCE) {
      throw new Error('AJAX config missing');
    }
    const form = new FormData();
    form.append('action', action);
    form.append('nonce', NONCE);
    Object.keys(payload || {}).forEach((k) => form.append(k, payload[k]));

    const res = await fetch(AJAX_URL, { method: 'POST', body: form, credentials: 'same-origin' });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const json = await res.json();
    return json;
  }

  async function fetchLive(slug) {
    const json = await ajaxPost('zc_dmt_get_data', { slug });
    if (!json || json.status !== 'success' || !json.data) {
      throw new Error(json && json.message ? json.message : 'Live data format error');
    }
    return json.data;
  }

  async function fetchBackup(slug) {
    const json = await ajaxPost('zc_dmt_get_backup', { slug });
    if (!json || json.status !== 'success' || !json.data) {
      throw new Error(json && json.message ? json.message : 'Backup data format error');
    }
    return json.data;
  }

  // Render with Chart.js
  async function renderChartJs(container, data, timeframe, title) {
    await ensureChartJs();

    const filtered = { ...data, series: filterByTimeframe(data.series, timeframe) };
    const { labels, data: values } = toChartJsDataset(filtered.series);
    if (!labels.length) {
      renderError(container, 'Data Not Found', 'No data points available for the selected timeframe.');
      return;
    }

    const canvas = ensureCanvas(container);
    const ctx = canvas.getContext('2d');
    if (canvas._zcChart) {
      try { canvas._zcChart.destroy(); } catch (e) {}
      canvas._zcChart = null;
    }

    const color = '#2B7A78';
    const gridColor = 'rgba(0,0,0,0.08)';
    const textColor = '#334155';

    const chart = new Chart(ctx, {
      type: 'line',
      data: {
        labels,
        datasets: [{
          label: title || (data.indicator && data.indicator.name) || 'Indicator',
          data: values,
          borderColor: color,
          backgroundColor: 'rgba(43,122,120,0.1)',
          pointRadius: 0,
          borderWidth: 2,
          tension: 0.2,
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { display: true, labels: { color: textColor } },
          tooltip: { enabled: true },
        },
        scales: {
          x: { ticks: { color: textColor, maxTicksLimit: 8 }, grid: { color: gridColor } },
          y: { ticks: { color: textColor }, grid: { color: gridColor } },
        },
      },
    });

    canvas._zcChart = chart;
  }

  // Load a single container
  async function loadContainer(container) {
    const type = container.getAttribute('data-type') || 'dynamic';
    const slug = container.getAttribute('data-slug') || '';
    const library = (container.getAttribute('data-library') || DEFAULTS.library || 'chartjs').toLowerCase();
    const timeframe = container.getAttribute('data-timeframe') || DEFAULTS.timeframe || '1y';
    const controls = (container.getAttribute('data-controls') || String(DEFAULTS.controls)) === 'true';
    const fallbackEnabled = !!DEFAULTS.fallback;

    if (!slug) {
      renderError(container, 'Missing indicator slug', 'Provide id attribute in shortcode, e.g., id="gdp_us".');
      return;
    }

    if (library === 'highcharts') {
      container.innerHTML = '<div class="zc-chart-error"><div class="error-icon">ℹ️</div><div class="error-message">Highcharts support will be enabled later. Use library="chartjs" for now.</div></div>';
      return;
    }

    // Try live, then backup
    let data = null;
    let usedFallback = false;
    try {
      data = await fetchLive(slug);
    } catch (err) {
      if (fallbackEnabled) {
        try {
          data = await fetchBackup(slug);
          usedFallback = true;
        } catch (backupErr) {
          renderError(container, 'Data unavailable', 'Live and backup data could not be loaded.');
          return;
        }
      } else {
        renderError(container, 'Unable to load data', 'Live fetch failed and fallback is disabled.');
        return;
      }
    }

    if (usedFallback) {
      container.classList.add('zc-chart-fallback');
      const notice = document.createElement('div');
      notice.className = 'zc-chart-notice';
      notice.textContent = 'Displaying cached data';
      container.prepend(notice);
    }

    try {
      await renderChartJs(container, data, timeframe);
    } catch (e) {
      renderError(container, 'Chart rendering error', e && e.message ? e.message : 'Unknown error');
    }
  }

  // Bind timeframe controls
  function bindControls() {
    document.querySelectorAll('.zc-chart-wrapper').forEach((wrap) => {
      const controls = wrap.querySelector('.zc-chart-controls');
      const containerId = controls && controls.getAttribute('data-for');
      const container = containerId ? document.getElementById(containerId) : wrap.querySelector('.zc-chart-container');
      if (!controls || !container) return;

      controls.addEventListener('click', async (e) => {
        const btn = e.target.closest('.zc-tf-btn');
        if (!btn) return;
        const range = btn.getAttribute('data-range');
        controls.querySelectorAll('.zc-tf-btn.active').forEach((el) => el.classList.remove('active'));
        btn.classList.add('active');
        container.setAttribute('data-timeframe', range);
        await loadContainer(container);
      });
    });
  }

  function init() {
    const containers = document.querySelectorAll('.zc-chart-container');
    if (!containers.length) return;
    containers.forEach((c) => loadContainer(c));
    bindControls();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
