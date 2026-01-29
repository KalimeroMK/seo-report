<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\TransferStats;
use KalimeroMK\SeoReport\Actions\ActionRegistry;
use KalimeroMK\SeoReport\Actions\AnalysisContext;
use KalimeroMK\SeoReport\Config\SeoReportConfig;
use KalimeroMK\SeoReport\Dto\AnalysisResult;
use KalimeroMK\SeoReport\Extractors\DomainDataExtractor;
use KalimeroMK\SeoReport\Extractors\PageDataExtractor;
use KalimeroMK\SeoReport\Extractors\RobotsAnd404Extractor;
use KalimeroMK\SeoReport\Support\UrlHelperTrait;
use Psr\Http\Message\ResponseInterface;

final class SeoAnalyzer
{
    use UrlHelperTrait {
        resolveUrl as private resolveUrlWithBase;
        isInternalUrl as private isInternalUrlWithBase;
        normalizeUrlForCanonical as private normalizeUrlForCanonicalInternal;
    }

    private ?string $url = null;

    private PageDataExtractor $pageExtractor;

    private DomainDataExtractor $domainExtractor;

    private RobotsAnd404Extractor $robotsExtractor;

    private ActionRegistry $actionRegistry;

    public function __construct(
        private readonly SeoReportConfig $config,
        private readonly HttpClient|null $httpClient = null,
    ) {
        $this->pageExtractor = new PageDataExtractor();
        $this->domainExtractor = new DomainDataExtractor();
        $this->robotsExtractor = new RobotsAnd404Extractor();
        $this->actionRegistry = new ActionRegistry();
    }

    /**
     * Analyze a single URL and return API result.
     *
     * @throws SeoAnalyzerException When the URL cannot be fetched
     */
    public function analyze(string $url): AnalysisResult
    {
        $url = seo_report_clean_url($url);
        if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
            $url = 'https://' . $url;
        }

        $urlRequest = $this->fetchUrl($url);

        if ($urlRequest === null) {
            throw new SeoAnalyzerException('Could not fetch URL: ' . $url);
        }

        $data = $this->runAnalysis($urlRequest, $url);
        $score = $this->computeScore($data['results']);
        $fullUrl = $data['results']['seo_friendly_url']['value'] ?? $url;

        return new AnalysisResult(
            url: (string) $fullUrl,
            results: $data['results'],
            score: $score,
            generatedAt: new \DateTimeImmutable(),
        );
    }

    /**
     * Analyze all URLs from a sitemap. Returns array of AnalysisResult.
     *
     * @return list<AnalysisResult>
     * @throws SeoAnalyzerException When sitemap cannot be fetched
     */
    public function analyzeSitemap(string $sitemapUrl, ?int $maxLinks = null): array
    {
        $maxLinks ??= $this->config->getSitemapLinks();
        if (ini_get('max_execution_time') > 0 && $maxLinks > 0) {
            $extra = $maxLinks * $this->config->getRequestTimeout();
            ini_set('max_execution_time', (string) ((int) ini_get('max_execution_time') + $extra));
        }

        $sitemapRequest = $this->fetchUrl($sitemapUrl);
        if ($sitemapRequest === null) {
            throw new SeoAnalyzerException('Could not fetch sitemap: ' . $sitemapUrl);
        }

        $sitemapResponse = $sitemapRequest['response']->getBody()->getContents();
        $sitemapStats = $sitemapRequest['stats'];
        $domDocument = new \DOMDocument();
        libxml_use_internal_errors(true);
        $domDocument->loadHTML('<?xml encoding="utf-8" ?>' . $sitemapResponse);

        $results = [];
        $i = 1;

        foreach ($domDocument->getElementsByTagName('urlset') as $urlsetNode) {
            foreach ($urlsetNode->getElementsByTagName('loc') as $node) {
                $parent = $node->parentNode;
                if ($parent === null || $parent->nodeName !== 'url') {
                    continue;
                }
                $this->url = $sitemapStats['url'] ?? $sitemapUrl;
                $pageUrl = (string) $node->nodeValue;
                if (!$this->isInternalUrl($this->resolveUrl($pageUrl))) {
                    continue;
                }
                if ($maxLinks > 0 && $i > $maxLinks) {
                    break 2;
                }
                try {
                    usleep(random_int(750000, 1250000));
                    $results[] = $this->analyze($pageUrl);
                } catch (\Exception) {
                    continue;
                }
                $i++;
            }
        }

        return $results;
    }

    /**
     * @return array{response: ResponseInterface, stats: array<string, mixed>}|null
     */
    private function fetchUrl(string $url): ?array
    {
        $client = $this->httpClient ?? new HttpClient();
        $proxy = $this->config->getRequestProxy();
        $proxyOpt = $proxy !== null ? ['http' => $proxy, 'https' => $proxy] : [];

        $reportRequestStats = null;
        try {
            $requestUrl = str_replace('https://', 'http://', $url);
            $request = $client->request('GET', $requestUrl, [
                'version' => $this->config->getRequestHttpVersion(),
                'proxy' => $proxyOpt,
                'timeout' => $this->config->getRequestTimeout(),
                'allow_redirects' => [
                    'max' => 10,
                    'strict' => true,
                    'referer' => true,
                    'protocols' => ['http', 'https'],
                    'track_redirects' => true,
                ],
                'headers' => [
                    'Accept-Encoding' => 'gzip, deflate, br',
                    'User-Agent' => $this->config->getRequestUserAgent(),
                ],
                'on_stats' => function (TransferStats $stats) use (&$reportRequestStats): void {
                    if ($stats->hasResponse()) {
                        $reportRequestStats = $stats->getHandlerStats();
                    }
                },
            ]);
        } catch (GuzzleException) {
            return null;
        }

        $reportRequestStats ??= [];
        if (isset($reportRequestStats['url'])) {
            $reportRequestStats['url'] = (string) $reportRequestStats['url'];
        }
        $reportRequestStats['total_time'] = (float) ($reportRequestStats['total_time'] ?? 0);
        $reportRequestStats['size_download'] = (float) ($reportRequestStats['size_download'] ?? 0);
        $reportRequestStats['starttransfer_time'] = (float) ($reportRequestStats['starttransfer_time'] ?? 0);

        return [
            'response' => $request,
            'stats' => $reportRequestStats,
        ];
    }

    /**
     * @param array{response: ResponseInterface, stats: array<string, mixed>} $urlRequest
     * @return array{results: array<string, mixed>}
     */
    private function runAnalysis(array $urlRequest, string $inputUrl): array
    {
        $reportResponse = $urlRequest['response']->getBody()->getContents();
        $this->url = (string) ($urlRequest['stats']['url'] ?? $inputUrl);

        if (str_starts_with($reportResponse, "\xEF\xBB\xBF")) {
            $reportResponse = str_replace("\xEF\xBB\xBF", '', $reportResponse);
        }

        $domDocument = new \DOMDocument();
        libxml_use_internal_errors(true);
        $domDocument->loadHTML('<?xml encoding="utf-8" ?>' . $reportResponse, LIBXML_HTML_NODEFDTD);

        $baseUrl = $this->url ?? $inputUrl;
        $pageData = $this->pageExtractor->extract($domDocument, $reportResponse, $baseUrl, $this->config);
        $host = parse_url($baseUrl, PHP_URL_HOST);
        $hostStr = is_string($host) ? $host : '';
        $domainData = $this->domainExtractor->extract($baseUrl, $hostStr, $this->config, $this->httpClient);
        $robotsData = $this->robotsExtractor->getRobotsData($baseUrl, $this->config, $this->httpClient);
        $notFoundPage = $this->robotsExtractor->getNotFoundPage($baseUrl, $this->config, $this->httpClient);

        $pageText = $pageData['page_text'];
        $bodyKeywords = $pageData['body_keywords'];
        $docType = $pageData['doc_type'];
        $title = $pageData['title'];
        $titleTagsCount = $pageData['title_tags_count'];
        $metaDescription = $pageData['meta_description'];
        $headings = $pageData['headings'];
        $h1Count = $pageData['h1_count'];
        $secondaryHeadingUsage = $pageData['secondary_heading_usage'];
        $secondaryHeadingLevels = $pageData['secondary_heading_levels'];
        $titleKeywords = $pageData['title_keywords'];
        $keywordConsistency = $pageData['keyword_consistency'];
        $imageAlts = $pageData['image_alts'];
        $pageLinks = $pageData['page_links'];
        $unfriendlyLinkUrls = $pageData['unfriendly_link_urls'];
        $httpScheme = $pageData['http_scheme'];
        $noIndex = $pageData['noindex'];
        $robotsDirectives = $pageData['robots_directives'];
        $language = $pageData['language'];
        $favicon = $pageData['favicon'];
        $canonicalTag = $pageData['canonical_tag'];
        $canonicalTags = $pageData['canonical_tags'];
        $hreflang = $pageData['hreflang'];
        $mixedContent = $pageData['mixed_content'];
        $unsafeCrossOriginLinks = $pageData['unsafe_cross_origin_links'];
        $plaintextEmails = $pageData['plaintext_emails'];
        $httpRequests = $pageData['http_requests'];
        $emptySrcHref = $pageData['empty_src_or_href'];
        $imageFormatsConfig = $pageData['image_formats_config'];
        $imageFormats = $pageData['image_formats'];
        $imagesMissingDimensions = $pageData['images_missing_dimensions'];
        $imagesMissingLazy = $pageData['images_missing_lazy'];
        $imageUrls = $pageData['image_urls'];
        $deferJavaScript = $pageData['defer_javascript'];
        $renderBlocking = $pageData['render_blocking'];
        $domNodesCount = $pageData['dom_nodes_count'];
        $structuredData = $pageData['structured_data'];
        $openGraphData = $structuredData['Open Graph'] ?? [];
        $twitterData = $structuredData['Twitter'] ?? [];
        $metaViewport = $pageData['meta_viewport'];
        $charset = $pageData['charset'];
        $textRatio = $pageData['text_ratio'];
        $deprecatedHtmlTags = $pageData['deprecated_html_tags'];
        $social = $pageData['social'];
        $inlineCss = $pageData['inline_css'];
        $analyticsDetected = $pageData['analytics_detected'];
        $technologyDetected = $pageData['technology_detected'];
        $nonMinifiedJs = $pageData['non_minified_js'];
        $nonMinifiedCss = $pageData['non_minified_css'];
        $nofollowLinks = $pageData['nofollow_links'];
        $nofollowCount = $pageData['nofollow_count'];
        $flashContent = $pageData['flash_content'];
        $iframes = $pageData['iframes'];

        $robots = $robotsData['robots'];
        $robotsRulesFailed = $robotsData['rules_failed'];
        $sitemaps = $robotsData['sitemaps'];

        $serverIp = $domainData['server_ip'];
        $dnsServers = $domainData['dns_servers'];
        $dmarcRecord = $domainData['dmarc_record'];
        $spfRecord = $domainData['spf_record'];
        $sslCertificate = $domainData['ssl_certificate'];
        $reverseDns = $domainData['reverse_dns'];
        $llmsTxtUrl = $domainData['llms_txt_url'];

        $noindexHeader = $urlRequest['response']->getHeader('X-Robots-Tag');
        $noindexHeaderValue = $noindexHeader !== [] ? implode(', ', $noindexHeader) : null;

        $stats = $urlRequest['stats'];
        $response = $urlRequest['response'];

        $data = ['results' => []];
        $seoData = [
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
            'nofollow_count' => $nofollowCount,
            'nofollow_links' => $nofollowLinks,
            'canonical_tag' => $canonicalTag,
            'canonical_tags' => $canonicalTags,
            'hreflang' => $hreflang,
            'language' => $language,
            'favicon' => $favicon,
            'noindex' => $noIndex,
            'robots_directives' => $robotsDirectives,
            'robots' => $robots,
            'robots_rules_failed' => $robotsRulesFailed,
            'not_found_page' => $notFoundPage,
            'noindex_header_value' => $noindexHeaderValue,
            'open_graph_data' => $openGraphData,
            'twitter_data' => $twitterData,
            'current_url' => (string) ($stats['url'] ?? $this->url),
        ];
        $data['results'] = array_merge(
            $data['results'],
            $this->runActions(
                $seoData,
                $response,
                $stats,
                $domDocument,
                $reportResponse,
                $pageText,
                $bodyKeywords,
                $docType,
                $inputUrl,
                $this->actionRegistry->seo(),
            ),
        );

        $assetHeadCache = [];
        $fetchAssetHead = function (string $assetUrl) use (&$assetHeadCache): ?ResponseInterface {
            if (array_key_exists($assetUrl, $assetHeadCache)) {
                return $assetHeadCache[$assetUrl];
            }
            $assetHeadCache[$assetUrl] = $this->fetchAssetResponse($assetUrl);
            return $assetHeadCache[$assetUrl];
        };

        $staticAssets = array_unique(array_merge(
            $httpRequests['JavaScripts'],
            $httpRequests['CSS'],
            $httpRequests['Images'],
        ));
        $missingStaticCache = [];
        $checkedStaticAssets = [];
        foreach ($staticAssets as $assetUrl) {
            if (preg_match('/^data:/i', (string) $assetUrl)) {
                continue;
            }
            $scheme = parse_url((string) $assetUrl, PHP_URL_SCHEME);
            if (!in_array($scheme, ['http', 'https'], true)) {
                continue;
            }
            $assetResponse = $fetchAssetHead($assetUrl);
            if (!$assetResponse instanceof ResponseInterface || $assetResponse->getStatusCode() >= 400) {
                continue;
            }
            $checkedStaticAssets[] = $assetUrl;
            $assetCacheControl = $assetResponse->getHeaderLine('Cache-Control');
            $assetExpires = $assetResponse->getHeaderLine('Expires');
            $hasMaxAge = (bool) preg_match('/(?:^|,)\s*(?:s-maxage|max-age)=\d+/i', $assetCacheControl);
            if ($assetExpires === '' && !$hasMaxAge) {
                $missingStaticCache[] = $assetUrl;
            }
        }
        $maxImageBytes = $this->config->getReportLimitImageMaxBytes();
        $largeImages = [];
        $checkedImages = [];
        $largestImage = null;
        if ($maxImageBytes > 0) {
            foreach (array_unique($imageUrls) as $imgUrl) {
                if (preg_match('/^data:/i', (string) $imgUrl)) {
                    continue;
                }
                $scheme = parse_url((string) $imgUrl, PHP_URL_SCHEME);
                if (!in_array($scheme, ['http', 'https'], true)) {
                    continue;
                }
                $assetResponse = $fetchAssetHead($imgUrl);
                if (!$assetResponse instanceof ResponseInterface || $assetResponse->getStatusCode() >= 400) {
                    continue;
                }
                $checkedImages[] = $imgUrl;
                $contentLength = (int) $assetResponse->getHeaderLine('Content-Length');
                if ($contentLength > 0 && ($largestImage === null || $contentLength > $largestImage['bytes'])) {
                    $largestImage = ['url' => $imgUrl, 'bytes' => $contentLength];
                }
                if ($contentLength > 0 && $contentLength > $maxImageBytes) {
                    $largeImages[] = ['url' => $imgUrl, 'bytes' => $contentLength];
                }
            }
        }

        $assetRedirects = [];
        foreach ($staticAssets as $assetUrl) {
            if (preg_match('/^data:/i', (string) $assetUrl)) {
                continue;
            }
            $scheme = parse_url((string) $assetUrl, PHP_URL_SCHEME);
            if (!in_array($scheme, ['http', 'https'], true)) {
                continue;
            }
            $assetResponse = $fetchAssetHead($assetUrl);
            if (!$assetResponse instanceof ResponseInterface || $assetResponse->getStatusCode() >= 400) {
                continue;
            }
            $history = $assetResponse->getHeader('X-Guzzle-Redirect-History');
            $count = count($history);
            if ($count > 0) {
                $assetRedirects[] = ['url' => $assetUrl, 'count' => $count];
            }
        }
        $redirectCount = (int) ($stats['redirect_count'] ?? 0);
        $redirectHistory = $response->getHeader('X-Guzzle-Redirect-History');
        $redirectHistoryCount = count($redirectHistory);
        if ($redirectHistoryCount > $redirectCount) {
            $redirectCount = $redirectHistoryCount;
        }

        $mainHost = strtolower((string) (parse_url((string) ($stats['url'] ?? $this->url), PHP_URL_HOST) ?? ''));
        $setCookieHeaders = $response->getHeader('Set-Cookie');
        $cookieDomainHits = [];
        if ($mainHost !== '' && $setCookieHeaders !== []) {
            foreach ($httpRequests as $requests) {
                foreach ($requests as $assetUrl) {
                    $assetHost = strtolower((string) (parse_url((string) $assetUrl, PHP_URL_HOST) ?? ''));
                    if ($assetHost !== '' && $assetHost === $mainHost) {
                        $cookieDomainHits[] = $assetUrl;
                    }
                }
            }
        }

        $cacheControl = $response->getHeaderLine('Cache-Control');
        $expires = $response->getHeaderLine('Expires');
        $hasMaxAge = (bool) preg_match('/(?:^|,)\s*(?:s-maxage|max-age)=\d+/i', $cacheControl);

        $encHeader = $response->getHeader('Content-Encoding');
        $encTokens = [];
        foreach ($encHeader as $value) {
            $parts = array_map(trim(...), explode(',', $value));
            foreach ($parts as $part) {
                if ($part !== '') {
                    $encTokens[] = strtolower($part);
                }
            }
        }

        $performanceData = [
            'http_requests' => $httpRequests,
            'checked_static_assets' => $checkedStaticAssets,
            'missing_static_cache' => $missingStaticCache,
            'cache_control' => $cacheControl,
            'expires_header' => $expires,
            'has_max_age' => $hasMaxAge,
            'redirect_count' => $redirectCount,
            'redirect_history' => $redirectHistory,
            'asset_redirects' => $assetRedirects,
            'main_host' => $mainHost,
            'cookie_domain_hits' => $cookieDomainHits,
            'empty_src_or_href' => $emptySrcHref,
            'image_formats_config' => $imageFormatsConfig,
            'image_formats' => $imageFormats,
            'images_missing_dimensions' => $imagesMissingDimensions,
            'images_missing_lazy' => $imagesMissingLazy,
            'image_max_bytes' => $maxImageBytes,
            'large_images' => $largeImages,
            'largest_image' => $largestImage,
            'defer_javascript' => $deferJavaScript,
            'render_blocking' => $renderBlocking,
            'dom_nodes_count' => $domNodesCount,
            'doc_type' => $docType,
            'enc_tokens' => $encTokens,
            'non_minified_js' => $nonMinifiedJs,
            'non_minified_css' => $nonMinifiedCss,
        ];
        $data['results'] = array_merge(
            $data['results'],
            $this->runActions(
                $performanceData,
                $response,
                $stats,
                $domDocument,
                $reportResponse,
                $pageText,
                $bodyKeywords,
                $docType,
                $inputUrl,
                $this->actionRegistry->performance(),
            ),
        );

        $securityData = [
            'http_scheme' => $httpScheme,
            'mixed_content' => $mixedContent,
            'server_header' => $response->getHeader('Server'),
            'unsafe_cross_origin_links' => $unsafeCrossOriginLinks,
            'hsts_header' => $response->getHeader('Strict-Transport-Security'),
            'plaintext_emails' => $plaintextEmails,
        ];
        $data['results'] = array_merge(
            $data['results'],
            $this->runActions(
                $securityData,
                $response,
                $stats,
                $domDocument,
                $reportResponse,
                $pageText,
                $bodyKeywords,
                $docType,
                $inputUrl,
                $this->actionRegistry->security(),
            ),
        );

        $miscData = [
            'structured_data' => $structuredData,
            'meta_viewport' => $metaViewport,
            'charset' => $charset,
            'sitemaps' => $sitemaps,
            'social' => $social,
            'text_ratio' => $textRatio,
            'inline_css' => $inlineCss,
            'deprecated_html_tags' => $deprecatedHtmlTags,
            'llms_txt_url' => $llmsTxtUrl,
            'flash_content' => $flashContent,
            'iframes' => $iframes,
        ];
        $data['results'] = array_merge(
            $data['results'],
            $this->runActions(
                $miscData,
                $response,
                $stats,
                $domDocument,
                $reportResponse,
                $pageText,
                $bodyKeywords,
                $docType,
                $inputUrl,
                $this->actionRegistry->misc(),
            ),
        );

        $technologyData = [
            'host_str' => $hostStr,
            'base_url' => $baseUrl,
            'server_ip' => $serverIp,
            'dns_servers' => $dnsServers,
            'dmarc_record' => $dmarcRecord,
            'spf_record' => $spfRecord,
            'ssl_certificate' => $sslCertificate,
            'reverse_dns' => $reverseDns,
            'analytics_detected' => $analyticsDetected,
            'technology_detected' => $technologyDetected,
        ];
        $data['results'] = array_merge(
            $data['results'],
            $this->runActions(
                $technologyData,
                $response,
                $stats,
                $domDocument,
                $reportResponse,
                $pageText,
                $bodyKeywords,
                $docType,
                $inputUrl,
                $this->actionRegistry->technology(),
            ),
        );

        return $data;
    }


    /** @param array<string, mixed> $results */
    private function computeScore(array $results): float
    {
        $totalPoints = 0;
        $resultPoints = 0;
        foreach ($results as $value) {
            $importance = $value['importance'] ?? 'low';
            $weight = $importance === 'high' ? $this->config->getReportScoreHigh()
                : ($importance === 'medium' ? $this->config->getReportScoreMedium() : $this->config->getReportScoreLow());
            $totalPoints += $weight;
            if (!empty($value['passed'])) {
                $resultPoints += $weight;
            }
        }
        return $totalPoints > 0 ? (float) (($resultPoints / $totalPoints) * 100) : 0.0;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $stats
     * @param list<string> $bodyKeywords
     * @param list<\KalimeroMK\SeoReport\Actions\AnalysisActionInterface> $actions
     *
     * @return array<string, mixed>
     */
    private function runActions(
        array $data,
        ResponseInterface $response,
        array $stats,
        \DOMDocument $domDocument,
        string $reportResponse,
        string $pageText,
        array $bodyKeywords,
        string $docType,
        string $inputUrl,
        array $actions,
    ): array {
        $context = $this->createContext(
            $data,
            $response,
            $stats,
            $domDocument,
            $reportResponse,
            $pageText,
            $bodyKeywords,
            $docType,
            $inputUrl,
        );

        $results = [];
        foreach ($actions as $action) {
            $results = array_merge($results, $action->handle($context));
        }

        return $results;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $stats
     * @param list<string> $bodyKeywords
     */
    private function createContext(
        array $data,
        ResponseInterface $response,
        array $stats,
        \DOMDocument $domDocument,
        string $reportResponse,
        string $pageText,
        array $bodyKeywords,
        string $docType,
        string $inputUrl,
    ): AnalysisContext {
        return new AnalysisContext(
            $this->config,
            $response,
            $stats,
            $this->url ?? $inputUrl,
            $domDocument,
            $reportResponse,
            $pageText,
            $bodyKeywords,
            $docType,
            $data,
        );
    }

    private function isInternalUrl(string $url): bool
    {
        return $this->isInternalUrlWithBase($url, $this->url ?? '');
    }

    private function resolveUrl(string $url): string
    {
        return $this->resolveUrlWithBase($url, $this->url ?? '');
    }


    private function fetchAssetResponse(string $url): ?ResponseInterface
    {
        $client = $this->httpClient ?? new HttpClient();
        $proxy = $this->config->getRequestProxy();
        $proxyOpt = $proxy !== null ? ['http' => $proxy, 'https' => $proxy] : [];

        try {
            return $client->request('HEAD', $url, [
                'version' => $this->config->getRequestHttpVersion(),
                'proxy' => $proxyOpt,
                'timeout' => $this->config->getRequestTimeout(),
                'allow_redirects' => [
                    'max' => 10,
                    'strict' => true,
                    'referer' => true,
                    'protocols' => ['http', 'https'],
                    'track_redirects' => true,
                ],
                'headers' => [
                    'User-Agent' => $this->config->getRequestUserAgent(),
                ],
                'http_errors' => false,
            ]);
        } catch (GuzzleException) {
            return null;
        }
    }

}
