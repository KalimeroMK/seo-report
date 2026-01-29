<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Actions\Seo;

use KalimeroMK\SeoReport\Actions\AnalysisActionInterface;
use KalimeroMK\SeoReport\Actions\AnalysisContext;

final class TitleAction implements AnalysisActionInterface
{
    public function handle(AnalysisContext $context): array
    {
        $title = $context->getData('title');
        $titleTagsCount = (int) $context->getData('title_tags_count', 0);

        $result = [
            'title' => ['passed' => true, 'importance' => 'high', 'value' => $title],
        ];
        if (!$title) {
            $result['title']['passed'] = false;
            $result['title']['errors'] = ['missing' => null];
        }
        $min = $context->getConfig()->getReportLimitMinTitle();
        $max = $context->getConfig()->getReportLimitMaxTitle();
        if (mb_strlen((string) $title) < $min || mb_strlen((string) $title) > $max) {
            $result['title']['passed'] = false;
            $result['title']['errors'] = ['length' => ['min' => $min, 'max' => $max]];
        }
        if ($titleTagsCount > 1) {
            $result['title']['passed'] = false;
            $result['title']['errors'] = ['too_many' => null];
        }

        $titleLength = mb_strlen((string) $title);
        $result['title_optimal_length'] = ['passed' => true, 'importance' => 'low', 'value' => $titleLength];
        if ($titleLength < 50 || $titleLength > 60) {
            $result['title_optimal_length']['passed'] = false;
            $result['title_optimal_length']['errors'] = ['not_optimal' => ['optimal' => '50-60', 'current' => $titleLength]];
        }

        return $result;
    }
}
