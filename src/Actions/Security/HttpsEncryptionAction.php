<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Actions\Security;

use KalimeroMK\SeoReport\Actions\AnalysisActionInterface;
use KalimeroMK\SeoReport\Actions\AnalysisContext;

final class HttpsEncryptionAction implements AnalysisActionInterface
{
    public function handle(AnalysisContext $context): array
    {
        $httpScheme = (string) $context->getData('http_scheme', '');
        $urlValue = (string) ($context->getStats()['url'] ?? $context->getUrl());

        $result = [
            'https_encryption' => ['passed' => true, 'importance' => 'high', 'value' => $urlValue],
        ];
        if (strtolower($httpScheme) !== 'https') {
            $result['https_encryption']['passed'] = false;
            $result['https_encryption']['errors'] = ['missing' => 'https'];
        }

        return $result;
    }
}
