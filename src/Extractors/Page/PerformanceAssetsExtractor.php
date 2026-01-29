<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Extractors\Page;

use KalimeroMK\SeoReport\Support\UrlHelperTrait;

final class PerformanceAssetsExtractor
{
    use UrlHelperTrait {
        resolveUrl as private resolveUrlWithBase;
    }

    /**
     * @return array{
     *     http_requests: array<string, array<int, string>>,
     *     empty_src_or_href: array<int, string>,
     *     defer_javascript: array<int, string>,
     *     render_blocking: array{js: array<int, string>, css: array<int, string>}
     * }
     */
    public function extract(\DOMDocument $domDocument, string $baseUrl): array
    {
        $resolveUrl = fn (string $url): string => $this->resolveUrlWithBase($url, $baseUrl);

        $httpRequests = ['JavaScripts' => [], 'CSS' => [], 'Images' => [], 'Audios' => [], 'Videos' => [], 'Iframes' => []];
        foreach ($domDocument->getElementsByTagName('script') as $node) {
            if ($node->getAttribute('src') !== '' && $node->getAttribute('src') !== '0') {
                $httpRequests['JavaScripts'][] = $resolveUrl($node->getAttribute('src'));
            }
        }
        foreach ($domDocument->getElementsByTagName('link') as $node) {
            if (preg_match('/\bstylesheet\b/', $node->getAttribute('rel'))) {
                $httpRequests['CSS'][] = $resolveUrl($node->getAttribute('href'));
            }
        }
        foreach ($domDocument->getElementsByTagName('img') as $node) {
            $src = $node->getAttribute('src');
            if ($src !== '' && !preg_match('/\blazy\b/', $node->getAttribute('loading'))) {
                $httpRequests['Images'][] = $resolveUrl($src);
            }
        }
        foreach ($domDocument->getElementsByTagName('iframe') as $node) {
            $src = $node->getAttribute('src');
            if ($src !== '' && !preg_match('/\blazy\b/', $node->getAttribute('loading'))) {
                $httpRequests['Iframes'][] = $resolveUrl($src);
            }
        }

        $emptySrcHref = [];
        $srcHrefTags = [
            ['tag' => 'img', 'attr' => 'src'],
            ['tag' => 'script', 'attr' => 'src'],
            ['tag' => 'iframe', 'attr' => 'src'],
            ['tag' => 'link', 'attr' => 'href'],
            ['tag' => 'a', 'attr' => 'href'],
        ];
        foreach ($srcHrefTags as $spec) {
            foreach ($domDocument->getElementsByTagName($spec['tag']) as $node) {
                if (!$node->hasAttribute($spec['attr'])) {
                    continue;
                }
                $value = trim($node->getAttribute($spec['attr']));
                if ($value === '' || $value === '0') {
                    $emptySrcHref[] = $spec['tag'] . '[' . $spec['attr'] . ']';
                }
            }
        }

        $deferJavaScript = [];
        foreach ($domDocument->getElementsByTagName('script') as $node) {
            if ($node->getAttribute('src') && !$node->hasAttribute('defer')) {
                $deferJavaScript[] = $resolveUrl($node->getAttribute('src'));
            }
        }

        $renderBlocking = ['js' => [], 'css' => []];
        foreach ($domDocument->getElementsByTagName('head') as $headNode) {
            foreach ($headNode->getElementsByTagName('script') as $node) {
                if ($node->getAttribute('src') && !$node->hasAttribute('defer') && !$node->hasAttribute('async')) {
                    $renderBlocking['js'][] = $resolveUrl($node->getAttribute('src'));
                }
            }
            foreach ($headNode->getElementsByTagName('link') as $node) {
                if (!preg_match('/\bstylesheet\b/i', $node->getAttribute('rel'))) {
                    continue;
                }
                $href = $node->getAttribute('href');
                if ($href === '' || $href === '0') {
                    continue;
                }
                $media = trim(mb_strtolower($node->getAttribute('media')));
                if ($media !== '' && $media !== 'all') {
                    continue;
                }
                $renderBlocking['css'][] = $resolveUrl($href);
            }
        }

        return [
            'http_requests' => $httpRequests,
            'empty_src_or_href' => $emptySrcHref,
            'defer_javascript' => $deferJavaScript,
            'render_blocking' => $renderBlocking,
        ];
    }
}
