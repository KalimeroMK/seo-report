<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Actions\Performance;

use KalimeroMK\SeoReport\Actions\AnalysisActionInterface;
use KalimeroMK\SeoReport\Actions\AnalysisContext;

final class MinificationAction implements AnalysisActionInterface
{
    public function handle(AnalysisContext $context): array
    {
        $nonMinifiedJs = (array) $context->getData('non_minified_js', []);
        $nonMinifiedCss = (array) $context->getData('non_minified_css', []);

        $result = [
            'minification' => [
                'passed' => true,
                'importance' => 'low',
                'value' => ['js' => count($nonMinifiedJs), 'css' => count($nonMinifiedCss)],
            ],
        ];
        if ($nonMinifiedJs !== [] || $nonMinifiedCss !== []) {
            $result['minification']['passed'] = false;
            $result['minification']['errors'] = ['not_minified' => ['js' => $nonMinifiedJs, 'css' => $nonMinifiedCss]];
        }

        return $result;
    }
}
