<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\TransferStats;
use KalimeroMK\SeoReport\Actions\AnalysisContext;
use KalimeroMK\SeoReport\Actions\Performance\CacheHeadersAction;
use KalimeroMK\SeoReport\Actions\Performance\CompressionAction;
use KalimeroMK\SeoReport\Actions\Performance\CookieFreeDomainsAction;
use KalimeroMK\SeoReport\Actions\Performance\DeferJavascriptAction;
use KalimeroMK\SeoReport\Actions\Performance\DoctypeAction;
use KalimeroMK\SeoReport\Actions\Performance\DomSizeAction;
use KalimeroMK\SeoReport\Actions\Performance\EmptySrcHrefAction;
use KalimeroMK\SeoReport\Actions\Performance\HttpRequestsAction;
use KalimeroMK\SeoReport\Actions\Performance\ImageOptimizationAction;
use KalimeroMK\SeoReport\Actions\Performance\MinificationAction;
use KalimeroMK\SeoReport\Actions\Performance\PageSizeAction;
use KalimeroMK\SeoReport\Actions\Performance\RedirectsAction;
use KalimeroMK\SeoReport\Actions\Performance\RenderBlockingResourcesAction;
use KalimeroMK\SeoReport\Actions\Performance\TimingAction;
use KalimeroMK\SeoReport\Actions\Security\Http2Action;
use KalimeroMK\SeoReport\Actions\Security\HttpsEncryptionAction;
use KalimeroMK\SeoReport\Actions\Security\HstsAction;
use KalimeroMK\SeoReport\Actions\Security\MixedContentAction;
use KalimeroMK\SeoReport\Actions\Security\PlaintextEmailAction;
use KalimeroMK\SeoReport\Actions\Security\ServerSignatureAction;
use KalimeroMK\SeoReport\Actions\Security\UnsafeCrossOriginLinksAction;
use KalimeroMK\SeoReport\Actions\Misc\CharsetAction;
use KalimeroMK\SeoReport\Actions\Misc\ContentLengthAction;
use KalimeroMK\SeoReport\Actions\Misc\DeprecatedHtmlTagsAction;
use KalimeroMK\SeoReport\Actions\Misc\FlashContentAction;
use KalimeroMK\SeoReport\Actions\Misc\IframesAction;
use KalimeroMK\SeoReport\Actions\Misc\InlineCssAction;
use KalimeroMK\SeoReport\Actions\Misc\LlmsTxtAction;
use KalimeroMK\SeoReport\Actions\Misc\MetaViewportAction;
use KalimeroMK\SeoReport\Actions\Misc\SitemapAction;
use KalimeroMK\SeoReport\Actions\Misc\SocialLinksAction;
use KalimeroMK\SeoReport\Actions\Misc\StructuredDataAction;
use KalimeroMK\SeoReport\Actions\Misc\TextHtmlRatioAction;
use KalimeroMK\SeoReport\Actions\Seo\CanonicalAction;
use KalimeroMK\SeoReport\Actions\Technology\AnalyticsAction;
use KalimeroMK\SeoReport\Actions\Technology\DmarcRecordAction;
use KalimeroMK\SeoReport\Actions\Technology\DnsServersAction;
use KalimeroMK\SeoReport\Actions\Technology\ReverseDnsAction;
use KalimeroMK\SeoReport\Actions\Technology\ServerIpAction;
use KalimeroMK\SeoReport\Actions\Technology\SpfRecordAction;
use KalimeroMK\SeoReport\Actions\Technology\SslCertificateAction;
use KalimeroMK\SeoReport\Actions\Technology\TechnologyDetectionAction;
use KalimeroMK\SeoReport\Actions\Seo\ContentKeywordsAction;
use KalimeroMK\SeoReport\Actions\Seo\FaviconAction;
use KalimeroMK\SeoReport\Actions\Seo\HeadingsAction;
use KalimeroMK\SeoReport\Actions\Seo\HreflangAction;
use KalimeroMK\SeoReport\Actions\Seo\ImageKeywordsAction;
use KalimeroMK\SeoReport\Actions\Seo\InPageLinksAction;
use KalimeroMK\SeoReport\Actions\Seo\LanguageAction;
use KalimeroMK\SeoReport\Actions\Seo\LinkUrlReadabilityAction;
use KalimeroMK\SeoReport\Actions\Seo\MetaDescriptionAction;
use KalimeroMK\SeoReport\Actions\Seo\NofollowLinksAction;
use KalimeroMK\SeoReport\Actions\Seo\NoindexHeaderAction;
use KalimeroMK\SeoReport\Actions\Seo\NotFoundAction;
use KalimeroMK\SeoReport\Actions\Seo\OpenGraphAction;
use KalimeroMK\SeoReport\Actions\Seo\RobotsAction;
use KalimeroMK\SeoReport\Actions\Seo\SeoFriendlyUrlAction;
use KalimeroMK\SeoReport\Actions\Seo\TitleAction;
use KalimeroMK\SeoReport\Actions\Seo\TwitterCardsAction;
use KalimeroMK\SeoReport\Config\SeoReportConfig;
use KalimeroMK\SeoReport\Dto\AnalysisResult;
use KalimeroMK\SeoReport\Support\UrlHelperTrait;

final class SeoAnalyzer
{
    use UrlHelperTrait {
        resolveUrl as private resolveUrlWithBase;
        isInternalUrl as private isInternalUrlWithBase;
        normalizeUrlForCanonical as private normalizeUrlForCanonicalInternal;
    }

    private ?string $url = null;

    private string|false|null $cachedNotFoundPage = null;

    private ?\Psr\Http\Message\ResponseInterface $cachedRobotsRequest = null;

    public function __construct(
        private readonly SeoReportConfig $config,
        private readonly HttpClient|null $httpClient = null,
    ) {}

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
     * @return array{response: \Psr\Http\Message\ResponseInterface, stats: array<string, mixed>}|null
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
     * @param array{response: \Psr\Http\Message\ResponseInterface, stats: array<string, mixed>} $urlRequest
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

        $bodyEl = $domDocument->getElementsByTagName('body')->item(0);
        $pageText = seo_report_clean_tag_text($bodyEl !== null ? $bodyEl->textContent : null);
        $bodyKeywords = array_values(array_filter(explode(' ', (string) preg_replace('/[^\w]/ui', ' ', mb_strtolower($pageText)))));
        $docType = $domDocument->doctype instanceof \DOMDocumentType ? $domDocument->doctype->nodeName : '';

        $title = null;
        $titleTagsCount = 0;
        foreach ($domDocument->getElementsByTagName('head') as $headNode) {
            foreach ($headNode->getElementsByTagName('title') as $titleNode) {
                $title .= seo_report_clean_tag_text($titleNode->textContent);
                $titleTagsCount++;
            }
        }

        $metaDescription = null;
        foreach ($domDocument->getElementsByTagName('head') as $headNode) {
            foreach ($headNode->getElementsByTagName('meta') as $node) {
                if (strtolower($node->getAttribute('name')) === 'description' && seo_report_clean_tag_text($node->getAttribute('content'))) {
                    $metaDescription = seo_report_clean_tag_text($node->getAttribute('content'));
                }
            }
        }

        $headings = [];
        foreach (['h1', 'h2', 'h3', 'h4', 'h5', 'h6'] as $heading) {
            foreach ($domDocument->getElementsByTagName($heading) as $node) {
                $headings[$heading][] = seo_report_clean_tag_text($node->textContent);
            }
        }
        $h1Count = isset($headings['h1']) ? count($headings['h1']) : 0;
        $secondaryHeadingUsage = [];
        $secondaryHeadingLevels = 0;
        foreach (['h2', 'h3', 'h4', 'h5', 'h6'] as $heading) {
            $count = isset($headings[$heading]) ? count($headings[$heading]) : 0;
            $secondaryHeadingUsage[$heading] = $count;
            if ($count > 0) {
                $secondaryHeadingLevels++;
            }
        }

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

        $imageAlts = [];
        foreach ($domDocument->getElementsByTagName('img') as $node) {
            if (!empty($node->getAttribute('src')) && empty($node->getAttribute('alt'))) {
                $imageAlts[] = [
                    'url' => $this->resolveUrl($node->getAttribute('src')),
                    'text' => seo_report_clean_tag_text($node->getAttribute('alt')),
                ];
            }
        }

        $pageLinks = ['Internals' => [], 'Externals' => []];
        $unfriendlyLinkUrls = [];
        foreach ($domDocument->getElementsByTagName('a') as $node) {
            if (empty($node->getAttribute('href')) || mb_substr($node->getAttribute('href'), 0, 1) === '#') {
                continue;
            }
            $resolved = $this->resolveUrl($node->getAttribute('href'));
            $entry = ['url' => $resolved, 'text' => seo_report_clean_tag_text($node->textContent)];
            if ($this->isInternalUrl($resolved)) {
                $pageLinks['Internals'][] = $entry;
            } else {
                $pageLinks['Externals'][] = $entry;
            }
            if (preg_match('/[\?\=\_\%\,\ ]/ui', $resolved)) {
                $unfriendlyLinkUrls[] = [
                    'url' => $resolved,
                    'text' => seo_report_clean_tag_text($node->textContent),
                ];
            }
        }

        $httpScheme = parse_url($this->url, PHP_URL_SCHEME);

        if ($this->cachedNotFoundPage === null) {
            $notFoundPage = false;
            $notFoundUrl = parse_url($this->url, PHP_URL_SCHEME) . '://' . parse_url($this->url, PHP_URL_HOST) . '/404-' . md5(uniqid((string) mt_rand(), true));
            try {
                $hc = $this->httpClient ?? new HttpClient();
                $proxy = $this->config->getRequestProxy();
                $proxyOpt = $proxy !== null ? ['http' => $proxy, 'https' => $proxy] : [];
                $hc->get($notFoundUrl, [
                    'version' => $this->config->getRequestHttpVersion(),
                    'proxy' => $proxyOpt,
                    'timeout' => $this->config->getRequestTimeout(),
                    'headers' => ['User-Agent' => $this->config->getRequestUserAgent()],
                ]);
            } catch (RequestException $e) {
                $response = $e->getResponse();
                if ($response instanceof \Psr\Http\Message\ResponseInterface && $response->getStatusCode() === 404) {
                    $notFoundPage = $notFoundUrl;
                }
            } catch (\Exception) {
                // ignore
            }
            $this->cachedNotFoundPage = $notFoundPage;
        }
        $notFoundPage = $this->cachedNotFoundPage;

        $sitemaps = [];
        $robotsRulesFailed = [];
        $robots = true;
        if (!$this->cachedRobotsRequest instanceof \Psr\Http\Message\ResponseInterface) {
            $robotsUrl = parse_url($this->url, PHP_URL_SCHEME) . '://' . parse_url($this->url, PHP_URL_HOST) . '/robots.txt';
            try {
                $hc = $this->httpClient ?? new HttpClient();
                $proxy = $this->config->getRequestProxy();
                $proxyOpt = $proxy !== null ? ['http' => $proxy, 'https' => $proxy] : [];
                $this->cachedRobotsRequest = $hc->get($robotsUrl, [
                    'version' => $this->config->getRequestHttpVersion(),
                    'proxy' => $proxyOpt,
                    'timeout' => $this->config->getRequestTimeout(),
                    'headers' => ['User-Agent' => $this->config->getRequestUserAgent()],
                ]);
            } catch (\Exception) {
                $this->cachedRobotsRequest = null;
            }
        }
        $robotsRequest = $this->cachedRobotsRequest;
        $robotsRules = $robotsRequest instanceof \Psr\Http\Message\ResponseInterface
            ? preg_split('/\n|\r/', $robotsRequest->getBody()->getContents(), -1, PREG_SPLIT_NO_EMPTY) ?: []
            : [];
        foreach ($robotsRules as $robotsRule) {
            $rule = explode(':', $robotsRule, 2);
            $directive = trim(strtolower($rule[0]));
            $value = trim($rule[1] ?? '');
            if ($directive === 'disallow' && $value !== '' && preg_match($this->formatRobotsRule($value), $this->url)) {
                $robotsRulesFailed[] = $value;
                $robots = false;
            }
            if ($directive === 'sitemap' && $value !== '') {
                $sitemaps[] = $value;
            }
        }

        $noIndex = null;
        $robotsDirectives = [];
        foreach ($domDocument->getElementsByTagName('head') as $headNode) {
            foreach ($headNode->getElementsByTagName('meta') as $node) {
                $metaName = strtolower($node->getAttribute('name'));
                if ($metaName !== 'robots' && $metaName !== 'googlebot') {
                    continue;
                }
                $content = trim($node->getAttribute('content'));
                if ($content === '') {
                    continue;
                }
                $robotsDirectives = array_merge($robotsDirectives, array_map(trim(...), explode(',', strtolower($content))));
                if (preg_match('/\bnoindex\b/', $content)) {
                    $noIndex = $content;
                }
            }
        }

        $language = null;
        foreach ($domDocument->getElementsByTagName('html') as $node) {
            if ($node->getAttribute('lang') !== '' && $node->getAttribute('lang') !== '0') {
                $language = $node->getAttribute('lang');
            }
        }

        $favicon = null;
        foreach ($domDocument->getElementsByTagName('head') as $headNode) {
            foreach ($headNode->getElementsByTagName('link') as $node) {
                if (preg_match('/\bicon\b/i', $node->getAttribute('rel'))) {
                    $favicon = $this->resolveUrl($node->getAttribute('href'));
                }
            }
        }

        $canonicalTag = null;
        $canonicalTags = [];
        $hreflang = [];
        foreach ($domDocument->getElementsByTagName('head') as $headNode) {
            foreach ($headNode->getElementsByTagName('link') as $node) {
                $rel = strtolower($node->getAttribute('rel'));
                if ($rel === 'canonical' && $node->getAttribute('href') !== '') {
                    $canonical = $this->resolveUrl($node->getAttribute('href'));
                    $canonicalTags[] = $canonical;
                    if ($canonicalTag === null) {
                        $canonicalTag = $canonical;
                    }
                }
                if ($rel === 'alternate' && $node->getAttribute('hreflang') !== '') {
                    $hreflang[] = [
                        'hreflang' => $node->getAttribute('hreflang'),
                        'href' => $this->resolveUrl($node->getAttribute('href')),
                    ];
                }
            }
        }

        $mixedContent = [];
        if (str_starts_with($this->url, 'https://')) {
            foreach ($domDocument->getElementsByTagName('script') as $node) {
                if ($node->getAttribute('src') && str_starts_with($node->getAttribute('src'), 'http://')) {
                    $mixedContent['JavaScripts'][] = $this->resolveUrl($node->getAttribute('src'));
                }
            }
            foreach ($domDocument->getElementsByTagName('link') as $node) {
                if (preg_match('/\bstylesheet\b/', $node->getAttribute('rel')) && str_starts_with($node->getAttribute('href'), 'http://')) {
                    $mixedContent['CSS'][] = $this->resolveUrl($node->getAttribute('href'));
                }
            }
            foreach ($domDocument->getElementsByTagName('img') as $node) {
                if (!empty($node->getAttribute('src')) && str_starts_with($node->getAttribute('src'), 'http://')) {
                    $mixedContent['Images'][] = $this->resolveUrl($node->getAttribute('src'));
                }
            }
            foreach ($domDocument->getElementsByTagName('iframe') as $node) {
                if (!empty($node->getAttribute('src')) && str_starts_with($node->getAttribute('src'), 'http://')) {
                    $mixedContent['Iframes'][] = $this->resolveUrl($node->getAttribute('src'));
                }
            }
        }

        $unsafeCrossOriginLinks = [];
        foreach ($domDocument->getElementsByTagName('a') as $node) {
            if (!$this->isInternalUrl($this->resolveUrl($node->getAttribute('href'))) && $node->getAttribute('target') === '_blank' && (!str_contains(strtolower($node->getAttribute('rel')), 'noopener') && !str_contains(strtolower($node->getAttribute('rel')), 'nofollow'))) {
                $unsafeCrossOriginLinks[] = $this->resolveUrl($node->getAttribute('href'));
            }
        }

        preg_match_all('/([a-zA-Z0-9._-]+@[a-zA-Z0-9._-]+\.[a-zA-Z0-9_-]+)/i', $reportResponse, $plaintextEmailsRaw, PREG_UNMATCHED_AS_NULL);
        $rawEmails = $plaintextEmailsRaw[0];
        $plaintextEmails = array_values(array_filter(
            $rawEmails,
            fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false
        ));

        $httpRequests = ['JavaScripts' => [], 'CSS' => [], 'Images' => [], 'Audios' => [], 'Videos' => [], 'Iframes' => []];
        foreach ($domDocument->getElementsByTagName('script') as $node) {
            if ($node->getAttribute('src') !== '' && $node->getAttribute('src') !== '0') {
                $httpRequests['JavaScripts'][] = $this->resolveUrl($node->getAttribute('src'));
            }
        }
        foreach ($domDocument->getElementsByTagName('link') as $node) {
            if (preg_match('/\bstylesheet\b/', $node->getAttribute('rel'))) {
                $httpRequests['CSS'][] = $this->resolveUrl($node->getAttribute('href'));
            }
        }
        foreach ($domDocument->getElementsByTagName('img') as $node) {
            $src = $node->getAttribute('src');
            if ($src !== '' && !preg_match('/\blazy\b/', $node->getAttribute('loading'))) {
                $httpRequests['Images'][] = $this->resolveUrl($src);
            }
        }
        foreach ($domDocument->getElementsByTagName('iframe') as $node) {
            $src = $node->getAttribute('src');
            if ($src !== '' && !preg_match('/\blazy\b/', $node->getAttribute('loading'))) {
                $httpRequests['Iframes'][] = $this->resolveUrl($src);
            }
        }

        $emptySrcHref = [];
        $srcHrefTags = [
            ['tag' => 'img', 'attr' => 'src'],
            ['tag' => 'script', 'attr' => 'src'],
            ['tag' => 'iframe', 'attr' => 'src'],
            ['tag' => 'link', 'attr' => 'href'],
            ['tag' => 'a', 'attr' => 'href'],
        ];
        foreach ($srcHrefTags as $spec) {
            foreach ($domDocument->getElementsByTagName($spec['tag']) as $node) {
                if (!$node->hasAttribute($spec['attr'])) {
                    continue;
                }
                $value = trim($node->getAttribute($spec['attr']));
                if ($value === '' || $value === '0') {
                    $emptySrcHref[] = $spec['tag'] . '[' . $spec['attr'] . ']';
                }
            }
        }

        $imageFormatsConfig = preg_split('/\n|\r/', $this->config->getReportLimitImageFormats(), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $allowedExtensions = array_map(strtolower(...), $imageFormatsConfig);
        $imageFormats = [];
        $imagesMissingDimensions = [];
        $imagesMissingLazy = [];
        $imageUrls = [];
        foreach ($domDocument->getElementsByTagName('img') as $node) {
            if (empty($node->getAttribute('src'))) {
                continue;
            }
            $imgUrl = $this->resolveUrl($node->getAttribute('src'));
            $imageUrls[] = $imgUrl;
            $missing = [];
            $width = trim($node->getAttribute('width'));
            $height = trim($node->getAttribute('height'));
            if ($width === '' || $width === '0') {
                $missing[] = 'width';
            }
            if ($height === '' || $height === '0') {
                $missing[] = 'height';
            }
            if ($missing !== []) {
                $imagesMissingDimensions[] = ['url' => $imgUrl, 'missing' => $missing];
            }

            $loading = trim(mb_strtolower($node->getAttribute('loading')));
            if ($loading !== 'lazy') {
                $imagesMissingLazy[] = $imgUrl;
            }

            $ext = mb_strtolower(pathinfo($imgUrl, PATHINFO_EXTENSION));
            if ($ext === 'svg' || in_array($ext, $allowedExtensions, true)) {
                continue;
            }
            $imageFormats[] = [
                'url' => $imgUrl,
                'text' => seo_report_clean_tag_text($node->getAttribute('alt')),
            ];
        }

        $deferJavaScript = [];
        foreach ($domDocument->getElementsByTagName('script') as $node) {
            if ($node->getAttribute('src') && !$node->hasAttribute('defer')) {
                $deferJavaScript[] = $this->resolveUrl($node->getAttribute('src'));
            }
        }

        $renderBlocking = ['js' => [], 'css' => []];
        foreach ($domDocument->getElementsByTagName('head') as $headNode) {
            foreach ($headNode->getElementsByTagName('script') as $node) {
                if ($node->getAttribute('src') && !$node->hasAttribute('defer') && !$node->hasAttribute('async')) {
                    $renderBlocking['js'][] = $this->resolveUrl($node->getAttribute('src'));
                }
            }
            foreach ($headNode->getElementsByTagName('link') as $node) {
                if (!preg_match('/\bstylesheet\b/i', $node->getAttribute('rel'))) {
                    continue;
                }
                $href = $node->getAttribute('href');
                if ($href === '' || $href === '0') {
                    continue;
                }
                $media = trim(mb_strtolower($node->getAttribute('media')));
                if ($media !== '' && $media !== 'all') {
                    continue;
                }
                $renderBlocking['css'][] = $this->resolveUrl($href);
            }
        }

        $domNodesCount = count($domDocument->getElementsByTagName('*'));

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
        $openGraphData = $structuredData['Open Graph'] ?? [];
        $twitterData = $structuredData['Twitter'] ?? [];

        $metaViewport = null;
        foreach ($domDocument->getElementsByTagName('head') as $headNode) {
            foreach ($headNode->getElementsByTagName('meta') as $node) {
                if (strtolower($node->getAttribute('name')) === 'viewport') {
                    $metaViewport = seo_report_clean_tag_text($node->getAttribute('content'));
                }
            }
        }

        $charset = null;
        foreach ($domDocument->getElementsByTagName('head') as $headNode) {
            foreach ($headNode->getElementsByTagName('meta') as $node) {
                if ($node->getAttribute('charset') !== '' && $node->getAttribute('charset') !== '0') {
                    $charset = seo_report_clean_tag_text($node->getAttribute('charset'));
                }
            }
        }

        $textRatio = (int) round((!empty($reportResponse) && $pageText !== '' && $pageText !== '0')
            ? (mb_strlen($pageText) / mb_strlen($reportResponse) * 100)
            : 0);

        $deprecatedTagsConfig = preg_split('/\n|\r/', $this->config->getReportLimitDeprecatedHtmlTags(), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $deprecatedHtmlTags = [];
        foreach ($deprecatedTagsConfig as $tagName) {
            foreach ($domDocument->getElementsByTagName($tagName) as $node) {
                $deprecatedHtmlTags[$node->nodeName] = ($deprecatedHtmlTags[$node->nodeName] ?? 0) + 1;
            }
        }

        $social = [];
        $socials = ['twitter.com' => 'Twitter', 'www.twitter.com' => 'Twitter', 'facebook.com' => 'Facebook', 'www.facebook.com' => 'Facebook', 'instagram.com' => 'Instagram', 'www.instagram.com' => 'Instagram', 'youtube.com' => 'YouTube', 'www.youtube.com' => 'YouTube', 'linkedin.com' => 'LinkedIn', 'www.linkedin.com' => 'LinkedIn'];
        foreach ($domDocument->getElementsByTagName('a') as $node) {
            if (empty($node->getAttribute('href')) || mb_substr($node->getAttribute('href'), 0, 1) === '#' || $this->isInternalUrl($this->resolveUrl($node->getAttribute('href')))) {
                continue;
            }
            $host = parse_url($this->resolveUrl($node->getAttribute('href')), PHP_URL_HOST);
            if (!empty($host) && isset($socials[$host])) {
                $social[$socials[$host]][] = [
                    'url' => $this->resolveUrl($node->getAttribute('href')),
                    'text' => seo_report_clean_tag_text($node->textContent),
                ];
            }
        }

        $inlineCss = [];
        foreach ($domDocument->getElementsByTagName('*') as $node) {
            if ($node->nodeName !== 'svg' && !empty($node->getAttribute('style'))) {
                $inlineCss[] = $node->getAttribute('style');
            }
        }

        $baseUrl = $this->url ?? '';
        $host = parse_url($baseUrl, PHP_URL_HOST);
        $hostStr = is_string($host) ? $host : '';

        $serverIp = null;
        $dnsServers = [];
        $dmarcRecord = null;
        $spfRecord = null;
        if ($hostStr !== '') {
            $serverIp = gethostbyname($hostStr);
            if ($serverIp === $hostStr) {
                $serverIp = null;
            }
            $nsRecords = @dns_get_record($hostStr, DNS_NS);
            if (is_array($nsRecords)) {
                foreach ($nsRecords as $r) {
                    if (isset($r['target'])) {
                        $dnsServers[] = $r['target'];
                    }
                }
            }
            $dmarcRecords = @dns_get_record('_dmarc.' . $hostStr, DNS_TXT);
            if (is_array($dmarcRecords)) {
                foreach ($dmarcRecords as $r) {
                    if (isset($r['txt']) && preg_match('/v=DMARC1/i', $r['txt'])) {
                        $dmarcRecord = $r['txt'];
                        break;
                    }
                }
            }
            $txtRecords = @dns_get_record($hostStr, DNS_TXT);
            if (is_array($txtRecords)) {
                foreach ($txtRecords as $r) {
                    if (isset($r['txt']) && preg_match('/v=spf1/i', $r['txt'])) {
                        $spfRecord = $r['txt'];
                        break;
                    }
                }
            }
        }

        $sslCertificate = null;
        if ($hostStr !== '' && str_starts_with($baseUrl, 'https://')) {
            $sslCertificate = $this->fetchSslCertificate($hostStr);
        }

        $reverseDns = null;
        if ($serverIp !== null) {
            $ptr = @gethostbyaddr($serverIp);
            if ($ptr !== false && $ptr !== $serverIp) {
                $reverseDns = $ptr;
            }
        }

        $llmsTxtUrl = null;
        if ($hostStr !== '') {
            $scheme = parse_url($baseUrl, PHP_URL_SCHEME) ?: 'https';
            $llmsUrl = $scheme . '://' . $hostStr . '/llms.txt';
            $hc = $this->httpClient ?? new HttpClient();
            $proxy = $this->config->getRequestProxy();
            $proxyOpt = $proxy !== null ? ['http' => $proxy, 'https' => $proxy] : [];
            $opts = ['timeout' => 3, 'proxy' => $proxyOpt, 'headers' => ['User-Agent' => $this->config->getRequestUserAgent()], 'http_errors' => false];
            try {
                $headResp = $hc->head($llmsUrl, $opts);
                if ($headResp->getStatusCode() === 200) {
                    $llmsTxtUrl = $llmsUrl;
                }
            } catch (\Exception) {
                try {
                    $resp = $hc->get($llmsUrl, $opts);
                    if ($resp->getStatusCode() === 200) {
                        $llmsTxtUrl = $llmsUrl;
                    }
                } catch (\Exception) {
                    // ignore
                }
            }
        }

        $analyticsDetected = [];
        $technologyDetected = [];
        $nonMinifiedJs = [];
        $nonMinifiedCss = [];
        foreach ($domDocument->getElementsByTagName('script') as $node) {
            $src = $node->getAttribute('src');
            $content = $node->textContent ?? '';
            if ($src !== '') {
                $srcLower = mb_strtolower($src);
                $resolvedSrc = $this->resolveUrl($src);
                if (str_contains($srcLower, 'google-analytics.com') || str_contains($srcLower, 'googletagmanager.com')) {
                    $analyticsDetected['Google Analytics'] = true;
                }
                if (str_contains($srcLower, 'facebook.net') || str_contains($srcLower, 'connect.facebook')) {
                    $technologyDetected['Facebook Pixel'] = true;
                }
                if (str_contains($srcLower, 'fontawesome') || str_contains($srcLower, 'font-awesome')) {
                    $technologyDetected['Font Awesome'] = true;
                }
                if (str_contains($srcLower, 'jquery')) {
                    $technologyDetected['jQuery'] = true;
                }
                if (preg_match('/\.js$/i', $resolvedSrc) && !preg_match('/\.min\.js$/i', $resolvedSrc)) {
                    $nonMinifiedJs[] = $resolvedSrc;
                }
            }
            if ($content !== '') {
                if (preg_match('/\b(gtag|ga\s*\(|googleAnalytics)/i', $content)) {
                    $analyticsDetected['Google Analytics'] = true;
                }
                if (preg_match('/\bfbq\s*\(/i', $content)) {
                    $technologyDetected['Facebook Pixel'] = true;
                }
            }
        }
        foreach ($domDocument->getElementsByTagName('link') as $node) {
            if (preg_match('/\bstylesheet\b/', $node->getAttribute('rel'))) {
                $href = $node->getAttribute('href');
                if ($href !== '') {
                    $resolvedHref = $this->resolveUrl($href);
                    if (preg_match('/\.css$/i', $resolvedHref) && !preg_match('/\.min\.css$/i', $resolvedHref)) {
                        $nonMinifiedCss[] = $resolvedHref;
                    }
                }
            }
        }

        $nofollowLinks = [];
        $nofollowCount = 0;
        foreach ($domDocument->getElementsByTagName('a') as $node) {
            $rel = strtolower($node->getAttribute('rel'));
            if (str_contains($rel, 'nofollow')) {
                $nofollowCount++;
                $href = $node->getAttribute('href');
                if ($href !== '') {
                    $nofollowLinks[] = [
                        'url' => $this->resolveUrl($href),
                        'text' => seo_report_clean_tag_text($node->textContent),
                    ];
                }
            }
        }

        $flashContent = [];
        foreach ($domDocument->getElementsByTagName('object') as $node) {
            $type = strtolower($node->getAttribute('type'));
            $data = $node->getAttribute('data');
            if (str_contains($type, 'flash') || str_contains($type, 'shockwave') || preg_match('/\.swf$/i', $data)) {
                $flashContent[] = $this->resolveUrl($data ?: '');
            }
        }
        foreach ($domDocument->getElementsByTagName('embed') as $node) {
            $type = strtolower($node->getAttribute('type'));
            $src = $node->getAttribute('src');
            if (str_contains($type, 'flash') || str_contains($type, 'shockwave') || preg_match('/\.swf$/i', $src)) {
                $flashContent[] = $this->resolveUrl($src ?: '');
            }
        }

        $iframes = [];
        foreach ($domDocument->getElementsByTagName('iframe') as $node) {
            $src = $node->getAttribute('src');
            if ($src !== '') {
                $iframes[] = [
                    'url' => $this->resolveUrl($src),
                    'title' => seo_report_clean_tag_text($node->getAttribute('title')),
                ];
            }
        }

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
        $seoContext = $this->createContext(
            $seoData,
            $response,
            $stats,
            $domDocument,
            $reportResponse,
            $pageText,
            $bodyKeywords,
            $docType,
            $inputUrl,
        );
        foreach ($this->getSeoActions() as $action) {
            $data['results'] = array_merge($data['results'], $action->handle($seoContext));
        }

        $assetHeadCache = [];
        $fetchAssetHead = function (string $assetUrl) use (&$assetHeadCache): ?\Psr\Http\Message\ResponseInterface {
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
            if (preg_match('/^data:/i', $assetUrl)) {
                continue;
            }
            $scheme = parse_url($assetUrl, PHP_URL_SCHEME);
            if (!in_array($scheme, ['http', 'https'], true)) {
                continue;
            }
            $assetResponse = $fetchAssetHead($assetUrl);
            if (!$assetResponse instanceof \Psr\Http\Message\ResponseInterface || $assetResponse->getStatusCode() >= 400) {
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
                if (preg_match('/^data:/i', $imgUrl)) {
                    continue;
                }
                $scheme = parse_url($imgUrl, PHP_URL_SCHEME);
                if (!in_array($scheme, ['http', 'https'], true)) {
                    continue;
                }
                $assetResponse = $fetchAssetHead($imgUrl);
                if (!$assetResponse instanceof \Psr\Http\Message\ResponseInterface || $assetResponse->getStatusCode() >= 400) {
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
            if (preg_match('/^data:/i', $assetUrl)) {
                continue;
            }
            $scheme = parse_url($assetUrl, PHP_URL_SCHEME);
            if (!in_array($scheme, ['http', 'https'], true)) {
                continue;
            }
            $assetResponse = $fetchAssetHead($assetUrl);
            if (!$assetResponse instanceof \Psr\Http\Message\ResponseInterface || $assetResponse->getStatusCode() >= 400) {
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
                    $assetHost = strtolower((string) (parse_url($assetUrl, PHP_URL_HOST) ?? ''));
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
        $performanceContext = $this->createContext(
            $performanceData,
            $response,
            $stats,
            $domDocument,
            $reportResponse,
            $pageText,
            $bodyKeywords,
            $docType,
            $inputUrl,
        );
        foreach ($this->getPerformanceActions() as $action) {
            $data['results'] = array_merge($data['results'], $action->handle($performanceContext));
        }

        $securityData = [
            'http_scheme' => $httpScheme,
            'mixed_content' => $mixedContent,
            'server_header' => $response->getHeader('Server'),
            'unsafe_cross_origin_links' => $unsafeCrossOriginLinks,
            'hsts_header' => $response->getHeader('Strict-Transport-Security'),
            'plaintext_emails' => $plaintextEmails,
        ];
        $securityContext = $this->createContext(
            $securityData,
            $response,
            $stats,
            $domDocument,
            $reportResponse,
            $pageText,
            $bodyKeywords,
            $docType,
            $inputUrl,
        );
        foreach ($this->getSecurityActions() as $action) {
            $data['results'] = array_merge($data['results'], $action->handle($securityContext));
        }

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
        $miscContext = $this->createContext(
            $miscData,
            $response,
            $stats,
            $domDocument,
            $reportResponse,
            $pageText,
            $bodyKeywords,
            $docType,
            $inputUrl,
        );
        foreach ($this->getMiscActions() as $action) {
            $data['results'] = array_merge($data['results'], $action->handle($miscContext));
        }

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
        $technologyContext = $this->createContext(
            $technologyData,
            $response,
            $stats,
            $domDocument,
            $reportResponse,
            $pageText,
            $bodyKeywords,
            $docType,
            $inputUrl,
        );
        foreach ($this->getTechnologyActions() as $action) {
            $data['results'] = array_merge($data['results'], $action->handle($technologyContext));
        }

        return $data;
    }

    /**
     * Fetch SSL certificate info for host (pure PHP, no external APIs).
     * Inspired by phpRank Software SSL Checker (Spatie uses same approach).
     *
     * @return array{valid: bool, valid_from: string, valid_to: string, issuer_cn: string|null, subject_cn: string|null}|null
     */
    private function fetchSslCertificate(string $host): ?array
    {
        $timeout = min($this->config->getRequestTimeout(), 10);
        $ctx = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'verify_peer' => false,
                'SNI_enabled' => true,
            ],
        ]);
        $socket = @stream_socket_client(
            'ssl://' . $host . ':443',
            $errno,
            $errstr,
            $timeout,
            STREAM_CLIENT_CONNECT,
            $ctx
        );
        if ($socket === false) {
            return null;
        }
        fclose($socket);
        $params = stream_context_get_params($ctx);
        $certResource = $params['options']['ssl']['peer_certificate'] ?? null;
        if ($certResource === null) {
            return null;
        }
        $info = openssl_x509_parse($certResource);
        if ($info === false) {
            return null;
        }
        $validFrom = isset($info['validFrom_time_t']) ? date('Y-m-d H:i:s', $info['validFrom_time_t']) : '';
        $validTo = isset($info['validTo_time_t']) ? date('Y-m-d H:i:s', $info['validTo_time_t']) : '';
        $now = time();
        $valid = isset($info['validFrom_time_t'], $info['validTo_time_t'])
            && $now >= $info['validFrom_time_t']
            && $now <= $info['validTo_time_t'];
        $subjectCn = $info['subject']['CN'] ?? null;
        $issuerCn = $info['issuer']['CN'] ?? null;
        return [
            'valid' => $valid,
            'valid_from' => $validFrom,
            'valid_to' => $validTo,
            'issuer_cn' => $issuerCn,
            'subject_cn' => $subjectCn,
        ];
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

    /** @return list<\KalimeroMK\SeoReport\Actions\AnalysisActionInterface> */
    private function getSeoActions(): array
    {
        return [
            new TitleAction(),
            new MetaDescriptionAction(),
            new HeadingsAction(),
            new ContentKeywordsAction(),
            new ImageKeywordsAction(),
            new InPageLinksAction(),
            new LinkUrlReadabilityAction(),
            new NofollowLinksAction(),
            new OpenGraphAction(),
            new TwitterCardsAction(),
            new SeoFriendlyUrlAction(),
            new CanonicalAction(),
            new HreflangAction(),
            new NotFoundAction(),
            new RobotsAction(),
            new NoindexHeaderAction(),
            new LanguageAction(),
            new FaviconAction(),
        ];
    }

    /** @return list<\KalimeroMK\SeoReport\Actions\AnalysisActionInterface> */
    private function getPerformanceActions(): array
    {
        return [
            new CompressionAction(),
            new TimingAction(),
            new PageSizeAction(),
            new HttpRequestsAction(),
            new CacheHeadersAction(),
            new RedirectsAction(),
            new CookieFreeDomainsAction(),
            new EmptySrcHrefAction(),
            new ImageOptimizationAction(),
            new DeferJavascriptAction(),
            new RenderBlockingResourcesAction(),
            new MinificationAction(),
            new DomSizeAction(),
            new DoctypeAction(),
        ];
    }

    /** @return list<\KalimeroMK\SeoReport\Actions\AnalysisActionInterface> */
    private function getSecurityActions(): array
    {
        return [
            new HttpsEncryptionAction(),
            new Http2Action(),
            new MixedContentAction(),
            new ServerSignatureAction(),
            new UnsafeCrossOriginLinksAction(),
            new HstsAction(),
            new PlaintextEmailAction(),
        ];
    }

    /** @return list<\KalimeroMK\SeoReport\Actions\AnalysisActionInterface> */
    private function getMiscActions(): array
    {
        return [
            new StructuredDataAction(),
            new MetaViewportAction(),
            new CharsetAction(),
            new SitemapAction(),
            new SocialLinksAction(),
            new ContentLengthAction(),
            new TextHtmlRatioAction(),
            new InlineCssAction(),
            new DeprecatedHtmlTagsAction(),
            new LlmsTxtAction(),
            new FlashContentAction(),
            new IframesAction(),
        ];
    }

    /** @return list<\KalimeroMK\SeoReport\Actions\AnalysisActionInterface> */
    private function getTechnologyActions(): array
    {
        return [
            new ServerIpAction(),
            new DnsServersAction(),
            new DmarcRecordAction(),
            new SpfRecordAction(),
            new SslCertificateAction(),
            new ReverseDnsAction(),
            new AnalyticsAction(),
            new TechnologyDetectionAction(),
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $stats
     * @param list<string> $bodyKeywords
     */
    private function createContext(
        array $data,
        \Psr\Http\Message\ResponseInterface $response,
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


    private function fetchAssetResponse(string $url): ?\Psr\Http\Message\ResponseInterface
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

    private function formatRobotsRule(string $value): string
    {
        $before = ['*' => '_ASTERISK_', '$' => '_DOLLAR_'];
        $after = ['_ASTERISK_' => '.*', '_DOLLAR_' => '$'];
        $quoted = preg_quote(str_replace(array_keys($before), array_values($before), $value), '/');
        return '/^' . str_replace(array_keys($after), array_values($after), $quoted) . '/';
    }
}
