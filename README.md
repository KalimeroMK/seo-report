# SEO Report (framework-agnostic PHP)

SEO analysis library: analyze a URL or sitemap, run SEO/performance/security checks, and **return an API result** (no database). Works with **Laravel**, **Yii**, or plain PHP.

## Requirements

- PHP 8.2+
- ext-dom, ext-json
- guzzlehttp/guzzle ^7.2

**Optional (for advanced features):**
- Docker 20.10+ (for Core Web Vitals, JS rendering, screenshots)
- Docker Compose 2.0+

**No framework required.** Use the same package in Laravel, Yii, Symfony, or standalone.

## Installation

```bash
composer require kalimeromk/seo-report
```

## Quick Start (CLI)

Use directly from terminal:

```bash
# Analyze a URL
php run_test.php ogledalo.mk

# With full URL
php run_test.php https://ogledalo.mk/

# Analyze sitemap
php run_test.php https://example.com/sitemap.xml --sitemap

# Save to file
php run_test.php ogledalo.mk > report.json

# Pretty format with jq
php run_test.php ogledalo.mk | jq
```

**Output:** JSON API response with analysis results.

## Checks Covered

The report is grouped by categories and includes checks like:

- **SEO:** title, meta description, headings (H1-H6), keyword consistency, image alts, canonical, hreflang, robots, noindex, in-page links, nofollow, language, favicon, friendly URLs
- **Content Quality:** duplicate content detection, internal linking analysis, content readability (Flesch score), keyword stuffing detection, thin content detection
- **Performance:** compression, load time, TTFB, page size, HTTP requests, cache headers, redirects, cookie-free domains, empty src/href, image optimization, defer JS, render blocking, minification, DOM size, doctype, resource hints (preconnect, preload, prefetch)
- **Security:** HTTPS, HTTP/2, mixed content, server signature, unsafe cross-origin links, HSTS, plaintext emails, security headers (CSP, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy)
- **Mobile:** viewport configuration, touch target size, font size readability, flexible layout detection
- **Misc:** structured data (Open Graph, Twitter Cards, Schema.org validation), meta viewport, charset, sitemap, social links, content length, text/HTML ratio, inline CSS, deprecated HTML tags, llms.txt, flash, iframes, accessibility checks
- **Technology:** server IP, DNS, DMARC/SPF, SSL certificate, reverse DNS, analytics and tech detection

### Advanced Features (with Docker)

With Docker installed, you get additional real-world analysis:

- **Core Web Vitals:** Real LCP, FID, CLS, TTFB using Google Lighthouse (no API key needed)
- **JavaScript Rendering:** Detect React/Vue/Angular, hydration issues, console errors
- **Screenshots:** Desktop, mobile, and tablet screenshots
- **Advanced Technology Detection:** Detailed tech stack using Wappalyzer

---

## Docker Setup (Optional but Recommended)

For **real Core Web Vitals** and **JavaScript SEO analysis**, install Docker:

```bash
# 1. Build Docker images (one-time setup)
cd vendor/kalimeromk/seo-report/docker  # or wherever the package is
docker-compose build

# 2. Verify it works
docker-compose run lighthouse https://example.com
```

**That's it!** No API keys, no registration, no rate limits.

### Why Docker?

| Feature | Without Docker | With Docker |
|---------|---------------|-------------|
| Core Web Vitals | ❌ Proxy estimates only | ✅ Real Chrome metrics (LCP, FID, CLS) |
| JavaScript SEO | ❌ Can't analyze JS | ✅ Detects React/Vue/Hydration issues |
| Screenshots | ❌ Not available | ✅ Desktop/Mobile/Tablet screenshots |
| Cost | Free | Free (just your server) |
| Rate Limits | None | None |

---

## What It Returns

The package returns a JSON API response with this structure:

```json
{
    "url": "https://ogledalo.mk/",
    "score": 83.33,
    "generated_at": "2026-01-28T08:44:42+00:00",
    "results": {
        "title": {
            "passed": true,
            "importance": "high",
            "value": "Ogledalo - Orthodox news portal & daily aggregator"
        },
        "meta_description": {
            "passed": true,
            "importance": "high",
            "value": "Ogledalo is an Orthodox news portal and aggregator..."
        },
        "headings": {
            "passed": false,
            "importance": "high",
            "value": {
                "h1": ["Ogledalo - Orthodox news portal & daily aggregator"],
                "h2": ["Article 1", "Article 2", ...],
                "h3": [...]
            },
            "errors": {
                "duplicate": null
            }
        },
        "load_time": {
            "passed": true,
            "importance": "medium",
            "value": 0.94
        },
        "page_size": {
            "passed": true,
            "importance": "medium",
            "value": 14886
        },
        "https_encryption": {
            "passed": true,
            "importance": "high",
            "value": "https://ogledalo.mk/"
        },
        "structured_data": {
            "passed": true,
            "importance": "medium",
            "value": {
                "Open Graph": { "og:title": "...", "og:description": "..." },
                "Twitter": { "twitter:card": "..." },
                "Schema.org": { "@context": "https://schema.org", ... }
            }
        }
    },
    "categories": {
        "seo": ["title", "title_optimal_length", "meta_description", "meta_description_optimal_length", "headings", "h1_usage", "header_tag_usage", "content_keywords", "keyword_consistency", "image_keywords", "open_graph", "twitter_cards", "seo_friendly_url", "canonical_tag", "canonical_self_reference", "hreflang", "404_page", "robots", "noindex", "robots_directives", "noindex_header", "in_page_links", "nofollow_links", "link_url_readability", "language", "favicon", "duplicate_h1", "title_uniqueness", "thin_content", "content_uniqueness", "meta_description_quality", "canonical_effectiveness", "link_ratio", "link_distribution", "contextual_links", "outbound_links", "anchor_text_quality", "url_length", "url_depth", "url_parameters", "url_format", "trailing_slash", "url_case", "url_encoding", "international_seo", "pagination"],
        "performance": ["text_compression", "brotli_compression", "load_time", "ttfb", "page_size", "http_requests", "static_cache_headers", "expires_headers", "avoid_redirects", "redirect_chains", "cookie_free_domains", "empty_src_or_href", "image_format", "image_dimensions", "image_lazy_loading", "image_size_optimization", "lcp_proxy", "cls_proxy", "defer_javascript", "render_blocking_resources", "minification", "dom_size", "doctype", "preconnect_hints", "dns_prefetch_hints", "preload_hints", "prefetch_hints", "resource_hints_coverage"],
        "security": ["https_encryption", "http2", "mixed_content", "server_signature", "unsafe_cross_origin_links", "hsts", "plaintext_email", "content_security_policy", "x_frame_options", "x_content_type_options", "referrer_policy", "permissions_policy", "cross_origin_policies", "security_headers_score"],
        "mobile": ["viewport_config", "touch_target_size", "font_size_readability", "flexible_layout", "mobile_friendly_patterns", "zoom_accessibility"],
        "miscellaneous": ["structured_data", "structured_data_validation", "meta_viewport", "charset", "sitemap", "social", "content_length", "text_html_ratio", "inline_css", "deprecated_html_tags", "llms_txt", "flash_content", "iframes", "form_labels", "skip_navigation", "aria_usage", "heading_hierarchy", "link_text_quality", "table_accessibility"],
        "technology": ["server_ip", "dns_servers", "dmarc_record", "spf_record", "ssl_certificate", "reverse_dns", "analytics", "technology_detection"]
    }
}
```

### Result Structure

Each check in `results` has:

- **`passed`** (bool) - Whether the check passed
- **`importance`** ('high' | 'medium' | 'low') - Priority level
- **`value`** (mixed) - The actual value found (string, number, array, etc.)
- **`errors`** (array, optional) - Error details if `passed` is false

**Example checks:**
- `title` - Page title (string)
- `meta_description` - Meta description (string)
- `headings` - All headings h1-h6 (array)
- `header_tag_usage` - H2-H6 heading counts and usage (array)
- `load_time` - Page load time in seconds (float)
- `page_size` - HTML size in bytes (int)
- `https_encryption` - Whether HTTPS is used (bool via passed)
- `structured_data` - Open Graph, Twitter, Schema.org (array)
- `http_requests` - Count of JS/CSS/Images/etc. (array)
- And 70+ more checks...

## Usage in Code

### Basic Usage

```php
use KalimeroMK\SeoReport\Config\SeoReportConfig;
use KalimeroMK\SeoReport\SeoAnalyzer;

// Create config (empty = use defaults)
$config = new SeoReportConfig([]);

// Create analyzer
$analyzer = new SeoAnalyzer($config);

// Analyze URL
$result = $analyzer->analyze('https://ogledalo.mk/');

// Get values
echo $result->getUrl();        // "https://ogledalo.mk/"
echo $result->getScore();      // 83.33
echo $result->getGeneratedAt(); // DateTimeImmutable

// Get all results
$results = $result->getResults();
echo $results['title']['value'];        // "Ogledalo - Orthodox news portal..."
echo $results['load_time']['value'];    // 0.94
echo $results['page_size']['value'];    // 14886

// Check if specific check passed
if ($results['title']['passed']) {
    echo "Title is OK";
}

// Get API response (JSON)
$json = $result->toJson();
$array = $result->toArray();
```

### Advanced Usage (with Docker)

Use `SeoAnalyzerWithDocker` instead of `SeoAnalyzer` to get real Core Web Vitals and JavaScript analysis:

```php
use KalimeroMK\SeoReport\SeoAnalyzerWithDocker;
use KalimeroMK\SeoReport\Config\SeoReportConfig;

$config = new SeoReportConfig([]);

// Create analyzer with Docker support
$analyzer = new SeoAnalyzerWithDocker($config);

// Analyze URL - automatically includes Docker checks if available
$result = $analyzer->analyze('https://example.com');

// Check if Docker is available
if ($analyzer->hasDockerSupport()) {
    echo "Docker features enabled!";
}

// Get specific Docker-based results
$cwv = $analyzer->getCoreWebVitals('https://example.com');
echo "LCP: " . $cwv['performance']['metrics']['lcp'] . "ms";

// Take screenshot
$screenshot = $analyzer->takeScreenshot('https://example.com', 'mobile');
$base64Image = $screenshot['screenshot']['base64'];

// JavaScript analysis
$js = $analyzer->getJavaScriptAnalysis('https://example.com');
echo "Framework: " . $js['pageInfo']['framework']; // React, Vue, etc.
echo "Console errors: " . $js['console']['errors'];
```

**Without Docker?** No problem! `SeoAnalyzerWithDocker` gracefully falls back to basic analysis.

### Custom Configuration

```php
use KalimeroMK\SeoReport\Config\SeoReportConfig;

$config = new SeoReportConfig([
    'request_timeout' => 10,              // HTTP timeout in seconds
    'request_http_version' => '2',         // HTTP version: '1.1' or '2'
    'request_user_agent' => 'MyBot/1.0',  // Custom user agent
    'request_proxy' => "http://proxy1:8080\nhttp://proxy2:8080", // Proxy list
    
    'sitemap_links' => 100,               // Max URLs from sitemap (-1 = unlimited)
    
    'report_limit_min_title' => 10,       // Min title length
    'report_limit_max_title' => 70,       // Max title length
    'report_limit_min_words' => 300,      // Min words on page
    'report_limit_max_links' => 200,      // Max links on page
    'report_limit_load_time' => 3,       // Max load time (seconds)
    'report_limit_page_size' => 500000,   // Max page size (bytes)
    
    'report_score_high' => 10,            // Points for high importance checks
    'report_score_medium' => 5,           // Points for medium importance checks
    'report_score_low' => 0,              // Points for low importance checks
]);

$analyzer = new SeoAnalyzer($config);
$result = $analyzer->analyze('https://example.com');
```

### Analyze Sitemap

```php
// Analyze all URLs from sitemap
$results = $analyzer->analyzeSitemap('https://example.com/sitemap.xml');

// Limit to first 50 URLs
$results = $analyzer->analyzeSitemap('https://example.com/sitemap.xml', 50);

// $results is array of AnalysisResult
foreach ($results as $result) {
    echo $result->getUrl() . ' - Score: ' . $result->getScore() . "\n";
}
```

### REST API Example (Laravel)

```php
use KalimeroMK\SeoReport\Config\SeoReportConfig;
use KalimeroMK\SeoReport\SeoAnalyzer;
use KalimeroMK\SeoReport\SeoAnalyzerException;

class SeoReportController extends Controller
{
    public function analyze(Request $request)
    {
        try {
            $config = new SeoReportConfig(config('seo-report'));
            $analyzer = new SeoAnalyzer($config);
            $result = $analyzer->analyze($request->input('url'));
            
            return response()->json($result->toArray());
        } catch (SeoAnalyzerException $e) {
            return response()->json([
                'error' => 'Could not analyze URL: ' . $e->getMessage()
            ], 400);
        }
    }
}
```

### REST API Example (Yii)

```php
use KalimeroMK\SeoReport\Config\SeoReportConfig;
use KalimeroMK\SeoReport\SeoAnalyzer;

class SeoReportController extends \yii\web\Controller
{
    public function actionAnalyze()
    {
        $url = \Yii::$app->request->get('url');
        
        $config = new SeoReportConfig(\Yii::$app->params['seo-report'] ?? []);
        $analyzer = new SeoAnalyzer($config);
        $result = $analyzer->analyze($url);
        
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        return $result->toArray();
    }
}
```

### Storing Results

The package **does not save to database**. Your app decides how to persist:

```php
// Laravel example
$result = $analyzer->analyze($url);

Report::create([
    'user_id' => auth()->id(),
    'url' => $result->getUrl(),
    'results' => $result->getResults(),  // JSON column
    'score' => $result->getScore(),
    'generated_at' => $result->getGeneratedAt(),
]);

// Yii example
$report = new Report();
$report->url = $result->getUrl();
$report->results = json_encode($result->getResults());
$report->score = $result->getScore();
$report->generated_at = $result->getGeneratedAt()->format('Y-m-d H:i:s');
$report->save();
```

## All Available Checks

### SEO (33+ checks)
- `title` - Page title tag
- `title_optimal_length` - Title optimal length (50-60 chars)
- `meta_description` - Meta description tag
- `meta_description_optimal_length` - Meta description optimal length (120-160 chars)
- `headings` - H1-H6 headings structure
- `header_tag_usage` - H2-H6 heading tag usage counts
- `content_keywords` - Keywords in title vs content
- `keyword_consistency` - Keyword distribution across title, meta description, headings
- `image_keywords` - Images with missing alt attributes
- `seo_friendly_url` - URL structure and keywords
- `canonical_tag` - Canonical link tag
- `hreflang` - Alternate hreflang links
- `404_page` - Custom 404 error page
- `robots` - robots.txt rules
- `noindex` - Noindex meta tag
- `noindex_header` - X-Robots-Tag header (noindex)
- `in_page_links` - Internal/external links count
- `nofollow_links` - Nofollow links count
- `link_url_readability` - URL structure of all links (unfriendly URLs)
- `language` - HTML lang attribute
- `favicon` - Favicon presence

**Content Quality & Duplicate Content:**
- `duplicate_h1` - Multiple H1 tags detection
- `title_uniqueness` - Title vs H1 duplication check
- `thin_content` - Low word count detection (< 300 words)
- `content_uniqueness` - Boilerplate content ratio analysis
- `meta_description_quality` - Meta description quality check
- `canonical_effectiveness` - Canonical tag implementation check
- `readability_score` - Flesch Reading Ease score
- `keyword_stuffing` - Excessive keyword density detection
- `title_quality` - Title format analysis (power words, numbers)
- `content_structure` - Paragraph length and heading hierarchy

**Internal Linking:**
- `link_ratio` - Internal vs external link ratio
- `link_distribution` - Duplicate links and excessive links
- `contextual_links` - Descriptive vs navigational links
- `outbound_links` - Self-referencing link detection
- `anchor_text_quality` - Empty/short anchor text detection

**URL Structure:**
- `url_length` - URL length validation (< 115 chars)
- `url_depth` - Path depth analysis (max 3 levels)
- `url_parameters` - Query parameter analysis
- `url_format` - Underscores, case, special chars check
- `trailing_slash` - Trailing slash consistency

**International SEO:**
- `hreflang_validation` - Valid language codes
- `html_lang` - HTML lang attribute validation
- `language_consistency` - HTML lang vs hreflang match
- `x_default_hreflang` - X-default fallback check

**Pagination:**
- `pagination_detection` - Pagination indicators
- `rel_next_prev` - Next/prev link detection
- `infinite_scroll` - Infinite scroll detection

### Performance (14+ checks)
- `text_compression` - Gzip/Brotli compression
- `load_time` - Page load time
- `page_size` - HTML file size
- `http_requests` - Number of resources (JS/CSS/Images)
- `image_format` - Modern image formats (WebP, AVIF)
- `defer_javascript` - Defer attribute on scripts
- `minification` - JS/CSS minification check
- `dom_size` - DOM nodes count
- `doctype` - DOCTYPE declaration
- `static_cache_headers` - Static asset caching
- `expires_headers` - Cache expiration headers
- `redirect_chains` - Redirect chain detection

**Resource Hints:**
- `preconnect_hints` - Preconnect to external domains
- `dns_prefetch_hints` - DNS prefetch hints
- `preload_hints` - Preload critical resources
- `prefetch_hints` - Prefetch next-page resources

### Security (14+ checks)
- `https_encryption` - HTTPS usage
- `http2` - HTTP/2 protocol
- `mixed_content` - HTTP resources on HTTPS page
- `server_signature` - Server header exposure
- `unsafe_cross_origin_links` - Links without rel="noopener"
- `hsts` - HTTP Strict Transport Security header
- `plaintext_email` - Plaintext emails in HTML

**Security Headers:**
- `content_security_policy` - CSP header validation
- `x_frame_options` - Clickjacking protection (DENY/SAMEORIGIN)
- `x_content_type_options` - MIME sniffing protection
- `referrer_policy` - Referrer information control
- `permissions_policy` - Feature policy restrictions
- `cross_origin_policies` - COOP/COEP/CORP headers
- `security_headers_score` - Overall security score (A-D rating)

### Mobile (6 checks)
- `viewport_config` - Viewport meta tag validation
- `touch_target_size` - Minimum 44x44px touch targets (WCAG 2.1)
- `font_size_readability` - Minimum 12px font size
- `flexible_layout` - Fixed width detection
- `mobile_friendly_patterns` - Flash, frames, deprecated plugins
- `zoom_accessibility` - User zoom prevention detection

### Miscellaneous (18+ checks)
- `structured_data` - Open Graph, Twitter Cards, Schema.org
- `meta_viewport` - Viewport meta tag
- `charset` - Character encoding
- `sitemap` - Sitemap in robots.txt
- `social` - Social media links
- `content_length` - Word count
- `text_html_ratio` - Text to HTML ratio
- `inline_css` - Inline CSS usage
- `deprecated_html_tags` - Deprecated HTML tags
- `llms_txt` - llms.txt file presence (for AI crawlers)
- `flash_content` - Flash/Shockwave content detection
- `iframes` - iFrames usage

**Structured Data Validation:**
- `json_ld_validation` - JSON-LD syntax validation
- `schema_requirements` - Required properties for common schemas
- `duplicate_ids` - Duplicate @id detection
- `structured_data_images` - Image URL validation
- `schema_context` - @context validation

**Accessibility:**
- `form_labels` - Form inputs with missing labels
- `skip_navigation` - Skip to content link
- `aria_usage` - ARIA roles and attributes validation
- `heading_hierarchy` - H1-H6 hierarchy validation
- `link_text_quality` - Generic link text detection
- `table_accessibility` - Table headers and captions

### Technology (8 checks, no external APIs)
- `server_ip` - Resolved server IP (gethostbyname)
- `dns_servers` - NS records
- `dmarc_record` - DMARC TXT record (_dmarc.domain)
- `spf_record` - SPF TXT record
- `ssl_certificate` - SSL/TLS certificate (valid, valid_from, valid_to, issuer_cn, subject_cn) — pure PHP, inspired by phpRank Software
- `reverse_dns` - PTR record for server IP (gethostbyaddr)
- `analytics` - Detected analytics (e.g. Google Analytics)
- `technology_detection` - Detected tech (e.g. jQuery, Font Awesome, Facebook Pixel)

### Docker-Based Checks (requires Docker)

These checks run only when Docker is available:

**Core Web Vitals (Real Chrome Metrics):**
- `core_web_vitals` - Real LCP, FID, CLS, TTFB using Google Lighthouse
  - `lcp` - Largest Contentful Paint (ms) - should be < 2500ms
  - `fid` - First Input Delay (ms) - should be < 100ms
  - `cls` - Cumulative Layout Shift - should be < 0.1
  - `ttfb` - Time to First Byte (ms)
  - `performance_score` - Lighthouse performance score (0-100)

**JavaScript Rendering Analysis:**
- `javascript_rendering` - Full browser JavaScript execution check
  - `framework` - Detected framework (React, Vue, Angular, Next.js, Nuxt.js)
  - `has_hydration` - Whether client-side hydration is detected
  - `title_rendered` - Whether title is rendered by JS
  - `h1_rendered` - Whether H1 is rendered by JS
  - `render_time_ms` - Total render time
  - `console_errors` - JavaScript console errors count

**Advanced Technology Detection:**
- `advanced_technology_detection` - Detailed tech stack using Wappalyzer
  - CMS, frameworks, analytics, hosting, CDN detection

**Screenshots:**
- `screenshot` - Available via `takeScreenshot()` method
  - Desktop, mobile, tablet viewports
  - Base64 encoded images
  - Visual metrics (CLS during load)

## Exceptions

```php
use KalimeroMK\SeoReport\SeoAnalyzerException;

try {
    $result = $analyzer->analyze('https://example.com');
} catch (SeoAnalyzerException $e) {
    // URL cannot be fetched (connection error, timeout, etc.)
    echo "Error: " . $e->getMessage();
}
```

## Docker FAQ

### Do I need Docker?
**No.** The package works great without Docker. Docker is only needed for:
- Real Core Web Vitals (LCP, FID, CLS)
- JavaScript rendering analysis
- Screenshots

Without Docker, you still get 70+ SEO checks.

### How much does Docker cost?
**Free.** You run it on your own server. No API fees, no rate limits.

### What are the system requirements for Docker?
- Docker 20.10+
- Docker Compose 2.0+
- 4GB+ RAM (for headless Chrome)
- 2GB disk space

### Can I use this in production?
**Yes.** Docker containers are isolated and run fresh on every request. No state is preserved between runs.

### Is caching used?
**No.** By default, all Docker-based checks return fresh results. This is intentional - you want to see if issues were fixed after making changes.

### What if Docker is not available?
`SeoAnalyzerWithDocker` gracefully falls back to basic analysis. No errors, just fewer features.

```php
$analyzer = new SeoAnalyzerWithDocker($config);

if ($analyzer->hasDockerSupport()) {
    echo "Full analysis with Core Web Vitals";
} else {
    echo "Basic analysis (no Docker available)";
}
```

### Example: Full Analysis with Docker

```php
use KalimeroMK\SeoReport\SeoAnalyzerWithDocker;
use KalimeroMK\SeoReport\Config\SeoReportConfig;

$config = new SeoReportConfig([]);
$analyzer = new SeoAnalyzerWithDocker($config);

$result = $analyzer->analyze('https://example.com');

// Results include Docker-based checks if available
$results = $result->getResults();

if (isset($results['core_web_vitals'])) {
    $cwv = $results['core_web_vitals']['value'];
    echo "LCP: {$cwv['lcp']}ms\n";
    echo "CLS: {$cwv['cls']}\n";
    echo "Performance Score: {$cwv['performance_score']}\n";
}

if (isset($results['javascript_rendering'])) {
    $js = $results['javascript_rendering']['value'];
    echo "Framework: {$js['framework']}\n";
    echo "Hydration: " . ($js['has_hydration'] ? 'Yes' : 'No') . "\n";
    echo "Console Errors: {$js['console_errors']}\n";
}
```

## Testing

```bash
# Run all tests
composer test

# Run integration test (scans real domain)
composer test -- --filter=OgledaloMkApiResponseTest

# Code quality
composer analyse     # PHPStan
composer rector-dry  # Rector dry-run
```

## Reference

SSL certificate check (pure PHP, no Spatie dependency) and DNS/IP logic are inspired by **phpRank Software** (e.g. SSL Checker, DNS Lookup, Domain IP Lookup in `ToolController`). If you have that codebase at `/path/to/phpRank Software`, you can compare behaviour; seo-report uses only built-in PHP and Guzzle (no GeoIP, no WHOIS, no external APIs).

## License

MIT.
