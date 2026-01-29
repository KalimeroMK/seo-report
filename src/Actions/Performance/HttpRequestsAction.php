<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Actions\Performance;

use KalimeroMK\SeoReport\Actions\AnalysisActionInterface;
use KalimeroMK\SeoReport\Actions\AnalysisContext;

final class HttpRequestsAction implements AnalysisActionInterface
{
    public function handle(AnalysisContext $context): array
    {
        $httpRequests = (array) $context->getData('http_requests', []);
        $count = array_sum(array_map(count(...), $httpRequests));

        $result = [
            'http_requests' => ['passed' => true, 'importance' => 'medium', 'value' => $httpRequests],
        ];
        if ($count > $context->getConfig()->getReportLimitHttpRequests()) {
            $result['http_requests']['passed'] = false;
            $result['http_requests']['errors'] = ['too_many' => ['max' => $context->getConfig()->getReportLimitHttpRequests()]];
        }

        return $result;
    }
}
