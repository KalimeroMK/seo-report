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
        $notFoundUrl = $this->buildNotFoundUrl($baseUrl);
        
        if ($notFoundUrl === null) {
            $this->cachedNotFoundPage = false;
            return false;
        }
        
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
     * Build the not found test URL
     */
    private function buildNotFoundUrl(string $baseUrl): ?string
    {
        $parsed = parse_url($baseUrl);
        
        if ($parsed === false) {
            return null;
        }
        
        $scheme = $parsed['scheme'] ?? 'https';
        $host = $parsed['host'] ?? null;
        
        if ($host === null) {
            return null;
        }
        
        $randomPath = '/404-' . md5(uniqid((string) mt_rand(), true));
        
        return $scheme . '://' . $host . $randomPath;
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
            $robotsUrl = $this->buildRobotsUrl($baseUrl);
            
            if ($robotsUrl === null) {
                return [
                    'robots' => false,
                    'rules_failed' => ['Failed to parse base URL'],
                    'sitemaps' => [],
                ];
            }
            
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
            
            // Skip comments and empty lines
            if ($directive === '' || str_starts_with($directive, '#')) {
                continue;
            }
            
            if ($directive === 'disallow' && $value !== '' && $this->matchesRobotsRule($value, $baseUrl)) {
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

    /**
     * Build the robots.txt URL
     */
    private function buildRobotsUrl(string $baseUrl): ?string
    {
        $parsed = parse_url($baseUrl);
        
        if ($parsed === false) {
            return null;
        }
        
        $scheme = $parsed['scheme'] ?? 'https';
        $host = $parsed['host'] ?? null;
        
        if ($host === null) {
            return null;
        }
        
        return $scheme . '://' . $host . '/robots.txt';
    }

    /**
     * Check if URL matches a robots.txt rule
     */
    private function matchesRobotsRule(string $rule, string $url): bool
    {
        $parsed = parse_url($url);
        $path = $parsed['path'] ?? '/';
        
        $pattern = $this->formatRobotsRule($rule);
        
        return preg_match($pattern, $path) === 1;
    }

    /**
     * Format robots.txt rule into regex pattern
     */
    private function formatRobotsRule(string $value): string
    {
        $before = ['*' => '_ASTERISK_', '$' => '_DOLLAR_'];
        $after = ['_ASTERISK_' => '.*', '_DOLLAR_' => '$'];
        $quoted = preg_quote(str_replace(array_keys($before), array_values($before), $value), '/');
        return '/^' . str_replace(array_keys($after), array_values($after), $quoted) . '/';
    }
}
