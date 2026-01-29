<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Extractors\Page;

use KalimeroMK\SeoReport\Support\UrlHelperTrait;

final class LinksExtractor
{
    use UrlHelperTrait {
        resolveUrl as private resolveUrlWithBase;
        isInternalUrl as private isInternalUrlWithBase;
    }

    /**
     * @return array{
     *     page_links: array{Internals: array<int, array{url: string, text: string}>, Externals: array<int, array{url: string, text: string}>},
     *     unfriendly_link_urls: array<int, array{url: string, text: string}>,
     *     nofollow_links: array<int, array{url: string, text: string}>,
     *     nofollow_count: int,
     *     social: array<string, array<int, array{url: string, text: string}>>
     * }
     */
    public function extract(\DOMDocument $domDocument, string $baseUrl): array
    {
        $resolveUrl = fn (string $url): string => $this->resolveUrlWithBase($url, $baseUrl);
        $isInternalUrl = fn (string $url): bool => $this->isInternalUrlWithBase($url, $baseUrl);

        $pageLinks = ['Internals' => [], 'Externals' => []];
        $unfriendlyLinkUrls = [];
        foreach ($domDocument->getElementsByTagName('a') as $node) {
            if (empty($node->getAttribute('href')) || mb_substr($node->getAttribute('href'), 0, 1) === '#') {
                continue;
            }
            $resolved = $resolveUrl($node->getAttribute('href'));
            $entry = ['url' => $resolved, 'text' => seo_report_clean_tag_text($node->textContent)];
            if ($isInternalUrl($resolved)) {
                $pageLinks['Internals'][] = $entry;
            } else {
                $pageLinks['Externals'][] = $entry;
            }
            if (preg_match('/[\?\=\_\%\,\ ]/ui', $resolved)) {
                $unfriendlyLinkUrls[] = [
                    'url' => $resolved,
                    'text' => seo_report_clean_tag_text($node->textContent),
                ];
            }
        }

        $nofollowLinks = [];
        $nofollowCount = 0;
        foreach ($domDocument->getElementsByTagName('a') as $node) {
            $rel = strtolower($node->getAttribute('rel'));
            if (str_contains($rel, 'nofollow')) {
                $nofollowCount++;
                $href = $node->getAttribute('href');
                if ($href !== '') {
                    $nofollowLinks[] = [
                        'url' => $resolveUrl($href),
                        'text' => seo_report_clean_tag_text($node->textContent),
                    ];
                }
            }
        }

        $social = [];
        $socials = [
            'twitter.com' => 'Twitter',
            'www.twitter.com' => 'Twitter',
            'facebook.com' => 'Facebook',
            'www.facebook.com' => 'Facebook',
            'instagram.com' => 'Instagram',
            'www.instagram.com' => 'Instagram',
            'youtube.com' => 'YouTube',
            'www.youtube.com' => 'YouTube',
            'linkedin.com' => 'LinkedIn',
            'www.linkedin.com' => 'LinkedIn',
        ];
        foreach ($domDocument->getElementsByTagName('a') as $node) {
            $href = $node->getAttribute('href');
            if ($href === '' || mb_substr($href, 0, 1) === '#') {
                continue;
            }
            $resolved = $resolveUrl($href);
            if ($isInternalUrl($resolved)) {
                continue;
            }
            $host = parse_url($resolved, PHP_URL_HOST);
            if (!empty($host) && isset($socials[$host])) {
                $social[$socials[$host]][] = [
                    'url' => $resolved,
                    'text' => seo_report_clean_tag_text($node->textContent),
                ];
            }
        }

        return [
            'page_links' => $pageLinks,
            'unfriendly_link_urls' => $unfriendlyLinkUrls,
            'nofollow_links' => $nofollowLinks,
            'nofollow_count' => $nofollowCount,
            'social' => $social,
        ];
    }
}
