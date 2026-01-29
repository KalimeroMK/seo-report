<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Actions\Seo;

use KalimeroMK\SeoReport\Actions\AnalysisActionInterface;
use KalimeroMK\SeoReport\Actions\AnalysisContext;

final class ImageKeywordsAction implements AnalysisActionInterface
{
    public function handle(AnalysisContext $context): array
    {
        $imageAlts = (array) $context->getData('image_alts', []);

        $result = [
            'image_keywords' => ['passed' => true, 'importance' => 'high', 'value' => null],
        ];
        if ($imageAlts !== []) {
            $result['image_keywords']['passed'] = false;
            $result['image_keywords']['errors'] = ['missing' => $imageAlts];
        }

        return $result;
    }
}
