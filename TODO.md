# ZC Plugins Implementation TODO

Project: Two WordPress plugins for Zestra Capital
- ZC DMT (Data Management Tool) — data, API, keys, backups
- ZC Charts (Visualization) — shortcodes, chart rendering, API key validation

Approach: Build a vertical slice first (core features working end-to-end), then expand.

---

## Phase 0 — Scaffolding and Bootstrap
- [x] Create plugin folders: zc-dmt and zc-charts
- [x] Each plugin: main file with headers, activation hooks, constants, includes loader
- [x] Basic Settings page for each plugin (admin submenu)
- [x] Minimal CSS to match theme (safe palette)

Deliverables:
- [x] zc-dmt/zc-dmt.php
- [x] zc-dmt/admin/settings.php
- [x] zc-charts/zc-charts.php
- [x] zc-charts/admin/settings.php

---

## Phase 1 — Security & API Keys (DMT)
- [x] Database tables (initial set): zc_dmt_api_keys, zc_dmt_indicators, zc_dmt_data_points, zc_dmt_error_logs
- [x] Key generation: 32-char random, store SHA256 hash + preview
- [x] Admin UI: Create, list, revoke keys
- [x] REST: POST /zc-dmt/v1/validate-key

Deliverables:
- [x] zc-dmt/includes/class-database.php (create tables)
- [x] zc-dmt/includes/class-security.php (generate/validate keys)
- [x] zc-dmt/includes/class-rest-api.php (register/handle validate-key)
- [x] zc-dmt/assets/css/admin.css (basic styling)

---

## Phase 2 — Indicators & Data (DMT)
- [x] CRUD for indicators (minimal)
- [x] Data points storage
- [x] REST: GET /zc-dmt/v1/data/{slug}?access_key=KEY (returns [ [date, value], ... ])
- [ ] Error logging on failures

Deliverables:
- [x] zc-dmt/admin/indicators.php (list/add/edit minimal)
- [x] zc-dmt/includes/class-indicators.php (CRUD helpers)
- [x] Extend class-rest-api.php with data/{slug}

---

## Phase 3 — Charts Vertical Slice (Charts)
- [x] Dependency check: DMT active
- [x] Settings: store API key (paste from DMT)
- [x] Shortcodes: [zc_chart_dynamic], [zc_chart_static]
- [x] Frontend JS loader: fetch from DMT REST and render Chart.js
- [x] Fallback UX (message when data missing / invalid key)

Deliverables:
- [x] zc-charts/includes/class-shortcodes.php
- [x] zc-charts/assets/js/chart-loader.js
- [x] zc-charts/assets/css/public.css

Testing Steps (Critical-path):
1. Install and activate zc-dmt
2. Generate API key in DMT Settings
3. Add one indicator and a few data points
4. Install and activate zc-charts
5. Paste API key in Charts Settings
6. Place shortcode: [zc_chart_dynamic id="your-indicator-slug"] on a page
7. Verify chart renders with Chart.js

---

## Phase 4 — Fallback & Logging (Stubs)
- [x] Stub endpoint: GET /zc-dmt/v1/backup/{slug}?access_key=KEY (placeholder)
- [x] Error logger table and simple logging calls

Deliverables:
- [x] Extend class-rest-api.php with backup/{slug}
- [ ] zc-dmt/includes/class-error-logger.php (DB insert; simple list admin)

---

## Phase 5 — Next Priority Features

### Option A: Google Drive Mirror & Fallback (Rate‑limit aware)
Goal: For sources with limited/free-tier API quotas, avoid serving users directly from the live API. Instead, fetch on schedule (or on-demand by admin), store a fresh snapshot to Google Drive, and serve charts from Drive by default. If Drive is unavailable, fall back to live API.

Implementation:
- [ ] Google Drive API integration (Service Account JSON; folder ID per environment)
- [ ] Admin → Settings → Backups:
  - [ ] Upload credentials JSON (securely stored)
  - [ ] Folder ID for mirrors
  - [ ] Retention (e.g., keep last 7)
  - [ ] Mirror policy per source type (checkboxes: alpha_vantage, polygon, twelve_data, iex, fmp, quandl, fred, yahoo, etc.)
  - [ ] Global “Serve from Drive first” toggle for limited sources
- [ ] Rate‑limit registry
  - [ ] Local file (rate-limits.json) with known limits and a “limited: true/false” flag per source
  - [ ] Examples (initial defaults):
    - World Bank (open/fair use): limited=false
    - Eurostat (open/fair use): limited=false
    - OECD (open/fair use): limited=false
    - DBnomics (open/fair use): limited=false
    - Google Sheets (self‑hosted): limited=false
    - FRED (120/min; key): limited=optional (allow mirror if desired)
    - Alpha Vantage (25/day free): limited=true
    - IEX Cloud (500/day free): limited=true
    - Twelve Data (800/day free): limited=true
    - Polygon.io (5/min free): limited=true
    - FMP (250/day free): limited=true
    - Quandl/Nasdaq (varies/free limited): limited=true
    - Yahoo Finance (unofficial; fair use): limited=optional
- [ ] Mirror write path
  - [ ] After successful fetch of a limited source, serialize normalized series to JSON/CSV and upload to Drive (name: {slug}-{YYYYMMDDHHmm}.json)
  - [ ] Maintain “latest.json” pointer per slug for quick reads
  - [ ] Cron job to refresh mirrors (per source cadence, e.g., hourly/daily)
  - [ ] Retention cleanup (delete older than N)
- [ ] Serve path
  - [ ] For limited sources: chart loader requests AJAX → backend checks mirror policy
  - [ ] If mirror enabled: read “latest.json” from Drive and return data
  - [ ] If Drive read fails: fall back to live API (and optionally re‑upload on success)
- [ ] Observability
  - [ ] Log mirror writes/reads/errors to zc_dmt_error_logs (level info/warning/error)
  - [ ] Admin “Mirror Status” table (slug, last refresh, points, file size, errors)

Notes:
- Live open sources (WB/Eurostat/OECD/DBnomics/Sheets) continue to serve directly unless you toggle them to mirror.
- This approach minimizes user‑side API calls and keeps data fresh under limited quotas while reducing hosting load.

### Option B: Additional Data Sources
- [ ] World Bank API adapter
- [ ] Eurostat API adapter  
- [ ] OECD API adapter
- [ ] Yahoo Finance adapter
- [ ] Enhanced CSV/ZIP import system

### Option C: Enhanced Charts & Analytics
- [ ] Highcharts integration (pluggable)
- [ ] Multiple series support
- [ ] Export features (PNG, PDF, CSV)
- [ ] Dashboard with usage analytics
- [ ] Data source health monitoring

### Option D: Advanced Features
- [ ] Manual calculations engine (ROC, moving averages, etc.)
- [ ] Formula Builder UI
- [ ] Error logging with email alerts
- [ ] Rate limiting and security enhancements
- [ ] Internationalization (i18n)

---

## Test Plan

Status: No testing done yet.

Planned Testing Levels:
- Critical-path testing for the vertical slice
  - DMT install → API key → indicator/data → REST → Charts install → shortcode render
- Then Thorough testing
  - All admin UIs (DMT + Charts)
  - REST endpoints (happy/errors)
  - Edge cases (invalid/missing keys, empty datasets, bad slug)
  - Performance (multiple charts, large data)
  - Accessibility and mobile responsiveness

---

## Notes for Non-Developers (Simple Usage)

After installation:
1. DMT → Settings → API Keys → Generate Key → Copy
2. DMT → Indicators → Add one indicator and data points
3. Charts → Settings → Paste API Key → Save
4. Create a WordPress Page → Add this shortcode:
   [zc_chart_dynamic id="your-indicator-slug"]
5. View the page: you should see the chart.

If you see an error:
- Check API key in Charts settings
- Ensure indicator slug matches the shortcode
- Ensure DMT plugin is active
