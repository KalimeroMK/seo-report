<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Actions\Security;

use KalimeroMK\SeoReport\Actions\AnalysisActionInterface;
use KalimeroMK\SeoReport\Actions\AnalysisContext;

final class Http2Action implements AnalysisActionInterface
{
    public function handle(AnalysisContext $context): array
    {
        $result = [];
        if ($context->getConfig()->getRequestHttpVersion() === '2') {
            $protocol = $context->getResponse()->getProtocolVersion();
            $result['http2'] = ['passed' => true, 'importance' => 'medium', 'value' => $protocol];
            if ($protocol !== '2') {
                $result['http2']['passed'] = false;
                $result['http2']['errors'] = ['failed' => null];
            }
        }

        return $result;
    }
}
