# ZC DMT Calculations & Formula Guide
## Complete Guide to Economic Analysis Templates

This guide explains all the calculation templates available in the ZC DMT plugin and how to use them for economic analysis.

## How the System Works

### 1. Template-Based Approach
Instead of writing complex formulas, you simply:
1. **Choose a Template** - Select from pre-built economic analysis templates
2. **Select Indicator** - Choose which economic indicator to analyze
3. **Configure Settings** - Adjust periods/parameters if needed
4. **Create Analysis** - One click creates both calculation and chart-ready indicator

### 2. Auto-Integration with Charts
When you create a calculation:
- ✅ **Automatically saved** to calculations database
- ✅ **Automatically added** as new indicator (appears in Charts Builder)
- ✅ **Immediately available** for charting with `[zc_chart_calculation]` shortcode
- ✅ **Searchable** in dashboard search panel

## Available Templates

### 📈 Growth Analysis Templates

#### 1. Annual Growth Rate
**What it does:** Calculates year-over-year percentage change
**Formula:** `ROC(INDICATOR, 12)`
**Best for:** GDP growth, inflation trends, employment changes
**Example:** If GDP was 100 in Jan 2023 and 105 in Jan 2024, shows +5% growth

**Use Cases:**
- GDP annual growth rate
- Inflation year-over-year change
- Employment annual change
- Population growth analysis

#### 2. Quarterly Growth Rate  
**What it does:** Calculates quarter-over-quarter percentage change
**Formula:** `ROC(INDICATOR, 3)`
**Best for:** Quarterly economic reports, seasonal analysis
**Example:** Q4 vs Q3 GDP change

**Use Cases:**
- Quarterly GDP reports
- Seasonal employment changes
- Quarterly earnings analysis
- Business cycle tracking

#### 3. Monthly Growth Rate
**What it does:** Calculates month-over-month percentage change  
**Formula:** `ROC(INDICATOR, 1)`
**Best for:** Monthly economic indicators, short-term trends
**Example:** Monthly inflation change, employment reports

**Use Cases:**
- Monthly inflation reports
- Employment monthly changes
- Retail sales month-to-month
- Industrial production changes

### 📊 Trend Analysis Templates

#### 4. 12-Month Moving Average
**What it does:** Smooths out short-term fluctuations with annual average
**Formula:** `MA(INDICATOR, 12)`
**Best for:** Long-term trend identification, removing seasonal noise
**Example:** Shows underlying annual trend without monthly volatility

**Use Cases:**
- Unemployment trend analysis
- Inflation underlying trend
- GDP trend without quarterly noise
- Long-term economic direction

#### 5. 6-Month Moving Average
**What it does:** Medium-term trend analysis with semi-annual average
**Formula:** `MA(INDICATOR, 6)`
**Best for:** Medium-term trend analysis, business planning
**Example:** 6-month average removes short-term noise but shows recent trends

**Use Cases:**
- Business planning horizons
- Investment trend analysis
- Policy impact assessment
- Medium-term forecasting

#### 6. 3-Month Moving Average
**What it does:** Short-term trend analysis with quarterly average
**Formula:** `MA(INDICATOR, 3)`
**Best for:** Recent trend identification, tactical decisions
**Example:** Shows recent 3-month direction

**Use Cases:**
- Recent economic direction
- Tactical business decisions
- Short-term policy impacts
- Market timing analysis

### ⚡ Momentum Analysis Templates

#### 7. RSI (14 periods)
**What it does:** Relative Strength Index for momentum analysis
**Formula:** `RSI(INDICATOR, 14)`
**Best for:** Identifying overbought/oversold conditions
**Example:** RSI above 70 = potentially overbought, below 30 = potentially oversold

**Use Cases:**
- Stock market momentum
- Economic cycle analysis
- Commodity price momentum
- Currency strength analysis

#### 8. Momentum (10 periods)
**What it does:** Price momentum over specified periods
**Formula:** `MOMENTUM(INDICATOR, 10)`
**Best for:** Trend strength measurement
**Example:** Positive momentum = upward trend, negative = downward trend

**Use Cases:**
- Economic momentum tracking
- Trend strength measurement
- Acceleration/deceleration analysis
- Turning point identification

### 📊 Statistical Analysis Templates

#### 9. Historical Average
**What it does:** Calculates average of all historical values
**Formula:** `AVG(INDICATOR)`
**Best for:** Baseline comparison, historical context
**Example:** Current value vs historical average

**Use Cases:**
- Historical baseline comparison
- "Normal" level identification
- Deviation analysis
- Long-term context

#### 10. Historical Maximum
**What it does:** Finds highest value in the series
**Formula:** `MAX(INDICATOR)`
**Best for:** Peak identification, ceiling analysis
**Example:** Highest unemployment rate, peak inflation

**Use Cases:**
- Crisis peak identification
- Historical ceiling analysis
- Worst-case scenarios
- Record-breaking events

#### 11. Historical Minimum
**What it does:** Finds lowest value in the series
**Formula:** `MIN(INDICATOR)`
**Best for:** Floor identification, best performance
**Example:** Lowest unemployment, minimum interest rates

**Use Cases:**
- Best performance identification
- Historical floor analysis
- Recovery benchmarks
- Optimal conditions

## How to Use Templates

### Step 1: Access the Calculator
1. Go to **ZC DMT → Calculations** in WordPress admin
2. You'll see template cards organized by category

### Step 2: Choose Template
1. **Browse categories:** Growth, Trend, Momentum, Statistics
2. **Read descriptions** to understand what each template does
3. **Click "Use This Template"** on your chosen analysis

### Step 3: Configure Template
1. **Select Indicator:** Choose from dropdown of your available indicators
2. **Set Periods:** Adjust time periods if applicable (e.g., 12 months, 14 periods)
3. **Custom Name:** Optionally provide custom name (auto-generated if empty)
4. **Preview Formula:** See exactly what formula will be created

### Step 4: Create Analysis
1. **Click "Create Analysis"**
2. **Calculation saved** to database
3. **New indicator created** automatically (appears in Charts Builder)
4. **Ready for charting** immediately

## Using Calculation Results

### In Charts Builder
1. Go to **ZC DMT → Charts Builder**
2. Search for your calculation name
3. It appears as a regular indicator
4. Create charts normally

### With Shortcodes
```php
// Display calculation as chart
[zc_chart_calculation id="annual-growth-rate-gdp-us" height="600"]

// Use in dashboard
[zc_economic_dashboard default_indicator="quarterly-growth-rate-unemployment-us"]

// Compare with other indicators
[zc_chart_comparison indicators="gdp_us,annual-growth-rate-gdp-us"]
```

## Real-World Examples

### Example 1: GDP Growth Analysis
**Goal:** Track US GDP annual growth rate
**Template:** Annual Growth Rate
**Indicator:** GDP_US
**Result:** Shows year-over-year GDP growth percentage
**Usage:** `[zc_chart_calculation id="annual-growth-rate-gdp-us"]`

### Example 2: Unemployment Trend
**Goal:** See unemployment trend without monthly noise
**Template:** 12-Month Moving Average  
**Indicator:** UNEMPLOYMENT_US
**Result:** Smooth unemployment trend line
**Usage:** `[zc_chart_calculation id="12-month-moving-average-unemployment-us"]`

### Example 3: Stock Market Momentum
**Goal:** Analyze stock market momentum
**Template:** RSI (14 periods)
**Indicator:** SP500_INDEX
**Result:** RSI momentum indicator (0-100 scale)
**Usage:** `[zc_chart_calculation id="rsi-14-periods-sp500-index"]`

### Example 4: Inflation Baseline
**Goal:** Compare current inflation to historical average
**Template:** Historical Average
**Indicator:** INFLATION_US
**Result:** Single value showing historical average inflation
**Usage:** Compare current inflation chart with this baseline

## Technical Details

### Formula Syntax
- **Indicator names:** Use UPPERCASE (GDP_US, UNEMPLOYMENT_US)
- **Functions:** SUM, AVG, MIN, MAX, COUNT, ROC, MA, RSI, MOMENTUM
- **Parameters:** Numbers for periods (ROC(GDP_US, 12))
- **Case sensitive:** Function names must be UPPERCASE

### Data Requirements
- **Minimum data points:** Functions need sufficient historical data
- **ROC/MA/RSI:** Need at least as many points as periods specified
- **Date alignment:** Calculations align with original indicator dates
- **Missing data:** Handled gracefully (skipped in calculations)

### Output Types
- **Time Series:** Creates new data series for charting (most common)
- **Single Value:** Returns one number (like historical average)
- **New Indicator:** Saves as permanent indicator for future use

## Troubleshooting

### Common Issues

#### "Invalid expression" Error
**Cause:** Formula syntax error
**Solution:** Use templates instead of manual formulas

#### "Indicator not found" Error  
**Cause:** Indicator slug doesn't exist or is inactive
**Solution:** Check indicator exists in Indicators menu

#### "No data available" Error
**Cause:** Indicator has no data points
**Solution:** Ensure indicator has been populated with data

#### Calculation not appearing in Charts
**Cause:** Auto-indicator creation failed
**Solution:** Check Indicators menu - calculation should appear with source_type "calculation"

### Best Practices

1. **Start Simple:** Use basic templates (Average, Growth Rate) first
2. **Test with Known Data:** Use indicators you know have good data
3. **Meaningful Names:** Use descriptive names for calculations
4. **Appropriate Periods:** 
   - Monthly data: Use 12 periods for annual analysis
   - Quarterly data: Use 4 periods for annual analysis
   - Daily data: Use 252 periods for annual analysis (trading days)

## Advanced Usage

### Combining Templates
You can create multiple calculations and compare them:

```php
// Create these calculations:
// 1. Annual Growth Rate for GDP
// 2. 12-Month Moving Average for GDP  
// 3. Historical Average for GDP

// Then compare all three:
[zc_chart_comparison indicators="gdp_us,annual-growth-rate-gdp-us,12-month-moving-average-gdp-us"]
```

### Economic Analysis Workflows

#### Business Cycle Analysis
1. **GDP Growth Rate** (quarterly) - Economic expansion/contraction
2. **Unemployment Moving Average** (12-month) - Employment trend
3. **Inflation Growth Rate** (annual) - Price stability
4. **Compare all three** to see business cycle phase

#### Investment Analysis  
1. **Stock Index RSI** (14 periods) - Momentum
2. **Interest Rate Moving Average** (6-month) - Monetary policy trend
3. **GDP Growth Rate** (annual) - Economic backdrop
4. **Inflation Historical Average** - Baseline comparison

#### Policy Impact Assessment
1. **Before/After Moving Averages** - Compare periods before and after policy
2. **Growth Rate Analysis** - Measure acceleration/deceleration
3. **Historical Context** - Compare to historical averages and extremes

## Next Steps

After creating calculations:
1. **View in Indicators** - Check ZC DMT → Indicators to see new calculated indicators
2. **Create Charts** - Use Charts Builder to create visualizations
3. **Embed in Content** - Use shortcodes to display in posts/pages
4. **Monitor Performance** - Track how calculations perform over time

---

*This guide covers all calculation templates. For technical support or custom formulas, refer to the advanced formula builder or contact support.*
