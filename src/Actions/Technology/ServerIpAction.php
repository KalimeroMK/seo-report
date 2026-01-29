<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Actions\Technology;

use KalimeroMK\SeoReport\Actions\AnalysisActionInterface;
use KalimeroMK\SeoReport\Actions\AnalysisContext;

final class ServerIpAction implements AnalysisActionInterface
{
    public function handle(AnalysisContext $context): array
    {
        $hostStr = (string) $context->getData('host_str', '');
        $serverIp = $context->getData('server_ip');

        $result = [
            'server_ip' => ['passed' => true, 'importance' => 'low', 'value' => $serverIp],
        ];
        if ($serverIp === null && $hostStr !== '') {
            $result['server_ip']['passed'] = false;
            $result['server_ip']['errors'] = ['unresolved' => null];
        }

        return $result;
    }
}
