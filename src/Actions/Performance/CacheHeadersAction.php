<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Actions\Performance;

use KalimeroMK\SeoReport\Actions\AnalysisActionInterface;
use KalimeroMK\SeoReport\Actions\AnalysisContext;

final class CacheHeadersAction implements AnalysisActionInterface
{
    public function handle(AnalysisContext $context): array
    {
        $checkedStaticAssets = (array) $context->getData('checked_static_assets', []);
        $missingStaticCache = (array) $context->getData('missing_static_cache', []);
        $cacheControl = (string) $context->getData('cache_control', '');
        $expires = (string) $context->getData('expires_header', '');
        $hasMaxAge = (bool) $context->getData('has_max_age', false);
        $response = $context->getResponse();

        $cacheDirectives = $this->parseCacheControl($cacheControl);

        $etag = $response->getHeader('ETag');
        $lastModified = $response->getHeader('Last-Modified');
        $vary = $response->getHeader('Vary');

        $staticAssetsCount = count($checkedStaticAssets);
        $missingCacheCount = count($missingStaticCache);
        $cacheCoveragePercent = $staticAssetsCount > 0 
            ? round((($staticAssetsCount - $missingCacheCount) / $staticAssetsCount) * 100, 2)
            : 100;

        $result = [
            'static_cache_headers' => ['passed' => true, 'importance' => 'medium', 'value' => [
                'checked_assets' => $staticAssetsCount,
                'missing_cache' => $missingCacheCount,
                'coverage_percent' => $cacheCoveragePercent,
            ]],
        ];
        
        if ($missingStaticCache !== []) {
            $result['static_cache_headers']['passed'] = false;
            $result['static_cache_headers']['errors'] = [
                'missing' => array_slice($missingStaticCache, 0, 10),
                'count' => $missingCacheCount,
                'coverage_percent' => $cacheCoveragePercent,
            ];
        }

        $value = $expires !== '' ? $expires : $cacheControl;
        $result['expires_headers'] = ['passed' => true, 'importance' => 'medium', 'value' => [
            'cache_control' => $cacheControl ?: null,
            'expires' => $expires ?: null,
            'max_age' => $cacheDirectives['max_age'] ?? null,
        ]];
        
        if ($expires === '' && !$hasMaxAge) {
            $result['expires_headers']['passed'] = false;
            $result['expires_headers']['errors'] = [
                'missing' => 'No Cache-Control or Expires header found',
                'recommendation' => 'Add Cache-Control: max-age=3600 or similar',
            ];
        }

        $result['validation_headers'] = [
            'passed' => !empty($etag) || !empty($lastModified),
            'importance' => 'low',
            'value' => [
                'etag_present' => !empty($etag),
                'etag_value' => $etag[0] ?? null,
                'last_modified_present' => !empty($lastModified),
                'last_modified_value' => $lastModified[0] ?? null,
            ],
        ];
        
        if (empty($etag) && empty($lastModified)) {
            $result['validation_headers']['errors'] = [
                'message' => 'Neither ETag nor Last-Modified header present',
                'recommendation' => 'Add ETag for better cache validation',
            ];
        }

        $result['vary_header'] = [
            'passed' => !empty($vary),
            'importance' => 'low',
            'value' => [
                'present' => !empty($vary),
                'value' => $vary[0] ?? null,
            ],
        ];
        
        if (empty($vary)) {
            $result['vary_header']['errors'] = [
                'message' => 'Vary header not present',
                'recommendation' => 'Consider adding Vary: Accept-Encoding for compressed responses',
            ];
        }

        $result['immutable_cache'] = [
            'passed' => isset($cacheDirectives['immutable']) || $this->hasLongCacheDuration($cacheDirectives),
            'importance' => 'low',
            'value' => [
                'immutable' => $cacheDirectives['immutable'] ?? false,
                'max_age' => $cacheDirectives['max_age'] ?? null,
            ],
        ];

        return $result;
    }

    /**
     * Parse Cache-Control header into directives
     *
     * @return array<string, mixed>
     */
    private function parseCacheControl(string $cacheControl): array
    {
        $directives = [];
        
        if ($cacheControl === '') {
            return $directives;
        }
        
        $parts = array_map('trim', explode(',', $cacheControl));
        
        foreach ($parts as $part) {
            if (str_contains($part, '=')) {
                [$name, $value] = explode('=', $part, 2);
                $directives[strtolower(trim($name))] = is_numeric($value) ? (int) $value : trim($value);
            } else {
                $directives[strtolower(trim($part))] = true;
            }
        }
        
        return $directives;
    }

    /**
     * Check if cache duration is considered "long" (more than 1 month)
     *
     * @param array<string, mixed> $directives
     */
    private function hasLongCacheDuration(array $directives): bool
    {
        $maxAge = $directives['max_age'] ?? $directives['s-maxage'] ?? 0;
        return $maxAge > 2592000;
    }
}
