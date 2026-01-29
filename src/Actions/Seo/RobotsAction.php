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

        $result = [
            'noindex' => ['passed' => true, 'importance' => 'high', 'value' => $noIndex],
        ];
        if ($noIndex !== null) {
            $result['noindex']['passed'] = false;
            $result['noindex']['errors'] = ['noindex' => $noIndex];
        }

        $robotFlags = array_intersect($robotsDirectives, ['nofollow', 'noarchive', 'nosnippet', 'noimageindex']);
        $result['robots_directives'] = ['passed' => true, 'importance' => 'medium', 'value' => $robotsDirectives];
        if ($robotFlags !== []) {
            $result['robots_directives']['passed'] = false;
            $result['robots_directives']['errors'] = ['restricted' => array_values($robotFlags)];
        }

        $result['robots'] = ['passed' => true, 'importance' => 'high', 'value' => null];
        if (!$robots) {
            $result['robots']['passed'] = false;
            $result['robots']['errors'] = ['failed' => $robotsRulesFailed];
        }

        return $result;
    }
}
