<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Actions\Misc;

use KalimeroMK\SeoReport\Actions\AnalysisActionInterface;
use KalimeroMK\SeoReport\Actions\AnalysisContext;

final class LlmsTxtAction implements AnalysisActionInterface
{
    public function handle(AnalysisContext $context): array
    {
        $llmsTxtUrl = $context->getData('llms_txt_url');

        $result = [
            'llms_txt' => ['passed' => true, 'importance' => 'low', 'value' => $llmsTxtUrl],
        ];
        if ($llmsTxtUrl === null) {
            $result['llms_txt']['passed'] = false;
            $result['llms_txt']['errors'] = ['missing' => null];
        }

        return $result;
    }
}
