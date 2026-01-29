<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Actions\Performance;

use KalimeroMK\SeoReport\Actions\AnalysisActionInterface;
use KalimeroMK\SeoReport\Actions\AnalysisContext;

final class PageSizeAction implements AnalysisActionInterface
{
    public function handle(AnalysisContext $context): array
    {
        $size = (float) ($context->getStats()['size_download'] ?? 0);

        $result = [
            'page_size' => ['passed' => true, 'importance' => 'medium', 'value' => $size],
        ];
        if ($size > $context->getConfig()->getReportLimitPageSize()) {
            $result['page_size']['passed'] = false;
            $result['page_size']['errors'] = ['too_large' => ['max' => $context->getConfig()->getReportLimitPageSize()]];
        }

        return $result;
    }
}
