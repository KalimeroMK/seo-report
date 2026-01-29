<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Actions\Seo;

use KalimeroMK\SeoReport\Actions\AnalysisActionInterface;
use KalimeroMK\SeoReport\Actions\AnalysisContext;

final class MetaDescriptionAction implements AnalysisActionInterface
{
    public function handle(AnalysisContext $context): array
    {
        $metaDescription = $context->getData('meta_description');

        $result = [
            'meta_description' => ['passed' => true, 'importance' => 'high', 'value' => $metaDescription],
        ];
        if (!$metaDescription) {
            $result['meta_description']['passed'] = false;
            $result['meta_description']['errors'] = ['missing' => null];
        }

        $metaDescriptionLength = mb_strlen((string) $metaDescription);
        $result['meta_description_optimal_length'] = ['passed' => true, 'importance' => 'low', 'value' => $metaDescriptionLength];
        if ($metaDescription && ($metaDescriptionLength < 120 || $metaDescriptionLength > 160)) {
            $result['meta_description_optimal_length']['passed'] = false;
            $result['meta_description_optimal_length']['errors'] = ['not_optimal' => ['optimal' => '120-160', 'current' => $metaDescriptionLength]];
        }

        return $result;
    }
}
