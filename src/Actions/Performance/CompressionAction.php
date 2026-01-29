<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Actions\Performance;

use KalimeroMK\SeoReport\Actions\AnalysisActionInterface;
use KalimeroMK\SeoReport\Actions\AnalysisContext;

final class CompressionAction implements AnalysisActionInterface
{
    public function handle(AnalysisContext $context): array
    {
        $encTokens = (array) $context->getData('enc_tokens', []);
        $sizeDownload = (float) ($context->getStats()['size_download'] ?? 0);

        $result = [
            'text_compression' => ['passed' => true, 'importance' => 'high', 'value' => $sizeDownload],
        ];
        if (!in_array('gzip', $encTokens, true) && !in_array('br', $encTokens, true)) {
            $result['text_compression']['passed'] = false;
            $result['text_compression']['errors'] = ['missing' => null];
        }

        $result['brotli_compression'] = ['passed' => true, 'importance' => 'medium', 'value' => null];
        if (!in_array('br', $encTokens, true)) {
            $result['brotli_compression']['passed'] = false;
            $result['brotli_compression']['errors'] = ['missing' => null];
        }

        return $result;
    }
}
