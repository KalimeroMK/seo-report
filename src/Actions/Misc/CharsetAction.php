<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Actions\Misc;

use KalimeroMK\SeoReport\Actions\AnalysisActionInterface;
use KalimeroMK\SeoReport\Actions\AnalysisContext;

final class CharsetAction implements AnalysisActionInterface
{
    public function handle(AnalysisContext $context): array
    {
        $charset = $context->getData('charset');

        $result = [
            'charset' => ['passed' => true, 'importance' => 'medium', 'value' => $charset],
        ];
        if (!$charset) {
            $result['charset']['passed'] = false;
            $result['charset']['errors'] = ['missing' => null];
        }

        return $result;
    }
}
