<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Actions\Misc;

use KalimeroMK\SeoReport\Actions\AnalysisActionInterface;
use KalimeroMK\SeoReport\Actions\AnalysisContext;

final class StructuredDataAction implements AnalysisActionInterface
{
    public function handle(AnalysisContext $context): array
    {
        $structuredData = (array) $context->getData('structured_data', []);

        $result = [
            'structured_data' => ['passed' => true, 'importance' => 'medium', 'value' => $structuredData],
        ];
        if ($structuredData === []) {
            $result['structured_data']['passed'] = false;
            $result['structured_data']['errors'] = ['missing' => null];
        }

        return $result;
    }
}
