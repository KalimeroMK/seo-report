<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Actions\Misc;

use KalimeroMK\SeoReport\Actions\AnalysisActionInterface;
use KalimeroMK\SeoReport\Actions\AnalysisContext;

final class SitemapAction implements AnalysisActionInterface
{
    public function handle(AnalysisContext $context): array
    {
        $sitemaps = (array) $context->getData('sitemaps', []);
        $response = $context->getResponse();
        $url = $context->getUrl();

        $result = [
            'sitemap' => ['passed' => true, 'importance' => 'low', 'value' => null],
        ];

        if ($sitemaps === []) {
            $result['sitemap']['passed'] = false;
            $result['sitemap']['errors'] = [
                'message' => 'No sitemap found in robots.txt',
                'recommendation' => 'Create and submit a sitemap.xml file',
            ];
        } else {
            $sitemapAnalysis = $this->analyzeSitemaps($sitemaps);
            $result['sitemap']['value'] = $sitemapAnalysis;
            
            if (!empty($sitemapAnalysis['errors'])) {
                $result['sitemap']['passed'] = false;
                $result['sitemap']['errors'] = $sitemapAnalysis['errors'];
            }
        }

        return $result;
    }

    /**
     * Analyze sitemap URLs for common issues
     *
     * @param array<int, string> $sitemaps
     * @return array<string, mixed>
     */
    private function analyzeSitemaps(array $sitemaps): array
    {
        $analysis = [
            'count' => count($sitemaps),
            'urls' => $sitemaps,
            'valid_urls' => [],
            'invalid_urls' => [],
            'errors' => [],
        ];

        foreach ($sitemaps as $sitemapUrl) {
            $validation = $this->validateSitemapUrl((string) $sitemapUrl);
            
            if ($validation['valid']) {
                $analysis['valid_urls'][] = [
                    'url' => $sitemapUrl,
                    'protocol' => $validation['protocol'],
                    'is_xml' => $validation['is_xml'],
                ];
            } else {
                $analysis['invalid_urls'][] = [
                    'url' => $sitemapUrl,
                    'reason' => $validation['reason'],
                ];
            }
        }

        if (!empty($analysis['invalid_urls'])) {
            $analysis['errors'][] = [
                'type' => 'invalid_urls',
                'message' => 'Some sitemap URLs appear to be invalid',
                'invalid_urls' => $analysis['invalid_urls'],
            ];
        }

        $httpsCount = 0;
        foreach ($analysis['valid_urls'] as $validUrl) {
            if ($validUrl['protocol'] === 'https') {
                $httpsCount++;
            }
        }
        
        if ($httpsCount < count($analysis['valid_urls'])) {
            $analysis['warnings'][] = [
                'type' => 'http_sitemap',
                'message' => 'Some sitemaps use HTTP instead of HTTPS',
                'recommendation' => 'Use HTTPS for all sitemap URLs',
            ];
        }

        return $analysis;
    }

    /**
     * Validate a sitemap URL
     *
     * @return array<string, mixed>
     */
    private function validateSitemapUrl(string $url): array
    {
        $result = [
            'valid' => false,
            'protocol' => null,
            'is_xml' => false,
            'reason' => null,
        ];

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $result['reason'] = 'Invalid URL format';
            return $result;
        }

        $parsed = parse_url($url);
        
        if ($parsed === false || !isset($parsed['scheme'])) {
            $result['reason'] = 'Missing URL scheme';
            return $result;
        }

        $result['protocol'] = strtolower($parsed['scheme']);
        
        if (!in_array($result['protocol'], ['http', 'https'], true)) {
            $result['reason'] = 'Invalid protocol (must be HTTP or HTTPS)';
            return $result;
        }

        if (!isset($parsed['host'])) {
            $result['reason'] = 'Missing host';
            return $result;
        }

        $path = $parsed['path'] ?? '';
        $result['is_xml'] = str_ends_with(strtolower($path), '.xml') || str_contains(strtolower($path), 'sitemap');
        
        if (!$result['is_xml']) {
            $result['warnings'] = 'URL does not end with .xml';
        }

        $result['valid'] = true;
        return $result;
    }
}
