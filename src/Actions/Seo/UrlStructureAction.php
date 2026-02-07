<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Actions\Seo;

use KalimeroMK\SeoReport\Actions\AnalysisActionInterface;
use KalimeroMK\SeoReport\Actions\AnalysisContext;

/**
 * Analyze URL structure for SEO optimization.
 * Checks length, depth, parameters, and best practices.
 */
final class UrlStructureAction implements AnalysisActionInterface
{
    public function handle(AnalysisContext $context): array
    {
        $currentUrl = $context->getUrl();
        $canonicalTag = $context->getData('canonical_tag');

        $result = [];

        $result['url_length'] = $this->checkUrlLength($currentUrl);
        $result['url_depth'] = $this->checkUrlDepth($currentUrl);
        $result['url_parameters'] = $this->checkQueryParameters($currentUrl);
        $result['url_format'] = $this->checkUrlFormat($currentUrl);
        $result['trailing_slash'] = $this->checkTrailingSlash($currentUrl, $canonicalTag);
        $result['url_case'] = $this->checkUrlCase($currentUrl);
        $result['url_encoding'] = $this->checkUrlEncoding($currentUrl);

        return $result;
    }

    /**
     * Check URL length
     *
     * @return array<string, mixed>
     */
    private function checkUrlLength(string $url): array
    {
        $length = strlen($url);
        $maxRecommended = 115;
        $maxAcceptable = 2048;

        $result = [
            'passed' => $length <= $maxRecommended,
            'importance' => 'high',
            'value' => [
                'length' => $length,
                'max_recommended' => $maxRecommended,
            ],
        ];

        if ($length > $maxAcceptable) {
            $result['passed'] = false;
            $result['errors'] = [
                'message' => 'URL exceeds maximum length',
                'length' => $length,
                'max_acceptable' => $maxAcceptable,
                'recommendation' => 'Shorten URL significantly - may cause browser issues',
            ];
        } elseif ($length > $maxRecommended) {
            $result['errors'] = [
                'message' => 'URL too long for optimal display in search results',
                'length' => $length,
                'recommendation' => 'Keep URLs under 115 characters for better SERP display',
            ];
        }

        return $result;
    }

    /**
     * Check URL depth (number of path segments)
     *
     * @return array<string, mixed>
     */
    private function checkUrlDepth(string $url): array
    {
        $parsed = parse_url($url);
        $path = $parsed['path'] ?? '/';

        $segments = array_filter(explode('/', trim($path, '/')));
        $depth = count($segments);

        $maxRecommended = 3;

        $result = [
            'passed' => $depth <= $maxRecommended,
            'importance' => 'medium',
            'value' => [
                'depth' => $depth,
                'segments' => array_values($segments),
                'max_recommended' => $maxRecommended,
            ],
        ];

        if ($depth > $maxRecommended) {
            $result['errors'] = [
                'message' => 'URL depth too high',
                'depth' => $depth,
                'recommendation' => 'Keep URL depth to 3 or fewer levels from root',
            ];
        }

        return $result;
    }

    /**
     * Check for query parameters
     *
     * @return array<string, mixed>
     */
    private function checkQueryParameters(string $url): array
    {
        $parsed = parse_url($url);
        $query = $parsed['query'] ?? '';

        if (empty($query)) {
            return [
                'passed' => true,
                'importance' => 'low',
                'value' => [
                    'has_parameters' => false,
                    'parameter_count' => 0,
                ],
            ];
        }

        parse_str($query, $params);
        $paramCount = count($params);

        $problematicParams = [
            'sessionid', 'session_id', 'phpsessid', 'sid',
            'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
            'fbclid', 'gclid', 'ref', 'source',
        ];

        $foundProblematic = [];
        foreach ($problematicParams as $param) {
            if (isset($params[$param])) {
                $foundProblematic[] = $param;
            }
        }

        $result = [
            'passed' => $paramCount <= 2 && empty($foundProblematic),
            'importance' => 'medium',
            'value' => [
                'has_parameters' => true,
                'parameter_count' => $paramCount,
                'parameters' => array_keys($params),
            ],
        ];

        if (!empty($foundProblematic)) {
            $result['passed'] = false;
            $result['errors'] = [
                'message' => 'URL contains problematic query parameters',
                'parameters' => $foundProblematic,
                'recommendation' => 'Remove session IDs and tracking parameters from canonical URLs',
            ];
        } elseif ($paramCount > 2) {
            $result['errors'] = [
                'message' => 'Too many query parameters',
                'count' => $paramCount,
                'recommendation' => 'Minimize query parameters or use URL rewriting',
            ];
        }

        return $result;
    }

    /**
     * Check URL format best practices
     *
     * @return array<string, mixed>
     */
    private function checkUrlFormat(string $url): array
    {
        $parsed = parse_url($url);
        $path = $parsed['path'] ?? '';

        $issues = [];

        if (str_contains($path, '_')) {
            $issues[] = 'Contains underscores (use hyphens instead)';
        }

        if (preg_match('/[A-Z]/', $path)) {
            $issues[] = 'Contains uppercase letters';
        }

        if (str_contains($path, ' ') || str_contains($path, '%20')) {
            $issues[] = 'Contains spaces';
        }

        if (preg_match('/\/\/+/', $path)) {
            $issues[] = 'Multiple consecutive slashes';
        }

        $badExtensions = ['.php', '.html', '.htm', '.aspx', '.jsp'];
        foreach ($badExtensions as $ext) {
            if (str_contains(strtolower($path), $ext)) {
                $issues[] = "Contains {$ext} extension";
                break;
            }
        }

        if (preg_match('/[^a-z0-9\/\-\.]/', strtolower($path))) {
            $specialChars = [];
            preg_match_all('/[^a-z0-9\/\-\.]/', $path, $matches);
            $specialChars = array_unique($matches[0]);
            if (!empty($specialChars)) {
                $issues[] = 'Contains special characters: ' . implode('', $specialChars);
            }
        }

        $result = [
            'passed' => empty($issues),
            'importance' => 'medium',
            'value' => [
                'issues_found' => count($issues),
                'issues' => $issues,
            ],
        ];

        if (!empty($issues)) {
            $result['errors'] = [
                'message' => 'URL format issues detected',
                'issues' => $issues,
                'recommendation' => 'Use lowercase, hyphens, and avoid special characters',
            ];
        }

        return $result;
    }

    /**
     * Check trailing slash consistency
     *
     * @return array<string, mixed>
     */
    private function checkTrailingSlash(string $url, ?string $canonicalTag): array
    {
        $parsed = parse_url($url);
        $path = $parsed['path'] ?? '/';

        if (preg_match('/\.[a-z0-9]+$/i', $path)) {
            return [
                'passed' => true,
                'importance' => 'low',
                'value' => [
                    'is_file' => true,
                    'has_trailing_slash' => false,
                ],
            ];
        }

        $hasTrailingSlash = str_ends_with($path, '/');

        $canonicalMatches = true;
        if ($canonicalTag !== null && $canonicalTag !== '') {
            $canonicalPath = parse_url($canonicalTag, PHP_URL_PATH);
            $canonicalHasSlash = str_ends_with((string) $canonicalPath, '/');
            $canonicalMatches = $hasTrailingSlash === $canonicalHasSlash;
        }

        $result = [
            'passed' => $canonicalMatches,
            'importance' => 'medium',
            'value' => [
                'has_trailing_slash' => $hasTrailingSlash,
                'canonical_matches' => $canonicalMatches,
            ],
        ];

        if (!$canonicalMatches) {
            $result['errors'] = [
                'message' => 'Trailing slash inconsistency',
                'url_has_slash' => $hasTrailingSlash,
                'recommendation' => 'Ensure URL and canonical tag use consistent trailing slash',
            ];
        }

        return $result;
    }

    /**
     * Check for mixed case in URL
     *
     * @return array<string, mixed>
     */
    private function checkUrlCase(string $url): array
    {
        $parsed = parse_url($url);
        $path = $parsed['path'] ?? '';
        $host = $parsed['host'] ?? '';

        $hasUppercasePath = (bool) preg_match('/[A-Z]/', $path);
        $hasUppercaseHost = (bool) preg_match('/[A-Z]/', $host);

        $result = [
            'passed' => !$hasUppercasePath && !$hasUppercaseHost,
            'importance' => 'medium',
            'value' => [
                'lowercase_path' => !$hasUppercasePath,
                'lowercase_host' => !$hasUppercaseHost,
            ],
        ];

        if ($hasUppercasePath) {
            $result['errors'] = [
                'message' => 'URL path contains uppercase letters',
                'recommendation' => 'Use lowercase letters in URLs to avoid duplicate content issues',
            ];
        }

        return $result;
    }

    /**
     * Check URL encoding
     *
     * @return array<string, mixed>
     */
    private function checkUrlEncoding(string $url): array
    {
        $decoded = urldecode($url);
        $doubleEncoded = urldecode($decoded) !== $decoded;

        // Check for common encoding issues
        $issues = [];

        if ($doubleEncoded) {
            $issues[] = 'Possible double URL encoding';
        }

        // Unencoded special characters
        $unencoded = preg_match('/[<>{\}|\\^`\[\]]/', $url);
        if ($unencoded) {
            $issues[] = 'Unencoded special characters';
        }

        if (preg_match('/%[2-7][0-9A-F](?![^%])/', $url)) {
            $issues[] = 'Potentially over-encoded characters';
        }

        $result = [
            'passed' => empty($issues),
            'importance' => 'low',
            'value' => [
                'encoding_issues' => count($issues),
                'issues' => $issues,
            ],
        ];

        if (!empty($issues)) {
            $result['warnings'] = [
                'message' => 'URL encoding issues detected',
                'issues' => $issues,
                'recommendation' => 'Review URL encoding for special characters',
            ];
        }

        return $result;
    }
}
