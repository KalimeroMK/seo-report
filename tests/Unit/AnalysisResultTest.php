<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Tests\Unit;

use KalimeroMK\SeoReport\Dto\AnalysisResult;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AnalysisResultTest extends TestCase
{
    #[Test]
    public function it_has_expected_categories(): void
    {
        $this->assertArrayHasKey('seo', AnalysisResult::CATEGORIES);
        $this->assertArrayHasKey('performance', AnalysisResult::CATEGORIES);
        $this->assertArrayHasKey('security', AnalysisResult::CATEGORIES);
        $this->assertArrayHasKey('miscellaneous', AnalysisResult::CATEGORIES);
        $this->assertContains('title', AnalysisResult::CATEGORIES['seo']);
        $this->assertContains('meta_description', AnalysisResult::CATEGORIES['seo']);
        $this->assertContains('https_encryption', AnalysisResult::CATEGORIES['security']);
    }

    #[Test]
    public function getters_return_constructor_values(): void
    {
        $url = 'https://example.com';
        $results = ['title' => ['passed' => true, 'importance' => 'high', 'value' => 'Test']];
        $score = 85.5;
        $generatedAt = new \DateTimeImmutable('2026-01-28 12:00:00');

        $result = new AnalysisResult($url, $results, $score, $generatedAt);

        $this->assertSame($url, $result->getUrl());
        $this->assertSame($results, $result->getResults());
        $this->assertSame($score, $result->getScore());
        $this->assertSame($generatedAt, $result->getGeneratedAt());
        $this->assertSame(AnalysisResult::CATEGORIES, $result->getCategories());
    }

    #[Test]
    public function get_full_url_returns_seo_friendly_value_when_present(): void
    {
        $results = [
            'seo_friendly_url' => ['value' => 'https://example.com/page'],
        ];
        $result = new AnalysisResult('https://example.com', $results, 0, new \DateTimeImmutable());
        $this->assertSame('https://example.com/page', $result->getFullUrl());
    }

    #[Test]
    public function get_full_url_returns_url_when_seo_friendly_missing(): void
    {
        $result = new AnalysisResult('https://example.com', [], 0, new \DateTimeImmutable());
        $this->assertSame('https://example.com', $result->getFullUrl());
    }

    #[Test]
    public function to_array_contains_all_keys(): void
    {
        $url = 'https://example.com';
        $results = ['title' => ['passed' => true, 'importance' => 'high', 'value' => 'X']];
        $score = 50.0;
        $generatedAt = new \DateTimeImmutable('2026-01-28 12:00:00');

        $result = new AnalysisResult($url, $results, $score, $generatedAt);
        $array = $result->toArray();

        $this->assertArrayHasKey('url', $array);
        $this->assertArrayHasKey('score', $array);
        $this->assertArrayHasKey('generated_at', $array);
        $this->assertArrayHasKey('results', $array);
        $this->assertArrayHasKey('categories', $array);
        $this->assertSame($url, $array['url']);
        $this->assertSame($score, $array['score']);
        $this->assertSame($results, $array['results']);
        $this->assertStringContainsString('2026-01-28', $array['generated_at']);
    }

    #[Test]
    public function to_json_returns_valid_json(): void
    {
        $result = new AnalysisResult('https://example.com', ['title' => ['passed' => true]], 75, new \DateTimeImmutable());
        $json = $result->toJson();
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertSame('https://example.com', $decoded['url']);
        $this->assertSame(75, $decoded['score']); // JSON may encode as int
    }
}
