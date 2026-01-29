<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Actions\Technology;

use KalimeroMK\SeoReport\Actions\AnalysisActionInterface;
use KalimeroMK\SeoReport\Actions\AnalysisContext;

final class ReverseDnsAction implements AnalysisActionInterface
{
    public function handle(AnalysisContext $context): array
    {
        $reverseDns = $context->getData('reverse_dns');

        return [
            'reverse_dns' => ['passed' => true, 'importance' => 'low', 'value' => $reverseDns],
        ];
    }
}
