<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Actions\Performance;

use KalimeroMK\SeoReport\Actions\AnalysisActionInterface;
use KalimeroMK\SeoReport\Actions\AnalysisContext;

final class TimingAction implements AnalysisActionInterface
{
    public function handle(AnalysisContext $context): array
    {
        $stats = $context->getStats();
        $loadTime = (float) ($stats['total_time'] ?? 0);

        $result = [
            'load_time' => ['passed' => true, 'importance' => 'medium', 'value' => $loadTime],
        ];
        if ($loadTime > $context->getConfig()->getReportLimitLoadTime()) {
            $result['load_time']['passed'] = false;
            $result['load_time']['errors'] = ['too_slow' => ['max' => $context->getConfig()->getReportLimitLoadTime()]];
        }

        $ttfb = (float) ($stats['starttransfer_time'] ?? 0);
        $ttfbLimit = $context->getConfig()->getReportLimitTtfb();
        $result['ttfb'] = ['passed' => true, 'importance' => 'medium', 'value' => $ttfb > 0 ? $ttfb : null];
        if ($ttfb > 0 && $ttfbLimit > 0 && $ttfb > $ttfbLimit) {
            $result['ttfb']['passed'] = false;
            $result['ttfb']['errors'] = ['too_slow' => ['max' => $ttfbLimit]];
        }

        return $result;
    }
}
