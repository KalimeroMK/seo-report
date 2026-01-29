<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Extractors\Page;

use KalimeroMK\SeoReport\Support\UrlHelperTrait;

final class ContentSecurityExtractor
{
    use UrlHelperTrait {
        resolveUrl as private resolveUrlWithBase;
        isInternalUrl as private isInternalUrlWithBase;
    }

    /**
     * @return array{
     *     http_scheme: string|null,
     *     mixed_content: array<string, list<string>>,
     *     unsafe_cross_origin_links: list<string>,
     *     plaintext_emails: list<string>
     * }
     */
    public function extract(\DOMDocument $domDocument, string $baseUrl, string $reportResponse): array
    {
        $resolveUrl = fn (string $url): string => $this->resolveUrlWithBase($url, $baseUrl);
        $isInternalUrl = fn (string $url): bool => $this->isInternalUrlWithBase($url, $baseUrl);

        $httpScheme = parse_url($baseUrl, PHP_URL_SCHEME) ?: null;

        $mixedContent = [];
        if (str_starts_with($baseUrl, 'https://')) {
            foreach ($domDocument->getElementsByTagName('script') as $node) {
                if ($node->getAttribute('src') !== '' && str_starts_with($node->getAttribute('src'), 'http://')) {
                    $mixedContent['JavaScripts'][] = $resolveUrl($node->getAttribute('src'));
                }
            }
            foreach ($domDocument->getElementsByTagName('link') as $node) {
                if (preg_match('/\bstylesheet\b/', $node->getAttribute('rel')) && str_starts_with($node->getAttribute('href'), 'http://')) {
                    $mixedContent['CSS'][] = $resolveUrl($node->getAttribute('href'));
                }
            }
            foreach ($domDocument->getElementsByTagName('img') as $node) {
                if (!empty($node->getAttribute('src')) && str_starts_with($node->getAttribute('src'), 'http://')) {
                    $mixedContent['Images'][] = $resolveUrl($node->getAttribute('src'));
                }
            }
            foreach ($domDocument->getElementsByTagName('iframe') as $node) {
                if (!empty($node->getAttribute('src')) && str_starts_with($node->getAttribute('src'), 'http://')) {
                    $mixedContent['Iframes'][] = $resolveUrl($node->getAttribute('src'));
                }
            }
        }

        $unsafeCrossOriginLinks = [];
        foreach ($domDocument->getElementsByTagName('a') as $node) {
            $href = $node->getAttribute('href');
            if ($href === '') {
                continue;
            }
            $resolved = $resolveUrl($href);
            if ($isInternalUrl($resolved)) {
                continue;
            }
            if ($node->getAttribute('target') === '_blank') {
                $rel = strtolower($node->getAttribute('rel'));
                if (!str_contains($rel, 'noopener') && !str_contains($rel, 'nofollow')) {
                    $unsafeCrossOriginLinks[] = $resolved;
                }
            }
        }

        preg_match_all('/([a-zA-Z0-9._-]+@[a-zA-Z0-9._-]+\.[a-zA-Z0-9_-]+)/i', $reportResponse, $plaintextEmailsRaw, PREG_UNMATCHED_AS_NULL);
        $rawEmails = $plaintextEmailsRaw[0];
        $plaintextEmails = array_values(array_filter(
            $rawEmails,
            fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false
        ));

        return [
            'http_scheme' => $httpScheme,
            'mixed_content' => $mixedContent,
            'unsafe_cross_origin_links' => $unsafeCrossOriginLinks,
            'plaintext_emails' => $plaintextEmails,
        ];
    }
}
