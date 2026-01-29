<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Actions\Misc;

use KalimeroMK\SeoReport\Actions\AnalysisActionInterface;
use KalimeroMK\SeoReport\Actions\AnalysisContext;

final class DeprecatedHtmlTagsAction implements AnalysisActionInterface
{
    public function handle(AnalysisContext $context): array
    {
        $deprecatedHtmlTags = (array) $context->getData('deprecated_html_tags', []);

        $result = [
            'deprecated_html_tags' => ['passed' => true, 'importance' => 'low', 'value' => null],
        ];
        if (count($deprecatedHtmlTags) > 1) {
            $result['deprecated_html_tags']['passed'] = false;
            $result['deprecated_html_tags']['errors'] = ['bad_tags' => $deprecatedHtmlTags];
        }

        return $result;
    }
}
