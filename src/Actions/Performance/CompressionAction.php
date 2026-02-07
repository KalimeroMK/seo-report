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
        $stats = $context->getStats();
        $sizeDownload = (float) ($stats['size_download'] ?? 0);
        
        $compressionType = null;
        $compressionRatio = null;
        
        if (in_array('br', $encTokens, true)) {
            $compressionType = 'brotli';
        } elseif (in_array('gzip', $encTokens, true)) {
            $compressionType = 'gzip';
        } elseif (in_array('deflate', $encTokens, true)) {
            $compressionType = 'deflate';
        }

        if ($sizeDownload > 0 && $compressionType !== null) {
            $estimatedRatios = [
                'brotli' => 0.75,
                'gzip' => 0.70,
                'deflate' => 0.70,
            ];
            $ratio = $estimatedRatios[$compressionType];
            $originalSize = $sizeDownload / (1 - $ratio);
            $savedBytes = (int) ($originalSize - $sizeDownload);
            $compressionRatio = [
                'original_estimate' => (int) $originalSize,
                'compressed' => (int) $sizeDownload,
                'saved_bytes' => $savedBytes,
                'saved_percent' => round(($savedBytes / $originalSize) * 100, 2),
            ];
        }

        $result = [
            'text_compression' => [
                'passed' => $compressionType !== null,
                'importance' => 'high',
                'value' => [
                    'compression_enabled' => $compressionType !== null,
                    'compression_type' => $compressionType,
                    'compressed_size' => $sizeDownload > 0 ? (int) $sizeDownload : null,
                    'savings' => $compressionRatio,
                ],
            ],
        ];
        
        if ($compressionType === null) {
            $result['text_compression']['errors'] = [
                'message' => 'Text compression not enabled',
                'recommendation' => 'Enable Gzip or Brotli compression on your web server',
                'benefits' => 'Can reduce file sizes by 60-80% for text assets',
            ];
        }

        $result['brotli_compression'] = [
            'passed' => in_array('br', $encTokens, true),
            'importance' => 'medium',
            'value' => [
                'enabled' => in_array('br', $encTokens, true),
                'compression_types_available' => $encTokens,
                'best_available' => $compressionType,
            ],
        ];
        
        if (!in_array('br', $encTokens, true)) {
            $currentType = in_array('gzip', $encTokens, true) ? 'gzip' : 'none';
            $result['brotli_compression']['errors'] = [
                'message' => 'Brotli compression not available',
                'current' => $currentType === 'none' ? 'No compression' : 'Using ' . strtoupper($currentType),
                'recommendation' => 'Brotli offers better compression than Gzip (15-25% smaller)',
            ];
        }

        $result['compression_details'] = [
            'passed' => true,
            'importance' => 'low',
            'value' => [
                'accept_encoding_supported' => $encTokens,
                'optimal' => in_array('br', $encTokens, true),
                'acceptable' => in_array('gzip', $encTokens, true) || in_array('br', $encTokens, true),
            ],
        ];

        return $result;
    }
}
