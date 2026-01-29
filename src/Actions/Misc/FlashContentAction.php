<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Actions\Misc;

use KalimeroMK\SeoReport\Actions\AnalysisActionInterface;
use KalimeroMK\SeoReport\Actions\AnalysisContext;

final class FlashContentAction implements AnalysisActionInterface
{
    public function handle(AnalysisContext $context): array
    {
        $flashContent = (array) $context->getData('flash_content', []);

        $result = [
            'flash_content' => ['passed' => true, 'importance' => 'low', 'value' => null],
        ];
        if ($flashContent !== []) {
            $result['flash_content']['passed'] = false;
            $result['flash_content']['errors'] = ['found' => $flashContent];
        }

        return $result;
    }
}
