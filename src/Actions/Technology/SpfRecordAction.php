<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Actions\Technology;

use KalimeroMK\SeoReport\Actions\AnalysisActionInterface;
use KalimeroMK\SeoReport\Actions\AnalysisContext;

final class SpfRecordAction implements AnalysisActionInterface
{
    public function handle(AnalysisContext $context): array
    {
        $hostStr = (string) $context->getData('host_str', '');
        $spfRecord = $context->getData('spf_record');

        $result = [
            'spf_record' => ['passed' => true, 'importance' => 'low', 'value' => $spfRecord],
        ];
        if ($spfRecord === null && $hostStr !== '') {
            $result['spf_record']['passed'] = false;
            $result['spf_record']['errors'] = ['missing' => null];
        }

        return $result;
    }
}
