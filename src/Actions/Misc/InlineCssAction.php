<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Actions\Misc;

use KalimeroMK\SeoReport\Actions\AnalysisActionInterface;
use KalimeroMK\SeoReport\Actions\AnalysisContext;

final class InlineCssAction implements AnalysisActionInterface
{
    public function handle(AnalysisContext $context): array
    {
        $inlineCss = (array) $context->getData('inline_css', []);

        $result = [
            'inline_css' => ['passed' => true, 'importance' => 'low', 'value' => null],
        ];
        if (count($inlineCss) > 1) {
            $result['inline_css']['passed'] = false;
            $result['inline_css']['errors'] = ['failed' => $inlineCss];
        }

        return $result;
    }
}
