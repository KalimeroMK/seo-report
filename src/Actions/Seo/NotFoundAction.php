<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Actions\Seo;

use KalimeroMK\SeoReport\Actions\AnalysisActionInterface;
use KalimeroMK\SeoReport\Actions\AnalysisContext;

final class NotFoundAction implements AnalysisActionInterface
{
    public function handle(AnalysisContext $context): array
    {
        $notFoundPage = $context->getData('not_found_page');

        $result = [
            '404_page' => ['passed' => true, 'importance' => 'high', 'value' => $notFoundPage],
        ];
        if (!$notFoundPage) {
            $result['404_page']['passed'] = false;
            $result['404_page']['errors'] = ['missing' => null];
        }

        return $result;
    }
}
