<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Actions\Seo;

use KalimeroMK\SeoReport\Actions\AnalysisActionInterface;
use KalimeroMK\SeoReport\Actions\AnalysisContext;

/**
 * Basic mobile usability checks without external API.
 * Validates viewport, touch targets, font sizes, and mobile-friendly patterns.
 */
final class MobileUsabilityAction implements AnalysisActionInterface
{
    public function handle(AnalysisContext $context): array
    {
        $domDocument = $context->getDom();
        $metaViewport = (string) $context->getData('meta_viewport', '');
        $inlineCss = (array) $context->getData('inline_css', []);

        $result = [];

        $result['viewport_config'] = $this->checkViewportConfig($metaViewport);
        $result['touch_target_size'] = $this->checkTouchTargetSize($domDocument);
        $result['font_size_readability'] = $this->checkFontSizes($domDocument, $inlineCss);
        $result['flexible_layout'] = $this->checkFlexibleLayout($domDocument);
        $result['mobile_friendly_patterns'] = $this->checkMobileFriendlyPatterns($domDocument);
        $result['zoom_accessibility'] = $this->checkZoomAccessibility($metaViewport);

        return $result;
    }

    /**
     * Validate viewport meta tag configuration
     *
     * @return array<string, mixed>
     */
    private function checkViewportConfig(string $metaViewport): array
    {
        $result = [
            'passed' => false,
            'importance' => 'high',
            'value' => ['viewport' => $metaViewport],
        ];

        if ($metaViewport === '') {
            $result['errors'] = [
                'message' => 'Viewport meta tag not found',
                'recommendation' => 'Add <meta name="viewport" content="width=device-width, initial-scale=1">',
            ];
            return $result;
        }

        $hasWidth = str_contains($metaViewport, 'width=device-width');
        $hasInitialScale = preg_match('/initial-scale=\d/', $metaViewport);
        $hasUserScalableNo = str_contains($metaViewport, 'user-scalable=no');

        $passed = $hasWidth && !$hasUserScalableNo;

        $result['passed'] = $passed;
        $result['value'] = [
            'viewport' => $metaViewport,
            'has_width_device_width' => $hasWidth,
            'has_initial_scale' => $hasInitialScale,
            'user_scalable_disabled' => $hasUserScalableNo,
        ];

        if (!$hasWidth) {
            $result['errors'] = [
                'message' => 'Viewport missing width=device-width',
                'recommendation' => 'Use width=device-width to match screen width',
            ];
        }

        return $result;
    }

    /**
     * Check touch target sizes for buttons and links
     *
     * @return array<string, mixed>
     */
    private function checkTouchTargetSize(\DOMDocument $domDocument): array
    {
        $smallTargets = [];
        $minTouchSize = 44;

        foreach ($domDocument->getElementsByTagName('a') as $node) {
            $style = $node->getAttribute('style');
            $size = $this->extractElementSize($style);
            
            if ($size !== null && ($size['width'] < $minTouchSize || $size['height'] < $minTouchSize)) {
                $smallTargets[] = [
                    'type' => 'link',
                    'text' => substr(trim($node->textContent), 0, 30),
                    'size' => $size,
                ];
            }
        }

        foreach ($domDocument->getElementsByTagName('button') as $node) {
            $style = $node->getAttribute('style');
            $size = $this->extractElementSize($style);
            
            if ($size !== null && ($size['width'] < $minTouchSize || $size['height'] < $minTouchSize)) {
                $smallTargets[] = [
                    'type' => 'button',
                    'text' => substr(trim($node->textContent), 0, 30),
                    'size' => $size,
                ];
            }
        }

        foreach ($domDocument->getElementsByTagName('input') as $node) {
            $type = strtolower($node->getAttribute('type'));
            if (in_array($type, ['submit', 'button', 'image'], true)) {
                $style = $node->getAttribute('style');
                $size = $this->extractElementSize($style);
                
                if ($size !== null && ($size['width'] < $minTouchSize || $size['height'] < $minTouchSize)) {
                    $smallTargets[] = [
                        'type' => 'input:' . $type,
                        'size' => $size,
                    ];
                }
            }
        }

        $result = [
            'passed' => count($smallTargets) === 0,
            'importance' => 'high',
            'value' => [
                'small_targets_found' => count($smallTargets),
                'min_recommended_size' => $minTouchSize . 'x' . $minTouchSize . 'px',
            ],
        ];

        if (count($smallTargets) > 0) {
            $result['errors'] = [
                'message' => 'Touch targets too small detected',
                'count' => count($smallTargets),
                'examples' => array_slice($smallTargets, 0, 5),
                'recommendation' => 'Ensure all interactive elements are at least 44x44 pixels (WCAG 2.1)',
            ];
        }

        return $result;
    }

    /**
     * Check font sizes for readability
     *
     * @param array<int, string> $inlineCss
     * @return array<string, mixed>
     */
    private function checkFontSizes(\DOMDocument $domDocument, array $inlineCss): array
    {
        $smallFonts = [];
        $minFontSize = 12;

        foreach ($inlineCss as $style) {
            if (preg_match('/font-size:\s*(\d+)px/i', $style, $matches)) {
                $size = (int) $matches[1];
                if ($size < $minFontSize) {
                    $smallFonts[] = [
                        'size' => $size,
                        'source' => 'inline_style',
                    ];
                }
            }
        }

        foreach ($domDocument->getElementsByTagName('font') as $node) {
            $size = $node->getAttribute('size');
            if ($size !== '' && (int) $size < 2) {
                $smallFonts[] = [
                    'size' => $size,
                    'source' => 'font_tag',
                ];
            }
        }

        foreach ($domDocument->getElementsByTagName('small') as $node) {
            $smallFonts[] = [
                'tag' => 'small',
                'source' => 'small_tag',
                'text' => substr(trim($node->textContent), 0, 50),
            ];
        }

        $result = [
            'passed' => count($smallFonts) === 0,
            'importance' => 'medium',
            'value' => [
                'small_font_instances' => count($smallFonts),
                'min_recommended_size' => $minFontSize . 'px',
            ],
        ];

        if (count($smallFonts) > 0) {
            $result['errors'] = [
                'message' => 'Small font sizes detected',
                'count' => count($smallFonts),
                'examples' => array_slice($smallFonts, 0, 5),
                'recommendation' => 'Use minimum 12px font size for mobile readability',
            ];
        }

        return $result;
    }

    /**
     * Check for fixed width layout issues
     *
     * @return array<string, mixed>
     */
    private function checkFlexibleLayout(\DOMDocument $domDocument): array
    {
        $fixedWidthElements = [];

        $tagsToCheck = ['div', 'table', 'img', 'iframe', 'object', 'embed'];

        foreach ($tagsToCheck as $tagName) {
            foreach ($domDocument->getElementsByTagName($tagName) as $node) {
                $style = $node->getAttribute('style');
                $width = $node->getAttribute('width');

                if (preg_match('/width:\s*(\d+)px/i', $style, $matches)) {
                    $pixelWidth = (int) $matches[1];
                    if ($pixelWidth > 400) { // Likely problematic on mobile
                        $fixedWidthElements[] = [
                            'tag' => $tagName,
                            'width' => $pixelWidth . 'px',
                            'source' => 'style',
                        ];
                    }
                }

                if (preg_match('/^\d+$/', $width)) {
                    $pixelWidth = (int) $width;
                    if ($pixelWidth > 400) {
                        $fixedWidthElements[] = [
                            'tag' => $tagName,
                            'width' => $pixelWidth . 'px',
                            'source' => 'attribute',
                        ];
                    }
                }
            }
        }

        $result = [
            'passed' => count($fixedWidthElements) === 0,
            'importance' => 'high',
            'value' => [
                'fixed_width_elements' => count($fixedWidthElements),
            ],
        ];

        if (count($fixedWidthElements) > 0) {
            $result['errors'] = [
                'message' => 'Fixed width elements may cause horizontal scrolling',
                'count' => count($fixedWidthElements),
                'examples' => array_slice($fixedWidthElements, 0, 5),
                'recommendation' => 'Use responsive units (%, vw, max-width) instead of fixed pixels',
            ];
        }

        return $result;
    }

    /**
     * Check for mobile-friendly patterns and issues
     *
     * @return array<string, mixed>
     */
    private function checkMobileFriendlyPatterns(\DOMDocument $domDocument): array
    {
        $issues = [];

        $flashElements = $domDocument->getElementsByTagName('object');
        foreach ($flashElements as $element) {
            $type = $element->getAttribute('type');
            $data = $element->getAttribute('data');
            if (str_contains($type, 'flash') || str_contains($data, '.swf')) {
                $issues[] = [
                    'type' => 'flash_content',
                    'message' => 'Flash content detected (not supported on mobile)',
                ];
            }
        }

        $frames = $domDocument->getElementsByTagName('frame');
        $iframes = $domDocument->getElementsByTagName('iframe');

        if ($frames->length > 0) {
            $issues[] = [
                'type' => 'frames',
                'count' => $frames->length,
                'message' => 'Framesets not mobile-friendly',
            ];
        }

        $plugins = ['applet', 'bgsound', 'blink', 'marquee'];
        foreach ($plugins as $plugin) {
            $elements = $domDocument->getElementsByTagName($plugin);
            if ($elements->length > 0) {
                $issues[] = [
                    'type' => 'deprecated_plugin',
                    'element' => $plugin,
                    'count' => $elements->length,
                ];
            }
        }

        $result = [
            'passed' => count($issues) === 0,
            'importance' => 'high',
            'value' => [
                'mobile_issues_found' => count($issues),
                'issues' => $issues,
            ],
        ];

        if (count($issues) > 0) {
            $result['errors'] = [
                'message' => 'Mobile-unfriendly content detected',
                'count' => count($issues),
                'issues' => $issues,
                'recommendation' => 'Remove or replace Flash, frames, and deprecated elements',
            ];
        }

        return $result;
    }

    /**
     * Check if zoom is disabled (bad for accessibility)
     *
     * @return array<string, mixed>
     */
    private function checkZoomAccessibility(string $metaViewport): array
    {
        $hasUserScalableNo = str_contains($metaViewport, 'user-scalable=no');
        $hasMaximumScale = preg_match('/maximum-scale=([\d.]+)/', $metaViewport, $matches);
        $maxScale = $hasMaximumScale ? (float) $matches[1] : null;

        $result = [
            'passed' => !$hasUserScalableNo && (!$hasMaximumScale || $maxScale >= 2),
            'importance' => 'medium',
            'value' => [
                'user_scalable_disabled' => $hasUserScalableNo,
                'maximum_scale' => $maxScale,
            ],
        ];

        if ($hasUserScalableNo) {
            $result['errors'] = [
                'message' => 'Zoom disabled via user-scalable=no',
                'recommendation' => 'Remove user-scalable=no to allow users to zoom (WCAG 2.1 requirement)',
            ];
        } elseif ($hasMaximumScale && $maxScale !== null && $maxScale < 2) {
            $result['warnings'] = [
                'message' => 'Maximum zoom level too restrictive',
                'max_scale' => $maxScale,
                'recommendation' => 'Set maximum-scale to at least 2 for accessibility',
            ];
        }

        return $result;
    }

    /**
     * Extract width and height from inline style
     *
     * @return array<string, int>|null
     */
    private function extractElementSize(string $style): ?array
    {
        $width = null;
        $height = null;

        if (preg_match('/width:\s*(\d+)px/i', $style, $matches)) {
            $width = (int) $matches[1];
        }

        if (preg_match('/height:\s*(\d+)px/i', $style, $matches)) {
            $height = (int) $matches[1];
        }

        if ($width !== null || $height !== null) {
            return [
                'width' => $width ?? 0,
                'height' => $height ?? 0,
            ];
        }

        return null;
    }
}
