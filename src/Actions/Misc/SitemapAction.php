<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Actions\Misc;

use KalimeroMK\SeoReport\Actions\AnalysisActionInterface;
use KalimeroMK\SeoReport\Actions\AnalysisContext;

final class SitemapAction implements AnalysisActionInterface
{
    public function handle(AnalysisContext $context): array
    {
        $sitemaps = (array) $context->getData('sitemaps', []);

        $result = [
            'sitemap' => ['passed' => true, 'importance' => 'low', 'value' => $sitemaps],
        ];
        if ($sitemaps === []) {
            $result['sitemap']['passed'] = false;
            $result['sitemap']['errors'] = ['failed' => null];
        }

        return $result;
    }
}
