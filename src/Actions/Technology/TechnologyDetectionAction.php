<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Actions\Technology;

use KalimeroMK\SeoReport\Actions\AnalysisActionInterface;
use KalimeroMK\SeoReport\Actions\AnalysisContext;

final class TechnologyDetectionAction implements AnalysisActionInterface
{
    public function handle(AnalysisContext $context): array
    {
        $technologyDetected = (array) $context->getData('technology_detected', []);

        return [
            'technology_detection' => ['passed' => true, 'importance' => 'low', 'value' => array_keys($technologyDetected)],
        ];
    }
}
