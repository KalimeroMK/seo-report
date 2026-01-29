<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Actions\Performance;

use KalimeroMK\SeoReport\Actions\AnalysisActionInterface;
use KalimeroMK\SeoReport\Actions\AnalysisContext;

final class DoctypeAction implements AnalysisActionInterface
{
    public function handle(AnalysisContext $context): array
    {
        $docType = $context->getDocType();

        $result = [
            'doctype' => ['passed' => true, 'importance' => 'medium', 'value' => $docType],
        ];
        if ($docType === '' || $docType === '0') {
            $result['doctype']['passed'] = false;
            $result['doctype']['errors'] = ['missing' => null];
        }

        return $result;
    }
}
