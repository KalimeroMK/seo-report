<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Support;

trait UrlHelperTrait
{
    protected function resolveUrl(string $url, string $baseUrl): string
    {
        $url = str_replace(['\\?', '\\&', '\\#', '\\~', '\\;'], ['?', '&', '#', '~', ';'], $url);
        if (mb_strpos($url, '#') !== false) {
            $url = mb_substr($url, 0, mb_strpos($url, '#'));
        }
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }
        $scheme = parse_url($baseUrl, PHP_URL_SCHEME);
        $host = parse_url($baseUrl, PHP_URL_HOST);
        if (str_starts_with($url, '//')) {
            return ($scheme ?? 'https') . '://' . trim($url, '/');
        }
        if (str_starts_with($url, '/')) {
            return rtrim(($scheme ?? 'https') . '://' . ($host ?? ''), '/') . '/' . ltrim($url, '/');
        }
        if (str_starts_with($url, 'data:image') || str_starts_with($url, 'tel') || str_starts_with($url, 'mailto')) {
            return $url;
        }
        return rtrim(($scheme ?? 'https') . '://' . ($host ?? ''), '/') . '/' . ltrim($url, '/');
    }

    protected function isInternalUrl(string $url, string $baseUrl): bool
    {
        $baseHost = parse_url($baseUrl, PHP_URL_HOST);
        $urlHost = parse_url($url, PHP_URL_HOST);
        return $baseHost !== false && $urlHost !== false && str_starts_with((string) $urlHost, (string) $baseHost);
    }

    protected function normalizeUrlForCanonical(string $url): string
    {
        $parts = parse_url($url);
        if ($parts === false) {
            return rtrim($url, '/');
        }
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        $port = $parts['port'] ?? null;
        $path = (string) ($parts['path'] ?? '');
        $query = (string) ($parts['query'] ?? '');

        $normalized = $scheme !== '' ? $scheme . '://' : '';
        $normalized .= $host;
        if ($port !== null) {
            $isDefault = ($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443);
            if (!$isDefault) {
                $normalized .= ':' . $port;
            }
        }
        $normalized .= $path !== '' ? $path : '/';
        if ($query !== '') {
            $normalized .= '?' . $query;
        }
        return rtrim($normalized, '/');
    }
}
