<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Actions\Security;

use KalimeroMK\SeoReport\Actions\AnalysisActionInterface;
use KalimeroMK\SeoReport\Actions\AnalysisContext;

final class UnsafeCrossOriginLinksAction implements AnalysisActionInterface
{
    public function handle(AnalysisContext $context): array
    {
        $unsafeCrossOriginLinks = (array) $context->getData('unsafe_cross_origin_links', []);

        $result = [
            'unsafe_cross_origin_links' => ['passed' => true, 'importance' => 'medium', 'value' => null],
        ];
        if ($unsafeCrossOriginLinks !== []) {
            $result['unsafe_cross_origin_links']['passed'] = false;
            $result['unsafe_cross_origin_links']['errors'] = ['failed' => $unsafeCrossOriginLinks];
        }

        return $result;
    }
}
