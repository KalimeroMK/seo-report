<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Actions;

use KalimeroMK\SeoReport\Config\SeoReportConfig;
use KalimeroMK\SeoReport\Support\UrlHelperTrait;
use Psr\Http\Message\ResponseInterface;

final readonly class AnalysisContext
{
    use UrlHelperTrait {
        resolveUrl as private resolveUrlWithBase;
        isInternalUrl as private isInternalUrlWithBase;
        normalizeUrlForCanonical as private normalizeUrlForCanonicalInternal;
    }

    /**
     * @param array<string, mixed> $stats
     * @param list<string> $bodyKeywords
     * @param array<string, mixed> $data
     */
    public function __construct(
        private SeoReportConfig $config,
        private ResponseInterface $response,
        private array $stats,
        private string $url,
        private \DOMDocument $dom,
        private string $responseBody,
        private string $pageText,
        private array $bodyKeywords,
        private string $docType,
        private array $data = [],
    ) {}

    public function getConfig(): SeoReportConfig
    {
        return $this->config;
    }

    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }

    /** @return array<string, mixed> */
    public function getStats(): array
    {
        return $this->stats;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getDom(): \DOMDocument
    {
        return $this->dom;
    }

    public function getResponseBody(): string
    {
        return $this->responseBody;
    }

    public function getPageText(): string
    {
        return $this->pageText;
    }

    /** @return list<string> */
    public function getBodyKeywords(): array
    {
        return $this->bodyKeywords;
    }

    public function getDocType(): string
    {
        return $this->docType;
    }

    public function getData(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function resolveUrl(string $url): string
    {
        return $this->resolveUrlWithBase($url, $this->url);
    }

    public function isInternalUrl(string $url): bool
    {
        return $this->isInternalUrlWithBase($url, $this->url);
    }

    public function normalizeUrlForCanonical(string $url): string
    {
        return $this->normalizeUrlForCanonicalInternal($url);
    }
}
