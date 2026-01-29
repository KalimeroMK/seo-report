# SEO Report (framework-agnostic PHP)

SEO analysis library: analyze a URL or sitemap, run SEO/performance/security checks, and **return an API result** (no database). Works with **Laravel**, **Yii**, or plain PHP.

## Requirements

- PHP 8.2+
- ext-dom, ext-json
- guzzlehttp/guzzle ^7.2

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

## Architecture (Quick Guide)

- `SeoAnalyzer` orchestrates fetching + parsing and then runs action classes.
- `src/Actions/` groups checks by category: `Seo`, `Performance`, `Security`, `Misc`, `Technology`.
- Each action implements `AnalysisActionInterface` and returns result keys to merge.
- `AnalysisContext` carries shared data (DOM, response, stats, config, computed arrays).

Adding a new check:
1) Create an action class in the right category that implements `AnalysisActionInterface`.
2) Add it to the corresponding `get*Actions()` list in `SeoAnalyzer`.

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
        "seo": ["title", "title_optimal_length", "meta_description", "meta_description_optimal_length", "headings", "h1_usage", "header_tag_usage", "content_keywords", "keyword_consistency", "image_keywords", "open_graph", "twitter_cards", "seo_friendly_url", "canonical_tag", "canonical_self_reference", "hreflang", "404_page", "robots", "noindex", "robots_directives", "noindex_header", "in_page_links", "nofollow_links", "link_url_readability", "language", "favicon"],
        "performance": ["text_compression", "brotli_compression", "load_time", "ttfb", "page_size", "http_requests", "static_cache_headers", "expires_headers", "avoid_redirects", "redirect_chains", "cookie_free_domains", "empty_src_or_href", "image_format", "image_dimensions", "image_lazy_loading", "image_size_optimization", "lcp_proxy", "cls_proxy", "defer_javascript", "render_blocking_resources", "minification", "dom_size", "doctype"],
        "security": ["https_encryption", "http2", "mixed_content", "server_signature", "unsafe_cross_origin_links", "htst", "plaintext_email"],
        "miscellaneous": ["structured_data", "meta_viewport", "charset", "sitemap", "social", "content_length", "text_html_ratio", "inline_css", "deprecated_html_tags", "llms_txt", "flash_content", "iframes"],
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
- And 55+ more checks...

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

### SEO (20 checks)
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

### Performance (9 checks)
- `text_compression` - Gzip compression
- `load_time` - Page load time
- `page_size` - HTML file size
- `http_requests` - Number of resources (JS/CSS/Images)
- `image_format` - Modern image formats (WebP, AVIF)
- `defer_javascript` - Defer attribute on scripts
- `minification` - JS/CSS minification check
- `dom_size` - DOM nodes count
- `doctype` - DOCTYPE declaration

### Security (7 checks)
- `https_encryption` - HTTPS usage
- `http2` - HTTP/2 protocol
- `mixed_content` - HTTP resources on HTTPS page
- `server_signature` - Server header exposure
- `unsafe_cross_origin_links` - Links without rel="noopener"
- `htst` - HTTP Strict Transport Security header
- `plaintext_email` - Plaintext emails in HTML

### Miscellaneous (12 checks)
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

### Technology (8 checks, no external APIs)
- `server_ip` - Resolved server IP (gethostbyname)
- `dns_servers` - NS records
- `dmarc_record` - DMARC TXT record (_dmarc.domain)
- `spf_record` - SPF TXT record
- `ssl_certificate` - SSL/TLS certificate (valid, valid_from, valid_to, issuer_cn, subject_cn) — pure PHP, inspired by phpRank Software
- `reverse_dns` - PTR record for server IP (gethostbyaddr)
- `analytics` - Detected analytics (e.g. Google Analytics)
- `technology_detection` - Detected tech (e.g. jQuery, Font Awesome, Facebook Pixel)

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
