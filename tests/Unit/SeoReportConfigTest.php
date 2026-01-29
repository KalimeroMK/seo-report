<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Tests\Unit;

use KalimeroMK\SeoReport\Config\SeoReportConfig;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SeoReportConfigTest extends TestCase
{
    #[Test]
    public function it_returns_default_values_when_empty_options(): void
    {
        $config = new SeoReportConfig([]);

        $this->assertSame(5, $config->getRequestTimeout());
        $this->assertSame('1.1', $config->getRequestHttpVersion());
        $this->assertStringContainsString('Chrome', $config->getRequestUserAgent());
        $this->assertNull($config->getRequestProxy());
        $this->assertSame(-1, $config->getSitemapLinks());

        $this->assertSame(1, $config->getReportLimitMinTitle());
        $this->assertSame(60, $config->getReportLimitMaxTitle());
        $this->assertSame(500, $config->getReportLimitMinWords());
        $this->assertSame(10, $config->getReportLimitMinTextRatio());
        $this->assertSame(150, $config->getReportLimitMaxLinks());
        $this->assertSame(2, $config->getReportLimitLoadTime());
        $this->assertSame(330000, $config->getReportLimitPageSize());
        $this->assertSame(50, $config->getReportLimitHttpRequests());
        $this->assertSame(1500, $config->getReportLimitMaxDomNodes());
        $this->assertStringContainsString('WebP', $config->getReportLimitImageFormats());
        $this->assertSame(200000, $config->getReportLimitImageMaxBytes());
        $this->assertSame(0.8, $config->getReportLimitTtfb());
        $this->assertSame(400000, $config->getReportLimitLcpProxyBytes());
        $this->assertStringContainsString('font', $config->getReportLimitDeprecatedHtmlTags());

        $this->assertSame(10, $config->getReportScoreHigh());
        $this->assertSame(5, $config->getReportScoreMedium());
        $this->assertSame(0, $config->getReportScoreLow());
    }

    #[Test]
    public function it_returns_custom_values_from_options(): void
    {
        $config = new SeoReportConfig([
            'request_timeout' => 10,
            'request_http_version' => '2',
            'request_user_agent' => 'CustomBot/1.0',
            'request_proxy' => "http://proxy1:8080\nhttp://proxy2:8080",
            'sitemap_links' => 50,
            'report_limit_min_title' => 5,
            'report_limit_max_title' => 70,
            'report_score_high' => 15,
        ]);

        $this->assertSame(10, $config->getRequestTimeout());
        $this->assertSame('2', $config->getRequestHttpVersion());
        $this->assertSame('CustomBot/1.0', $config->getRequestUserAgent());
        $proxy = $config->getRequestProxy();
        $this->assertNotNull($proxy);
        $this->assertTrue($proxy === 'http://proxy1:8080' || $proxy === 'http://proxy2:8080');
        $this->assertSame(50, $config->getSitemapLinks());
        $this->assertSame(5, $config->getReportLimitMinTitle());
        $this->assertSame(70, $config->getReportLimitMaxTitle());
        $this->assertSame(15, $config->getReportScoreHigh());
    }

    #[Test]
    public function it_returns_null_proxy_when_empty(): void
    {
        $config = new SeoReportConfig(['request_proxy' => null]);
        $this->assertNull($config->getRequestProxy());

        $config = new SeoReportConfig(['request_proxy' => '']);
        $this->assertNull($config->getRequestProxy());
    }

    #[Test]
    public function to_array_returns_options(): void
    {
        $options = ['request_timeout' => 3, 'report_score_high' => 12];
        $config = new SeoReportConfig($options);
        $this->assertSame($options, $config->toArray());
    }
}
