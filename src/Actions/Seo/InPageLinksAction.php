<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Actions\Seo;

use KalimeroMK\SeoReport\Actions\AnalysisActionInterface;
use KalimeroMK\SeoReport\Actions\AnalysisContext;

final class InPageLinksAction implements AnalysisActionInterface
{
    public function handle(AnalysisContext $context): array
    {
        $pageLinks = (array) $context->getData('page_links', ['Internals' => [], 'Externals' => []]);

        $result = [
            'in_page_links' => ['passed' => true, 'importance' => 'medium', 'value' => $pageLinks],
        ];
        if (array_sum(array_map(count(...), $pageLinks)) > $context->getConfig()->getReportLimitMaxLinks()) {
            $result['in_page_links']['passed'] = false;
            $result['in_page_links']['errors'] = ['too_many' => ['max' => $context->getConfig()->getReportLimitMaxLinks()]];
        }

        return $result;
    }
}
