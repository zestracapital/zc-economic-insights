# ZC Economic Insights — Data Sources How‑To (Simple Steps)

This guide shows exactly how to add indicators from each source. Follow the steps and copy the examples. You only need to Add / Edit / Delete indicators; the system fetches data automatically and charts use live data with caching.

Contents
- 0) Quick Checklist
- 1) Google Sheets (Public CSV)
- 2) FRED (USA)
- 3) World Bank (Global)
- 4) DBnomics (Many providers incl. IMF)
- 5) Eurostat (EU)
- 6) OECD (SDMX JSON)
- 7) UK ONS (UK Open Data)
- 8) Yahoo Finance (Market Data)
- 9) Google Finance (CSV/JSON)
- 10) Quandl / Nasdaq Data Link
- 11) Canada — Bank of Canada (Valet)
- 12) Canada — Statistics Canada (JSON/CSV)
- 13) Australia — RBA (CSV/JSON)
- Additional Sources (Addendum): ECB (ECB SDW), Universal CSV, Universal JSON
- 14) Common Issues and Fixes
- 15) After Creating an Indicator (Shortcode)

0) Quick Checklist
- Go to: WP Admin → ZC DMT → Indicators → “Add New Indicator”.
- Enter Name and Slug (slug must be unique).
- Choose Source Type and fill the required field(s).
- Click “Save Indicator”.
- Optional: Go to ZC DMT → Settings → Shortcode Builder → pick the indicator → Test Fetch → Build Shortcode → Copy → paste into a page.

1) Google Sheets (Public CSV)
Best for quick custom data you maintain in Google Sheets.

Steps:
1. In Google Sheets, publish as CSV:
   - File → Share → Publish to web → Select the sheet/tab → Format “CSV” → Publish.
   - Or use CSV export link:
     https://docs.google.com/spreadsheets/d/{SHEET_ID}/export?format=csv&gid={GID}
2. Copy the CSV/Export URL.
3. WP Admin → ZC DMT → Indicators → Add New Indicator:
   - Source Type: “Google Sheets (live CSV)”
   - Google Sheets URL: paste the CSV link
   - Save.

Notes:
- The plugin auto-detects date/value columns; dates are normalized to Y-m-d.
- Uses caching (10 minutes). If you update the sheet, wait briefly or re-save indicator to invalidate caches.

2) FRED (USA)
Official U.S. economic data. Requires FRED API key (set in Settings).

Steps:
1. WP Admin → ZC DMT → Settings → Data Sources → set your FRED API key and Save.
2. Find the series ID on fred.stlouisfed.org (examples: GDP, UNRATE, CPIAUCSL, FEDFUNDS).
3. WP Admin → Indicators → Add New Indicator:
   - Source Type: “FRED API (live data)”
   - FRED Series ID: e.g., GDP
   - Save.

Notes:
- Caching: 15 minutes.
- If “Test Fetch” shows 0 points, check series ID spelling and FRED key.

3) World Bank (Global)
Open data. No key required.

Steps:
1. Find indicator code on data.worldbank.org or the API docs (examples below).
2. WP Admin → Indicators → Add New Indicator:
   - Source Type: “World Bank API (open data)”
   - Country Code: “US” for USA, “WLD” for World, “DE” for Germany, etc.
   - Indicator Code: e.g., NY.GDP.MKTP.CD
   - Save.

Useful indicator codes:
- NY.GDP.MKTP.CD → GDP (current US$)
- NY.GDP.PCAP.CD → GDP per capita (current US$)
- FP.CPI.TOTL.ZG → Inflation (CPI, annual %)
- SL.UEM.TOTL.ZS → Unemployment (%)
- SP.POP.TOTL → Population (total)

Notes:
- Caching: 20 minutes.
- World Bank returns annual (often). Dates normalized to first day of period.

4) DBnomics (Many providers incl. IMF)
Open meta-platform aggregating many providers. You must copy the exact series path.

Important: Series ID format is:
PROVIDER/DATASET/SERIES
Examples:
- AMECO/ZUTN/EA19.1.0.0.0.ZUTN (works)
- IMF datasets exist but you must copy the exact series path from DBnomics. The order of dimensions matters.

Steps to get the correct series:
1. Open browser and navigate to https://db.nomics.world
2. Search your indicator (e.g., “Current account IMF BOP US”).
3. Click into the exact series (not just the dataset). You should see a chart for the single series.
4. On that series page, look for:
   - “API” or “Link”/“Download” → Copy the DB.nomics series path (the part after the domain).
   - It will look like PROVIDER/DATASET/SERIES
5. Paste that full path into “DBnomics Series ID”.

Troubleshooting the IMF examples you tried:
- 404 error: IMF/BOP/Q.US.BACK_BP6_USD → likely wrong order of dimensions or not a real series key.
- No data: IMF/BOP/Q.US.BCA_BP6_USD → again order/dimensions typically differ.
Fix:
- On the DB.nomics site, drill into the series and copy the series path from the page (not guessing).
- IMF/BOP usually has a structure like:
  IMF/BOP/FREQ.INDICATOR.CURRENCY?COUNTRY (varies by dataset)
  Some series keys end with “.US” (country at the end).
- If you share the exact DB.nomics series page URL, we can confirm the correct series ID for you.

Notes:
- Caching: 20 minutes.
- Our adapter accepts both “observations” maps and “periods/values” arrays.

5) Eurostat (EU)
Open EU data using JSON-stat. You set a dataset code and an optional query.

Basic Steps:
1. Find the dataset code on Eurostat (examples below).
2. WP Admin → Indicators → Add New Indicator:
   - Source Type: “Eurostat (open data)”
   - Dataset Code: e.g., nama_10_gdp
   - Optional Query (filters): e.g., geo=EU27_2020&amp;na_item=B1GQ&amp;unit=CP_MEUR
   - Save.

Popular Examples:
- GDP by expenditure (current prices):
  - dataset: nama_10_gdp
  - query: geo=EU27_2020&amp;na_item=B1GQ&amp;unit=CP_MEUR
- HICP (inflation index), monthly:
  - dataset: prc_hicp_midx
  - query: geo=EA19&amp;coicop=CP00&amp;unit=I15
- Unemployment rate:
  - dataset: une_rt_m
  - query: geo=EU27_2020&amp;sex=T&amp;age=Y15-74&amp;s_adj=SA&amp;unit=PC_ACT

How the query works:
- The dataset contains multiple dimensions (geo, na_item, unit, time…).
- The “Optional Query” picks fixed values for non-time dimensions.
- If you omit the query, the adapter picks index 0 for other dimensions (not always what you want).

Notes:
- Caching: 20 minutes.
- Time can be Annual (YYYY), Quarterly (YYYY-Qn), Monthly (YYYY-MM). We normalize to Y-m-d.

6) OECD (SDMX JSON)
Open OECD data using SDMX JSON.

Steps:
1. Construct the path like:
   DATASET/KEY/all
   Example:
   QNA/USA.B1_GE.CQRSA.Q/all
2. WP Admin → Indicators → Add New Indicator:
   - Source Type: “OECD (open data)”
   - OECD Path: QNA/USA.B1_GE.CQRSA.Q/all
   - Save.

Notes:
- The last “/all” often selects all time. If needed, we can extend with startPeriod later.
- Caching: 20 minutes.
- If you see 0 points, double-check the path spelling and dataset/key combination.

7) UK ONS (UK Open Data)
Add UK Office for National Statistics series. Choose ONE method.

Steps:
1) WP Admin → ZC DMT → Indicators → Add New Indicator
2) Source Type: “UK ONS (open data)”
3) Fill only ONE of the following:
   - JSON URL (recommended if available)
     Example:
     https://api.ons.gov.uk/timeseries/{series_id}/dataset/{dataset_id}/data?time=from+2010
   - CSV URL
     Example: any ONS CSV “download.csv” link
   - Timeseries (Dataset + Series)
     Dataset ID: e.g., pn2
     Series ID: e.g., mgsx
     Extra Query (optional): e.g., time=from+2010
4) Save.

Notes:
- Dates auto-normalize (YYYY, YYYY-MM, “YYYY MON”, “YYYY Qn”).
- Caching: 20 minutes. Re-save to refresh immediately.

8) Yahoo Finance (Market Data)
Fetch market data via Yahoo. Choose EITHER a Symbol OR direct URLs.

Steps:
1) WP Admin → Indicators → Add New
2) Source Type: “Yahoo Finance (market data)”
3) Preferred: Symbol method
   - Symbol: e.g., AAPL, ^GSPC, EURUSD=X
   - Range: e.g., 1y (supports 1mo, 3mo, 6mo, 1y, 2y, 5y, ytd, max)
   - Interval: e.g., 1d (supports 1d, 1wk, 1mo)
   The system uses Yahoo chart JSON and falls back to CSV if needed.
4) Optional instead of Symbol:
   - Yahoo JSON URL: https://query1.finance.yahoo.com/v8/finance/chart/AAPL?interval=1d&range=1y
   - Yahoo CSV URL: https://query1.finance.yahoo.com/v7/finance/download/AAPL?...&interval=1d&events=history
5) Save.

Notes:
- Output normalized to [Y-m-d, value]. Caching: 20 minutes.
- If JSON lacks timestamps/close, we try CSV fallback (for Symbol flow).

9) Google Finance (CSV/JSON)
There is no official public JSON API. Recommended approach is via Google Sheets publish to CSV, or provide any JSON you control.

A) Google Sheets + GOOGLEFINANCE (recommended)
1) In Google Sheets cell A2:
   =GOOGLEFINANCE("GOOG","price",TODAY()-365,TODAY(),"DAILY")
2) File → Share → Publish to the web → select the sheet/tab → Format “CSV” → Publish
   Or use export form:
   https://docs.google.com/spreadsheets/d/{SHEET_ID}/export?format=csv&gid={GID}
3) Copy the CSV URL.
4) WP Admin → Indicators → Add New
   - Source Type: “Google Finance (CSV/JSON)”
   - CSV URL: paste the published CSV URL
5) Save.

B) JSON (advanced)
- Provide any JSON endpoint that returns either:
  { "data": [ { "date|time|period": "...", "value|close|price": ... }, ... ] }
  or { "observations": { "YYYY-MM-DD": value, ... } }.

Notes:
- Adapter auto-detects date/value columns and normalizes to [Y-m-d, value].
- Caching: 20 minutes.

10) Quandl / Nasdaq Data Link
Fetch datasets from data.nasdaq.com (formerly Quandl). Choose ONE method.

Steps:
1) WP Admin → Indicators → Add New
2) Source Type: “Quandl / Nasdaq Data Link”
3) Choose ONE:
   - JSON URL:
     https://data.nasdaq.com/api/v3/datasets/FRED/GDP.json?api_key=YOUR_KEY
   - CSV URL:
     https://data.nasdaq.com/api/v3/datasets/FRED/GDP.csv?api_key=YOUR_KEY
   - Dataset:
     - Database: FRED
     - Dataset: GDP
     - API Key (optional)
     - Collapse (optional): monthly | quarterly | annual
     - Start/End Date (optional): YYYY-MM-DD
4) Save.

Notes:
- Output normalized to [Y-m-d, value]. Caching: 20 minutes.
- If JSON fails, adapter falls back to CSV automatically when using Dataset method.

11) Canada — Bank of Canada (Valet)
Use Bank of Canada Valet service. Choose ONE method.

Steps:
1) WP Admin → Indicators → Add New
2) Source Type: “Bank of Canada (Valet)”
3) Choose ONE:
   - JSON URL:
     https://www.bankofcanada.ca/valet/observations/V39079/json?start_date=2019-01-01
   - CSV URL:
     https://www.bankofcanada.ca/valet/observations/V39079/csv?start_date=2019-01-01
   - Series Code:
     - Series: V39079
     - Start/End Date (optional)
4) Save.

Notes:
- Adapter searches for observation date (“d”/“date”) and first numeric value (handles nested v).
- Caching: 20 minutes.

12) Canada — Statistics Canada (JSON/CSV)
Use StatCan WDS JSON or direct CSV downloads. Choose ONE method.

Steps:
1) WP Admin → Indicators → Add New
2) Source Type: “Statistics Canada (JSON/CSV)”
3) Choose ONE:
   - JSON URL: any WDS/compatible JSON that includes date/period and value
     Example: https://www150.statcan.gc.ca/t1/wds/en/grp/{TABLE_ID}/all
   - CSV URL: any StatCan/Open Data CSV with date/period + value
4) Save.

Notes:
- Adapter detects common keys like ref_date/date/time/period and value/val/v.
- Caching: 20 minutes.

13) Australia — RBA (CSV/JSON)
Use Reserve Bank of Australia statistical tables (prefer CSV). Choose ONE method.

Steps:
1) WP Admin → Indicators → Add New
2) Source Type: “Australia RBA (CSV/JSON)”
3) Choose ONE:
   - CSV URL (recommended): direct CSV link from RBA table download
   - JSON URL (optional): any JSON endpoint with date/period and value
4) Save.

Notes:
- Adapter auto-detects date/value columns. Caching: 20 minutes.

Additional Sources (Addendum): ECB (ECB SDW), Universal CSV, Universal JSON

A) European Central Bank (ECB SDW)
Use the ECB Statistical Data Warehouse. Prefer CSV for reliability.

Steps:
1) WP Admin → Indicators → Add New
2) Source Type: European Central Bank (ECB SDW)
3) Choose ONE:
   - CSV URL (recommended):
     https://sdw-wsrest.ecb.europa.eu/service/data/EXR/D.USD.EUR.SP00.A?startPeriod=2000&format=csvdata
   - JSON URL (optional):
     Any JSON with date/value fields. SDMX-JSON can be complex, so CSV is preferred.
   - PATH (auto CSV):
     Example PATH: EXR/D.USD.EUR.SP00.A?startPeriod=2000
     The adapter builds a CSV URL automatically from PATH.
4) Save.

Notes:
- Date normalization supports YYYY, YYYY-MM, YYYY-Qn (mapped to quarter-end), and YYYY-MM-DD.
- Caching: 20 minutes.
- If you have an ECONOMIC concept but not the URL, you can use PATH to keep it simple.

B) Universal CSV (any URL)
Fetch time-series from ANY CSV on the internet with minimal config.

Steps:
1) WP Admin → Indicators → Add New
2) Source Type: Universal CSV (any URL)
3) Provide:
   - CSV URL: direct CSV link (publicly accessible)
   - Optional:
     - Date Column (name or index): “date” or 0
     - Value Column (name or index): “value” or 1
     - Delimiter: , ; tab | (auto-detected if not set)
     - Skip Rows: number of header/comment rows to skip before header
4) Save.

Notes:
- If columns aren’t specified, the adapter auto-detects likely date/value columns and normalizes the series.
- Date formats: YYYY, YYYY-MM, YYYY-Qn, YYYY-MM-DD (and common variants) are normalized to Y-m-d.
- Caching: 20 minutes.

C) Universal JSON (any URL)
Fetch time-series from ANY JSON endpoint with minimal config and optional mapping.

Steps:
1) WP Admin → Indicators → Add New
2) Source Type: Universal JSON (any URL)
3) Provide:
   - JSON URL: direct JSON link (publicly accessible)
   - Optional:
     - Root (dot path): e.g., data.items (to drill into nested arrays)
     - Date Key: e.g., date | time | period
     - Value Key: e.g., value | close | price | obs_value
     - Map JSON (key remapping JSON): e.g., {"d":"date","v":"value"} to rename keys before parsing
4) Save.

Notes:
- If keys aren’t provided, the adapter tries common shapes:
  - Array of items each with date/time/period and value/close/price
  - observations object: { "YYYY-MM-DD": value }
  - data wrapper arrays (e.g., { data: [...] })
- Date normalization same as above; caching 20 minutes.

14) Common Issues and Fixes
- 0 points in “Test Fetch”:
  - Wrong code/path or symbol. Re-check spelling and spaces.
  - DBnomics/IMF: never guess series keys. Open the DB.nomics series page and copy the full PROVIDER/DATASET/SERIES path.
  - New indicator: wait for cache (15–20 minutes) or re-save the indicator to refresh immediately.
- Eurostat empty:
  - Add a minimal query to specify “geo” and required dimensions (e.g., na_item, unit).
- OECD empty:
  - Ensure dataset/key and country code are correct (e.g., USA not US). Use “/all” to include all time.
- World Bank empty:
  - Ensure country code (US/WLD/DE/…) and indicator code match.
- FRED empty:
  - Ensure FRED API key is saved and series ID exists.
- Yahoo Finance empty:
  - Try Symbol method first. If rate-limited, wait and try again; verify range/interval are supported.
- Google Finance (CSV) empty:
  - Ensure the CSV is publicly accessible (Published to web). Confirm the URL opens without login.

15) After Creating an Indicator (Shortcode)
- Go to ZC DMT → Settings → Shortcode Builder
- Click “Load Indicators”, pick your indicator OR enter slug manually
- Choose options (library, timeframe, height)
- Click “Test Fetch” to confirm data points > 0
- Click “Build Shortcode” → “Copy”
- Paste into any Page:
  [zc_chart_dynamic id="your-indicator-slug"]

Edit or Delete Indicators (Quick)
- ZC DMT → Indicators → All Indicators table (Actions on each row)
  - Edit: click “Edit” to inline-update Name, Slug, Active, Description, or advanced Source Config (JSON). Save without leaving the page.
  - Delete: removes the indicator and all its stored data points.

Need help with a specific series?
- Share the public page URL (DBnomics/Eurostat/OECD/ONS) or the symbol and indicator slug. We will validate and provide a ready-to-paste configuration.
