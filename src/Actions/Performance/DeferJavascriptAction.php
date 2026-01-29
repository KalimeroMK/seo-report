<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Actions\Performance;

use KalimeroMK\SeoReport\Actions\AnalysisActionInterface;
use KalimeroMK\SeoReport\Actions\AnalysisContext;

final class DeferJavascriptAction implements AnalysisActionInterface
{
    public function handle(AnalysisContext $context): array
    {
        $deferJavaScript = (array) $context->getData('defer_javascript', []);

        $result = [
            'defer_javascript' => ['passed' => true, 'importance' => 'low', 'value' => null],
        ];
        if ($deferJavaScript !== []) {
            $result['defer_javascript']['passed'] = false;
            $result['defer_javascript']['errors'] = ['missing' => $deferJavaScript];
        }

        return $result;
    }
}
