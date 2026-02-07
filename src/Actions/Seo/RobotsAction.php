<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Actions\Seo;

use KalimeroMK\SeoReport\Actions\AnalysisActionInterface;
use KalimeroMK\SeoReport\Actions\AnalysisContext;

final class RobotsAction implements AnalysisActionInterface
{
    public function handle(AnalysisContext $context): array
    {
        $noIndex = $context->getData('noindex');
        $robotsDirectives = array_values(array_unique(array_filter((array) $context->getData('robots_directives', []))));
        $robots = (bool) $context->getData('robots', true);
        $robotsRulesFailed = (array) $context->getData('robots_rules_failed', []);
        $sitemaps = (array) $context->getData('sitemaps', []);

        $result = [
            'noindex' => ['passed' => true, 'importance' => 'high', 'value' => $noIndex],
        ];
        
        if ($noIndex !== null) {
            $result['noindex']['passed'] = false;
            $result['noindex']['errors'] = [
                'noindex_detected' => $noIndex,
                'message' => 'Page has noindex directive - will not be indexed by search engines',
            ];
        }

        // Check for restrictive robots directives
        $robotFlags = array_intersect($robotsDirectives, ['nofollow', 'noarchive', 'nosnippet', 'noimageindex', 'nocache']);
        $result['robots_directives'] = [
            'passed' => empty($robotFlags),
            'importance' => 'medium',
            'value' => [
                'directives' => $robotsDirectives,
                'restrictive' => array_values($robotFlags),
            ],
        ];
        
        if ($robotFlags !== []) {
            $result['robots_directives']['passed'] = false;
            $result['robots_directives']['errors'] = [
                'restricted' => array_values($robotFlags),
                'message' => 'Restrictive robots meta directives detected',
            ];
        }

        // robots.txt accessibility check
        $result['robots'] = [
            'passed' => $robots,
            'importance' => 'high',
            'value' => [
                'accessible' => $robots,
                'sitemaps_found' => count($sitemaps),
                'sitemaps' => $sitemaps,
            ],
        ];
        
        if (!$robots) {
            $result['robots']['errors'] = [
                'message' => 'Page blocked by robots.txt',
                'blocked_by_rules' => $robotsRulesFailed,
            ];
        }

        // Sitemap reference check in robots.txt
        $result['robots_sitemap'] = [
            'passed' => !empty($sitemaps),
            'importance' => 'medium',
            'value' => [
                'sitemaps' => $sitemaps,
                'count' => count($sitemaps),
            ],
        ];
        
        if (empty($sitemaps)) {
            $result['robots_sitemap']['passed'] = false;
            $result['robots_sitemap']['errors'] = [
                'message' => 'No sitemap reference found in robots.txt',
                'recommendation' => 'Add Sitemap: https://example.com/sitemap.xml to robots.txt',
            ];
        }

        return $result;
    }
}
