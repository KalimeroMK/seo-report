<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Actions\Technology;

use KalimeroMK\SeoReport\Actions\AnalysisActionInterface;
use KalimeroMK\SeoReport\Actions\AnalysisContext;

final class DnsServersAction implements AnalysisActionInterface
{
    public function handle(AnalysisContext $context): array
    {
        $hostStr = (string) $context->getData('host_str', '');
        $dnsServers = (array) $context->getData('dns_servers', []);

        $result = [
            'dns_servers' => ['passed' => true, 'importance' => 'low', 'value' => $dnsServers],
        ];
        if ($dnsServers === [] && $hostStr !== '') {
            $result['dns_servers']['passed'] = false;
            $result['dns_servers']['errors'] = ['missing' => null];
        }

        return $result;
    }
}
