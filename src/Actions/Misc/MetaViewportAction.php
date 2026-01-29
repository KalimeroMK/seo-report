<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Actions\Misc;

use KalimeroMK\SeoReport\Actions\AnalysisActionInterface;
use KalimeroMK\SeoReport\Actions\AnalysisContext;

final class MetaViewportAction implements AnalysisActionInterface
{
    public function handle(AnalysisContext $context): array
    {
        $metaViewport = $context->getData('meta_viewport');

        $result = [
            'meta_viewport' => ['passed' => true, 'importance' => 'medium', 'value' => $metaViewport],
        ];
        if (!$metaViewport) {
            $result['meta_viewport']['passed'] = false;
            $result['meta_viewport']['errors'] = ['missing' => null];
        }

        return $result;
    }
}
