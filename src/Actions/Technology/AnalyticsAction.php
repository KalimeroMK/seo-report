<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Actions\Technology;

use KalimeroMK\SeoReport\Actions\AnalysisActionInterface;
use KalimeroMK\SeoReport\Actions\AnalysisContext;

final class AnalyticsAction implements AnalysisActionInterface
{
    public function handle(AnalysisContext $context): array
    {
        $analyticsDetected = (array) $context->getData('analytics_detected', []);

        return [
            'analytics' => ['passed' => true, 'importance' => 'low', 'value' => array_keys($analyticsDetected)],
        ];
    }
}
