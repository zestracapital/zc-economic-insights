# Free Data Sources Collection - Complete List

## Economic & Financial Data Sources

### 1. FRED (Federal Reserve Economic Data) - USA
**URL**: `https://api.stlouisfed.org/fred/`
**Method**: REST API with JSON response
**Authentication**: Free API key required
**Rate Limit**: 120 requests per 60 seconds
**Data Format**: JSON with observations array
**Example**:
```
GET https://api.stlouisfed.org/fred/series/observations?series_id=GDP&api_key=YOUR_KEY&file_type=json
```
**Coverage**: US economic indicators, employment, inflation, GDP, etc.

### 2. World Bank Open Data
**URL**: `https://api.worldbank.org/v2/`
**Method**: REST API with JSON/XML
**Authentication**: None required
**Rate Limit**: Reasonable use policy
**Data Format**: JSON with nested structure
**Example**:
```
GET https://api.worldbank.org/v2/country/USA/indicator/NY.GDP.MKTP.CD?format=json&per_page=100
```
**Coverage**: Global development data, all countries

### 3. DBnomics (Multiple Providers)
**URL**: `https://api.db.nomics.world/v22/`
**Method**: REST API with JSON
**Authentication**: None required
**Rate Limit**: No official limits
**Data Format**: JSON with series data
**Example**:
```
GET https://api.db.nomics.world/v22/series?series_ids=AMECO/ZUTN/EA19.1.0.0.0.ZUTN
```
**Coverage**: 80+ statistical agencies worldwide

### 4. Eurostat (European Statistics)
**URL**: `https://ec.europa.eu/eurostat/api/dissemination/statistics/1.0/`
**Method**: REST API with JSON-stat
**Authentication**: None required
**Rate Limit**: Fair use policy
**Data Format**: JSON-stat format
**Example**:
```
GET https://ec.europa.eu/eurostat/api/dissemination/statistics/1.0/data/nama_10_gdp?format=JSON
```
**Coverage**: European Union statistics

### 5. OECD Data
**URL**: `https://stats.oecd.org/SDMX-JSON/`
**Method**: SDMX REST API
**Authentication**: None required
**Rate Limit**: Reasonable use
**Data Format**: SDMX-JSON
**Example**:
```
GET https://stats.oecd.org/SDMX-JSON/data/QNA/USA.B1_GE.CQRSA.Q/all?startPeriod=2020
```
**Coverage**: OECD member countries economic data

### 6. Alpha Vantage (Stock Market Data)
**URL**: `https://www.alphavantage.co/query`
**Method**: REST API with JSON
**Authentication**: Free API key (25 calls/day)
**Rate Limit**: 25 requests per day (free tier)
**Data Format**: JSON with time series
**Example**:
```
GET https://www.alphavantage.co/query?function=TIME_SERIES_DAILY&symbol=MSFT&apikey=YOUR_KEY
```
**Coverage**: Global stocks, forex, crypto

### 7. Yahoo Finance (Unofficial)
**URL**: `https://query1.finance.yahoo.com/v7/finance/`
**Method**: CSV download endpoints
**Authentication**: None required
**Rate Limit**: Fair use policy
**Data Format**: CSV format
**Example**:
```
GET https://query1.finance.yahoo.com/v7/finance/download/AAPL?period1=1609459200&period2=1640995200&interval=1d&events=history
```
**Coverage**: Global stocks, commodities, currencies

### 8. Quandl (Now part of Nasdaq)
**URL**: `https://www.quandl.com/api/v3/`
**Method**: REST API with JSON
**Authentication**: Free account (50,000 calls/day)
**Rate Limit**: 50 calls/day anonymous, 50,000 with account
**Data Format**: JSON with dataset
**Example**:
```
GET https://www.quandl.com/api/v3/datasets/WIKI/FB/data.json?rows=100
```
**Coverage**: Alternative data, commodities, economics

### 9. Bank of Canada
**URL**: `https://www.bankofcanada.ca/valet/`
**Method**: REST API with JSON/CSV/XML
**Authentication**: None required
**Rate Limit**: Reasonable use
**Data Format**: Multiple formats
**Example**:
```
GET https://www.bankofcanada.ca/valet/observations/FXUSDCAD/json
```
**Coverage**: Canadian financial and economic data

### 10. Bank of England
**URL**: `https://www.bankofengland.co.uk/statistics/`
**Method**: Statistical API with JSON
**Authentication**: None required
**Rate Limit**: Fair use
**Data Format**: JSON
**Example**:
```
GET http://www.bankofengland.co.uk/boeapps/iadb/fromshowcolumns.asp?csv.x=yes&SeriesCodes=IUDBEDR
```
**Coverage**: UK monetary and financial data

### 11. Australian Bureau of Statistics
**URL**: `https://api.data.abs.gov.au/`
**Method**: SDMX REST API
**Authentication**: None required
**Rate Limit**: Fair use
**Data Format**: SDMX-JSON
**Example**:
```
GET https://api.data.abs.gov.au/data/ABS,CPI,1.0.0/1.50.10001.10.Q
```
**Coverage**: Australian economic statistics

### 12. Statistics Canada
**URL**: `https://www150.statcan.gc.ca/t1/wds/rest/`
**Method**: REST API with JSON
**Authentication**: None required
**Rate Limit**: Fair use
**Data Format**: JSON
**Example**:
```
GET https://www150.statcan.gc.ca/t1/wds/rest/getFullTableDownloadCSV/en/18100004
```
**Coverage**: Canadian statistics

### 13. IMF (International Monetary Fund)
**URL**: `http://dataservices.imf.org/REST/SDMX_JSON.svc/`
**Method**: SDMX REST API
**Authentication**: None required
**Rate Limit**: Fair use
**Data Format**: SDMX-JSON
**Example**:
```
GET http://dataservices.imf.org/REST/SDMX_JSON.svc/CompactData/IFS/M.US.PMP_IX?startPeriod=2020
```
**Coverage**: Global economic and financial data

### 14. ECB (European Central Bank)
**URL**: `https://sdw-wsrest.ecb.europa.eu/service/`
**Method**: SDMX REST API
**Authentication**: None required
**Rate Limit**: Fair use
**Data Format**: SDMX-JSON
**Example**:
```
GET https://sdw-wsrest.ecb.europa.eu/service/data/EXR/D.USD.EUR.SP00.A
```
**Coverage**: European monetary data

### 15. IEX Cloud (Financial Data)
**URL**: `https://cloud.iexapis.com/`
**Method**: REST API with JSON
**Authentication**: Free tier with token
**Rate Limit**: 500 calls/day (free tier)
**Data Format**: JSON
**Example**:
```
GET https://cloud.iexapis.com/stable/stock/aapl/quote?token=YOUR_TOKEN
```
**Coverage**: US stock market data

## Cryptocurrency Data Sources

### 16. CoinGecko
**URL**: `https://api.coingecko.com/api/v3/`
**Method**: REST API with JSON
**Authentication**: None required (free tier)
**Rate Limit**: 10-50 calls/minute
**Data Format**: JSON
**Example**:
```
GET https://api.coingecko.com/api/v3/coins/bitcoin/market_chart?vs_currency=usd&days=365
```
**Coverage**: Cryptocurrency prices and market data

### 17. CoinCap
**URL**: `https://api.coincap.io/v2/`
**Method**: REST API with JSON
**Authentication**: None required
**Rate Limit**: 200 requests/minute
**Data Format**: JSON
**Example**:
```
GET https://api.coincap.io/v2/assets/bitcoin/history?interval=d1
```
**Coverage**: Cryptocurrency market data

### 18. CryptoCompare
**URL**: `https://min-api.cryptocompare.com/`
**Method**: REST API with JSON
**Authentication**: Free API key
**Rate Limit**: 100,000 calls/month (free)
**Data Format**: JSON
**Example**:
```
GET https://min-api.cryptocompare.com/data/v2/histoday?fsym=BTC&tsym=USD&limit=365
```
**Coverage**: Cryptocurrency data

## Alternative Data Sources

### 19. Google Sheets (Public)
**URL**: `https://docs.google.com/spreadsheets/`
**Method**: CSV export URL
**Authentication**: None for public sheets
**Rate Limit**: Google's fair use
**Data Format**: CSV
**Example**:
```
GET https://docs.google.com/spreadsheets/d/{SHEET_ID}/export?format=csv&gid={SHEET_GID}
```
**Coverage**: Any public spreadsheet data

### 20. GitHub Raw Files
**URL**: `https://raw.githubusercontent.com/`
**Method**: Direct file download
**Authentication**: None for public repos
**Rate Limit**: GitHub's fair use
**Data Format**: CSV, JSON, etc.
**Example**:
```
GET https://raw.githubusercontent.com/datasets/gdp/master/data/gdp.csv
```
**Coverage**: Open datasets hosted on GitHub

### 21. Data.gov (US Government)
**URL**: `https://catalog.data.gov/api/`
**Method**: CKAN API with JSON
**Authentication**: None required
**Rate Limit**: Fair use
**Data Format**: JSON, CSV
**Example**:
```
GET https://catalog.data.gov/api/3/action/package_search?q=gdp
```
**Coverage**: US government datasets

### 22. Open Data Portal APIs
**URL**: Various (city/country specific)
**Method**: REST APIs (usually CKAN-based)
**Authentication**: Usually none
**Rate Limit**: Varies
**Data Format**: JSON, CSV
**Examples**:
- Toronto: `https://ckan0.cf.opendata.inter.prod-toronto.ca/api/3/`
- UK: `https://data.gov.uk/api/`
- Australia: `https://data.gov.au/api/`

### 23. RSS/News Feeds
**URL**: Various news sources
**Method**: RSS/XML parsing
**Authentication**: None
**Rate Limit**: Per source policy
**Data Format**: RSS/XML
**Example**:
```
GET https://feeds.bbci.co.uk/news/business/rss.xml
```
**Coverage**: News sentiment, events data

### 24. Wikipedia API
**URL**: `https://en.wikipedia.org/api/rest_v1/`
**Method**: REST API with JSON
**Authentication**: None required
**Rate Limit**: 200 requests/second
**Data Format**: JSON
**Example**:
```
GET https://en.wikipedia.org/api/rest_v1/page/summary/Gross_domestic_product
```
**Coverage**: Economic definitions, historical data tables

### 25. FMP (Financial Modeling Prep)
**URL**: `https://financialmodelingprep.com/api/`
**Method**: REST API with JSON
**Authentication**: Free API key
**Rate Limit**: 250 calls/day (free)
**Data Format**: JSON
**Example**:
```
GET https://financialmodelingprep.com/api/v3/historical-price-full/AAPL?apikey=YOUR_KEY
```
**Coverage**: Financial statements, stock prices

### 26. Polygon.io
**URL**: `https://api.polygon.io/`
**Method**: REST API with JSON
**Authentication**: Free tier available
**Rate Limit**: 5 calls/minute (free)
**Data Format**: JSON
**Example**:
```
GET https://api.polygon.io/v2/aggs/ticker/AAPL/range/1/day/2023-01-09/2023-01-09?apikey=YOUR_KEY
```
**Coverage**: Market data

### 27. Twelve Data
**URL**: `https://api.twelvedata.com/`
**Method**: REST API with JSON
**Authentication**: Free API key
**Rate Limit**: 800 calls/day (free)
**Data Format**: JSON
**Example**:
```
GET https://api.twelvedata.com/time_series?symbol=MSFT&interval=1day&outputsize=30&apikey=YOUR_KEY
```
**Coverage**: Global market data

### 28. EOD Historical Data
**URL**: `https://eodhistoricaldata.com/api/`
**Method**: REST API with JSON
**Authentication**: Free trial available
**Rate Limit**: 20 calls/day (free)
**Data Format**: JSON, CSV
**Example**:
```
GET https://eodhistoricaldata.com/api/eod/AAPL.US?api_token=YOUR_TOKEN&fmt=json
```
**Coverage**: Historical stock data

### 29. Stooq
**URL**: `https://stooq.com/q/d/l/`
**Method**: CSV download
**Authentication**: None required
**Rate Limit**: Fair use
**Data Format**: CSV
**Example**:
```
GET https://stooq.com/q/d/l/?s=^spx&i=d
```
**Coverage**: Global market indices

### 30. Trading Economics
**URL**: `https://api.tradingeconomics.com/`
**Method**: REST API with JSON
**Authentication**: Free trial, then paid
**Rate Limit**: Varies by plan
**Data Format**: JSON
**Example**:
```
GET https://api.tradingeconomics.com/country/united%20states?c=guest:guest
```
**Coverage**: Global economic indicators

## Data Processing Methods

### Method 1: Direct API Calls
```php
function fetch_api_data($url, $headers = []) {
    $response = wp_remote_get($url, [
        'headers' => $headers,
        'timeout' => 30
    ]);
    
    if (is_wp_error($response)) {
        return false;
    }
    
    return json_decode(wp_remote_retrieve_body($response), true);
}
```

### Method 2: CSV Download & Parse
```php
function fetch_csv_data($url) {
    $csv_data = wp_remote_get($url);
    if (is_wp_error($csv_data)) {
        return false;
    }
    
    $lines = explode("\n", wp_remote_retrieve_body($csv_data));
    $result = [];
    foreach ($lines as $line) {
        $result[] = str_getcsv($line);
    }
    return $result;
}
```

### Method 3: XML/RSS Parsing
```php
function fetch_xml_data($url) {
    $xml_data = wp_remote_get($url);
    if (is_wp_error($xml_data)) {
        return false;
    }
    
    return simplexml_load_string(wp_remote_retrieve_body($xml_data));
}
```

### Method 4: SDMX-JSON Parsing
```php
function parse_sdmx_json($data) {
    // SDMX-JSON has specific structure
    $observations = [];
    if (isset($data['dataSets'][0]['observations'])) {
        foreach ($data['dataSets'][0]['observations'] as $key => $obs) {
            // Parse observation key and value
            $observations[] = [
                'date' => $this->parse_sdmx_date($key),
                'value' => $obs[0]
            ];
        }
    }
    return $observations;
}
```

This comprehensive list provides 30+ free data sources with specific implementation methods for each source type.