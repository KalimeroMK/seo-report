<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\TransferStats;
use KalimeroMK\SeoReport\Config\SeoReportConfig;
use KalimeroMK\SeoReport\Dto\AnalysisResult;

final class SeoAnalyzer
{
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
        $maxLinks = $maxLinks ?? $this->config->getSitemapLinks();
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
                    'Accept-Encoding' => 'gzip, deflate',
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

        $reportRequestStats = $reportRequestStats ?? [];
        if (isset($reportRequestStats['url'])) {
            $reportRequestStats['url'] = (string) $reportRequestStats['url'];
        }
        $reportRequestStats['total_time'] = (float) ($reportRequestStats['total_time'] ?? 0);
        $reportRequestStats['size_download'] = (float) ($reportRequestStats['size_download'] ?? 0);

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
        $bodyKeywords = array_filter(explode(' ', (string) preg_replace('/[^\w]/ui', ' ', mb_strtolower($pageText))));
        $docType = $domDocument->doctype !== null ? $domDocument->doctype->nodeName : '';

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

        $titleKeywords = array_filter(explode(' ', (string) preg_replace('/[^\w]/ui', ' ', mb_strtolower((string) $title))));

        $metaDescriptionKeywords = array_filter(explode(' ', (string) preg_replace('/[^\w]/ui', ' ', mb_strtolower((string) $metaDescription))));
        $headingTexts = [];
        foreach ($headings as $level => $texts) {
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

        if (!isset($this->cachedNotFoundPage)) {
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
                if ($response !== null && $response->getStatusCode() === 404) {
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
        if (!isset($this->cachedRobotsRequest)) {
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
        $robotsRules = $robotsRequest !== null
            ? preg_split('/\n|\r/', $robotsRequest->getBody()->getContents(), -1, PREG_SPLIT_NO_EMPTY) ?: []
            : [];
        foreach ($robotsRules as $robotsRule) {
            $rule = explode(':', $robotsRule, 2);
            $directive = trim(strtolower($rule[0]));
            $value = trim((string) ($rule[1] ?? ''));
            if ($directive === 'disallow' && $value !== '' && preg_match($this->formatRobotsRule($value), $this->url)) {
                $robotsRulesFailed[] = $value;
                $robots = false;
            }
            if ($directive === 'sitemap' && $value !== '') {
                $sitemaps[] = $value;
            }
        }

        $noIndex = null;
        foreach ($domDocument->getElementsByTagName('head') as $headNode) {
            foreach ($headNode->getElementsByTagName('meta') as $node) {
                if ((strtolower($node->getAttribute('name')) === 'robots' || strtolower($node->getAttribute('name')) === 'googlebot') && preg_match('/\bnoindex\b/', $node->getAttribute('content'))) {
                    $noIndex = $node->getAttribute('content');
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
        $hreflang = [];
        foreach ($domDocument->getElementsByTagName('head') as $headNode) {
            foreach ($headNode->getElementsByTagName('link') as $node) {
                $rel = strtolower($node->getAttribute('rel'));
                if ($rel === 'canonical' && $node->getAttribute('href') !== '') {
                    $canonicalTag = $this->resolveUrl($node->getAttribute('href'));
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
            if (!$this->isInternalUrl($this->resolveUrl($node->getAttribute('href'))) && $node->getAttribute('target') === '_blank') {
                if (!str_contains(strtolower($node->getAttribute('rel')), 'noopener') && !str_contains(strtolower($node->getAttribute('rel')), 'nofollow')) {
                    $unsafeCrossOriginLinks[] = $this->resolveUrl($node->getAttribute('href'));
                }
            }
        }

        preg_match_all('/([a-zA-Z0-9._-]+@[a-zA-Z0-9._-]+\.[a-zA-Z0-9_-]+)/i', $reportResponse, $plaintextEmailsRaw, PREG_UNMATCHED_AS_NULL);
        $rawEmails = $plaintextEmailsRaw[0] ?? [];
        $plaintextEmails = array_values(array_filter(
            is_array($rawEmails) ? $rawEmails : [],
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

        $imageFormatsConfig = preg_split('/\n|\r/', $this->config->getReportLimitImageFormats(), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $allowedExtensions = array_map('strtolower', $imageFormatsConfig);
        $imageFormats = [];
        foreach ($domDocument->getElementsByTagName('img') as $node) {
            if (empty($node->getAttribute('src'))) {
                continue;
            }
            $imgUrl = $this->resolveUrl($node->getAttribute('src'));
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

        $data['results']['title'] = ['passed' => true, 'importance' => 'high', 'value' => $title];
        if (!$title) {
            $data['results']['title']['passed'] = false;
            $data['results']['title']['errors'] = ['missing' => null];
        }
        if (mb_strlen((string) $title) < $this->config->getReportLimitMinTitle() || mb_strlen((string) $title) > $this->config->getReportLimitMaxTitle()) {
            $data['results']['title']['passed'] = false;
            $data['results']['title']['errors'] = ['length' => ['min' => $this->config->getReportLimitMinTitle(), 'max' => $this->config->getReportLimitMaxTitle()]];
        }
        if ($titleTagsCount > 1) {
            $data['results']['title']['passed'] = false;
            $data['results']['title']['errors'] = ['too_many' => null];
        }

        $titleLength = mb_strlen((string) $title);
        $data['results']['title_optimal_length'] = ['passed' => true, 'importance' => 'low', 'value' => $titleLength];
        if ($titleLength < 50 || $titleLength > 60) {
            $data['results']['title_optimal_length']['passed'] = false;
            $data['results']['title_optimal_length']['errors'] = ['not_optimal' => ['optimal' => '50-60', 'current' => $titleLength]];
        }

        $data['results']['meta_description'] = ['passed' => true, 'importance' => 'high', 'value' => $metaDescription];
        if (!$metaDescription) {
            $data['results']['meta_description']['passed'] = false;
            $data['results']['meta_description']['errors'] = ['missing' => null];
        }

        $metaDescriptionLength = mb_strlen((string) $metaDescription);
        $data['results']['meta_description_optimal_length'] = ['passed' => true, 'importance' => 'low', 'value' => $metaDescriptionLength];
        if ($metaDescription && ($metaDescriptionLength < 120 || $metaDescriptionLength > 160)) {
            $data['results']['meta_description_optimal_length']['passed'] = false;
            $data['results']['meta_description_optimal_length']['errors'] = ['not_optimal' => ['optimal' => '120-160', 'current' => $metaDescriptionLength]];
        }

        $data['results']['headings'] = ['passed' => true, 'importance' => 'high', 'value' => $headings];
        if (!isset($headings['h1'])) {
            $data['results']['headings']['passed'] = false;
            $data['results']['headings']['errors'] = ['missing' => null];
        }
        if (isset($headings['h1']) && count($headings['h1']) > 1) {
            $data['results']['headings']['passed'] = false;
            $data['results']['headings']['errors'] = ['too_many' => null];
        }
        if (isset($headings['h1'][0]) && $headings['h1'][0] == $title) {
            $data['results']['headings']['passed'] = false;
            $data['results']['headings']['errors'] = ['duplicate' => null];
        }

        $data['results']['content_keywords'] = ['passed' => true, 'importance' => 'high', 'value' => array_intersect($titleKeywords, $bodyKeywords)];
        if (array_intersect($titleKeywords, $bodyKeywords) === []) {
            $data['results']['content_keywords']['passed'] = false;
            $data['results']['content_keywords']['errors'] = ['missing' => $titleKeywords];
        }

        $data['results']['keyword_consistency'] = ['passed' => true, 'importance' => 'medium', 'value' => $keywordConsistency];
        if ($titleKeywords !== []) {
            $keywordConsistencyErrors = [];
            if ($keywordsInMeta === []) {
                $data['results']['keyword_consistency']['passed'] = false;
                $keywordConsistencyErrors['no_title_keywords_in_meta'] = $keywordsMissingInMeta;
            }
            if ($keywordsInHeadings === []) {
                $data['results']['keyword_consistency']['passed'] = false;
                $keywordConsistencyErrors['no_title_keywords_in_headings'] = $keywordsMissingInHeadings;
            }
            if ($keywordConsistencyErrors !== []) {
                $data['results']['keyword_consistency']['errors'] = $keywordConsistencyErrors;
            }
        }

        $data['results']['image_keywords'] = ['passed' => true, 'importance' => 'high', 'value' => null];
        if ($imageAlts !== []) {
            $data['results']['image_keywords']['passed'] = false;
            $data['results']['image_keywords']['errors'] = ['missing' => $imageAlts];
        }

        $data['results']['image_format'] = ['passed' => true, 'importance' => 'medium', 'value' => $imageFormatsConfig];
        if ($imageFormats !== []) {
            $data['results']['image_format']['passed'] = false;
            $data['results']['image_format']['errors'] = ['bad_format' => $imageFormats];
        }

        $data['results']['in_page_links'] = ['passed' => true, 'importance' => 'medium', 'value' => $pageLinks];
        if (array_sum(array_map('count', $pageLinks)) > $this->config->getReportLimitMaxLinks()) {
            $data['results']['in_page_links']['passed'] = false;
            $data['results']['in_page_links']['errors'] = ['too_many' => ['max' => $this->config->getReportLimitMaxLinks()]];
        }

        $data['results']['link_url_readability'] = ['passed' => true, 'importance' => 'low', 'value' => null];
        if ($unfriendlyLinkUrls !== []) {
            $data['results']['link_url_readability']['passed'] = false;
            $data['results']['link_url_readability']['errors'] = ['unfriendly_urls' => $unfriendlyLinkUrls];
        }

        $data['results']['load_time'] = ['passed' => true, 'importance' => 'medium', 'value' => $stats['total_time']];
        if ($stats['total_time'] > $this->config->getReportLimitLoadTime()) {
            $data['results']['load_time']['passed'] = false;
            $data['results']['load_time']['errors'] = ['too_slow' => ['max' => $this->config->getReportLimitLoadTime()]];
        }

        $data['results']['page_size'] = ['passed' => true, 'importance' => 'medium', 'value' => $stats['size_download']];
        if ($stats['size_download'] > $this->config->getReportLimitPageSize()) {
            $data['results']['page_size']['passed'] = false;
            $data['results']['page_size']['errors'] = ['too_large' => ['max' => $this->config->getReportLimitPageSize()]];
        }

        $data['results']['http_requests'] = ['passed' => true, 'importance' => 'medium', 'value' => $httpRequests];
        if (array_sum(array_map('count', $httpRequests)) > $this->config->getReportLimitHttpRequests()) {
            $data['results']['http_requests']['passed'] = false;
            $data['results']['http_requests']['errors'] = ['too_many' => ['max' => $this->config->getReportLimitHttpRequests()]];
        }

        $data['results']['defer_javascript'] = ['passed' => true, 'importance' => 'low', 'value' => null];
        if ($deferJavaScript !== []) {
            $data['results']['defer_javascript']['passed'] = false;
            $data['results']['defer_javascript']['errors'] = ['missing' => $deferJavaScript];
        }

        $data['results']['dom_size'] = ['passed' => true, 'importance' => 'low', 'value' => $domNodesCount];
        if ($domNodesCount > $this->config->getReportLimitMaxDomNodes()) {
            $data['results']['dom_size']['passed'] = false;
            $data['results']['dom_size']['errors'] = ['too_many' => ['max' => $this->config->getReportLimitMaxDomNodes()]];
        }

        $data['results']['doctype'] = ['passed' => true, 'importance' => 'medium', 'value' => $docType];
        if ($docType === '' || $docType === '0') {
            $data['results']['doctype']['passed'] = false;
            $data['results']['doctype']['errors'] = ['missing' => null];
        }

        $encHeader = $response->getHeader('Content-Encoding');
        $data['results']['text_compression'] = ['passed' => true, 'importance' => 'high', 'value' => $stats['size_download']];
        if (!in_array('gzip', $encHeader, true)) {
            $data['results']['text_compression']['passed'] = false;
            $data['results']['text_compression']['errors'] = ['missing' => null];
        }

        $data['results']['structured_data'] = ['passed' => true, 'importance' => 'medium', 'value' => $structuredData];
        if ($structuredData === []) {
            $data['results']['structured_data']['passed'] = false;
            $data['results']['structured_data']['errors'] = ['missing' => null];
        }

        $data['results']['meta_viewport'] = ['passed' => true, 'importance' => 'medium', 'value' => $metaViewport];
        if (!$metaViewport) {
            $data['results']['meta_viewport']['passed'] = false;
            $data['results']['meta_viewport']['errors'] = ['missing' => null];
        }

        $data['results']['https_encryption'] = ['passed' => true, 'importance' => 'high', 'value' => $stats['url'] ?? $this->url];
        if (strtolower((string) $httpScheme) !== 'https') {
            $data['results']['https_encryption']['passed'] = false;
            $data['results']['https_encryption']['errors'] = ['missing' => 'https'];
        }

        $data['results']['seo_friendly_url'] = ['passed' => true, 'importance' => 'high', 'value' => $stats['url'] ?? $this->url];
        if (preg_match('/[\?\=\_\%\,\ ]/ui', (string) ($stats['url'] ?? $this->url))) {
            $data['results']['seo_friendly_url']['passed'] = false;
            $data['results']['seo_friendly_url']['errors'] = ['bad_format' => null];
        }
        if (array_filter($titleKeywords, fn ($k) => str_contains(mb_strtolower($this->url), mb_strtolower((string) $k))) === []) {
            $data['results']['seo_friendly_url']['passed'] = false;
            $data['results']['seo_friendly_url']['errors'] = ['missing' => null];
        }

        $data['results']['language'] = ['passed' => true, 'importance' => 'medium', 'value' => $language];
        if (!$language) {
            $data['results']['language']['passed'] = false;
            $data['results']['language']['errors'] = ['missing' => null];
        }

        $data['results']['favicon'] = ['passed' => true, 'importance' => 'medium', 'value' => $favicon];
        if (!$favicon) {
            $data['results']['favicon']['passed'] = false;
            $data['results']['favicon']['errors'] = ['missing' => null];
        }

        $data['results']['content_length'] = ['passed' => true, 'importance' => 'low', 'value' => count($bodyKeywords)];
        if (count($bodyKeywords) < $this->config->getReportLimitMinWords()) {
            $data['results']['content_length']['passed'] = false;
            $data['results']['content_length']['errors'] = ['too_few' => ['min' => $this->config->getReportLimitMinWords()]];
        }

        $data['results']['text_html_ratio'] = ['passed' => true, 'importance' => 'low', 'value' => $textRatio];
        if ($textRatio < $this->config->getReportLimitMinTextRatio()) {
            $data['results']['text_html_ratio']['passed'] = false;
            $data['results']['text_html_ratio']['errors'] = ['too_small' => ['min' => $this->config->getReportLimitMinTextRatio()]];
        }

        $data['results']['charset'] = ['passed' => true, 'importance' => 'medium', 'value' => $charset];
        if (!$charset) {
            $data['results']['charset']['passed'] = false;
            $data['results']['charset']['errors'] = ['missing' => null];
        }

        $data['results']['deprecated_html_tags'] = ['passed' => true, 'importance' => 'low', 'value' => null];
        if (count($deprecatedHtmlTags) > 1) {
            $data['results']['deprecated_html_tags']['passed'] = false;
            $data['results']['deprecated_html_tags']['errors'] = ['bad_tags' => $deprecatedHtmlTags];
        }

        $data['results']['404_page'] = ['passed' => true, 'importance' => 'high', 'value' => $notFoundPage];
        if (!$notFoundPage) {
            $data['results']['404_page']['passed'] = false;
            $data['results']['404_page']['errors'] = ['missing' => null];
        }

        $data['results']['noindex'] = ['passed' => true, 'importance' => 'high', 'value' => $noIndex];
        if ($noIndex) {
            $data['results']['noindex']['passed'] = false;
            $data['results']['noindex']['errors'] = ['missing' => null];
        }

        $data['results']['robots'] = ['passed' => true, 'importance' => 'high', 'value' => null];
        if (!$robots) {
            $data['results']['robots']['passed'] = false;
            $data['results']['robots']['errors'] = ['failed' => $robotsRulesFailed];
        }

        $data['results']['sitemap'] = ['passed' => true, 'importance' => 'low', 'value' => $sitemaps];
        if ($sitemaps === []) {
            $data['results']['sitemap']['passed'] = false;
            $data['results']['sitemap']['errors'] = ['failed' => null];
        }

        $data['results']['mixed_content'] = ['passed' => true, 'importance' => 'medium', 'value' => null];
        if ($mixedContent !== []) {
            $data['results']['mixed_content']['passed'] = false;
            $data['results']['mixed_content']['errors'] = ['failed' => $mixedContent];
        }

        $serverHeader = $response->getHeader('Server');
        $data['results']['server_signature'] = ['passed' => true, 'importance' => 'medium', 'value' => $serverHeader];
        if ($serverHeader !== []) {
            $data['results']['server_signature']['passed'] = false;
            $data['results']['server_signature']['errors'] = ['failed' => null];
        }

        if ($this->config->getRequestHttpVersion() === '2') {
            $data['results']['http2'] = ['passed' => true, 'importance' => 'medium', 'value' => $response->getProtocolVersion()];
            if ($response->getProtocolVersion() !== '2') {
                $data['results']['http2']['passed'] = false;
                $data['results']['http2']['errors'] = ['failed' => null];
            }
        }

        $htstHeader = $response->getHeader('Strict-Transport-Security');
        $data['results']['htst'] = ['passed' => true, 'importance' => 'low', 'value' => $htstHeader];
        if ($htstHeader === []) {
            $data['results']['htst']['passed'] = false;
            $data['results']['htst']['errors'] = ['missing' => null];
        }

        $data['results']['unsafe_cross_origin_links'] = ['passed' => true, 'importance' => 'medium', 'value' => null];
        if ($unsafeCrossOriginLinks !== []) {
            $data['results']['unsafe_cross_origin_links']['passed'] = false;
            $data['results']['unsafe_cross_origin_links']['errors'] = ['failed' => $unsafeCrossOriginLinks];
        }

        $data['results']['plaintext_email'] = ['passed' => true, 'importance' => 'low', 'value' => null];
        if ($plaintextEmails !== []) {
            $data['results']['plaintext_email']['passed'] = false;
            $data['results']['plaintext_email']['errors'] = ['failed' => $plaintextEmails];
        }

        $data['results']['social'] = ['passed' => true, 'importance' => 'low', 'value' => $social];
        if ($social === []) {
            $data['results']['social']['passed'] = false;
            $data['results']['social']['errors'] = ['missing' => null];
        }

        $data['results']['inline_css'] = ['passed' => true, 'importance' => 'low', 'value' => null];
        if (count($inlineCss) > 1) {
            $data['results']['inline_css']['passed'] = false;
            $data['results']['inline_css']['errors'] = ['failed' => $inlineCss];
        }

        $data['results']['canonical_tag'] = ['passed' => true, 'importance' => 'medium', 'value' => $canonicalTag];
        if ($canonicalTag === null) {
            $data['results']['canonical_tag']['passed'] = false;
            $data['results']['canonical_tag']['errors'] = ['missing' => null];
        }

        $data['results']['hreflang'] = ['passed' => true, 'importance' => 'low', 'value' => $hreflang];
        if ($hreflang === []) {
            $data['results']['hreflang']['passed'] = false;
            $data['results']['hreflang']['errors'] = ['missing' => null];
        }

        $data['results']['noindex_header'] = ['passed' => true, 'importance' => 'high', 'value' => $noindexHeaderValue];
        if ($noindexHeaderValue !== null && preg_match('/\bnoindex\b/i', $noindexHeaderValue)) {
            $data['results']['noindex_header']['passed'] = false;
            $data['results']['noindex_header']['errors'] = ['noindex' => $noindexHeaderValue];
        }

        $data['results']['server_ip'] = ['passed' => true, 'importance' => 'low', 'value' => $serverIp];
        if ($serverIp === null && $hostStr !== '') {
            $data['results']['server_ip']['passed'] = false;
            $data['results']['server_ip']['errors'] = ['unresolved' => null];
        }

        $data['results']['dns_servers'] = ['passed' => true, 'importance' => 'low', 'value' => $dnsServers];
        if ($dnsServers === [] && $hostStr !== '') {
            $data['results']['dns_servers']['passed'] = false;
            $data['results']['dns_servers']['errors'] = ['missing' => null];
        }

        $data['results']['dmarc_record'] = ['passed' => true, 'importance' => 'low', 'value' => $dmarcRecord];
        if ($dmarcRecord === null && $hostStr !== '') {
            $data['results']['dmarc_record']['passed'] = false;
            $data['results']['dmarc_record']['errors'] = ['missing' => null];
        }

        $data['results']['spf_record'] = ['passed' => true, 'importance' => 'low', 'value' => $spfRecord];
        if ($spfRecord === null && $hostStr !== '') {
            $data['results']['spf_record']['passed'] = false;
            $data['results']['spf_record']['errors'] = ['missing' => null];
        }

        $data['results']['llms_txt'] = ['passed' => true, 'importance' => 'low', 'value' => $llmsTxtUrl];
        if ($llmsTxtUrl === null) {
            $data['results']['llms_txt']['passed'] = false;
            $data['results']['llms_txt']['errors'] = ['missing' => null];
        }

        $data['results']['analytics'] = ['passed' => true, 'importance' => 'low', 'value' => array_keys($analyticsDetected)];

        $data['results']['technology_detection'] = ['passed' => true, 'importance' => 'low', 'value' => array_keys($technologyDetected)];

        $data['results']['minification'] = ['passed' => true, 'importance' => 'low', 'value' => ['js' => count($nonMinifiedJs), 'css' => count($nonMinifiedCss)]];
        if ($nonMinifiedJs !== [] || $nonMinifiedCss !== []) {
            $data['results']['minification']['passed'] = false;
            $data['results']['minification']['errors'] = ['not_minified' => ['js' => $nonMinifiedJs, 'css' => $nonMinifiedCss]];
        }

        $data['results']['nofollow_links'] = ['passed' => true, 'importance' => 'low', 'value' => $nofollowCount];
        if ($nofollowCount > 0) {
            $data['results']['nofollow_links']['passed'] = false;
            $data['results']['nofollow_links']['errors'] = ['found' => $nofollowLinks];
        }

        $data['results']['flash_content'] = ['passed' => true, 'importance' => 'low', 'value' => null];
        if ($flashContent !== []) {
            $data['results']['flash_content']['passed'] = false;
            $data['results']['flash_content']['errors'] = ['found' => $flashContent];
        }

        $data['results']['iframes'] = ['passed' => true, 'importance' => 'low', 'value' => $iframes];

        $data['results']['ssl_certificate'] = ['passed' => true, 'importance' => 'medium', 'value' => $sslCertificate];
        if ($sslCertificate === null && $hostStr !== '' && str_starts_with($baseUrl, 'https://')) {
            $data['results']['ssl_certificate']['passed'] = false;
            $data['results']['ssl_certificate']['errors'] = ['unavailable' => null];
        } elseif ($sslCertificate !== null && !($sslCertificate['valid'] ?? false)) {
            $data['results']['ssl_certificate']['passed'] = false;
            $data['results']['ssl_certificate']['errors'] = ['invalid_or_expired' => null];
        }

        $data['results']['reverse_dns'] = ['passed' => true, 'importance' => 'low', 'value' => $reverseDns];

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
        $timeout = (int) min($this->config->getRequestTimeout(), 10);
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

    private function isInternalUrl(string $url): bool
    {
        $baseUrl = $this->url ?? '';
        $baseHost = parse_url($baseUrl, PHP_URL_HOST);
        $urlHost = parse_url($url, PHP_URL_HOST);
        return $baseHost !== false && $urlHost !== false && str_starts_with((string) $urlHost, (string) $baseHost);
    }

    private function resolveUrl(string $url): string
    {
        $baseUrl = $this->url ?? '';
        $url = str_replace(['\\?', '\\&', '\\#', '\\~', '\\;'], ['?', '&', '#', '~', ';'], $url);
        if (mb_strpos($url, '#') !== false) {
            $url = mb_substr($url, 0, (int) mb_strpos($url, '#'));
        }
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }
        $scheme = parse_url($baseUrl, PHP_URL_SCHEME);
        $host = parse_url($baseUrl, PHP_URL_HOST);
        if (str_starts_with($url, '//')) {
            return ($scheme ?? 'https') . '://' . trim($url, '/');
        }
        if (str_starts_with($url, '/')) {
            return rtrim(($scheme ?? 'https') . '://' . ($host ?? ''), '/') . '/' . ltrim($url, '/');
        }
        if (str_starts_with($url, 'data:image') || str_starts_with($url, 'tel') || str_starts_with($url, 'mailto')) {
            return $url;
        }
        return rtrim(($scheme ?? 'https') . '://' . ($host ?? ''), '/') . '/' . ltrim($url, '/');
    }

    private function formatRobotsRule(string $value): string
    {
        $before = ['*' => '_ASTERISK_', '$' => '_DOLLAR_'];
        $after = ['_ASTERISK_' => '.*', '_DOLLAR_' => '$'];
        $quoted = preg_quote(str_replace(array_keys($before), array_values($before), $value), '/');
        return '/^' . str_replace(array_keys($after), array_values($after), $quoted) . '/';
    }
}
