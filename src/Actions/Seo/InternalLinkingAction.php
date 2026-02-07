<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Actions\Seo;

use KalimeroMK\SeoReport\Actions\AnalysisActionInterface;
use KalimeroMK\SeoReport\Actions\AnalysisContext;

/**
 * Analyze internal linking structure for SEO optimization.
 * Includes link depth, distribution, and quality checks.
 */
final class InternalLinkingAction implements AnalysisActionInterface
{
    public function handle(AnalysisContext $context): array
    {
        $pageLinks = (array) $context->getData('page_links', []);
        $headings = (array) $context->getData('headings', []);
        $currentUrl = $context->getUrl();

        // Extract internal links from page_links
        $internalLinks = $pageLinks['internal'] ?? [];
        $externalLinks = $pageLinks['external'] ?? [];

        $result = [];

        $result['link_ratio'] = $this->checkLinkRatio($internalLinks, $externalLinks);
        $result['link_distribution'] = $this->analyzeLinkDistribution($internalLinks);
        $result['contextual_links'] = $this->checkContextualLinks($internalLinks, $headings);
        $result['outbound_links'] = $this->analyzeOutboundLinks($internalLinks, $currentUrl);
        $result['anchor_text_quality'] = $this->checkAnchorTextQuality($internalLinks);
        $result['broken_link_patterns'] = $this->detectBrokenLinkPatterns($internalLinks);

        return $result;
    }

    /**
     * Check internal vs external link ratio
     *
     * @param array<int, array<string, mixed>> $internalLinks
     * @param array<int, array<string, mixed>> $externalLinks
     * @return array<string, mixed>
     */
    private function checkLinkRatio(array $internalLinks, array $externalLinks): array
    {
        $internalCount = count($internalLinks);
        $externalCount = count($externalLinks);
        $total = $internalCount + $externalCount;

        $internalRatio = $total > 0 ? ($internalCount / $total) * 100 : 0;
        
        $minInternalRatio = 70;

        $result = [
            'passed' => $internalRatio >= $minInternalRatio,
            'importance' => 'medium',
            'value' => [
                'internal_count' => $internalCount,
                'external_count' => $externalCount,
                'internal_ratio' => round($internalRatio, 2),
                'total_links' => $total,
            ],
        ];

        if ($internalRatio < $minInternalRatio && $total > 0) {
            $result['errors'] = [
                'message' => 'Low internal link ratio',
                'internal_ratio' => round($internalRatio, 2),
                'recommendation' => 'Aim for at least 70% internal links to improve site structure',
            ];
        }

        return $result;
    }

    /**
     * Analyze distribution of internal links across the page
     *
     * @param array<int, array<string, mixed>> $internalLinks
     * @return array<string, mixed>
     */
    private function analyzeLinkDistribution(array $internalLinks): array
    {
        $totalLinks = count($internalLinks);
        
        $urlGroups = [];
        foreach ($internalLinks as $link) {
            $url = $link['url'] ?? '';
            if ($url !== '') {
                $normalizedUrl = $this->normalizeUrl($url);
                $urlGroups[$normalizedUrl][] = $link;
            }
        }

        $duplicates = [];
        foreach ($urlGroups as $url => $links) {
            if (count($links) > 1) {
                $duplicates[] = [
                    'url' => $url,
                    'count' => count($links),
                    'anchor_texts' => array_column($links, 'text'),
                ];
            }
        }

        $maxRecommendedLinks = 150;
        
        $result = [
            'passed' => $totalLinks <= $maxRecommendedLinks && count($duplicates) <= 5,
            'importance' => 'medium',
            'value' => [
                'total_internal_links' => $totalLinks,
                'unique_urls' => count($urlGroups),
                'duplicate_links' => count($duplicates),
            ],
        ];

        if ($totalLinks > $maxRecommendedLinks) {
            $result['errors'] = [
                'message' => 'Too many internal links on page',
                'count' => $totalLinks,
                'max_recommended' => $maxRecommendedLinks,
                'recommendation' => 'Reduce links to improve link equity distribution',
            ];
        }

        if (count($duplicates) > 5) {
            $result['warnings'] = [
                'message' => 'Multiple duplicate links detected',
                'duplicate_count' => count($duplicates),
                'examples' => array_slice($duplicates, 0, 3),
                'recommendation' => 'Consolidate duplicate links to a single instance per page',
            ];
        }

        return $result;
    }

    /**
     * Check for contextual links in headings
     *
     * @param array<int, array<string, mixed>> $internalLinks
     * @param array<string, mixed> $headings
     * @return array<string, mixed>
     */
    private function checkContextualLinks(array $internalLinks, array $headings): array
    {
        $contextualLinks = [];
        $nonContextualLinks = [];

        $navPatterns = [
            '/^(home|index|main|start)\b/i',
            '/\b(menu|navigation|nav)\b/i',
            '/^(next|previous|prev|back|forward)\b/i',
            '/^(click here|read more|learn more|details)\b/i',
        ];

        foreach ($internalLinks as $link) {
            $anchorText = $link['text'] ?? '';
            $isNavigational = false;

            foreach ($navPatterns as $pattern) {
                if (preg_match($pattern, $anchorText)) {
                    $isNavigational = true;
                    break;
                }
            }

            if ($isNavigational) {
                $nonContextualLinks[] = $link;
            } else {
                $contextualLinks[] = $link;
            }
        }

        $contextualRatio = count($internalLinks) > 0 
            ? (count($contextualLinks) / count($internalLinks)) * 100 
            : 0;

        $result = [
            'passed' => $contextualRatio >= 50,
            'importance' => 'medium',
            'value' => [
                'contextual_links' => count($contextualLinks),
                'navigational_links' => count($nonContextualLinks),
                'contextual_ratio' => round($contextualRatio, 2),
            ],
        ];

        if ($contextualRatio < 50 && count($internalLinks) > 0) {
            $result['errors'] = [
                'message' => 'Low ratio of contextual links',
                'contextual_ratio' => round($contextualRatio, 2),
                'examples' => array_slice($nonContextualLinks, 0, 3),
                'recommendation' => 'Use descriptive anchor text instead of "click here" or "read more"',
            ];
        }

        return $result;
    }

    /**
     * Analyze outbound links from current page
     *
     * @param array<int, array<string, mixed>> $internalLinks
     * @return array<string, mixed>
     */
    private function analyzeOutboundLinks(array $internalLinks, string $currentUrl): array
    {
        $linkCount = count($internalLinks);
        
        $selfReferences = [];
        foreach ($internalLinks as $link) {
            $linkUrl = $link['url'] ?? '';
            if ($this->normalizeUrl($linkUrl) === $this->normalizeUrl($currentUrl)) {
                $selfReferences[] = $link;
            }
        }

        $result = [
            'passed' => $linkCount >= 3 && count($selfReferences) === 0,
            'importance' => 'medium',
            'value' => [
                'outbound_internal_links' => $linkCount,
                'self_references' => count($selfReferences),
            ],
        ];

        if ($linkCount < 3) {
            $result['errors'] = [
                'message' => 'Low number of internal outbound links',
                'count' => $linkCount,
                'recommendation' => 'Add more internal links to related content (aim for at least 3-5 per page)',
            ];
        }

        if (count($selfReferences) > 0) {
            $result['warnings'] = [
                'message' => 'Self-referencing links detected',
                'count' => count($selfReferences),
                'recommendation' => 'Remove links pointing to the same page (useless for SEO)',
            ];
        }

        return $result;
    }

    /**
     * Check anchor text quality
     *
     * @param array<int, array<string, mixed>> $internalLinks
     * @return array<string, mixed>
     */
    private function checkAnchorTextQuality(array $internalLinks): array
    {
        $shortAnchors = [];
        $longAnchors = [];
        $imageAnchors = [];
        $emptyAnchors = [];

        foreach ($internalLinks as $link) {
            $anchorText = $link['text'] ?? '';
            $hasImage = $link['has_image'] ?? false;
            $length = strlen($anchorText);

            if ($hasImage) {
                $imageAnchors[] = $link;
            } elseif ($length === 0) {
                $emptyAnchors[] = $link;
            } elseif ($length < 3) {
                $shortAnchors[] = $link;
            } elseif ($length > 100) {
                $longAnchors[] = $link;
            }
        }

        $issues = count($emptyAnchors) + count($shortAnchors);
        
        $result = [
            'passed' => $issues === 0,
            'importance' => 'medium',
            'value' => [
                'total_links' => count($internalLinks),
                'empty_anchors' => count($emptyAnchors),
                'short_anchors' => count($shortAnchors),
                'long_anchors' => count($longAnchors),
                'image_anchors' => count($imageAnchors),
            ],
        ];

        if (count($emptyAnchors) > 0) {
            $result['errors'] = [
                'message' => 'Links with empty anchor text detected',
                'count' => count($emptyAnchors),
                'examples' => array_slice($emptyAnchors, 0, 3),
                'recommendation' => 'All links should have descriptive anchor text or proper alt text for images',
            ];
        }

        if (count($shortAnchors) > 0) {
            $result['warnings'] = [
                'message' => 'Links with very short anchor text',
                'count' => count($shortAnchors),
                'examples' => array_slice($shortAnchors, 0, 3),
                'recommendation' => 'Use more descriptive anchor text (at least 3 characters)',
            ];
        }

        return $result;
    }

    /**
     * Detect patterns that indicate broken links
     *
     * @param array<int, array<string, mixed>> $internalLinks
     * @return array<string, mixed>
     */
    private function detectBrokenLinkPatterns(array $internalLinks): array
    {
        $suspiciousLinks = [];

        $suspiciousPatterns = [
            '/^\s*$/', // Empty URLs
            '/^#/', // Hash-only links
            '/^javascript:/i', // JavaScript links
            '/^mailto:/i', // Email links (not broken but not internal)
            '/^tel:/i', // Phone links
            '/\.{3,}$/', // URLs ending with ...
            '/\s/', // URLs with spaces
        ];

        foreach ($internalLinks as $link) {
            $url = $link['url'] ?? '';
            $isSuspicious = false;

            foreach ($suspiciousPatterns as $pattern) {
                if (preg_match($pattern, $url)) {
                    $isSuspicious = true;
                    break;
                }
            }

            if ($isSuspicious) {
                $suspiciousLinks[] = $link;
            }
        }

        $result = [
            'passed' => count($suspiciousLinks) === 0,
            'importance' => 'high',
            'value' => [
                'suspicious_count' => count($suspiciousLinks),
                'total_checked' => count($internalLinks),
            ],
        ];

        if (count($suspiciousLinks) > 0) {
            $result['errors'] = [
                'message' => 'Suspicious internal link patterns detected',
                'count' => count($suspiciousLinks),
                'examples' => array_slice($suspiciousLinks, 0, 5),
                'recommendation' => 'Review and fix these links - they may be broken or improperly formatted',
            ];
        }

        return $result;
    }

    /**
     * Normalize URL for comparison
     */
    private function normalizeUrl(string $url): string
    {
        $parsed = parse_url($url);
        if ($parsed === false) {
            return strtolower($url);
        }
        
        $host = strtolower($parsed['host'] ?? '');
        $path = strtolower($parsed['path'] ?? '/');
        
        // Remove trailing slash
        $path = rtrim($path, '/');
        if ($path === '') {
            $path = '/';
        }
        
        // Remove query string and fragment for comparison
        return $host . $path;
    }
}
