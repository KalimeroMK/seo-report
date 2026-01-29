<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Extractors\Page;

use KalimeroMK\SeoReport\Config\SeoReportConfig;

final class DocumentBasicsExtractor
{
    /**
     * @return array{
     *     page_text: string,
     *     body_keywords: array<int, string>,
     *     doc_type: string,
     *     dom_nodes_count: int,
     *     text_ratio: int,
     *     deprecated_html_tags: array<string, int>
     * }
     */
    public function extract(\DOMDocument $domDocument, string $reportResponse, SeoReportConfig $config): array
    {
        $bodyEl = $domDocument->getElementsByTagName('body')->item(0);
        $pageText = seo_report_clean_tag_text($bodyEl !== null ? $bodyEl->textContent : null);
        $bodyKeywords = array_values(array_filter(explode(' ', (string) preg_replace('/[^\w]/ui', ' ', mb_strtolower($pageText)))));
        $docType = $domDocument->doctype instanceof \DOMDocumentType ? $domDocument->doctype->nodeName : '';

        $domNodesCount = count($domDocument->getElementsByTagName('*'));

        $textRatio = (int) round(($reportResponse !== '' && $reportResponse !== '0' && $pageText !== '' && $pageText !== '0')
            ? (mb_strlen($pageText) / mb_strlen($reportResponse) * 100)
            : 0);

        $deprecatedTagsConfig = preg_split('/\n|\r/', $config->getReportLimitDeprecatedHtmlTags(), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $deprecatedHtmlTags = [];
        foreach ($deprecatedTagsConfig as $tagName) {
            foreach ($domDocument->getElementsByTagName($tagName) as $node) {
                $deprecatedHtmlTags[$node->nodeName] = ($deprecatedHtmlTags[$node->nodeName] ?? 0) + 1;
            }
        }

        return [
            'page_text' => $pageText,
            'body_keywords' => $bodyKeywords,
            'doc_type' => $docType,
            'dom_nodes_count' => $domNodesCount,
            'text_ratio' => $textRatio,
            'deprecated_html_tags' => $deprecatedHtmlTags,
        ];
    }
}
