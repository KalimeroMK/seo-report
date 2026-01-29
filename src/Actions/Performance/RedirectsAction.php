<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Actions\Performance;

use KalimeroMK\SeoReport\Actions\AnalysisActionInterface;
use KalimeroMK\SeoReport\Actions\AnalysisContext;

final class RedirectsAction implements AnalysisActionInterface
{
    public function handle(AnalysisContext $context): array
    {
        $redirectCount = (int) $context->getData('redirect_count', 0);
        $redirectHistory = (array) $context->getData('redirect_history', []);
        $assetRedirects = (array) $context->getData('asset_redirects', []);

        $result = [
            'avoid_redirects' => ['passed' => true, 'importance' => 'medium', 'value' => $redirectCount],
        ];
        if ($redirectCount > 0) {
            $result['avoid_redirects']['passed'] = false;
            $result['avoid_redirects']['errors'] = ['redirects' => $redirectHistory ?: null];
        }

        $result['redirect_chains'] = [
            'passed' => true,
            'importance' => 'medium',
            'value' => ['main' => $redirectCount, 'assets' => $assetRedirects],
        ];
        if ($redirectCount > 0 || $assetRedirects !== []) {
            $result['redirect_chains']['passed'] = false;
            $result['redirect_chains']['errors'] = [
                'main' => $redirectCount,
                'assets' => $assetRedirects,
            ];
        }

        return $result;
    }
}
