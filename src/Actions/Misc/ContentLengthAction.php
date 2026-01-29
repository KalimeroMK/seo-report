<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Actions\Misc;

use KalimeroMK\SeoReport\Actions\AnalysisActionInterface;
use KalimeroMK\SeoReport\Actions\AnalysisContext;

final class ContentLengthAction implements AnalysisActionInterface
{
    public function handle(AnalysisContext $context): array
    {
        $bodyKeywords = $context->getBodyKeywords();
        $count = count($bodyKeywords);

        $result = [
            'content_length' => ['passed' => true, 'importance' => 'low', 'value' => $count],
        ];
        if ($count < $context->getConfig()->getReportLimitMinWords()) {
            $result['content_length']['passed'] = false;
            $result['content_length']['errors'] = ['too_few' => ['min' => $context->getConfig()->getReportLimitMinWords()]];
        }

        return $result;
    }
}
