<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Actions\Misc;

use KalimeroMK\SeoReport\Actions\AnalysisActionInterface;
use KalimeroMK\SeoReport\Actions\AnalysisContext;

final class SocialLinksAction implements AnalysisActionInterface
{
    public function handle(AnalysisContext $context): array
    {
        $social = (array) $context->getData('social', []);

        $result = [
            'social' => ['passed' => true, 'importance' => 'low', 'value' => $social],
        ];
        if ($social === []) {
            $result['social']['passed'] = false;
            $result['social']['errors'] = ['missing' => null];
        }

        return $result;
    }
}
