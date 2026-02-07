<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Actions\Security;

use KalimeroMK\SeoReport\Actions\AnalysisActionInterface;
use KalimeroMK\SeoReport\Actions\AnalysisContext;

final class HstsAction implements AnalysisActionInterface
{
    public function handle(AnalysisContext $context): array
    {
        $hstsHeader = (array) $context->getData('hsts_header', []);

        $result = [
            'hsts' => ['passed' => true, 'importance' => 'low', 'value' => $hstsHeader],
        ];
        if ($hstsHeader === []) {
            $result['hsts']['passed'] = false;
            $result['hsts']['errors'] = ['missing' => null];
        }

        return $result;
    }
}
