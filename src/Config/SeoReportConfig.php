<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Config;

final readonly class SeoReportConfig
{
    /** @param array<string, mixed> $options */
    public function __construct(
        private array $options = []
    ) {}

    public function getRequestTimeout(): int
    {
        return (int) ($this->options['request_timeout'] ?? 5);
    }

    public function getRequestHttpVersion(): string
    {
        return (string) ($this->options['request_http_version'] ?? '1.1');
    }

    public function getRequestUserAgent(): string
    {
        return (string) ($this->options['request_user_agent'] ?? 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36');
    }

    public function getRequestProxy(): ?string
    {
        $proxies = $this->options['request_proxy'] ?? null;
        if (empty($proxies)) {
            return null;
        }
        $list = preg_split('/\n|\r/', (string) $proxies, -1, PREG_SPLIT_NO_EMPTY);
        if ($list === false) {
            return null;
        }
        $list = array_values(array_filter($list));
        if ($list === []) {
            return null;
        }
        return $list[array_rand($list)];
    }

    public function getSitemapLinks(): int
    {
        return (int) ($this->options['sitemap_links'] ?? -1);
    }

    public function getReportLimitMinTitle(): int
    {
        return (int) ($this->options['report_limit_min_title'] ?? 1);
    }

    public function getReportLimitMaxTitle(): int
    {
        return (int) ($this->options['report_limit_max_title'] ?? 60);
    }

    public function getReportLimitMinWords(): int
    {
        return (int) ($this->options['report_limit_min_words'] ?? 500);
    }

    public function getReportLimitMinTextRatio(): int
    {
        return (int) ($this->options['report_limit_min_text_ratio'] ?? 10);
    }

    public function getReportLimitMaxLinks(): int
    {
        return (int) ($this->options['report_limit_max_links'] ?? 150);
    }

    public function getReportLimitLoadTime(): int
    {
        return (int) ($this->options['report_limit_load_time'] ?? 2);
    }

    public function getReportLimitPageSize(): int
    {
        return (int) ($this->options['report_limit_page_size'] ?? 330000);
    }

    public function getReportLimitHttpRequests(): int
    {
        return (int) ($this->options['report_limit_http_requests'] ?? 50);
    }

    public function getReportLimitMaxDomNodes(): int
    {
        return (int) ($this->options['report_limit_max_dom_nodes'] ?? 1500);
    }

    public function getReportLimitImageFormats(): string
    {
        return (string) ($this->options['report_limit_image_formats'] ?? "AVIF\nWebP");
    }

    public function getReportLimitDeprecatedHtmlTags(): string
    {
        return (string) ($this->options['report_limit_deprecated_html_tags'] ?? "acronym\napplet\nbasefont\nbig\ncenter\ndir\nfont\nframe\nframeset\nisindex\nnoframes\ns\nstrike\ntt\nu");
    }

    public function getReportScoreHigh(): int
    {
        return (int) ($this->options['report_score_high'] ?? 10);
    }

    public function getReportScoreMedium(): int
    {
        return (int) ($this->options['report_score_medium'] ?? 5);
    }

    public function getReportScoreLow(): int
    {
        return (int) ($this->options['report_score_low'] ?? 0);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->options;
    }
}
