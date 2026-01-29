<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Actions\Misc;

use KalimeroMK\SeoReport\Actions\AnalysisActionInterface;
use KalimeroMK\SeoReport\Actions\AnalysisContext;

final class IframesAction implements AnalysisActionInterface
{
    public function handle(AnalysisContext $context): array
    {
        $iframes = (array) $context->getData('iframes', []);

        return [
            'iframes' => ['passed' => true, 'importance' => 'low', 'value' => $iframes],
        ];
    }
}
