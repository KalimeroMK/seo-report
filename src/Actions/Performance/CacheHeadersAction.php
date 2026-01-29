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

        $result = [
            'static_cache_headers' => ['passed' => true, 'importance' => 'medium', 'value' => $checkedStaticAssets],
        ];
        if ($missingStaticCache !== []) {
            $result['static_cache_headers']['passed'] = false;
            $result['static_cache_headers']['errors'] = ['missing' => $missingStaticCache];
        }

        $value = $expires !== '' ? $expires : $cacheControl;
        $result['expires_headers'] = ['passed' => true, 'importance' => 'medium', 'value' => $value];
        if ($expires === '' && !$hasMaxAge) {
            $result['expires_headers']['passed'] = false;
            $result['expires_headers']['errors'] = ['missing' => null];
        }

        return $result;
    }
}
