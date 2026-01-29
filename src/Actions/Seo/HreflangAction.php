<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Actions\Seo;

use KalimeroMK\SeoReport\Actions\AnalysisActionInterface;
use KalimeroMK\SeoReport\Actions\AnalysisContext;

final class HreflangAction implements AnalysisActionInterface
{
    public function handle(AnalysisContext $context): array
    {
        $hreflang = (array) $context->getData('hreflang', []);

        $result = [
            'hreflang' => ['passed' => true, 'importance' => 'low', 'value' => $hreflang],
        ];
        if ($hreflang === []) {
            $result['hreflang']['passed'] = false;
            $result['hreflang']['errors'] = ['missing' => null];
        }

        return $result;
    }
}
