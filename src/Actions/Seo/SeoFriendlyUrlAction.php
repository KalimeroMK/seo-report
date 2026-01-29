<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Actions\Seo;

use KalimeroMK\SeoReport\Actions\AnalysisActionInterface;
use KalimeroMK\SeoReport\Actions\AnalysisContext;

final class SeoFriendlyUrlAction implements AnalysisActionInterface
{
    public function handle(AnalysisContext $context): array
    {
        $currentUrl = (string) $context->getData('current_url', $context->getUrl());
        $titleKeywords = (array) $context->getData('title_keywords', []);

        $result = [
            'seo_friendly_url' => ['passed' => true, 'importance' => 'high', 'value' => $currentUrl],
        ];
        if (preg_match('/[\?\=\_\%\,\ ]/ui', $currentUrl)) {
            $result['seo_friendly_url']['passed'] = false;
            $result['seo_friendly_url']['errors'] = ['bad_format' => null];
        }
        if (array_filter($titleKeywords, fn ($k) => str_contains(mb_strtolower($currentUrl), mb_strtolower((string) $k))) === []) {
            $result['seo_friendly_url']['passed'] = false;
            $result['seo_friendly_url']['errors'] = ['missing' => null];
        }

        return $result;
    }
}
