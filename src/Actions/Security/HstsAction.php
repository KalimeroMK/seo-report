<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Actions\Security;

use KalimeroMK\SeoReport\Actions\AnalysisActionInterface;
use KalimeroMK\SeoReport\Actions\AnalysisContext;

final class HstsAction implements AnalysisActionInterface
{
    public function handle(AnalysisContext $context): array
    {
        $htstHeader = (array) $context->getData('hsts_header', []);

        $result = [
            'htst' => ['passed' => true, 'importance' => 'low', 'value' => $htstHeader],
        ];
        if ($htstHeader === []) {
            $result['htst']['passed'] = false;
            $result['htst']['errors'] = ['missing' => null];
        }

        return $result;
    }
}
