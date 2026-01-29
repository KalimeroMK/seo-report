<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Actions\Security;

use KalimeroMK\SeoReport\Actions\AnalysisActionInterface;
use KalimeroMK\SeoReport\Actions\AnalysisContext;

final class MixedContentAction implements AnalysisActionInterface
{
    public function handle(AnalysisContext $context): array
    {
        $mixedContent = (array) $context->getData('mixed_content', []);

        $result = [
            'mixed_content' => ['passed' => true, 'importance' => 'medium', 'value' => null],
        ];
        if ($mixedContent !== []) {
            $result['mixed_content']['passed'] = false;
            $result['mixed_content']['errors'] = ['failed' => $mixedContent];
        }

        return $result;
    }
}
