<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Actions\Security;

use KalimeroMK\SeoReport\Actions\AnalysisActionInterface;
use KalimeroMK\SeoReport\Actions\AnalysisContext;

final class ServerSignatureAction implements AnalysisActionInterface
{
    public function handle(AnalysisContext $context): array
    {
        $serverHeader = (array) $context->getData('server_header', []);

        $result = [
            'server_signature' => ['passed' => true, 'importance' => 'medium', 'value' => $serverHeader],
        ];
        if ($serverHeader !== []) {
            $result['server_signature']['passed'] = false;
            $result['server_signature']['errors'] = ['failed' => null];
        }

        return $result;
    }
}
