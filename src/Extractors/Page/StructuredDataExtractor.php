<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Extractors\Page;

final class StructuredDataExtractor
{
    /**
     * @return array{structured_data: array<string, mixed>}
     */
    public function extract(\DOMDocument $domDocument): array
    {
        $structuredData = [];
        foreach ($domDocument->getElementsByTagName('head') as $headNode) {
            foreach ($headNode->getElementsByTagName('meta') as $node) {
                if (preg_match('/\bog:\b/', $node->getAttribute('property')) && $node->getAttribute('content')) {
                    $structuredData['Open Graph'][$node->getAttribute('property')] = seo_report_clean_tag_text($node->getAttribute('content'));
                }
                if (preg_match('/\btwitter:\b/', $node->getAttribute('name')) && $node->getAttribute('content')) {
                    $structuredData['Twitter'][$node->getAttribute('name')] = seo_report_clean_tag_text($node->getAttribute('content'));
                }
            }
            foreach ($headNode->getElementsByTagName('script') as $node) {
                if (strtolower($node->getAttribute('type')) === 'application/ld+json') {
                    $data = json_decode((string) $node->nodeValue, true);
                    if (isset($data['@context']) && is_string($data['@context']) && in_array(mb_strtolower($data['@context']), ['https://schema.org', 'http://schema.org'], true)) {
                        $structuredData['Schema.org'] = $data;
                    }
                }
            }
        }

        return [
            'structured_data' => $structuredData,
        ];
    }
}
