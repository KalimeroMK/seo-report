<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport;

use KalimeroMK\SeoReport\Config\SeoReportConfig;
use KalimeroMK\SeoReport\Dto\AnalysisResult;
use KalimeroMK\SeoReport\Services\DockerAnalyzer;

/**
 * Extended SEO Analyzer with Docker-based advanced features
 *
 * This class wraps the base SeoAnalyzer with Docker-based capabilities:
 * - Core Web Vitals (Lighthouse)
 * - JavaScript Rendering Analysis
 * - Screenshots
 * - Advanced Technology Detection
 *
 * Usage:
 *   $analyzer = new SeoAnalyzerWithDocker($config);
 *   $result = $analyzer->analyze('https://example.com');
 *
 *   // Check if Docker features are available
 *   if ($analyzer->hasDockerSupport()) {
 *       $cwv = $analyzer->getCoreWebVitals();
 *   }
 */
final class SeoAnalyzerWithDocker
{
    private SeoAnalyzer $baseAnalyzer;
    private ?DockerAnalyzer $docker = null;
    private bool $dockerEnabled;
    /** @var array<string, mixed> */
    private array $dockerResults = [];

    public function __construct(
        SeoReportConfig $config,
        ?\GuzzleHttp\Client $httpClient = null,
        bool $enableDocker = true
    ) {
        $this->baseAnalyzer = new SeoAnalyzer($config, $httpClient);
        $this->dockerEnabled = $enableDocker;

        if ($enableDocker) {
            try {
                $this->docker = new DockerAnalyzer(
                    dockerComposePath: __DIR__ . '/../docker/docker-compose.yml',
                    timeout: 60
                );

                if (!$this->docker->isAvailable()) {
                    $this->docker = null;
                    $this->dockerEnabled = false;
                }
            } catch (\Exception $e) {
                $this->docker = null;
                $this->dockerEnabled = false;
            }
        }
    }

    /**
     * Analyze URL with optional Docker-based advanced features
     *
     * @throws SeoAnalyzerException
     */
    public function analyze(string $url): AnalysisResult
    {
        $result = $this->baseAnalyzer->analyze($url);

        if ($this->dockerEnabled && $this->docker !== null) {
            $this->dockerResults = $this->runDockerAnalysis($url);

            $results = $result->getResults();
            $mergedResults = array_merge($results, $this->dockerResults);

            $newScore = $this->calculateExtendedScore($mergedResults);

            return new AnalysisResult(
                url: $result->getUrl(),
                results: $mergedResults,
                score: $newScore,
                generatedAt: $result->getGeneratedAt(),
            );
        }

        return $result;
    }

    /**
     * Check if Docker support is available
     */
    public function hasDockerSupport(): bool
    {
        return $this->dockerEnabled && $this->docker !== null;
    }

    /**
     * Get Core Web Vitals (requires Docker)
     *
     * @return array<string, mixed>|null
     */
    public function getCoreWebVitals(string $url, string $device = 'desktop'): ?array
    {
        if ($this->docker === null) {
            return null;
        }

        try {
            return $this->docker->analyzeCoreWebVitals($url, $device);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get JavaScript rendering analysis (requires Docker)
     *
     * @return array<string, mixed>|null
     */
    public function getJavaScriptAnalysis(string $url): ?array
    {
        if ($this->docker === null) {
            return null;
        }

        try {
            return $this->docker->analyzeJavaScriptRendering($url);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Take screenshot (requires Docker)
     *
     * @return array<string, mixed>|null
     */
    public function takeScreenshot(string $url, string $viewport = 'desktop'): ?array
    {
        if ($this->docker === null) {
            return null;
        }

        try {
            return $this->docker->takeScreenshot($url, $viewport);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Run Docker-based analysis
     *
     * @return array<string, mixed>
     */
    private function runDockerAnalysis(string $url): array
    {
        $dockerResults = [];

        /** @var DockerAnalyzer $docker */
        $docker = $this->docker;

        try {
            $cwv = $docker->analyzeCoreWebVitals($url);
            $dockerResults['core_web_vitals'] = [
                'passed' => ($cwv['performance']['metrics']['lcp'] ?? 9999) < 2500
                    && ($cwv['performance']['metrics']['cls'] ?? 9999) < 0.1,
                'importance' => 'high',
                'value' => [
                    'lcp' => $cwv['performance']['metrics']['lcp'] ?? null,
                    'fid' => $cwv['performance']['metrics']['fid'] ?? null,
                    'cls' => $cwv['performance']['metrics']['cls'] ?? null,
                    'ttfb' => $cwv['performance']['metrics']['ttfb'] ?? null,
                    'performance_score' => isset($cwv['performance']['score'])
                        ? round($cwv['performance']['score'] * 100, 2)
                        : null,
                ],
            ];

            if (!$dockerResults['core_web_vitals']['passed']) {
                $dockerResults['core_web_vitals']['errors'] = [];
                if (($cwv['performance']['metrics']['lcp'] ?? 0) > 2500) {
                    $dockerResults['core_web_vitals']['errors']['lcp_slow'] =
                        'LCP > 2.5s: ' . round($cwv['performance']['metrics']['lcp'] / 1000, 2) . 's';
                }
                if (($cwv['performance']['metrics']['cls'] ?? 0) > 0.1) {
                    $dockerResults['core_web_vitals']['errors']['cls_high'] =
                        'CLS > 0.1: ' . $cwv['performance']['metrics']['cls'];
                }
            }
        } catch (\Exception $e) {
            $dockerResults['core_web_vitals'] = [
                'passed' => false,
                'importance' => 'high',
                'value' => null,
                'errors' => ['docker_error' => $e->getMessage()],
            ];
        }

        try {
            $js = $docker->analyzeJavaScriptRendering($url);
            $dockerResults['javascript_rendering'] = [
                'passed' => $js['seo']['titleRendered'] && $js['seo']['h1Rendered'],
                'importance' => 'high',
                'value' => [
                    'framework' => $js['pageInfo']['framework'] ?? 'Unknown',
                    'has_hydration' => $js['pageInfo']['hasHydration'] ?? false,
                    'title_rendered' => $js['seo']['titleRendered'] ?? false,
                    'h1_rendered' => $js['seo']['h1Rendered'] ?? false,
                    'render_time_ms' => $js['renderTime'] ?? null,
                    'console_errors' => count($js['console']['errors'] ?? []),
                ],
            ];

            if (!$dockerResults['javascript_rendering']['passed']) {
                $dockerResults['javascript_rendering']['errors'] = [];
                if (!($js['seo']['titleRendered'] ?? false)) {
                    $dockerResults['javascript_rendering']['errors']['title_not_rendered'] =
                        'Page title not rendered by JavaScript';
                }
                if (!($js['seo']['h1Rendered'] ?? false)) {
                    $dockerResults['javascript_rendering']['errors']['h1_not_rendered'] =
                        'H1 not rendered by JavaScript';
                }
            }
        } catch (\Exception $e) {
            $dockerResults['javascript_rendering'] = [
                'passed' => true, // Don't fail if Docker has issues
                'importance' => 'medium',
                'value' => null,
                'errors' => ['docker_error' => $e->getMessage()],
            ];
        }

        try {
            $tech = $docker->detectTechnologies($url);
            $technologies = [];

            foreach ($tech['technologies'] ?? [] as $category => $items) {
                $technologies[$category] = array_map(
                    fn($item) => $item['name'] ?? 'Unknown',
                    is_array($items) ? $items : []
                );
            }

            $dockerResults['advanced_technology_detection'] = [
                'passed' => true,
                'importance' => 'low',
                'value' => [
                    'technologies' => $technologies,
                    'total_detected' => array_sum(array_map('count', $technologies)),
                ],
            ];
        } catch (\Exception $e) {
            // Silent fail - this is optional info
        }

        return $dockerResults;
    }

    /**
     * Calculate extended score including Docker-based checks
     *
     * @param array<string, mixed> $results
     */
    private function calculateExtendedScore(array $results): float
    {
        $totalPoints = 0;
        $resultPoints = 0;

        foreach ($results as $value) {
            $importance = $value['importance'] ?? 'low';
            $weight = $importance === 'high' ? 10 : ($importance === 'medium' ? 5 : 0);
            $totalPoints += $weight;

            if (!empty($value['passed'])) {
                $resultPoints += $weight;
            }
        }

        return $totalPoints > 0 ? (float) (($resultPoints / $totalPoints) * 100) : 0.0;
    }

    /**
     * Get last Docker analysis results
     *
     * @return array<string, mixed>
     */
    public function getLastDockerResults(): array
    {
        return $this->dockerResults;
    }
}
