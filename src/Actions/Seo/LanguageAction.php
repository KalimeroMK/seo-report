<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Actions\Seo;

use KalimeroMK\SeoReport\Actions\AnalysisActionInterface;
use KalimeroMK\SeoReport\Actions\AnalysisContext;

final class LanguageAction implements AnalysisActionInterface
{
    public function handle(AnalysisContext $context): array
    {
        $language = $context->getData('language');

        $result = [
            'language' => ['passed' => true, 'importance' => 'medium', 'value' => $language],
        ];
        if (!$language) {
            $result['language']['passed'] = false;
            $result['language']['errors'] = ['missing' => null];
        }

        return $result;
    }
}
