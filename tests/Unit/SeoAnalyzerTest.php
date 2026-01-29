<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Tests\Unit;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use KalimeroMK\SeoReport\Config\SeoReportConfig;
use KalimeroMK\SeoReport\Dto\AnalysisResult;
use KalimeroMK\SeoReport\SeoAnalyzer;
use KalimeroMK\SeoReport\SeoAnalyzerException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SeoAnalyzerTest extends TestCase
{
    private function minimalHtml(): string
    {
        return (string) file_get_contents(__DIR__ . '/../Fixtures/minimal.html');
    }

    /** @param list<Response|\Throwable> $responses */
    private function createMockClient(array $responses): Client
    {
        $mock = new MockHandler($responses);
        return new Client(['handler' => HandlerStack::create($mock)]);
    }

    #[Test]
    public function analyze_returns_analysis_result_with_expected_structure(): void
    {
        $html = $this->minimalHtml();
        $responses = [
            new Response(200, ['Content-Encoding' => ['gzip']], $html),
            new Response(200, [], ''), // 404 check - not 404
            new Response(200, [], "User-agent: *\nSitemap: https://example.com/sitemap.xml"),
        ];
        $client = $this->createMockClient($responses);
        $config = new SeoReportConfig([]);
        $analyzer = new SeoAnalyzer($config, $client);

        $result = $analyzer->analyze('https://example.com');

        $this->assertInstanceOf(AnalysisResult::class, $result);
        $this->assertStringContainsString('example.com', $result->getUrl());
        $this->assertIsArray($result->getResults());
        $this->assertArrayHasKey('title', $result->getResults());
        $this->assertArrayHasKey('meta_description', $result->getResults());
        $this->assertArrayHasKey('headings', $result->getResults());
        $this->assertArrayHasKey('https_encryption', $result->getResults());
        $this->assertGreaterThanOrEqual(0, $result->getScore());
        $this->assertLessThanOrEqual(100, $result->getScore());
        $this->assertInstanceOf(\DateTimeImmutable::class, $result->getGeneratedAt());
    }

    #[Test]
    public function analyze_result_contains_passed_importance_value_for_checks(): void
    {
        $html = $this->minimalHtml();
        $responses = [
            new Response(200, ['Content-Encoding' => ['gzip']], $html),
            new Response(200, [], ''),
            new Response(200, [], "Sitemap: https://example.com/sitemap.xml"),
        ];
        $client = $this->createMockClient($responses);
        $analyzer = new SeoAnalyzer(new SeoReportConfig([]), $client);

        $result = $analyzer->analyze('https://example.com');

        $titleCheck = $result->getResults()['title'];
        $this->assertArrayHasKey('passed', $titleCheck);
        $this->assertArrayHasKey('importance', $titleCheck);
        $this->assertArrayHasKey('value', $titleCheck);
        $this->assertSame('high', $titleCheck['importance']);
        $this->assertSame('Test Page Title', $titleCheck['value']);
    }

    #[Test]
    public function analyze_flags_missing_secondary_headings(): void
    {
        $html = $this->minimalHtml();
        $responses = [
            new Response(200, ['Content-Encoding' => ['gzip']], $html),
            new Response(200, [], ''),
            new Response(200, [], ''),
            new Response(200, ['Cache-Control' => 'max-age=3600'], ''),
            new Response(200, ['Cache-Control' => 'max-age=3600'], ''),
            new Response(200, ['Cache-Control' => 'max-age=3600'], ''),
            new Response(200, ['Cache-Control' => 'max-age=3600'], ''),
        ];
        $client = $this->createMockClient($responses);
        $analyzer = new SeoAnalyzer(new SeoReportConfig([]), $client);

        $result = $analyzer->analyze('https://example.com');

        $headerUsage = $result->getResults()['header_tag_usage'];
        $this->assertFalse($headerUsage['passed']);
        $this->assertArrayHasKey('missing', $headerUsage['errors']);
        $this->assertSame(0, $headerUsage['value']['h2']);
    }

    #[Test]
    public function analyze_detects_brotli_compression(): void
    {
        $html = $this->minimalHtml();
        $responses = [
            new Response(200, ['Content-Encoding' => ['br']], $html),
            new Response(200, [], ''),
            new Response(200, [], ''),
        ];
        $client = $this->createMockClient($responses);
        $analyzer = new SeoAnalyzer(new SeoReportConfig([]), $client);

        $result = $analyzer->analyze('https://example.com');

        $compression = $result->getResults()['text_compression'];
        $this->assertTrue($compression['passed']);
        $brotli = $result->getResults()['brotli_compression'];
        $this->assertTrue($brotli['passed']);
    }

    #[Test]
    public function analyze_detects_render_blocking_resources(): void
    {
        $html = '<!DOCTYPE html><html><head><title>Test</title>
            <link rel="stylesheet" href="/style.css">
            <link rel="stylesheet" href="/print.css" media="print">
            <script src="/app.js"></script>
            <script src="/app-async.js" async></script>
        </head><body></body></html>';
        $responses = [
            new Response(200, ['Content-Encoding' => ['gzip']], $html),
            new Response(200, [], ''),
            new Response(200, [], ''),
            new Response(200, [], ''),
            new Response(200, ['Cache-Control' => 'max-age=3600'], ''),
            new Response(200, ['Cache-Control' => 'max-age=3600'], ''),
            new Response(200, ['Cache-Control' => 'max-age=3600'], ''),
            new Response(200, ['Cache-Control' => 'max-age=3600'], ''),
        ];
        $client = $this->createMockClient($responses);
        $analyzer = new SeoAnalyzer(new SeoReportConfig([]), $client);

        $result = $analyzer->analyze('https://example.com');

        $blocking = $result->getResults()['render_blocking_resources'];
        $this->assertFalse($blocking['passed']);
        $this->assertArrayHasKey('errors', $blocking);
        $this->assertContains('https://example.com/app.js', $blocking['errors']['js']);
        $this->assertContains('https://example.com/style.css', $blocking['errors']['css']);
        $this->assertNotContains('https://example.com/print.css', $blocking['errors']['css']);
        $this->assertNotContains('https://example.com/app-async.js', $blocking['errors']['js']);
    }

    #[Test]
    public function analyze_detects_missing_open_graph_and_twitter_tags(): void
    {
        $html = '<!DOCTYPE html><html><head><title>Test</title>
            <meta property="og:title" content="OG Title">
            <meta name="twitter:card" content="summary">
        </head><body></body></html>';
        $responses = [
            new Response(200, ['Content-Encoding' => ['gzip']], $html),
            new Response(200, [], ''),
            new Response(200, [], ''),
            new Response(404, [], ''),
        ];
        $client = $this->createMockClient($responses);
        $analyzer = new SeoAnalyzer(new SeoReportConfig([]), $client);

        $result = $analyzer->analyze('https://example.com');

        $openGraph = $result->getResults()['open_graph'];
        $this->assertFalse($openGraph['passed']);
        $this->assertContains('og:description', $openGraph['errors']['missing']);
        $this->assertContains('og:image', $openGraph['errors']['missing']);

        $twitter = $result->getResults()['twitter_cards'];
        $this->assertFalse($twitter['passed']);
        $this->assertContains('twitter:title', $twitter['errors']['missing']);
        $this->assertContains('twitter:description', $twitter['errors']['missing']);
        $this->assertContains('twitter:image', $twitter['errors']['missing']);
    }

    #[Test]
    public function analyze_detects_canonical_self_reference_and_duplicates(): void
    {
        $html = '<!DOCTYPE html><html><head><title>Test</title>
            <link rel="canonical" href="https://example.com/other">
            <link rel="canonical" href="https://example.com/other">
        </head><body></body></html>';
        $responses = [
            new Response(200, ['Content-Encoding' => ['gzip']], $html),
            new Response(200, [], ''),
            new Response(200, [], ''),
        ];
        $client = $this->createMockClient($responses);
        $analyzer = new SeoAnalyzer(new SeoReportConfig([]), $client);

        $result = $analyzer->analyze('https://example.com/page');

        $canonical = $result->getResults()['canonical_self_reference'];
        $this->assertFalse($canonical['passed']);
        $this->assertArrayHasKey('not_self_reference', $canonical['errors']);
        $this->assertArrayHasKey('duplicates', $canonical['errors']);
    }

    #[Test]
    public function analyze_detects_restrictive_robots_directives(): void
    {
        $html = '<!DOCTYPE html><html><head><title>Test</title>
            <meta name="robots" content="noarchive, nosnippet">
        </head><body></body></html>';
        $responses = [
            new Response(200, ['Content-Encoding' => ['gzip']], $html),
            new Response(200, [], ''),
            new Response(200, [], ''),
        ];
        $client = $this->createMockClient($responses);
        $analyzer = new SeoAnalyzer(new SeoReportConfig([]), $client);

        $result = $analyzer->analyze('https://example.com');

        $robots = $result->getResults()['robots_directives'];
        $this->assertFalse($robots['passed']);
        $this->assertContains('noarchive', $robots['errors']['restricted']);
        $this->assertContains('nosnippet', $robots['errors']['restricted']);
    }

    #[Test]
    public function analyze_detects_multiple_h1_tags(): void
    {
        $html = '<!DOCTYPE html><html><head><title>Test</title></head><body>
            <h1>Main</h1>
            <h1>Secondary</h1>
        </body></html>';
        $responses = [
            new Response(200, ['Content-Encoding' => ['gzip']], $html),
            new Response(200, [], ''),
            new Response(200, [], ''),
        ];
        $client = $this->createMockClient($responses);
        $analyzer = new SeoAnalyzer(new SeoReportConfig([]), $client);

        $result = $analyzer->analyze('https://example.com');

        $h1 = $result->getResults()['h1_usage'];
        $this->assertFalse($h1['passed']);
        $this->assertSame(2, $h1['value']);
        $this->assertSame(2, $h1['errors']['multiple']);
    }

    #[Test]
    public function analyze_passes_when_secondary_headings_exist(): void
    {
        $html = '<!DOCTYPE html><html><head><title>Test</title>'
            . '<meta name="description" content="Test"></head>'
            . '<body><h1>Title</h1><h2>Section</h2><h3>Sub</h3></body></html>';
        $responses = [
            new Response(200, ['Content-Encoding' => ['gzip']], $html),
            new Response(200, [], ''),
            new Response(200, [], ''),
            new Response(200, ['Cache-Control' => 'max-age=3600'], ''),
            new Response(200, ['Cache-Control' => 'max-age=3600'], ''),
            new Response(200, ['Cache-Control' => 'max-age=3600'], ''),
            new Response(200, ['Cache-Control' => 'max-age=3600'], ''),
        ];
        $client = $this->createMockClient($responses);
        $analyzer = new SeoAnalyzer(new SeoReportConfig([]), $client);

        $result = $analyzer->analyze('https://example.com');

        $headerUsage = $result->getResults()['header_tag_usage'];
        $this->assertTrue($headerUsage['passed']);
        $this->assertSame(1, $headerUsage['value']['h2']);
        $this->assertSame(1, $headerUsage['value']['h3']);
    }

    #[Test]
    public function analyze_to_array_is_valid_api_response(): void
    {
        $html = $this->minimalHtml();
        $responses = [
            new Response(200, ['Content-Encoding' => ['gzip']], $html),
            new Response(200, [], ''),
            new Response(200, [], ''),
            new Response(200, ['Cache-Control' => 'max-age=3600'], ''),
            new Response(200, ['Cache-Control' => 'max-age=3600'], ''),
            new Response(200, ['Cache-Control' => 'max-age=3600'], ''),
            new Response(200, ['Cache-Control' => 'max-age=3600'], ''),
            new Response(200, ['Cache-Control' => 'max-age=3600'], ''),
        ];
        $client = $this->createMockClient($responses);
        $analyzer = new SeoAnalyzer(new SeoReportConfig([]), $client);

        $result = $analyzer->analyze('https://example.com');
        $array = $result->toArray();

        $this->assertArrayHasKey('url', $array);
        $this->assertArrayHasKey('score', $array);
        $this->assertArrayHasKey('generated_at', $array);
        $this->assertArrayHasKey('results', $array);
        $this->assertArrayHasKey('categories', $array);
        $this->assertSame(AnalysisResult::CATEGORIES, $array['categories']);
    }

    #[Test]
    public function analyze_throws_when_url_cannot_be_fetched(): void
    {
        $mock = new MockHandler([
            new \GuzzleHttp\Exception\ConnectException('Connection refused', new \GuzzleHttp\Psr7\Request('GET', 'https://example.com')),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);
        $analyzer = new SeoAnalyzer(new SeoReportConfig([]), $client);

        $this->expectException(SeoAnalyzerException::class);
        $this->expectExceptionMessage('Could not fetch URL');

        $analyzer->analyze('https://example.com');
    }

    #[Test]
    public function analyze_accepts_url_without_scheme_and_prepends_https(): void
    {
        $html = $this->minimalHtml();
        $responses = [
            new Response(200, ['Content-Encoding' => ['gzip']], $html),
            new Response(200, [], ''),
            new Response(200, [], ''),
        ];
        $client = $this->createMockClient($responses);
        $analyzer = new SeoAnalyzer(new SeoReportConfig([]), $client);

        $result = $analyzer->analyze('example.com');

        $this->assertStringContainsString('example.com', $result->getUrl());
    }

    #[Test]
    public function analyze_detects_non_minified_js_and_css(): void
    {
        $html = '<!DOCTYPE html><html><head><title>Test</title>
            <script src="/app.js"></script>
            <script src="/app.min.js"></script>
            <link rel="stylesheet" href="/style.css">
            <link rel="stylesheet" href="/style.min.css">
        </head><body></body></html>';
        $mock = new MockHandler([
            new Response(200, ['Content-Encoding' => ['gzip']], $html),
            new Response(200, [], ''),
            new Response(200, [], ''),
        ]);
        $mock->append(
            new Response(200, ['Cache-Control' => 'max-age=3600'], ''),
            new Response(200, ['Cache-Control' => 'max-age=3600'], ''),
            new Response(200, ['Cache-Control' => 'max-age=3600'], ''),
            new Response(200, ['Cache-Control' => 'max-age=3600'], ''),
            new Response(200, ['Cache-Control' => 'max-age=3600'], ''),
            new Response(200, ['Cache-Control' => 'max-age=3600'], ''),
            new Response(200, ['Cache-Control' => 'max-age=3600'], ''),
            new Response(200, ['Cache-Control' => 'max-age=3600'], '')
        );
        $client = new Client(['handler' => HandlerStack::create($mock)]);
        $analyzer = new SeoAnalyzer(new SeoReportConfig([]), $client);

        $result = $analyzer->analyze('https://example.com');

        $this->assertArrayHasKey('minification', $result->getResults());
        $minCheck = $result->getResults()['minification'];
        $this->assertFalse($minCheck['passed']);
        $this->assertArrayHasKey('errors', $minCheck);
        $this->assertArrayHasKey('not_minified', $minCheck['errors']);
        $this->assertArrayHasKey('js', $minCheck['errors']['not_minified']);
        $this->assertArrayHasKey('css', $minCheck['errors']['not_minified']);
        $this->assertStringContainsString('app.js', implode('', $minCheck['errors']['not_minified']['js']));
        $this->assertStringContainsString('style.css', implode('', $minCheck['errors']['not_minified']['css']));
    }

    #[Test]
    public function analyze_detects_nofollow_links(): void
    {
        $html = '<!DOCTYPE html><html><head><title>Test</title></head><body>
            <a href="/page1" rel="nofollow">Link 1</a>
            <a href="/page2" rel="nofollow noopener">Link 2</a>
            <a href="/page3">Link 3</a>
        </body></html>';
        $responses = [
            new Response(200, ['Content-Encoding' => ['gzip']], $html),
            new Response(200, [], ''),
            new Response(200, [], ''),
        ];
        $client = $this->createMockClient($responses);
        $analyzer = new SeoAnalyzer(new SeoReportConfig([]), $client);

        $result = $analyzer->analyze('https://example.com');

        $this->assertArrayHasKey('nofollow_links', $result->getResults());
        $nofollowCheck = $result->getResults()['nofollow_links'];
        $this->assertFalse($nofollowCheck['passed']);
        $this->assertSame(2, $nofollowCheck['value']);
        $this->assertArrayHasKey('errors', $nofollowCheck);
        $this->assertArrayHasKey('found', $nofollowCheck['errors']);
        $this->assertCount(2, $nofollowCheck['errors']['found']);
    }

    #[Test]
    public function analyze_detects_missing_expires_headers_and_empty_src_or_href(): void
    {
        $html = '<!DOCTYPE html><html><head><title>Test</title></head><body>
            <img src="">
            <a href="">Link</a>
        </body></html>';
        $responses = [
            new Response(200, ['Content-Encoding' => ['gzip']], $html),
            new Response(200, [], ''),
            new Response(200, [], ''),
        ];
        $client = $this->createMockClient($responses);
        $analyzer = new SeoAnalyzer(new SeoReportConfig([]), $client);

        $result = $analyzer->analyze('https://example.com');

        $expiresCheck = $result->getResults()['expires_headers'];
        $this->assertFalse($expiresCheck['passed']);
        $this->assertArrayHasKey('missing', $expiresCheck['errors']);

        $emptyCheck = $result->getResults()['empty_src_or_href'];
        $this->assertFalse($emptyCheck['passed']);
        $this->assertArrayHasKey('errors', $emptyCheck);
        $this->assertArrayHasKey('empty', $emptyCheck['errors']);
        $this->assertContains('img[src]', $emptyCheck['errors']['empty']);
        $this->assertContains('a[href]', $emptyCheck['errors']['empty']);
    }

    #[Test]
    public function analyze_flags_cookie_domains_on_static_assets_and_no_redirects(): void
    {
        $html = '<!DOCTYPE html><html><head><title>Test</title></head><body>
            <img src="https://example.com/logo.png">
            <script src="/app.js"></script>
        </body></html>';
        $responses = [
            new Response(200, [
                'Content-Encoding' => ['gzip'],
                'Cache-Control' => 'max-age=3600',
                'Set-Cookie' => 'sid=abc; Path=/',
            ], $html),
            new Response(200, [], ''),
            new Response(200, [], ''),
            new Response(200, ['Cache-Control' => 'max-age=3600'], ''),
            new Response(200, ['Cache-Control' => 'max-age=3600'], ''),
            new Response(200, ['Cache-Control' => 'max-age=3600'], ''),
            new Response(200, ['Cache-Control' => 'max-age=3600'], ''),
        ];
        $client = $this->createMockClient($responses);
        $analyzer = new SeoAnalyzer(new SeoReportConfig([]), $client);

        $result = $analyzer->analyze('https://example.com');

        $cookieCheck = $result->getResults()['cookie_free_domains'];
        $this->assertFalse($cookieCheck['passed']);
        $this->assertArrayHasKey('cookies_on_static', $cookieCheck['errors']);
        $this->assertNotEmpty($cookieCheck['errors']['cookies_on_static']);

        $expiresCheck = $result->getResults()['expires_headers'];
        $this->assertTrue($expiresCheck['passed']);

        $redirectsCheck = $result->getResults()['avoid_redirects'];
        $this->assertTrue($redirectsCheck['passed']);
        $this->assertSame(0, $redirectsCheck['value']);
    }

    #[Test]
    public function analyze_flags_static_assets_without_cache_headers(): void
    {
        $html = '<!DOCTYPE html><html><head><title>Test</title>
            <link rel="stylesheet" href="/style.css">
        </head><body>
            <img src="/hero.jpg">
        </body></html>';
        $responses = [
            new Response(200, ['Content-Encoding' => ['gzip']], $html),
            new Response(200, [], ''),
            new Response(200, [], ''),
            new Response(200, [], ''),
            new Response(200, [], ''),
            new Response(200, [], ''),
            new Response(200, [], ''),
        ];
        $client = $this->createMockClient($responses);
        $analyzer = new SeoAnalyzer(new SeoReportConfig([]), $client);

        $result = $analyzer->analyze('https://example.com');

        $cacheCheck = $result->getResults()['static_cache_headers'];
        $this->assertFalse($cacheCheck['passed']);
        $this->assertArrayHasKey('missing', $cacheCheck['errors']);
        $this->assertContains('https://example.com/style.css', $cacheCheck['errors']['missing']);
        $this->assertContains('https://example.com/hero.jpg', $cacheCheck['errors']['missing']);
    }

    #[Test]
    public function analyze_flags_image_dimensions_lazy_loading_and_size(): void
    {
        $html = '<!DOCTYPE html><html><head><title>Test</title></head><body>
            <img src="/hero.jpg">
        </body></html>';
        $mock = new MockHandler([
            new Response(200, ['Content-Encoding' => ['gzip']], $html),
            new Response(200, [], ''),
            new Response(200, [], ''),
            new Response(404, [], ''),
        ]);
        $mock->append(
            new Response(200, ['Cache-Control' => 'max-age=3600', 'Content-Length' => '200'], ''),
            new Response(200, ['Cache-Control' => 'max-age=3600', 'Content-Length' => '200'], '')
        );
        $client = new Client(['handler' => HandlerStack::create($mock)]);
        $config = new SeoReportConfig([
            'report_limit_image_max_bytes' => 100,
            'report_limit_lcp_proxy_bytes' => 100,
        ]);
        $analyzer = new SeoAnalyzer($config, $client);

        $result = $analyzer->analyze('https://example.com');

        $dimensions = $result->getResults()['image_dimensions'];
        $this->assertFalse($dimensions['passed']);
        $this->assertArrayHasKey('missing', $dimensions['errors']);

        $lazy = $result->getResults()['image_lazy_loading'];
        $this->assertFalse($lazy['passed']);
        $this->assertContains('https://example.com/hero.jpg', $lazy['errors']['missing']);

        $size = $result->getResults()['image_size_optimization'];
        $this->assertFalse($size['passed']);
        $this->assertArrayHasKey('too_large', $size['errors']);
        $this->assertSame('https://example.com/hero.jpg', $size['errors']['too_large'][0]['url']);

        $lcp = $result->getResults()['lcp_proxy'];
        $this->assertFalse($lcp['passed']);
        $this->assertSame('https://example.com/hero.jpg', $lcp['errors']['too_large']['url']);

        $cls = $result->getResults()['cls_proxy'];
        $this->assertFalse($cls['passed']);
        $this->assertSame(1, $cls['value']);
    }

    #[Test]
    public function analyze_detects_redirect_chains_for_main_and_assets(): void
    {
        $html = '<!DOCTYPE html><html><head><title>Test</title>
            <script src="/app.js"></script>
        </head><body></body></html>';
        $responses = [
            new Response(200, ['Content-Encoding' => ['gzip'], 'X-Guzzle-Redirect-History' => ['http://example.com']], $html),
            new Response(200, [], ''),
            new Response(200, [], ''),
            new Response(404, [], ''),
            new Response(200, [
                'Cache-Control' => 'max-age=3600',
                'X-Guzzle-Redirect-History' => ['https://example.com/app.js'],
            ], ''),
        ];
        $client = $this->createMockClient($responses);
        $analyzer = new SeoAnalyzer(new SeoReportConfig([]), $client);

        $result = $analyzer->analyze('https://example.com');

        $redirects = $result->getResults()['redirect_chains'];
        $this->assertFalse($redirects['passed']);
        $this->assertSame(1, $redirects['errors']['main']);
        $this->assertSame('https://example.com/app.js', $redirects['errors']['assets'][0]['url']);
        $this->assertSame(1, $redirects['errors']['assets'][0]['count']);
    }

    #[Test]
    public function analyze_checks_title_optimal_length(): void
    {
        $htmlShort = '<!DOCTYPE html><html><head><title>Short</title></head><body></body></html>';
        $htmlOptimal = '<!DOCTYPE html><html><head><title>This is an optimal title length between 50-60 chars</title></head><body></body></html>';

        $responsesShort = [
            new Response(200, ['Content-Encoding' => ['gzip']], $htmlShort),
            new Response(200, [], ''),
            new Response(200, [], ''),
        ];
        $client = $this->createMockClient($responsesShort);
        $analyzer = new SeoAnalyzer(new SeoReportConfig([]), $client);
        $result = $analyzer->analyze('https://example.com');
        $this->assertArrayHasKey('title_optimal_length', $result->getResults());
        $this->assertFalse($result->getResults()['title_optimal_length']['passed']);

        $responsesOptimal = [
            new Response(200, ['Content-Encoding' => ['gzip']], $htmlOptimal),
            new Response(200, [], ''),
            new Response(200, [], ''),
        ];
        $client = $this->createMockClient($responsesOptimal);
        $analyzer = new SeoAnalyzer(new SeoReportConfig([]), $client);
        $result = $analyzer->analyze('https://example.com');
        $this->assertTrue($result->getResults()['title_optimal_length']['passed']);
    }

    #[Test]
    public function analyze_checks_meta_description_optimal_length(): void
    {
        $htmlShort = '<!DOCTYPE html><html><head><title>Test</title><meta name="description" content="Short"></head><body></body></html>';
        $htmlOptimal = '<!DOCTYPE html><html><head><title>Test</title><meta name="description" content="This is an optimal meta description length between 120-160 characters which is recommended for better SEO results and search engine visibility."></head><body></body></html>';

        $responsesShort = [
            new Response(200, ['Content-Encoding' => ['gzip']], $htmlShort),
            new Response(200, [], ''),
            new Response(200, [], ''),
        ];
        $client = $this->createMockClient($responsesShort);
        $analyzer = new SeoAnalyzer(new SeoReportConfig([]), $client);
        $result = $analyzer->analyze('https://example.com');
        $this->assertArrayHasKey('meta_description_optimal_length', $result->getResults());
        $this->assertFalse($result->getResults()['meta_description_optimal_length']['passed']);

        $responsesOptimal = [
            new Response(200, ['Content-Encoding' => ['gzip']], $htmlOptimal),
            new Response(200, [], ''),
            new Response(200, [], ''),
        ];
        $client = $this->createMockClient($responsesOptimal);
        $analyzer = new SeoAnalyzer(new SeoReportConfig([]), $client);
        $result = $analyzer->analyze('https://example.com');
        $this->assertTrue($result->getResults()['meta_description_optimal_length']['passed']);
    }

    #[Test]
    public function analyze_detects_flash_content(): void
    {
        $html = '<!DOCTYPE html><html><head><title>Test</title></head><body>
            <object type="application/x-shockwave-flash" data="/flash.swf"></object>
            <embed type="application/x-shockwave-flash" src="/another.swf"></embed>
        </body></html>';
        $responses = [
            new Response(200, ['Content-Encoding' => ['gzip']], $html),
            new Response(200, [], ''),
            new Response(200, [], ''),
        ];
        $client = $this->createMockClient($responses);
        $analyzer = new SeoAnalyzer(new SeoReportConfig([]), $client);

        $result = $analyzer->analyze('https://example.com');

        $this->assertArrayHasKey('flash_content', $result->getResults());
        $flashCheck = $result->getResults()['flash_content'];
        $this->assertFalse($flashCheck['passed']);
        $this->assertArrayHasKey('errors', $flashCheck);
        $this->assertArrayHasKey('found', $flashCheck['errors']);
        $this->assertNotEmpty($flashCheck['errors']['found']);
    }

    #[Test]
    public function analyze_detects_iframes(): void
    {
        $html = '<!DOCTYPE html><html><head><title>Test</title></head><body>
            <iframe src="https://youtube.com/embed/123"></iframe>
            <iframe src="/local.html" title="Local iframe"></iframe>
        </body></html>';
        $responses = [
            new Response(200, ['Content-Encoding' => ['gzip']], $html),
            new Response(200, [], ''),
            new Response(200, [], ''),
        ];
        $client = $this->createMockClient($responses);
        $analyzer = new SeoAnalyzer(new SeoReportConfig([]), $client);

        $result = $analyzer->analyze('https://example.com');

        $this->assertArrayHasKey('iframes', $result->getResults());
        $iframesCheck = $result->getResults()['iframes'];
        $this->assertTrue($iframesCheck['passed']);
        $this->assertIsArray($iframesCheck['value']);
        $this->assertCount(2, $iframesCheck['value']);
        $this->assertStringContainsString('youtube.com', $iframesCheck['value'][0]['url']);
    }

    #[Test]
    public function analyze_detects_unfriendly_link_urls(): void
    {
        $html = '<!DOCTYPE html><html><head><title>Test</title></head><body>
            <a href="/page?param=value">Good link</a>
            <a href="/page_with_underscore">Good link</a>
            <a href="/page=bad">Bad link</a>
            <a href="/page%20with%20spaces">Bad link</a>
            <a href="/page,comma">Bad link</a>
        </body></html>';
        $responses = [
            new Response(200, ['Content-Encoding' => ['gzip']], $html),
            new Response(200, [], ''),
            new Response(200, [], ''),
        ];
        $client = $this->createMockClient($responses);
        $analyzer = new SeoAnalyzer(new SeoReportConfig([]), $client);

        $result = $analyzer->analyze('https://example.com');

        $this->assertArrayHasKey('link_url_readability', $result->getResults());
        $readabilityCheck = $result->getResults()['link_url_readability'];
        $this->assertFalse($readabilityCheck['passed']);
        $this->assertArrayHasKey('errors', $readabilityCheck);
        $this->assertArrayHasKey('unfriendly_urls', $readabilityCheck['errors']);
        $this->assertGreaterThanOrEqual(3, count($readabilityCheck['errors']['unfriendly_urls']));
    }

    #[Test]
    public function analyze_checks_keyword_consistency_across_title_meta_headings(): void
    {
        $html = '<!DOCTYPE html><html><head>
            <title>SEO Best Practices Guide</title>
            <meta name="description" content="Learn SEO best practices and guide for your website.">
        </head><body>
            <h1>SEO Best Practices</h1>
            <h2>Guide Overview</h2>
            <p>Content here.</p>
        </body></html>';
        $responses = [
            new Response(200, ['Content-Encoding' => ['gzip']], $html),
            new Response(200, [], ''),
            new Response(200, [], ''),
        ];
        $client = $this->createMockClient($responses);
        $analyzer = new SeoAnalyzer(new SeoReportConfig([]), $client);

        $result = $analyzer->analyze('https://example.com');

        $this->assertArrayHasKey('keyword_consistency', $result->getResults());
        $kc = $result->getResults()['keyword_consistency'];
        $this->assertSame('medium', $kc['importance']);
        $this->assertArrayHasKey('title_keywords', $kc['value']);
        $this->assertArrayHasKey('in_meta_description', $kc['value']);
        $this->assertArrayHasKey('in_headings', $kc['value']);
        $this->assertArrayHasKey('missing_in_meta', $kc['value']);
        $this->assertArrayHasKey('missing_in_headings', $kc['value']);
        $this->assertTrue($kc['passed'], 'Title keywords should appear in meta and headings');
        $inMeta = $kc['value']['in_meta_description'];
        $inHeadings = $kc['value']['in_headings'];
        $this->assertContains('seo', $inMeta);
        $this->assertContains('best', $inMeta);
        $this->assertContains('seo', $inHeadings);
        $this->assertContains('best', $inHeadings);
    }

    #[Test]
    public function analyze_keyword_consistency_fails_when_no_title_keywords_in_meta_or_headings(): void
    {
        $html = '<!DOCTYPE html><html><head>
            <title>Completely Different Topic</title>
            <meta name="description" content="Unrelated description text only.">
        </head><body>
            <h1>Other Heading</h1>
        </body></html>';
        $responses = [
            new Response(200, ['Content-Encoding' => ['gzip']], $html),
            new Response(200, [], ''),
            new Response(200, [], ''),
        ];
        $client = $this->createMockClient($responses);
        $analyzer = new SeoAnalyzer(new SeoReportConfig([]), $client);

        $result = $analyzer->analyze('https://example.com');

        $kc = $result->getResults()['keyword_consistency'];
        $this->assertFalse($kc['passed']);
        $this->assertArrayHasKey('errors', $kc);
        $this->assertArrayHasKey('no_title_keywords_in_meta', $kc['errors']);
        $this->assertArrayHasKey('no_title_keywords_in_headings', $kc['errors']);
    }
}
