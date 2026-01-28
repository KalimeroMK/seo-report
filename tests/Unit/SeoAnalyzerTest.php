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
    private static function minimalHtml(): string
    {
        return (string) file_get_contents(__DIR__ . '/../Fixtures/minimal.html');
    }

    /** @param list<Response|\Throwable> $responses */
    private static function createMockClient(array $responses): Client
    {
        $mock = new MockHandler($responses);
        return new Client(['handler' => HandlerStack::create($mock)]);
    }

    #[Test]
    public function analyze_returns_analysis_result_with_expected_structure(): void
    {
        $html = self::minimalHtml();
        $responses = [
            new Response(200, ['Content-Encoding' => ['gzip']], $html),
            new Response(200, [], ''), // 404 check - not 404
            new Response(200, [], "User-agent: *\nSitemap: https://example.com/sitemap.xml"),
        ];
        $client = self::createMockClient($responses);
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
        $html = self::minimalHtml();
        $responses = [
            new Response(200, ['Content-Encoding' => ['gzip']], $html),
            new Response(200, [], ''),
            new Response(200, [], "Sitemap: https://example.com/sitemap.xml"),
        ];
        $client = self::createMockClient($responses);
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
    public function analyze_to_array_is_valid_api_response(): void
    {
        $html = self::minimalHtml();
        $responses = [
            new Response(200, ['Content-Encoding' => ['gzip']], $html),
            new Response(200, [], ''),
            new Response(200, [], ''),
        ];
        $client = self::createMockClient($responses);
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
        $html = self::minimalHtml();
        $responses = [
            new Response(200, ['Content-Encoding' => ['gzip']], $html),
            new Response(200, [], ''),
            new Response(200, [], ''),
        ];
        $client = self::createMockClient($responses);
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
        $responses = [
            new Response(200, ['Content-Encoding' => ['gzip']], $html),
            new Response(200, [], ''),
            new Response(200, [], ''),
        ];
        $client = self::createMockClient($responses);
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
        $client = self::createMockClient($responses);
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
    public function analyze_checks_title_optimal_length(): void
    {
        $htmlShort = '<!DOCTYPE html><html><head><title>Short</title></head><body></body></html>';
        $htmlOptimal = '<!DOCTYPE html><html><head><title>This is an optimal title length between 50-60 chars</title></head><body></body></html>';
        $htmlLong = '<!DOCTYPE html><html><head><title>This is a very long title that exceeds the optimal length of 60 characters and should fail</title></head><body></body></html>';

        $responsesShort = [
            new Response(200, ['Content-Encoding' => ['gzip']], $htmlShort),
            new Response(200, [], ''),
            new Response(200, [], ''),
        ];
        $client = self::createMockClient($responsesShort);
        $analyzer = new SeoAnalyzer(new SeoReportConfig([]), $client);
        $result = $analyzer->analyze('https://example.com');
        $this->assertArrayHasKey('title_optimal_length', $result->getResults());
        $this->assertFalse($result->getResults()['title_optimal_length']['passed']);

        $responsesOptimal = [
            new Response(200, ['Content-Encoding' => ['gzip']], $htmlOptimal),
            new Response(200, [], ''),
            new Response(200, [], ''),
        ];
        $client = self::createMockClient($responsesOptimal);
        $analyzer = new SeoAnalyzer(new SeoReportConfig([]), $client);
        $result = $analyzer->analyze('https://example.com');
        $this->assertTrue($result->getResults()['title_optimal_length']['passed']);
    }

    #[Test]
    public function analyze_checks_meta_description_optimal_length(): void
    {
        $htmlShort = '<!DOCTYPE html><html><head><title>Test</title><meta name="description" content="Short"></head><body></body></html>';
        $htmlOptimal = '<!DOCTYPE html><html><head><title>Test</title><meta name="description" content="This is an optimal meta description length between 120-160 characters which is recommended for better SEO results and search engine visibility."></head><body></body></html>';
        $htmlLong = '<!DOCTYPE html><html><head><title>Test</title><meta name="description" content="This is a very long meta description that exceeds the optimal length of 160 characters and should fail the optimal length check because it is too long for search engine results pages."></head><body></body></html>';

        $responsesShort = [
            new Response(200, ['Content-Encoding' => ['gzip']], $htmlShort),
            new Response(200, [], ''),
            new Response(200, [], ''),
        ];
        $client = self::createMockClient($responsesShort);
        $analyzer = new SeoAnalyzer(new SeoReportConfig([]), $client);
        $result = $analyzer->analyze('https://example.com');
        $this->assertArrayHasKey('meta_description_optimal_length', $result->getResults());
        $this->assertFalse($result->getResults()['meta_description_optimal_length']['passed']);

        $responsesOptimal = [
            new Response(200, ['Content-Encoding' => ['gzip']], $htmlOptimal),
            new Response(200, [], ''),
            new Response(200, [], ''),
        ];
        $client = self::createMockClient($responsesOptimal);
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
        $client = self::createMockClient($responses);
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
        $client = self::createMockClient($responses);
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
        $client = self::createMockClient($responses);
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
        $client = self::createMockClient($responses);
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
        $client = self::createMockClient($responses);
        $analyzer = new SeoAnalyzer(new SeoReportConfig([]), $client);

        $result = $analyzer->analyze('https://example.com');

        $kc = $result->getResults()['keyword_consistency'];
        $this->assertFalse($kc['passed']);
        $this->assertArrayHasKey('errors', $kc);
        $this->assertArrayHasKey('no_title_keywords_in_meta', $kc['errors']);
        $this->assertArrayHasKey('no_title_keywords_in_headings', $kc['errors']);
    }
}
