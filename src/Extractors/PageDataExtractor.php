<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Extractors;

use KalimeroMK\SeoReport\Config\SeoReportConfig;
use KalimeroMK\SeoReport\Extractors\Page\ContentSecurityExtractor;
use KalimeroMK\SeoReport\Extractors\Page\DocumentBasicsExtractor;
use KalimeroMK\SeoReport\Extractors\Page\HeadingExtractor;
use KalimeroMK\SeoReport\Extractors\Page\HeadMetaExtractor;
use KalimeroMK\SeoReport\Extractors\Page\LinksExtractor;
use KalimeroMK\SeoReport\Extractors\Page\MediaExtractor;
use KalimeroMK\SeoReport\Extractors\Page\PerformanceAssetsExtractor;
use KalimeroMK\SeoReport\Extractors\Page\StructuredDataExtractor;
use KalimeroMK\SeoReport\Extractors\Page\TechDetectorExtractor;

final readonly class PageDataExtractor
{
    public function __construct(
        private DocumentBasicsExtractor $documentBasicsExtractor = new DocumentBasicsExtractor(),
        private HeadMetaExtractor $headMetaExtractor = new HeadMetaExtractor(),
        private HeadingExtractor $headingExtractor = new HeadingExtractor(),
        private LinksExtractor $linksExtractor = new LinksExtractor(),
        private MediaExtractor $mediaExtractor = new MediaExtractor(),
        private PerformanceAssetsExtractor $performanceAssetsExtractor = new PerformanceAssetsExtractor(),
        private ContentSecurityExtractor $contentSecurityExtractor = new ContentSecurityExtractor(),
        private StructuredDataExtractor $structuredDataExtractor = new StructuredDataExtractor(),
        private TechDetectorExtractor $techDetectorExtractor = new TechDetectorExtractor(),
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function extract(\DOMDocument $domDocument, string $reportResponse, string $baseUrl, SeoReportConfig $config): array
    {
        $documentBasics = $this->documentBasicsExtractor->extract($domDocument, $reportResponse, $config);
        $headMeta = $this->headMetaExtractor->extract($domDocument, $baseUrl);
        $headingsData = $this->headingExtractor->extract($domDocument);
        $linksData = $this->linksExtractor->extract($domDocument, $baseUrl);
        $mediaData = $this->mediaExtractor->extract($domDocument, $baseUrl, $config);
        $performanceAssets = $this->performanceAssetsExtractor->extract($domDocument, $baseUrl);
        $securityData = $this->contentSecurityExtractor->extract($domDocument, $baseUrl, $reportResponse);
        $structuredData = $this->structuredDataExtractor->extract($domDocument);
        $techData = $this->techDetectorExtractor->extract($domDocument, $baseUrl);

        $pageText = $documentBasics['page_text'];
        $bodyKeywords = $documentBasics['body_keywords'];
        $docType = $documentBasics['doc_type'];

        $title = $headMeta['title'];
        $titleTagsCount = $headMeta['title_tags_count'];
        $metaDescription = $headMeta['meta_description'];

        $headings = $headingsData['headings'];
        $h1Count = $headingsData['h1_count'];
        $secondaryHeadingUsage = $headingsData['secondary_heading_usage'];
        $secondaryHeadingLevels = $headingsData['secondary_heading_levels'];

        $titleKeywords = array_filter(explode(' ', (string) preg_replace('/[^\w]/ui', ' ', mb_strtolower((string) $title))));
        $metaDescriptionKeywords = array_filter(explode(' ', (string) preg_replace('/[^\w]/ui', ' ', mb_strtolower((string) $metaDescription))));
        $headingTexts = [];
        foreach ($headings as $texts) {
            foreach ($texts as $t) {
                $headingTexts[] = $t;
            }
        }
        $headingKeywords = array_filter(explode(' ', (string) preg_replace('/[^\w]/ui', ' ', mb_strtolower(implode(' ', $headingTexts)))));

        $keywordsInMeta = array_intersect($titleKeywords, $metaDescriptionKeywords);
        $keywordsInHeadings = array_intersect($titleKeywords, $headingKeywords);
        $keywordsMissingInMeta = array_values(array_diff($titleKeywords, $metaDescriptionKeywords));
        $keywordsMissingInHeadings = array_values(array_diff($titleKeywords, $headingKeywords));

        $keywordConsistency = [
            'title_keywords' => array_values($titleKeywords),
            'in_meta_description' => array_values($keywordsInMeta),
            'in_headings' => array_values($keywordsInHeadings),
            'missing_in_meta' => $keywordsMissingInMeta,
            'missing_in_headings' => $keywordsMissingInHeadings,
        ];

        $imageAlts = $mediaData['image_alts'];
        $pageLinks = $linksData['page_links'];
        $unfriendlyLinkUrls = $linksData['unfriendly_link_urls'];
        $httpScheme = $securityData['http_scheme'];
        $noIndex = $headMeta['noindex'];
        $robotsDirectives = $headMeta['robots_directives'];
        $language = $headMeta['language'];
        $favicon = $headMeta['favicon'];
        $canonicalTag = $headMeta['canonical_tag'];
        $canonicalTags = $headMeta['canonical_tags'];
        $hreflang = $headMeta['hreflang'];
        $mixedContent = $securityData['mixed_content'];
        $unsafeCrossOriginLinks = $securityData['unsafe_cross_origin_links'];
        $plaintextEmails = $securityData['plaintext_emails'];
        $httpRequests = $performanceAssets['http_requests'];
        $emptySrcHref = $performanceAssets['empty_src_or_href'];
        $imageFormatsConfig = $mediaData['image_formats_config'];
        $imageFormats = $mediaData['image_formats'];
        $imagesMissingDimensions = $mediaData['images_missing_dimensions'];
        $imagesMissingLazy = $mediaData['images_missing_lazy'];
        $imageUrls = $mediaData['image_urls'];
        $deferJavaScript = $performanceAssets['defer_javascript'];
        $renderBlocking = $performanceAssets['render_blocking'];
        $domNodesCount = $documentBasics['dom_nodes_count'];
        $structuredData = $structuredData['structured_data'];
        $metaViewport = $headMeta['meta_viewport'];
        $charset = $headMeta['charset'];
        $textRatio = $documentBasics['text_ratio'];
        $deprecatedHtmlTags = $documentBasics['deprecated_html_tags'];
        $social = $linksData['social'];
        $inlineCss = $this->extractInlineCss($domDocument);
        $analyticsDetected = $techData['analytics_detected'];
        $technologyDetected = $techData['technology_detected'];
        $nonMinifiedJs = $techData['non_minified_js'];
        $nonMinifiedCss = $techData['non_minified_css'];
        $nofollowLinks = $linksData['nofollow_links'];
        $nofollowCount = $linksData['nofollow_count'];
        $flashContent = $mediaData['flash_content'];
        $iframes = $mediaData['iframes'];

        return [
            'page_text' => $pageText,
            'body_keywords' => $bodyKeywords,
            'doc_type' => $docType,
            'title' => $title,
            'title_tags_count' => $titleTagsCount,
            'meta_description' => $metaDescription,
            'headings' => $headings,
            'h1_count' => $h1Count,
            'secondary_heading_usage' => $secondaryHeadingUsage,
            'secondary_heading_levels' => $secondaryHeadingLevels,
            'title_keywords' => array_values($titleKeywords),
            'keyword_consistency' => $keywordConsistency,
            'image_alts' => $imageAlts,
            'page_links' => $pageLinks,
            'unfriendly_link_urls' => $unfriendlyLinkUrls,
            'http_scheme' => $httpScheme,
            'noindex' => $noIndex,
            'robots_directives' => $robotsDirectives,
            'language' => $language,
            'favicon' => $favicon,
            'canonical_tag' => $canonicalTag,
            'canonical_tags' => $canonicalTags,
            'hreflang' => $hreflang,
            'mixed_content' => $mixedContent,
            'unsafe_cross_origin_links' => $unsafeCrossOriginLinks,
            'plaintext_emails' => $plaintextEmails,
            'http_requests' => $httpRequests,
            'empty_src_or_href' => $emptySrcHref,
            'image_formats_config' => $imageFormatsConfig,
            'image_formats' => $imageFormats,
            'images_missing_dimensions' => $imagesMissingDimensions,
            'images_missing_lazy' => $imagesMissingLazy,
            'image_urls' => $imageUrls,
            'defer_javascript' => $deferJavaScript,
            'render_blocking' => $renderBlocking,
            'dom_nodes_count' => $domNodesCount,
            'structured_data' => $structuredData,
            'meta_viewport' => $metaViewport,
            'charset' => $charset,
            'text_ratio' => $textRatio,
            'deprecated_html_tags' => $deprecatedHtmlTags,
            'social' => $social,
            'inline_css' => $inlineCss,
            'analytics_detected' => $analyticsDetected,
            'technology_detected' => $technologyDetected,
            'non_minified_js' => $nonMinifiedJs,
            'non_minified_css' => $nonMinifiedCss,
            'nofollow_links' => $nofollowLinks,
            'nofollow_count' => $nofollowCount,
            'flash_content' => $flashContent,
            'iframes' => $iframes,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function extractInlineCss(\DOMDocument $domDocument): array
    {
        $inlineCss = [];
        foreach ($domDocument->getElementsByTagName('*') as $node) {
            if ($node->nodeName !== 'svg' && !empty($node->getAttribute('style'))) {
                $inlineCss[] = $node->getAttribute('style');
            }
        }

        return $inlineCss;
    }
}
