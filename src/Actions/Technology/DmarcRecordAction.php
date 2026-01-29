<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Actions\Technology;

use KalimeroMK\SeoReport\Actions\AnalysisActionInterface;
use KalimeroMK\SeoReport\Actions\AnalysisContext;

final class DmarcRecordAction implements AnalysisActionInterface
{
    public function handle(AnalysisContext $context): array
    {
        $hostStr = (string) $context->getData('host_str', '');
        $dmarcRecord = $context->getData('dmarc_record');

        $result = [
            'dmarc_record' => ['passed' => true, 'importance' => 'low', 'value' => $dmarcRecord],
        ];
        if ($dmarcRecord === null && $hostStr !== '') {
            $result['dmarc_record']['passed'] = false;
            $result['dmarc_record']['errors'] = ['missing' => null];
        }

        return $result;
    }
}
