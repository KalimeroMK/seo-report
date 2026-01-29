<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Extractors\Page;

use KalimeroMK\SeoReport\Support\UrlHelperTrait;

final class TechDetectorExtractor
{
    use UrlHelperTrait {
        resolveUrl as private resolveUrlWithBase;
    }

    /**
     * @return array{
     *     analytics_detected: array<string, bool>,
     *     technology_detected: array<string, bool>,
     *     non_minified_js: array<int, string>,
     *     non_minified_css: array<int, string>
     * }
     */
    public function extract(\DOMDocument $domDocument, string $baseUrl): array
    {
        $resolveUrl = fn (string $url): string => $this->resolveUrlWithBase($url, $baseUrl);

        $analyticsDetected = [];
        $technologyDetected = [];
        $nonMinifiedJs = [];
        $nonMinifiedCss = [];
        foreach ($domDocument->getElementsByTagName('script') as $node) {
            $src = $node->getAttribute('src');
            $content = $node->textContent ?? '';
            if ($src !== '') {
                $srcLower = mb_strtolower($src);
                $resolvedSrc = $resolveUrl($src);
                if (str_contains($srcLower, 'google-analytics.com') || str_contains($srcLower, 'googletagmanager.com')) {
                    $analyticsDetected['Google Analytics'] = true;
                }
                if (str_contains($srcLower, 'facebook.net') || str_contains($srcLower, 'connect.facebook')) {
                    $technologyDetected['Facebook Pixel'] = true;
                }
                if (str_contains($srcLower, 'fontawesome') || str_contains($srcLower, 'font-awesome')) {
                    $technologyDetected['Font Awesome'] = true;
                }
                if (str_contains($srcLower, 'jquery')) {
                    $technologyDetected['jQuery'] = true;
                }
                if (preg_match('/\.js$/i', $resolvedSrc) && !preg_match('/\.min\.js$/i', $resolvedSrc)) {
                    $nonMinifiedJs[] = $resolvedSrc;
                }
            }
            if ($content !== '') {
                if (preg_match('/\b(gtag|ga\s*\(|googleAnalytics)/i', $content)) {
                    $analyticsDetected['Google Analytics'] = true;
                }
                if (preg_match('/\bfbq\s*\(/i', $content)) {
                    $technologyDetected['Facebook Pixel'] = true;
                }
            }
        }
        foreach ($domDocument->getElementsByTagName('link') as $node) {
            if (preg_match('/\bstylesheet\b/', $node->getAttribute('rel'))) {
                $href = $node->getAttribute('href');
                if ($href !== '') {
                    $resolvedHref = $resolveUrl($href);
                    if (preg_match('/\.css$/i', $resolvedHref) && !preg_match('/\.min\.css$/i', $resolvedHref)) {
                        $nonMinifiedCss[] = $resolvedHref;
                    }
                }
            }
        }

        return [
            'analytics_detected' => $analyticsDetected,
            'technology_detected' => $technologyDetected,
            'non_minified_js' => $nonMinifiedJs,
            'non_minified_css' => $nonMinifiedCss,
        ];
    }
}
