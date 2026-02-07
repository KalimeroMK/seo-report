# Docker-based Advanced Analysis

This directory contains Docker containers for advanced SEO analysis features that require browser automation or external tools.

## 🚀 Quick Start

```bash
# Build all images
docker-compose build

# Or build individually
docker-compose build lighthouse
docker-compose build chromium
docker-compose build wappalyzer

# Optional: Start Redis for caching
docker-compose up -d redis
```

## 📦 Services

### 1. Lighthouse CI (Core Web Vitals)

Runs Google Lighthouse for Core Web Vitals and performance analysis.

```bash
# Analyze a URL
docker-compose run lighthouse https://example.com

# Mobile analysis
docker-compose run lighthouse https://example.com mobile
```

**Output:** JSON with LCP, FID, CLS, TTFB, Speed Index, and full Lighthouse report.

### 2. Chromium (JavaScript Rendering & Screenshots)

Headless Chrome with Puppeteer for JS rendering analysis and screenshots.

```bash
# Analyze JavaScript rendering
docker-compose run chromium /app/render.js https://example.com

# Take screenshot (desktop, mobile, tablet)
docker-compose run chromium /app/screenshot.js https://example.com desktop
docker-compose run chromium /app/screenshot.js https://example.com mobile
```

**Features:**
- Detects client-side frameworks (React, Vue, Angular, Next.js, Nuxt.js)
- Measures render time and hydration
- Tracks console errors
- Screenshots in multiple viewports
- Visual metrics (CLS, LCP element)

### 3. Wappalyzer (Technology Detection)

Advanced technology detection using Wappalyzer.

```bash
# Detect technologies
docker-compose run wappalyzer https://example.com
```

**Output:** JSON with detected CMS, frameworks, analytics, hosting, and more.

## 🔧 PHP Integration

Use the `DockerAnalyzer` service in your PHP code:

```php
use KalimeroMK\SeoReport\Services\DockerAnalyzer;

$docker = new DockerAnalyzer(
    dockerComposePath: __DIR__ . '/../docker/docker-compose.yml',
    timeout: 60,
    useCache: true // Requires Redis
);

// Check if Docker is available
if ($docker->isAvailable()) {
    // Get Core Web Vitals
    $cwv = $docker->analyzeCoreWebVitals('https://example.com');
    echo $cwv['performance']['metrics']['lcp']; // LCP value
    
    // Analyze JavaScript rendering
    $js = $docker->analyzeJavaScriptRendering('https://example.com');
    echo $js['pageInfo']['framework']; // React, Vue, etc.
    
    // Take screenshot
    $screenshot = $docker->takeScreenshot('https://example.com', 'mobile');
    $base64Image = $screenshot['screenshot']['base64'];
    
    // Detect technologies
    $tech = $docker->detectTechnologies('https://example.com');
}
```

## 🧪 Running Tests

```bash
# Test Lighthouse
docker-compose run lighthouse https://google.com | jq

# Test JS rendering
docker-compose run chromium /app/render.js https://react.dev | jq

# Test screenshot
docker-compose run chromium /app/screenshot.js https://example.com mobile | jq '.screenshot.size'

# Test Wappalyzer
docker-compose run wappalyzer https://wordpress.com | jq
```

## ❌ No Caching by Design

**SEO analysis requires fresh results every time.** Users scan a site, fix issues, and rescan to verify fixes. Caching would show stale data.

```php
// Default: Always fresh results
$docker = new DockerAnalyzer();

// Only enable caching if you have specific batch processing needs:
$docker = new DockerAnalyzer(useCache: true);
```

## 🎯 Use Cases

### Core Web Vitals without PageSpeed Insights API

```php
$docker = new DockerAnalyzer();
$cwv = $docker->analyzeCoreWebVitals($url);

$results = [
    'lcp' => ['passed' => $cwv['performance']['metrics']['lcp'] < 2500],
    'fid' => ['passed' => $cwv['performance']['metrics']['fid'] < 100],
    'cls' => ['passed' => $cwv['performance']['metrics']['cls'] < 0.1],
];
```

### JavaScript SEO Analysis

```php
$js = $docker->analyzeJavaScriptRendering($url);

$seoChecks = [
    'title_rendered' => $js['seo']['titleRendered'],
    'h1_rendered' => $js['seo']['h1Rendered'],
    'client_side_framework' => $js['pageInfo']['framework'],
    'hydration_detected' => $js['pageInfo']['hasHydration'],
];
```

### Visual Regression Testing

```php
$desktop = $docker->takeScreenshot($url, 'desktop');
$mobile = $docker->takeScreenshot($url, 'mobile');

// Compare with previous screenshots
// Store base64 images or save to file
```

## ⚙️ System Requirements

- Docker 20.10+
- Docker Compose 2.0+
- 4GB+ RAM (for headless Chrome)
- 2GB disk space for images

## 🔒 Security Notes

- Containers run with `--no-sandbox` for compatibility
- Each run creates a fresh container (no state persistence)
- Network access is unrestricted (can access any URL)
- Consider rate limiting for production use

## 🐛 Troubleshooting

### Container won't start

```bash
# Check Docker daemon
docker ps

# Rebuild images
docker-compose build --no-cache

# Check logs
docker-compose logs lighthouse
```

### Timeout errors

Increase timeout in PHP:
```php
$docker = new DockerAnalyzer(timeout: 120);
```

Or in Docker Compose:
```yaml
# docker-compose.yml
services:
  chromium:
    deploy:
      resources:
        limits:
          memory: 4G
```

### High memory usage

Limit concurrent runs:
```php
// Use queue system for batch processing
// Process one URL at a time
```

## 📊 Comparison with API-based Solutions

| Feature | Docker-based | API-based |
|---------|--------------|-----------|
| Core Web Vitals | ✅ Lighthouse CI | ✅ PageSpeed Insights |
| Cost | Free (infrastructure only) | Per-request cost |
| Rate Limits | None (self-hosted) | API quotas |
| Data Privacy | Full control | Third-party processing |
| JS Rendering | ✅ Full browser | Limited |
| Screenshots | ✅ Included | Extra cost |
| Setup Complexity | Medium (Docker) | Low |
| Maintenance | Self-hosted | Managed |

## 🔄 Alternative: Local Chrome/Chromium

If Docker is not available, you can use local Chrome:

```bash
# Install Chrome
# Ubuntu/Debian
sudo apt-get install chromium-browser

# Then use Puppeteer directly without Docker
```

But Docker is recommended for:
- Consistent environment
- No local Chrome installation needed
- Isolated resource usage
- Easy deployment
