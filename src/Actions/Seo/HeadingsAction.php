<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Actions\Seo;

use KalimeroMK\SeoReport\Actions\AnalysisActionInterface;
use KalimeroMK\SeoReport\Actions\AnalysisContext;

final class HeadingsAction implements AnalysisActionInterface
{
    public function handle(AnalysisContext $context): array
    {
        $headings = (array) $context->getData('headings', []);
        $h1Count = (int) $context->getData('h1_count', 0);
        $secondaryHeadingUsage = (array) $context->getData('secondary_heading_usage', []);
        $secondaryHeadingLevels = (int) $context->getData('secondary_heading_levels', 0);
        $title = $context->getData('title');

        $result = [
            'headings' => ['passed' => true, 'importance' => 'high', 'value' => $headings],
        ];
        if ($h1Count === 0) {
            $result['headings']['passed'] = false;
            $result['headings']['errors'] = ['missing' => null];
        }
        if (isset($headings['h1']) && count($headings['h1']) > 1) {
            $result['headings']['passed'] = false;
            $result['headings']['errors'] = ['too_many' => null];
        }
        if (isset($headings['h1'][0]) && $headings['h1'][0] == $title) {
            $result['headings']['passed'] = false;
            $result['headings']['errors'] = ['duplicate' => null];
        }

        $result['h1_usage'] = ['passed' => true, 'importance' => 'medium', 'value' => $h1Count];
        if ($h1Count === 0 || $h1Count > 1) {
            $result['h1_usage']['passed'] = false;
            $errors = [];
            if ($h1Count === 0) {
                $errors['missing'] = null;
            }
            if ($h1Count > 1) {
                $errors['multiple'] = $h1Count;
            }
            $result['h1_usage']['errors'] = $errors;
        }

        $result['header_tag_usage'] = ['passed' => true, 'importance' => 'medium', 'value' => $secondaryHeadingUsage];
        if ($secondaryHeadingLevels === 0) {
            $result['header_tag_usage']['passed'] = false;
            $result['header_tag_usage']['errors'] = ['missing' => null];
        }

        return $result;
    }
}
