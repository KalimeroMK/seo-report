<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Actions\Misc;

use KalimeroMK\SeoReport\Actions\AnalysisActionInterface;
use KalimeroMK\SeoReport\Actions\AnalysisContext;

final class TextHtmlRatioAction implements AnalysisActionInterface
{
    public function handle(AnalysisContext $context): array
    {
        $textRatio = (int) $context->getData('text_ratio', 0);

        $result = [
            'text_html_ratio' => ['passed' => true, 'importance' => 'low', 'value' => $textRatio],
        ];
        if ($textRatio < $context->getConfig()->getReportLimitMinTextRatio()) {
            $result['text_html_ratio']['passed'] = false;
            $result['text_html_ratio']['errors'] = ['too_small' => ['min' => $context->getConfig()->getReportLimitMinTextRatio()]];
        }

        return $result;
    }
}
