<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Extractors;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\RequestException;
use KalimeroMK\SeoReport\Config\SeoReportConfig;

final class RobotsAnd404Extractor
{
    private string|false|null $cachedNotFoundPage = null;

    private ?\Psr\Http\Message\ResponseInterface $cachedRobotsRequest = null;

    /**
     * @return string|false
     */
    public function getNotFoundPage(string $baseUrl, SeoReportConfig $config, ?HttpClient $httpClient = null): string|false
    {
        if ($this->cachedNotFoundPage !== null) {
            return $this->cachedNotFoundPage;
        }

        $notFoundPage = false;
        $notFoundUrl = parse_url($baseUrl, PHP_URL_SCHEME) . '://' . parse_url($baseUrl, PHP_URL_HOST) . '/404-' . md5(uniqid((string) mt_rand(), true));
        try {
            $hc = $httpClient ?? new HttpClient();
            $proxy = $config->getRequestProxy();
            $proxyOpt = $proxy !== null ? ['http' => $proxy, 'https' => $proxy] : [];
            $hc->get($notFoundUrl, [
                'version' => $config->getRequestHttpVersion(),
                'proxy' => $proxyOpt,
                'timeout' => $config->getRequestTimeout(),
                'headers' => ['User-Agent' => $config->getRequestUserAgent()],
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
        return $notFoundPage;
    }

    /**
     * @return array{robots: bool, rules_failed: array<int, string>, sitemaps: array<int, string>}
     */
    public function getRobotsData(string $baseUrl, SeoReportConfig $config, ?HttpClient $httpClient = null): array
    {
        $sitemaps = [];
        $robotsRulesFailed = [];
        $robots = true;

        if (!$this->cachedRobotsRequest instanceof \Psr\Http\Message\ResponseInterface) {
            $robotsUrl = parse_url($baseUrl, PHP_URL_SCHEME) . '://' . parse_url($baseUrl, PHP_URL_HOST) . '/robots.txt';
            try {
                $hc = $httpClient ?? new HttpClient();
                $proxy = $config->getRequestProxy();
                $proxyOpt = $proxy !== null ? ['http' => $proxy, 'https' => $proxy] : [];
                $this->cachedRobotsRequest = $hc->get($robotsUrl, [
                    'version' => $config->getRequestHttpVersion(),
                    'proxy' => $proxyOpt,
                    'timeout' => $config->getRequestTimeout(),
                    'headers' => ['User-Agent' => $config->getRequestUserAgent()],
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
            if ($directive === 'disallow' && $value !== '' && preg_match($this->formatRobotsRule($value), $baseUrl)) {
                $robotsRulesFailed[] = $value;
                $robots = false;
            }
            if ($directive === 'sitemap' && $value !== '') {
                $sitemaps[] = $value;
            }
        }

        return [
            'robots' => $robots,
            'rules_failed' => $robotsRulesFailed,
            'sitemaps' => $sitemaps,
        ];
    }

    private function formatRobotsRule(string $value): string
    {
        $before = ['*' => '_ASTERISK_', '$' => '_DOLLAR_'];
        $after = ['_ASTERISK_' => '.*', '_DOLLAR_' => '$'];
        $quoted = preg_quote(str_replace(array_keys($before), array_values($before), $value), '/');
        return '/^' . str_replace(array_keys($after), array_values($after), $quoted) . '/';
    }
}
