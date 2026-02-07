<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Actions\Performance;

use KalimeroMK\SeoReport\Actions\AnalysisActionInterface;
use KalimeroMK\SeoReport\Actions\AnalysisContext;

/**
 * Analyze resource hints for performance optimization.
 * Checks for preconnect, dns-prefetch, preload, prefetch hints.
 */
final class ResourceHintsAction implements AnalysisActionInterface
{
    public function handle(AnalysisContext $context): array
    {
        $domDocument = $context->getDom();
        $httpRequests = (array) $context->getData('http_requests', []);

        // Extract external domains from requests
        $externalDomains = $this->extractExternalDomains($httpRequests, $context->getUrl());

        $result = [];

        $result['preconnect_hints'] = $this->checkPreconnectHints($domDocument, $externalDomains);
        $result['dns_prefetch_hints'] = $this->checkDnsPrefetchHints($domDocument, $externalDomains);
        $result['preload_hints'] = $this->checkPreloadHints($domDocument, $httpRequests);
        $result['prefetch_hints'] = $this->checkPrefetchHints($domDocument);
        $result['resource_hints_coverage'] = $this->calculateCoverage($result);

        return $result;
    }

    /**
     * Check for preconnect hints
     *
     * @param array<int, string> $externalDomains
     * @return array<string, mixed>
     */
    private function checkPreconnectHints(\DOMDocument $domDocument, array $externalDomains): array
    {
        $existingPreconnects = [];

        foreach ($domDocument->getElementsByTagName('link') as $link) {
            if ($link->getAttribute('rel') === 'preconnect') {
                $href = $link->getAttribute('href');
                $crossorigin = $link->hasAttribute('crossorigin');
                $existingPreconnects[] = [
                    'url' => $href,
                    'crossorigin' => $crossorigin,
                ];
            }
        }

        $missingPreconnects = [];
        $priorityDomains = ['fonts.', 'cdn.', 'ajax.', 'api.', 'static.'];

        foreach ($externalDomains as $domain) {
            $hasPreconnect = false;
            foreach ($existingPreconnects as $preconnect) {
                if (str_contains($domain, $preconnect['url']) || str_contains($preconnect['url'], $domain)) {
                    $hasPreconnect = true;
                    break;
                }
            }

            if (!$hasPreconnect) {
                    foreach ($priorityDomains as $prefix) {
                    if (str_contains($domain, $prefix)) {
                        $missingPreconnects[] = $domain;
                        break;
                    }
                }
            }
        }

        $coverage = count($externalDomains) > 0 
            ? round((count($existingPreconnects) / min(count($externalDomains), 6)) * 100, 2)
            : 100;

        $result = [
            'passed' => $coverage >= 50 || count($externalDomains) <= 2,
            'importance' => 'medium',
            'value' => [
                'preconnects_found' => count($existingPreconnects),
                'external_domains' => count($externalDomains),
                'missing_for_priority' => $missingPreconnects,
                'coverage' => $coverage,
            ],
        ];

        if ($coverage < 50 && count($externalDomains) > 2) {
            $result['errors'] = [
                'message' => 'Missing preconnect hints for external domains',
                'missing' => array_slice($missingPreconnects, 0, 5),
                'recommendation' => 'Add <link rel="preconnect"> for critical third-party domains',
                'example' => '<link rel="preconnect" href="https://fonts.googleapis.com">',
            ];
        }

        return $result;
    }

    /**
     * Check for dns-prefetch hints
     *
     * @param array<int, string> $externalDomains
     * @return array<string, mixed>
     */
    private function checkDnsPrefetchHints(\DOMDocument $domDocument, array $externalDomains): array
    {
        $existingDnsPrefetch = [];

        foreach ($domDocument->getElementsByTagName('link') as $link) {
            if ($link->getAttribute('rel') === 'dns-prefetch') {
                $existingDnsPrefetch[] = $link->getAttribute('href');
            }
        }

        $hasDnsPrefetch = !empty($existingDnsPrefetch);

        return [
            'passed' => true, // This is a nice-to-have, not critical
            'importance' => 'low',
            'value' => [
                'dns_prefetch_found' => count($existingDnsPrefetch),
                'domains' => $existingDnsPrefetch,
            ],
            'recommendation' => !$hasDnsPrefetch && count($externalDomains) > 3
                ? 'Consider adding dns-prefetch for additional third-party domains'
                : null,
        ];
    }

    /**
     * Check for preload hints for critical resources
     *
     * @param array<string, array<int, string>> $httpRequests
     * @return array<string, mixed>
     */
    private function checkPreloadHints(\DOMDocument $domDocument, array $httpRequests): array
    {
        $existingPreloads = [];
        $preloadTypes = [];

        foreach ($domDocument->getElementsByTagName('link') as $link) {
            if ($link->getAttribute('rel') === 'preload') {
                $href = $link->getAttribute('href');
                $as = $link->getAttribute('as');
                $existingPreloads[] = [
                    'url' => $href,
                    'as' => $as,
                ];
                $preloadTypes[] = $as;
            }
        }

        $criticalResources = [];

        $cssFiles = $httpRequests['CSS'] ?? [];
        if (!empty($cssFiles) && !in_array('style', $preloadTypes, true)) {
            $criticalResources[] = [
                'type' => 'style',
                'resource' => $cssFiles[0] ?? null,
                'reason' => 'Critical CSS for above-the-fold content',
            ];
        }

        $fontFiles = $httpRequests['Fonts'] ?? [];
        if (!empty($fontFiles) && !in_array('font', $preloadTypes, true)) {
            $criticalResources[] = [
                'type' => 'font',
                'resource' => $fontFiles[0] ?? null,
                'reason' => 'Web fonts for above-the-fold text',
            ];
        }

        $images = $httpRequests['Images'] ?? [];
        $heroImage = null;
        foreach ($images as $img) {
            if (str_contains(strtolower($img), 'hero') || 
                str_contains(strtolower($img), 'banner') ||
                str_contains(strtolower($img), 'logo')) {
                $heroImage = $img;
                break;
            }
        }

        if ($heroImage !== null && !in_array('image', $preloadTypes, true)) {
            $criticalResources[] = [
                'type' => 'image',
                'resource' => $heroImage,
                'reason' => 'Hero/LCP image',
            ];
        }

        $result = [
            'passed' => !empty($existingPreloads) || empty($criticalResources),
            'importance' => 'medium',
            'value' => [
                'preloads_found' => count($existingPreloads),
                'types' => array_unique($preloadTypes),
                'critical_resources_not_preloaded' => count($criticalResources),
            ],
        ];

        if (!empty($criticalResources) && empty($existingPreloads)) {
            $result['warnings'] = [
                'message' => 'Critical resources not preloaded',
                'resources' => array_slice($criticalResources, 0, 3),
                'recommendation' => 'Use <link rel="preload"> for critical above-the-fold resources',
                'example' => '<link rel="preload" href="critical.css" as="style">',
            ];
        }

        return $result;
    }

    /**
     * Check for prefetch hints
     *
     * @return array<string, mixed>
     */
    private function checkPrefetchHints(\DOMDocument $domDocument): array
    {
        $prefetches = [];
        $prerenders = [];

        foreach ($domDocument->getElementsByTagName('link') as $link) {
            $rel = $link->getAttribute('rel');
            $href = $link->getAttribute('href');
            
            if ($rel === 'prefetch') {
                $prefetches[] = $href;
            } elseif ($rel === 'prerender') {
                $prerenders[] = $href;
            }
        }

        $hasPrefetchHints = !empty($prefetches) || !empty($prerenders);

        return [
            'passed' => true, // Nice to have, not critical
            'importance' => 'low',
            'value' => [
                'prefetch_count' => count($prefetches),
                'prerender_count' => count($prerenders),
                'prefetches' => array_slice($prefetches, 0, 5),
            ],
            'recommendation' => !$hasPrefetchHints
                ? 'Consider prefetching likely next-page resources'
                : null,
        ];
    }

    /**
     * Calculate overall resource hints coverage
     *
     * @param array<string, mixed> $results
     * @return array<string, mixed>
     */
    private function calculateCoverage(array $results): array
    {
        $checks = ['preconnect_hints', 'preload_hints'];
        $passed = 0;
        $total = count($checks);

        foreach ($checks as $check) {
            if (isset($results[$check]) && $results[$check]['passed']) {
                $passed++;
            }
        }

        $percentage = round(($passed / $total) * 100, 2);

        return [
            'passed' => $percentage >= 50,
            'importance' => 'medium',
            'value' => [
                'score' => $percentage,
                'checks_passed' => $passed,
                'total_checks' => $total,
            ],
            'recommendation' => $percentage < 50
                ? 'Implement resource hints to improve page load performance'
                : null,
        ];
    }

    /**
     * Extract external domains from HTTP requests
     *
     * @param array<string, array<int, string>> $httpRequests
     * @return array<int, string>
     */
    private function extractExternalDomains(array $httpRequests, string $baseUrl): array
    {
        $domains = [];
        $baseHost = parse_url($baseUrl, PHP_URL_HOST) ?? '';

        $allUrls = array_merge(
            $httpRequests['JavaScripts'] ?? [],
            $httpRequests['CSS'] ?? [],
            $httpRequests['Images'] ?? [],
            $httpRequests['Fonts'] ?? [],
            $httpRequests['Other'] ?? []
        );

        foreach ($allUrls as $url) {
            $host = parse_url($url, PHP_URL_HOST);
            if ($host !== null && $host !== '' && $host !== $baseHost) {
                $domains[] = $host;
            }
        }

        /** @var array<int, string> $result */
        $result = array_values(array_unique($domains));
        return $result;
    }
}
