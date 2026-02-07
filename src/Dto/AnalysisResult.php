<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Dto;

/** @phpstan-type CategoryMap array<string, array<int, string>> */
final readonly class AnalysisResult
{
    /** @var array<string, array<int, string>> */
    public const CATEGORIES = [
        'seo' => ['title', 'title_optimal_length', 'meta_description', 'meta_description_optimal_length', 'headings', 'h1_usage', 'header_tag_usage', 'content_keywords', 'keyword_consistency', 'image_keywords', 'open_graph', 'twitter_cards', 'seo_friendly_url', 'canonical_tag', 'canonical_self_reference', 'hreflang', '404_page', 'robots', 'noindex', 'robots_directives', 'noindex_header', 'in_page_links', 'nofollow_links', 'link_url_readability', 'language', 'favicon'],
        'performance' => ['text_compression', 'brotli_compression', 'load_time', 'ttfb', 'page_size', 'http_requests', 'static_cache_headers', 'expires_headers', 'avoid_redirects', 'redirect_chains', 'cookie_free_domains', 'empty_src_or_href', 'image_format', 'image_dimensions', 'image_lazy_loading', 'image_size_optimization', 'lcp_proxy', 'cls_proxy', 'defer_javascript', 'render_blocking_resources', 'minification', 'dom_size', 'doctype'],
        'security' => ['https_encryption', 'http2', 'mixed_content', 'server_signature', 'unsafe_cross_origin_links', 'hsts', 'plaintext_email'],
        'miscellaneous' => ['structured_data', 'meta_viewport', 'charset', 'sitemap', 'social', 'content_length', 'text_html_ratio', 'inline_css', 'deprecated_html_tags', 'llms_txt', 'flash_content', 'iframes'],
        'technology' => ['server_ip', 'dns_servers', 'dmarc_record', 'spf_record', 'ssl_certificate', 'reverse_dns', 'analytics', 'technology_detection'],
    ];

    /**
     * @param array<string, mixed> $results
     * @param array<string, array<int, string>>|null $categories
     */
    public function __construct(
        private string $url,
        private array $results,
        private float $score,
        private \DateTimeImmutable $generatedAt,
        private ?array $categories = null,
    ) {}

    public function getUrl(): string
    {
        return $this->url;
    }

    /** @return array<string, mixed> */
    public function getResults(): array
    {
        return $this->results;
    }

    public function getScore(): float
    {
        return $this->score;
    }

    public function getGeneratedAt(): \DateTimeImmutable
    {
        return $this->generatedAt;
    }

    /** @return array<string, array<int, string>> */
    public function getCategories(): array
    {
        return $this->categories ?? self::CATEGORIES;
    }

    public function getFullUrl(): string
    {
        if (isset($this->results['seo_friendly_url']['value'])) {
            return (string) $this->results['seo_friendly_url']['value'];
        }
        return $this->url;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'score' => $this->score,
            'generated_at' => $this->generatedAt->format(\DateTimeInterface::ATOM),
            'results' => $this->results,
            'categories' => $this->getCategories(),
        ];
    }

    public function toJson(int $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE): string
    {
        $json = json_encode($this->toArray(), $flags);
        if ($json === false) {
            throw new \RuntimeException('JSON encode failed');
        }
        return $json;
    }
}
