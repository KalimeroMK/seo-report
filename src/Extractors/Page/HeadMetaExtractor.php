<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Extractors\Page;

use KalimeroMK\SeoReport\Support\UrlHelperTrait;

final class HeadMetaExtractor
{
    use UrlHelperTrait {
        resolveUrl as private resolveUrlWithBase;
    }

    /**
     * @return array{
     *     title: string|null,
     *     title_tags_count: int,
     *     meta_description: string|null,
     *     noindex: string|null,
     *     robots_directives: array<int, string>,
     *     language: string|null,
     *     favicon: string|null,
     *     canonical_tag: string|null,
     *     canonical_tags: array<int, string>,
     *     hreflang: array<int, array{hreflang: string, href: string}>,
     *     meta_viewport: string|null,
     *     charset: string|null
     * }
     */
    public function extract(\DOMDocument $domDocument, string $baseUrl): array
    {
        $resolveUrl = fn (string $url): string => $this->resolveUrlWithBase($url, $baseUrl);

        $title = null;
        $titleTagsCount = 0;
        foreach ($domDocument->getElementsByTagName('head') as $headNode) {
            foreach ($headNode->getElementsByTagName('title') as $titleNode) {
                $title .= seo_report_clean_tag_text($titleNode->textContent);
                $titleTagsCount++;
            }
        }

        $metaDescription = null;
        foreach ($domDocument->getElementsByTagName('head') as $headNode) {
            foreach ($headNode->getElementsByTagName('meta') as $node) {
                if (strtolower($node->getAttribute('name')) === 'description' && seo_report_clean_tag_text($node->getAttribute('content'))) {
                    $metaDescription = seo_report_clean_tag_text($node->getAttribute('content'));
                }
            }
        }

        $noIndex = null;
        $robotsDirectives = [];
        foreach ($domDocument->getElementsByTagName('head') as $headNode) {
            foreach ($headNode->getElementsByTagName('meta') as $node) {
                $metaName = strtolower($node->getAttribute('name'));
                if ($metaName !== 'robots' && $metaName !== 'googlebot') {
                    continue;
                }
                $content = trim($node->getAttribute('content'));
                if ($content === '') {
                    continue;
                }
                $robotsDirectives = array_merge($robotsDirectives, array_map(trim(...), explode(',', strtolower($content))));
                if (preg_match('/\bnoindex\b/', $content)) {
                    $noIndex = $content;
                }
            }
        }

        $language = null;
        foreach ($domDocument->getElementsByTagName('html') as $node) {
            if ($node->getAttribute('lang') !== '' && $node->getAttribute('lang') !== '0') {
                $language = $node->getAttribute('lang');
            }
        }

        $favicon = null;
        foreach ($domDocument->getElementsByTagName('head') as $headNode) {
            foreach ($headNode->getElementsByTagName('link') as $node) {
                if (preg_match('/\bicon\b/i', $node->getAttribute('rel'))) {
                    $favicon = $resolveUrl($node->getAttribute('href'));
                }
            }
        }

        $canonicalTag = null;
        $canonicalTags = [];
        $hreflang = [];
        foreach ($domDocument->getElementsByTagName('head') as $headNode) {
            foreach ($headNode->getElementsByTagName('link') as $node) {
                $rel = strtolower($node->getAttribute('rel'));
                if ($rel === 'canonical' && $node->getAttribute('href') !== '') {
                    $canonical = $resolveUrl($node->getAttribute('href'));
                    $canonicalTags[] = $canonical;
                    if ($canonicalTag === null) {
                        $canonicalTag = $canonical;
                    }
                }
                if ($rel === 'alternate' && $node->getAttribute('hreflang') !== '') {
                    $hreflang[] = [
                        'hreflang' => $node->getAttribute('hreflang'),
                        'href' => $resolveUrl($node->getAttribute('href')),
                    ];
                }
            }
        }

        $metaViewport = null;
        foreach ($domDocument->getElementsByTagName('head') as $headNode) {
            foreach ($headNode->getElementsByTagName('meta') as $node) {
                if (strtolower($node->getAttribute('name')) === 'viewport') {
                    $metaViewport = seo_report_clean_tag_text($node->getAttribute('content'));
                }
            }
        }

        $charset = null;
        foreach ($domDocument->getElementsByTagName('head') as $headNode) {
            foreach ($headNode->getElementsByTagName('meta') as $node) {
                if ($node->getAttribute('charset') !== '' && $node->getAttribute('charset') !== '0') {
                    $charset = seo_report_clean_tag_text($node->getAttribute('charset'));
                }
            }
        }

        return [
            'title' => $title,
            'title_tags_count' => $titleTagsCount,
            'meta_description' => $metaDescription,
            'noindex' => $noIndex,
            'robots_directives' => $robotsDirectives,
            'language' => $language,
            'favicon' => $favicon,
            'canonical_tag' => $canonicalTag,
            'canonical_tags' => $canonicalTags,
            'hreflang' => $hreflang,
            'meta_viewport' => $metaViewport,
            'charset' => $charset,
        ];
    }
}
