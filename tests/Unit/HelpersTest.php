<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class HelpersTest extends TestCase
{
    #[Test]
    public function seo_report_clean_url_removes_scheme_and_www(): void
    {
        $this->assertSame('example.com', seo_report_clean_url('https://example.com'));
        $this->assertSame('example.com', seo_report_clean_url('http://example.com'));
        $this->assertSame('example.com', seo_report_clean_url('https://www.example.com'));
        $this->assertSame('example.com', seo_report_clean_url('http://www.example.com'));
    }

    #[Test]
    public function seo_report_clean_url_preserves_path(): void
    {
        $this->assertSame('example.com/path/to/page', seo_report_clean_url('https://example.com/path/to/page'));
        // Root path / is normalized: base is rtrim(url,'/') so scheme is stripped -> example.com
        $this->assertSame('example.com', seo_report_clean_url('https://example.com/'));
    }

    #[Test]
    public function seo_report_clean_tag_text_returns_empty_for_null_or_empty(): void
    {
        $this->assertSame('', seo_report_clean_tag_text(null));
        $this->assertSame('', seo_report_clean_tag_text(''));
    }

    #[Test]
    public function seo_report_clean_tag_text_collapses_whitespace(): void
    {
        $this->assertSame('a b c', seo_report_clean_tag_text("a  b   c"));
        $this->assertSame('hello world', seo_report_clean_tag_text("  hello \n\t world  "));
    }

    #[Test]
    public function seo_report_clean_tag_text_trims(): void
    {
        $this->assertSame('x', seo_report_clean_tag_text('  x  '));
    }
}
