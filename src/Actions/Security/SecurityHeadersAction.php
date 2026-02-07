<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Actions\Security;

use KalimeroMK\SeoReport\Actions\AnalysisActionInterface;
use KalimeroMK\SeoReport\Actions\AnalysisContext;

/**
 * Analyze security headers for protection against common web vulnerabilities.
 */
final class SecurityHeadersAction implements AnalysisActionInterface
{
    public function handle(AnalysisContext $context): array
    {
        $response = $context->getResponse();
        $baseUrl = $context->getData('base_url');

        $headers = [
            'content-security-policy' => $response->getHeader('Content-Security-Policy'),
            'x-frame-options' => $response->getHeader('X-Frame-Options'),
            'x-content-type-options' => $response->getHeader('X-Content-Type-Options'),
            'referrer-policy' => $response->getHeader('Referrer-Policy'),
            'permissions-policy' => $response->getHeader('Permissions-Policy'),
            'cross-origin-embedder-policy' => $response->getHeader('Cross-Origin-Embedder-Policy'),
            'cross-origin-opener-policy' => $response->getHeader('Cross-Origin-Opener-Policy'),
            'cross-origin-resource-policy' => $response->getHeader('Cross-Origin-Resource-Policy'),
        ];

        $result = [];

        $result['content_security_policy'] = $this->checkCSP($headers['content-security-policy']);
        $result['x_frame_options'] = $this->checkXFrameOptions($headers['x-frame-options']);
        $result['x_content_type_options'] = $this->checkXContentTypeOptions($headers['x-content-type-options']);
        $result['referrer_policy'] = $this->checkReferrerPolicy($headers['referrer-policy']);
        $result['permissions_policy'] = $this->checkPermissionsPolicy($headers['permissions-policy']);
        $result['cross_origin_policies'] = $this->checkCrossOriginPolicies([
            'embedder' => $headers['cross-origin-embedder-policy'],
            'opener' => $headers['cross-origin-opener-policy'],
            'resource' => $headers['cross-origin-resource-policy'],
        ]);
        $result['security_txt'] = ['passed' => true, 'importance' => 'low', 'value' => null];
        $result['security_headers_score'] = $this->calculateSecurityScore($result);

        return $result;
    }

    /**
     * Check Content Security Policy header
     *
     * @param array<int, string> $cspHeader
     * @return array<string, mixed>
     */
    private function checkCSP(array $cspHeader): array
    {
        $hasCSP = !empty($cspHeader);
        $cspValue = $cspHeader[0] ?? '';

        $result = [
            'passed' => $hasCSP,
            'importance' => 'high',
            'value' => [
                'present' => $hasCSP,
                'policy' => $cspValue ?: null,
            ],
        ];

        if (!$hasCSP) {
            $result['errors'] = [
                'message' => 'Content Security Policy (CSP) not implemented',
                'recommendation' => 'Add CSP header to prevent XSS and data injection attacks',
                'example' => "Content-Security-Policy: default-src 'self'",
            ];
            return $result;
        }

        $directives = $this->parseCSP($cspValue);
        $unsafeDirectives = [];

        $unsafePatterns = [
            "'unsafe-inline'",
            "'unsafe-eval'",
            'http:',
            '*',
        ];

        foreach ($directives as $directive => $values) {
            foreach ($unsafePatterns as $pattern) {
                if (str_contains($values, $pattern)) {
                    $unsafeDirectives[] = [
                        'directive' => $directive,
                        'unsafe_value' => $pattern,
                    ];
                }
            }
        }

        $result['value']['directives'] = $directives;
        $result['value']['unsafe_directives'] = $unsafeDirectives;

        if (!empty($unsafeDirectives)) {
            $result['warnings'] = [
                'message' => 'CSP contains unsafe directives',
                'unsafe_directives' => $unsafeDirectives,
                'recommendation' => 'Remove unsafe-inline and unsafe-eval where possible',
            ];
        }

        return $result;
    }

    /**
     * Check X-Frame-Options header
     *
     * @param array<int, string> $header
     * @return array<string, mixed>
     */
    private function checkXFrameOptions(array $header): array
    {
        $hasHeader = !empty($header);
        $value = $header[0] ?? '';

        $validValues = ['DENY', 'SAMEORIGIN'];
        $isValid = $hasHeader && in_array(strtoupper($value), $validValues, true);

        $result = [
            'passed' => $isValid,
            'importance' => 'high',
            'value' => [
                'present' => $hasHeader,
                'value' => $value ?: null,
            ],
        ];

        if (!$hasHeader) {
            $result['errors'] = [
                'message' => 'X-Frame-Options header missing',
                'recommendation' => 'Add X-Frame-Options: DENY or SAMEORIGIN to prevent clickjacking',
            ];
        } elseif (!$isValid) {
            $result['errors'] = [
                'message' => 'Invalid X-Frame-Options value',
                'value' => $value,
                'valid_options' => $validValues,
            ];
        }

        return $result;
    }

    /**
     * Check X-Content-Type-Options header
     *
     * @param array<int, string> $header
     * @return array<string, mixed>
     */
    private function checkXContentTypeOptions(array $header): array
    {
        $hasHeader = !empty($header);
        $value = $header[0] ?? '';
        $isNosniff = strcasecmp($value, 'nosniff') === 0;

        $result = [
            'passed' => $isNosniff,
            'importance' => 'high',
            'value' => [
                'present' => $hasHeader,
                'value' => $value ?: null,
            ],
        ];

        if (!$hasHeader) {
            $result['errors'] = [
                'message' => 'X-Content-Type-Options header missing',
                'recommendation' => 'Add X-Content-Type-Options: nosniff to prevent MIME sniffing',
            ];
        } elseif (!$isNosniff) {
            $result['errors'] = [
                'message' => 'X-Content-Type-Options should be nosniff',
                'current_value' => $value,
            ];
        }

        return $result;
    }

    /**
     * Check Referrer-Policy header
     *
     * @param array<int, string> $header
     * @return array<string, mixed>
     */
    private function checkReferrerPolicy(array $header): array
    {
        $hasHeader = !empty($header);
        $value = $header[0] ?? '';

        $validPolicies = [
            'no-referrer',
            'no-referrer-when-downgrade',
            'origin',
            'origin-when-cross-origin',
            'same-origin',
            'strict-origin',
            'strict-origin-when-cross-origin',
            'unsafe-url',
        ];

        $isValid = $hasHeader && in_array(strtolower($value), $validPolicies, true);
        $isStrict = in_array(strtolower($value), ['no-referrer', 'strict-origin-when-cross-origin'], true);

        $result = [
            'passed' => $isValid,
            'importance' => 'medium',
            'value' => [
                'present' => $hasHeader,
                'value' => $value ?: null,
                'strict' => $isStrict,
            ],
        ];

        if (!$hasHeader) {
            $result['errors'] = [
                'message' => 'Referrer-Policy header missing',
                'recommendation' => 'Add Referrer-Policy to control referrer information leakage',
                'recommended' => 'strict-origin-when-cross-origin',
            ];
        } elseif (!$isValid) {
            $result['errors'] = [
                'message' => 'Invalid Referrer-Policy value',
                'value' => $value,
                'valid_options' => $validPolicies,
            ];
        } elseif (!$isStrict) {
            $result['warnings'] = [
                'message' => 'Consider using stricter referrer policy',
                'current' => $value,
                'recommended' => 'strict-origin-when-cross-origin or no-referrer',
            ];
        }

        return $result;
    }

    /**
     * Check Permissions-Policy header
     *
     * @param array<int, string> $header
     * @return array<string, mixed>
     */
    private function checkPermissionsPolicy(array $header): array
    {
        $hasHeader = !empty($header);
        $value = $header[0] ?? '';

        $result = [
            'passed' => $hasHeader,
            'importance' => 'medium',
            'value' => [
                'present' => $hasHeader,
                'policy' => $value ?: null,
            ],
        ];

        if (!$hasHeader) {
            $result['errors'] = [
                'message' => 'Permissions-Policy header missing',
                'recommendation' => 'Add Permissions-Policy to restrict browser features',
                'example' => 'Permissions-Policy: geolocation=(), microphone=(), camera=()',
            ];
            return $result;
        }

        $sensitiveFeatures = ['camera', 'microphone', 'geolocation', 'payment'];
        $restrictedFeatures = [];

        foreach ($sensitiveFeatures as $feature) {
            if (str_contains($value, $feature . '=()')) {
                $restrictedFeatures[] = $feature;
            }
        }

        $result['value']['restricted_features'] = $restrictedFeatures;

        return $result;
    }

    /**
     * Check Cross-Origin policies
     *
     * @param array<string, array<int, string>> $policies
     * @return array<string, mixed>
     */
    private function checkCrossOriginPolicies(array $policies): array
    {
        $results = [];

        $coep = $policies['embedder'][0] ?? '';
        $hasCOEP = !empty($coep);
        $results['coep'] = [
            'present' => $hasCOEP,
            'value' => $coep ?: null,
            'valid' => in_array(strtolower($coep), ['require-corp', 'credentialless'], true),
        ];

        $coop = $policies['opener'][0] ?? '';
        $hasCOOP = !empty($coop);
        $results['coop'] = [
            'present' => $hasCOOP,
            'value' => $coop ?: null,
            'valid' => in_array(strtolower($coop), ['same-origin', 'same-origin-allow-popups', 'unsafe-none'], true),
        ];

        $corp = $policies['resource'][0] ?? '';
        $hasCORP = !empty($corp);
        $results['corp'] = [
            'present' => $hasCORP,
            'value' => $corp ?: null,
            'valid' => in_array(strtolower($corp), ['same-origin', 'same-site', 'cross-origin'], true),
        ];

        // Overall assessment
        $implemented = ($hasCOEP ? 1 : 0) + ($hasCOOP ? 1 : 0) + ($hasCORP ? 1 : 0);

        return [
            'passed' => $implemented >= 2,
            'importance' => 'medium',
            'value' => $results,
            'recommendation' => $implemented < 2 ? 
                'Implement Cross-Origin policies to protect against Spectre and XS-Leaks' : null,
        ];
    }

    /**
     * Calculate overall security score
     *
     * @param array<string, mixed> $results
     * @return array<string, mixed>
     */
    private function calculateSecurityScore(array $results): array
    {
        $checks = [
            'content_security_policy' => 3,
            'x_frame_options' => 2,
            'x_content_type_options' => 2,
            'referrer_policy' => 1,
            'permissions_policy' => 1,
            'cross_origin_policies' => 1,
        ];

        $score = 0;
        $maxScore = 0;

        foreach ($checks as $check => $weight) {
            $maxScore += $weight;
            if (isset($results[$check]) && $results[$check]['passed']) {
                $score += $weight;
            }
        }

        $percentage = round(($score / $maxScore) * 100, 2);

        return [
            'passed' => $percentage >= 70,
            'importance' => 'high',
            'value' => [
                'score' => $score,
                'max_score' => $maxScore,
                'percentage' => $percentage,
            ],
            'rating' => $percentage >= 90 ? 'A' : ($percentage >= 70 ? 'B' : ($percentage >= 50 ? 'C' : 'D')),
        ];
    }

    /**
     * Parse CSP directive string
     *
     * @return array<string, string>
     */
    private function parseCSP(string $csp): array
    {
        $directives = [];
        $parts = explode(';', $csp);

        foreach ($parts as $part) {
            $part = trim($part);
            if (empty($part)) {
                continue;
            }

            $spacePos = strpos($part, ' ');
            if ($spacePos === false) {
                $directives[$part] = '';
            } else {
                $directive = substr($part, 0, $spacePos);
                $values = substr($part, $spacePos + 1);
                $directives[$directive] = $values;
            }
        }

        return $directives;
    }
}
