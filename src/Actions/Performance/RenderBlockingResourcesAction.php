<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Actions\Performance;

use KalimeroMK\SeoReport\Actions\AnalysisActionInterface;
use KalimeroMK\SeoReport\Actions\AnalysisContext;

final class RenderBlockingResourcesAction implements AnalysisActionInterface
{
    public function handle(AnalysisContext $context): array
    {
        $renderBlocking = (array) $context->getData('render_blocking', ['js' => [], 'css' => []]);

        $result = [
            'render_blocking_resources' => ['passed' => true, 'importance' => 'medium', 'value' => $renderBlocking],
        ];
        if (($renderBlocking['js'] ?? []) !== [] || ($renderBlocking['css'] ?? []) !== []) {
            $result['render_blocking_resources']['passed'] = false;
            $result['render_blocking_resources']['errors'] = [
                'js' => $renderBlocking['js'] ?? [],
                'css' => $renderBlocking['css'] ?? [],
            ];
        }

        return $result;
    }
}
