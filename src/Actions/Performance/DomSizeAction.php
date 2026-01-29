<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Actions\Performance;

use KalimeroMK\SeoReport\Actions\AnalysisActionInterface;
use KalimeroMK\SeoReport\Actions\AnalysisContext;

final class DomSizeAction implements AnalysisActionInterface
{
    public function handle(AnalysisContext $context): array
    {
        $domNodesCount = (int) $context->getData('dom_nodes_count', 0);

        $result = [
            'dom_size' => ['passed' => true, 'importance' => 'low', 'value' => $domNodesCount],
        ];
        if ($domNodesCount > $context->getConfig()->getReportLimitMaxDomNodes()) {
            $result['dom_size']['passed'] = false;
            $result['dom_size']['errors'] = ['too_many' => ['max' => $context->getConfig()->getReportLimitMaxDomNodes()]];
        }

        return $result;
    }
}
