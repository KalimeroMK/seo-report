<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Services;

/**
 * Docker-based Analysis Services
 * 
 * This class provides integration with Docker containers for:
 * - Core Web Vitals (Lighthouse)
 * - JavaScript Rendering Analysis (Puppeteer)
 * - Screenshots (Puppeteer)
 * - Technology Detection (Wappalyzer)
 */
final class DockerAnalyzer
{
    private string $dockerComposePath;
    private int $timeout;

    public function __construct(
        string $dockerComposePath = __DIR__ . '/../../docker/docker-compose.yml',
        int $timeout = 60
    ) {
        $this->dockerComposePath = $dockerComposePath;
        $this->timeout = $timeout;
    }

    /**
     * Run Core Web Vitals analysis using Lighthouse CI
     * 
     * Note: No caching by default - always returns fresh results
     * to verify if performance issues were fixed.
     *
     * @return array<string, mixed>
     * @throws \RuntimeException
     */
    public function analyzeCoreWebVitals(string $url, string $device = 'desktop'): array
    {
        $command = sprintf(
            'docker-compose -f %s run --rm lighthouse %s %s 2>&1',
            escapeshellarg($this->dockerComposePath),
            escapeshellarg($url),
            escapeshellarg($device)
        );

        $output = $this->executeCommand($command);
        
        return $this->parseLighthouseOutput($output);
    }

    /**
     * Analyze JavaScript rendering with Puppeteer
     * 
     * Note: No caching - always fresh results to detect
     * JavaScript errors and rendering issues in real-time.
     *
     * @return array<string, mixed>
     * @throws \RuntimeException
     */
    public function analyzeJavaScriptRendering(string $url): array
    {
        $command = sprintf(
            'docker-compose -f %s run --rm chromium /app/render.js %s 2>&1',
            escapeshellarg($this->dockerComposePath),
            escapeshellarg($url)
        );

        $output = $this->executeCommand($command);
        $result = json_decode($output, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Failed to parse JS render output: ' . json_last_error_msg());
        }

        return $result;
    }

    /**
     * Take screenshot with Puppeteer
     * 
     * Note: No caching - always fresh screenshot to see
     * current visual state of the page.
     *
     * @return array<string, mixed>
     * @throws \RuntimeException
     */
    public function takeScreenshot(string $url, string $viewport = 'desktop'): array
    {
        $command = sprintf(
            'docker-compose -f %s run --rm chromium /app/screenshot.js %s %s 2>&1',
            escapeshellarg($this->dockerComposePath),
            escapeshellarg($url),
            escapeshellarg($viewport)
        );

        $output = $this->executeCommand($command);
        $result = json_decode($output, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Failed to parse screenshot output: ' . json_last_error_msg());
        }

        return $result;
    }

    /**
     * Detect technologies using Wappalyzer
     * 
     * Note: No caching by default - technology stack can change
     * when site is updated or redeployed.
     *
     * @return array<string, mixed>
     * @throws \RuntimeException
     */
    public function detectTechnologies(string $url): array
    {
        $command = sprintf(
            'docker-compose -f %s run --rm wappalyzer %s 2>&1',
            escapeshellarg($this->dockerComposePath),
            escapeshellarg($url)
        );

        $output = $this->executeCommand($command);
        $result = json_decode($output, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Failed to parse Wappalyzer output: ' . json_last_error_msg());
        }

        return $result;
    }

    /**
     * Check if Docker services are available
     */
    public function isAvailable(): bool
    {
        try {
            $command = 'docker --version';
            exec($command, $output, $returnCode);
            return $returnCode === 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Build all Docker images
     */
    public function buildImages(): void
    {
        $command = sprintf(
            'docker-compose -f %s build 2>&1',
            escapeshellarg($this->dockerComposePath)
        );

        $this->executeCommand($command);
    }

    /**
     * Execute shell command with timeout
     *
     * @throws \RuntimeException
     */
    private function executeCommand(string $command): string
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes);

        if (!is_resource($process)) {
            throw new \RuntimeException('Failed to start process');
        }

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $output = '';
        $stderr = '';
        $startTime = time();

        while (proc_get_status($process)['running']) {
            if (time() - $startTime > $this->timeout) {
                proc_terminate($process, 9);
                throw new \RuntimeException('Command timed out after ' . $this->timeout . ' seconds');
            }

            $output .= stream_get_contents($pipes[1]);
            $stderr .= stream_get_contents($pipes[2]);

            usleep(100000);
        }


        $output .= stream_get_contents($pipes[1]);
        $stderr .= stream_get_contents($pipes[2]);

        fclose($pipes[0]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            throw new \RuntimeException('Command failed with exit code ' . $exitCode . ': ' . $stderr);
        }

        return $output;
    }

    /**
     * Parse Lighthouse JSON output
     *
     * @return array<string, mixed>
     */
    private function parseLighthouseOutput(string $output): array
    {
        if (preg_match('/\{.*\}/s', $output, $matches)) {
            $json = $matches[0];
        } else {
            $json = $output;
        }

        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Failed to parse Lighthouse output: ' . json_last_error_msg());
        }

        // Extract key metrics
        return [
            'url' => $data['requestedUrl'] ?? null,
            'finalUrl' => $data['finalUrl'] ?? null,
            'fetchTime' => $data['fetchTime'] ?? null,
            'performance' => [
                'score' => $data['categories']['performance']['score'] ?? null,
                'metrics' => [
                    'fcp' => $data['audits']['first-contentful-paint']['numericValue'] ?? null,
                    'lcp' => $data['audits']['largest-contentful-paint']['numericValue'] ?? null,
                    'fid' => $data['audits']['max-potential-fid']['numericValue'] ?? null,
                    'cls' => $data['audits']['cumulative-layout-shift']['numericValue'] ?? null,
                    'ttfb' => $data['audits']['server-response-time']['numericValue'] ?? null,
                    'speedIndex' => $data['audits']['speed-index']['numericValue'] ?? null,
                    'interactive' => $data['audits']['interactive']['numericValue'] ?? null,
                ],
            ],
            'accessibility' => [
                'score' => $data['categories']['accessibility']['score'] ?? null,
            ],
            'bestPractices' => [
                'score' => $data['categories']['best-practices']['score'] ?? null,
            ],
            'seo' => [
                'score' => $data['categories']['seo']['score'] ?? null,
            ],
            'raw' => $data,
        ];
    }
}
