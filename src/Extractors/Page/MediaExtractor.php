<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Extractors\Page;

use KalimeroMK\SeoReport\Config\SeoReportConfig;
use KalimeroMK\SeoReport\Support\UrlHelperTrait;

final class MediaExtractor
{
    use UrlHelperTrait {
        resolveUrl as private resolveUrlWithBase;
    }

    /**
     * @return array{
     *     image_alts: array<int, array{url: string, text: string}>,
     *     image_formats_config: array<int, string>,
     *     image_formats: array<int, array{url: string, text: string}>,
     *     images_missing_dimensions: array<int, array{url: string, missing: array<int, string>}>,
     *     images_missing_lazy: array<int, string>,
     *     image_urls: array<int, string>,
     *     flash_content: array<int, string>,
     *     iframes: array<int, array{url: string, title: string}>
     * }
     */
    public function extract(\DOMDocument $domDocument, string $baseUrl, SeoReportConfig $config): array
    {
        $resolveUrl = fn (string $url): string => $this->resolveUrlWithBase($url, $baseUrl);

        $imageAlts = [];
        foreach ($domDocument->getElementsByTagName('img') as $node) {
            if (!empty($node->getAttribute('src')) && empty($node->getAttribute('alt'))) {
                $imageAlts[] = [
                    'url' => $resolveUrl($node->getAttribute('src')),
                    'text' => seo_report_clean_tag_text($node->getAttribute('alt')),
                ];
            }
        }

        $imageFormatsConfig = preg_split('/\n|\r/', $config->getReportLimitImageFormats(), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $allowedExtensions = array_map(strtolower(...), $imageFormatsConfig);
        $imageFormats = [];
        $imagesMissingDimensions = [];
        $imagesMissingLazy = [];
        $imageUrls = [];
        foreach ($domDocument->getElementsByTagName('img') as $node) {
            if (empty($node->getAttribute('src'))) {
                continue;
            }
            $imgUrl = $resolveUrl($node->getAttribute('src'));
            $imageUrls[] = $imgUrl;
            $missing = [];
            $width = trim($node->getAttribute('width'));
            $height = trim($node->getAttribute('height'));
            if ($width === '' || $width === '0') {
                $missing[] = 'width';
            }
            if ($height === '' || $height === '0') {
                $missing[] = 'height';
            }
            if ($missing !== []) {
                $imagesMissingDimensions[] = ['url' => $imgUrl, 'missing' => $missing];
            }

            $loading = trim(mb_strtolower($node->getAttribute('loading')));
            if ($loading !== 'lazy') {
                $imagesMissingLazy[] = $imgUrl;
            }

            $ext = mb_strtolower(pathinfo($imgUrl, PATHINFO_EXTENSION));
            if ($ext === 'svg' || in_array($ext, $allowedExtensions, true)) {
                continue;
            }
            $imageFormats[] = [
                'url' => $imgUrl,
                'text' => seo_report_clean_tag_text($node->getAttribute('alt')),
            ];
        }

        $flashContent = [];
        foreach ($domDocument->getElementsByTagName('object') as $node) {
            $type = strtolower($node->getAttribute('type'));
            $data = $node->getAttribute('data');
            if (str_contains($type, 'flash') || str_contains($type, 'shockwave') || preg_match('/\.swf$/i', $data)) {
                $flashContent[] = $resolveUrl($data ?: '');
            }
        }
        foreach ($domDocument->getElementsByTagName('embed') as $node) {
            $type = strtolower($node->getAttribute('type'));
            $src = $node->getAttribute('src');
            if (str_contains($type, 'flash') || str_contains($type, 'shockwave') || preg_match('/\.swf$/i', $src)) {
                $flashContent[] = $resolveUrl($src ?: '');
            }
        }

        $iframes = [];
        foreach ($domDocument->getElementsByTagName('iframe') as $node) {
            $src = $node->getAttribute('src');
            if ($src !== '') {
                $iframes[] = [
                    'url' => $resolveUrl($src),
                    'title' => seo_report_clean_tag_text($node->getAttribute('title')),
                ];
            }
        }

        return [
            'image_alts' => $imageAlts,
            'image_formats_config' => $imageFormatsConfig,
            'image_formats' => $imageFormats,
            'images_missing_dimensions' => $imagesMissingDimensions,
            'images_missing_lazy' => $imagesMissingLazy,
            'image_urls' => $imageUrls,
            'flash_content' => $flashContent,
            'iframes' => $iframes,
        ];
    }
}
