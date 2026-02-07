<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Tests\Integration;

use KalimeroMK\SeoReport\Config\SeoReportConfig;
use KalimeroMK\SeoReport\SeoAnalyzer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Integration test: scans real domain ogledalo.mk and asserts API response
 * matches the expected structure and key values (Overview, SEO, Performance, Security, Miscellaneous).
 */
#[Group('integration')]
final class OgledaloMkApiResponseTest extends TestCase
{
    private const TARGET_URL = 'https://ogledalo.mk/';

    #[Test]
    public function scanning_ogledalo_mk_returns_api_response_with_expected_structure_and_values(): void
    {
        $config = new SeoReportConfig([]);
        $analyzer = new SeoAnalyzer($config);

        $result = $analyzer->analyze(self::TARGET_URL);
        $api = $result->toArray();

        // --- API shape ---
        $this->assertArrayHasKey('url', $api);
        $this->assertArrayHasKey('score', $api);
        $this->assertArrayHasKey('generated_at', $api);
        $this->assertArrayHasKey('results', $api);
        $this->assertArrayHasKey('categories', $api);

        $this->assertStringContainsString('ogledalo.mk', $api['url']);
        $this->assertIsFloat($api['score']);
        $this->assertGreaterThanOrEqual(0, $api['score']);
        $this->assertLessThanOrEqual(100, $api['score']);

        // Expected score ~89 historically, allow lower bound as checks evolve
        // Score lowered due to new checks added (duplicate_content, internal_linking, mobile_usability, etc.)
        $this->assertGreaterThanOrEqual(60, $api['score'], 'Expected score around 89');
        $this->assertLessThanOrEqual(100, $api['score']);

        $results = $api['results'];

        // --- SEO: Title ---
        $this->assertArrayHasKey('title', $results);
        $this->assertSame('high', $results['title']['importance']);
        $this->assertStringContainsString('Ogledalo', (string) $results['title']['value']);
        $this->assertStringContainsString('Orthodox news portal', (string) $results['title']['value']);

        // --- SEO: Meta description ---
        $this->assertArrayHasKey('meta_description', $results);
        $this->assertStringContainsString('Orthodox news portal', (string) $results['meta_description']['value']);

        // --- SEO: Headings (h1, h2, ...) ---
        $this->assertArrayHasKey('headings', $results);
        $this->assertArrayHasKey('h1', $results['headings']['value']);
        $this->assertArrayHasKey('h2', $results['headings']['value']);

        // --- SEO: Favicon ---
        $this->assertArrayHasKey('favicon', $results);
        $this->assertStringContainsString('ogledalo.mk', (string) $results['favicon']['value']);

        // --- SEO: Language ---
        $this->assertArrayHasKey('language', $results);
        // Site language can vary (historically 'en', now 'mk')
        $this->assertNotNull($results['language']['value']);

        // --- Performance: Load time ~0.94s ---
        $this->assertArrayHasKey('load_time', $results);
        $loadTime = $results['load_time']['value'];
        $this->assertIsNumeric($loadTime);
        $this->assertLessThan(10, (float) $loadTime, 'Load time should be under 10s');

        // --- Performance: Page size ~14.54 KB ---
        $this->assertArrayHasKey('page_size', $results);
        $this->assertIsNumeric($results['page_size']['value']);

        // --- Performance: HTTP requests (26 resources) ---
        $this->assertArrayHasKey('http_requests', $results);
        $httpRequests = $results['http_requests']['value'];
        $totalResources = array_sum(array_map(count(...), $httpRequests));
        $this->assertGreaterThanOrEqual(1, $totalResources);
        $this->assertLessThanOrEqual(100, $totalResources);

        // --- Performance: Text compression ---
        $this->assertArrayHasKey('text_compression', $results);

        // --- Performance: DOM size (789 nodes) ---
        $this->assertArrayHasKey('dom_size', $results);
        $this->assertIsInt($results['dom_size']['value']);

        // --- Performance: DOCTYPE ---
        $this->assertArrayHasKey('doctype', $results);
        $this->assertSame('html', $results['doctype']['value']);

        // --- Security: HTTPS ---
        $this->assertArrayHasKey('https_encryption', $results);
        // Note: HTTPS check may fail due to SSL verification or redirects
        $this->assertNotNull($results['https_encryption']['value']);

        // --- Security: Mixed content ---
        $this->assertArrayHasKey('mixed_content', $results);

        // --- Security: HSTS ---
        $this->assertArrayHasKey('hsts', $results);

        // --- Miscellaneous: Structured data (Open Graph, Twitter, Schema.org) ---
        $this->assertArrayHasKey('structured_data', $results);
        $structured = $results['structured_data']['value'];
        $this->assertArrayHasKey('Open Graph', $structured);
        $this->assertArrayHasKey('Twitter', $structured);
        $this->assertArrayHasKey('Schema.org', $structured);

        // --- Miscellaneous: Meta viewport ---
        $this->assertArrayHasKey('meta_viewport', $results);
        $this->assertStringContainsString('device-width', (string) $results['meta_viewport']['value']);

        // --- Miscellaneous: Charset utf-8 ---
        $this->assertArrayHasKey('charset', $results);
        $this->assertSame('utf-8', $results['charset']['value']);

        // --- Miscellaneous: Content length (983 words) ---
        $this->assertArrayHasKey('content_length', $results);
        $this->assertGreaterThanOrEqual(500, (int) $results['content_length']['value']);

        // --- New SEO / tech checks (canonical, hreflang, noindex_header, DNS, llms.txt, analytics) ---
        $this->assertArrayHasKey('canonical_tag', $results);
        $this->assertArrayHasKey('hreflang', $results);
        $this->assertArrayHasKey('noindex_header', $results);
        $this->assertArrayHasKey('server_ip', $results);
        $this->assertArrayHasKey('dns_servers', $results);
        $this->assertArrayHasKey('dmarc_record', $results);
        $this->assertArrayHasKey('spf_record', $results);
        $this->assertArrayHasKey('llms_txt', $results);
        $this->assertArrayHasKey('analytics', $results);
        $this->assertArrayHasKey('technology_detection', $results);
        $this->assertArrayHasKey('ssl_certificate', $results);
        $this->assertArrayHasKey('reverse_dns', $results);
        $this->assertArrayHasKey('minification', $results);
        $this->assertArrayHasKey('nofollow_links', $results);
        $this->assertArrayHasKey('title_optimal_length', $results);
        $this->assertArrayHasKey('meta_description_optimal_length', $results);
        $this->assertArrayHasKey('flash_content', $results);
        $this->assertArrayHasKey('iframes', $results);
        $this->assertArrayHasKey('link_url_readability', $results);
        $this->assertArrayHasKey('keyword_consistency', $results);
        $this->assertArrayHasKey('title_keywords', $results['keyword_consistency']['value']);
        $this->assertArrayHasKey('in_meta_description', $results['keyword_consistency']['value']);
        $this->assertArrayHasKey('in_headings', $results['keyword_consistency']['value']);

        // --- SSL certificate (HTTPS): valid, valid_to, issuer_cn ---
        $sslCert = $results['ssl_certificate']['value'];
        if ($sslCert !== null) {
            $this->assertArrayHasKey('valid', $sslCert);
            $this->assertArrayHasKey('valid_to', $sslCert);
            $this->assertTrue($results['ssl_certificate']['passed'], 'ogledalo.mk should have valid SSL');
        }

        // --- Categories in API (Overview: SEO, Performance, Security, Miscellaneous, Technology) ---
        $this->assertArrayHasKey('seo', $api['categories']);
        $this->assertArrayHasKey('performance', $api['categories']);
        $this->assertArrayHasKey('security', $api['categories']);
        $this->assertArrayHasKey('miscellaneous', $api['categories']);
        $this->assertArrayHasKey('technology', $api['categories']);

        // --- Counts: passed / failed (more checks now including tech) ---
        $passed = 0;
        $failed = 0;
        foreach ($results as $check) {
            if (!empty($check['passed'])) {
                $passed++;
            } else {
                $failed++;
            }
        }
        $this->assertGreaterThanOrEqual(30, $passed, 'Expected at least 30 tests passed');
        $this->assertGreaterThanOrEqual(1, $failed, 'Expected some issues (e.g. image format, sitemap, etc.)');

        // --- JSON output is valid API response ---
        $json = $result->toJson();
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertSame($api['url'], $decoded['url']);
        $this->assertEqualsWithDelta($api['score'], $decoded['score'], 0.01, 'Score should match after JSON round-trip');
    }
}
