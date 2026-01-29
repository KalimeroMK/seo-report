<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Actions\Seo;

use KalimeroMK\SeoReport\Actions\AnalysisActionInterface;
use KalimeroMK\SeoReport\Actions\AnalysisContext;

final class NoindexHeaderAction implements AnalysisActionInterface
{
    public function handle(AnalysisContext $context): array
    {
        $noindexHeaderValue = $context->getData('noindex_header_value');

        $result = [
            'noindex_header' => ['passed' => true, 'importance' => 'high', 'value' => $noindexHeaderValue],
        ];
        if ($noindexHeaderValue !== null && preg_match('/\bnoindex\b/i', (string) $noindexHeaderValue)) {
            $result['noindex_header']['passed'] = false;
            $result['noindex_header']['errors'] = ['noindex' => $noindexHeaderValue];
        }

        return $result;
    }
}
