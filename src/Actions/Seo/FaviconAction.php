<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Actions\Seo;

use KalimeroMK\SeoReport\Actions\AnalysisActionInterface;
use KalimeroMK\SeoReport\Actions\AnalysisContext;

final class FaviconAction implements AnalysisActionInterface
{
    public function handle(AnalysisContext $context): array
    {
        $favicon = $context->getData('favicon');

        $result = [
            'favicon' => ['passed' => true, 'importance' => 'medium', 'value' => $favicon],
        ];
        if (!$favicon) {
            $result['favicon']['passed'] = false;
            $result['favicon']['errors'] = ['missing' => null];
        }

        return $result;
    }
}
