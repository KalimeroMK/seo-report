<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Actions\Performance;

use KalimeroMK\SeoReport\Actions\AnalysisActionInterface;
use KalimeroMK\SeoReport\Actions\AnalysisContext;

final class EmptySrcHrefAction implements AnalysisActionInterface
{
    public function handle(AnalysisContext $context): array
    {
        $emptySrcHref = (array) $context->getData('empty_src_or_href', []);

        $result = [
            'empty_src_or_href' => ['passed' => true, 'importance' => 'low', 'value' => null],
        ];
        if ($emptySrcHref !== []) {
            $result['empty_src_or_href']['passed'] = false;
            $result['empty_src_or_href']['errors'] = ['empty' => $emptySrcHref];
        }

        return $result;
    }
}
