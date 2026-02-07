<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Actions\Performance;

use KalimeroMK\SeoReport\Actions\AnalysisActionInterface;
use KalimeroMK\SeoReport\Actions\AnalysisContext;

final class ImageOptimizationAction implements AnalysisActionInterface
{
    public function handle(AnalysisContext $context): array
    {
        $imageFormatsConfig = (array) $context->getData('image_formats_config', []);
        $imageFormats = (array) $context->getData('image_formats', []);
        $imagesMissingDimensions = (array) $context->getData('images_missing_dimensions', []);
        $imagesMissingLazy = (array) $context->getData('images_missing_lazy', []);
        $maxImageBytes = (int) $context->getData('image_max_bytes', 0);
        $largeImages = (array) $context->getData('large_images', []);
        $largestImage = $context->getData('largest_image');

        $modernFormats = ['webp', 'avif', 'jxl'];
        $legacyImages = [];
        $modernImages = [];

        foreach ($imageFormats as $img) {
            $ext = strtolower(pathinfo($img['url'] ?? '', PATHINFO_EXTENSION));
            if (!in_array($ext, $modernFormats, true)) {
                $legacyImages[] = $img;
            } else {
                $modernImages[] = $img;
            }
        }

        $result = [
            'image_format' => ['passed' => true, 'importance' => 'medium', 'value' => [
                'modern_formats_used' => count($modernImages),
                'legacy_formats_used' => count($legacyImages),
                'recommended_formats' => $imageFormatsConfig,
            ]],
        ];
        
        $totalImages = count($modernImages) + count($legacyImages);
        if ($totalImages > 0 && count($legacyImages) / $totalImages > 0.5) {
            $result['image_format']['passed'] = false;
            $result['image_format']['errors'] = [
                'too_many_legacy_formats' => [
                    'legacy_count' => count($legacyImages),
                    'modern_count' => count($modernImages),
                    'examples' => array_slice($legacyImages, 0, 5),
                ]
            ];
        }

        $result['image_dimensions'] = ['passed' => true, 'importance' => 'low', 'value' => null];
        if ($imagesMissingDimensions !== []) {
            $result['image_dimensions']['passed'] = false;
            $result['image_dimensions']['errors'] = [
                'missing' => array_slice($imagesMissingDimensions, 0, 10),
                'count' => count($imagesMissingDimensions),
            ];
        }

        $result['image_lazy_loading'] = ['passed' => true, 'importance' => 'low', 'value' => null];
        if ($imagesMissingLazy !== []) {
            $result['image_lazy_loading']['passed'] = false;
            $result['image_lazy_loading']['errors'] = [
                'missing' => array_slice($imagesMissingLazy, 0, 10),
                'count' => count($imagesMissingLazy),
            ];
        }

        $result['image_size_optimization'] = ['passed' => true, 'importance' => 'medium', 'value' => [
            'max_allowed_bytes' => $maxImageBytes,
            'large_images_count' => count($largeImages),
        ]];
        if ($largeImages !== []) {
            $result['image_size_optimization']['passed'] = false;
            $result['image_size_optimization']['errors'] = [
                'too_large' => array_slice($largeImages, 0, 5),
                'count' => count($largeImages),
            ];
        }

        $lcpProxyLimit = $context->getConfig()->getReportLimitLcpProxyBytes();
        $result['lcp_proxy'] = ['passed' => true, 'importance' => 'medium', 'value' => $largestImage];
        if ($largestImage !== null && $lcpProxyLimit > 0 && ($largestImage['bytes'] ?? 0) > $lcpProxyLimit) {
            $result['lcp_proxy']['passed'] = false;
            $result['lcp_proxy']['errors'] = ['too_large' => $largestImage];
        }

        $result['cls_proxy'] = ['passed' => true, 'importance' => 'medium', 'value' => count($imagesMissingDimensions)];
        if ($imagesMissingDimensions !== []) {
            $result['cls_proxy']['passed'] = false;
            $result['cls_proxy']['errors'] = [
                'missing_dimensions' => array_slice($imagesMissingDimensions, 0, 5),
                'count' => count($imagesMissingDimensions),
            ];
        }

        return $result;
    }
}
