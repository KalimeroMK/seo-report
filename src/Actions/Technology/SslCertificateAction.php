<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Actions\Technology;

use KalimeroMK\SeoReport\Actions\AnalysisActionInterface;
use KalimeroMK\SeoReport\Actions\AnalysisContext;

final class SslCertificateAction implements AnalysisActionInterface
{
    public function handle(AnalysisContext $context): array
    {
        $hostStr = (string) $context->getData('host_str', '');
        $baseUrl = (string) $context->getData('base_url', '');
        $sslCertificate = $context->getData('ssl_certificate');

        $result = [
            'ssl_certificate' => ['passed' => true, 'importance' => 'medium', 'value' => $sslCertificate],
        ];
        if ($sslCertificate === null && $hostStr !== '' && str_starts_with($baseUrl, 'https://')) {
            $result['ssl_certificate']['passed'] = false;
            $result['ssl_certificate']['errors'] = ['unavailable' => null];
        } elseif ($sslCertificate !== null && !($sslCertificate['valid'] ?? false)) {
            $result['ssl_certificate']['passed'] = false;
            $result['ssl_certificate']['errors'] = ['invalid_or_expired' => null];
        }

        return $result;
    }
}
