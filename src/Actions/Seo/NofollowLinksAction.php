<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Actions\Seo;

use KalimeroMK\SeoReport\Actions\AnalysisActionInterface;
use KalimeroMK\SeoReport\Actions\AnalysisContext;

final class NofollowLinksAction implements AnalysisActionInterface
{
    public function handle(AnalysisContext $context): array
    {
        $nofollowCount = (int) $context->getData('nofollow_count', 0);
        $nofollowLinks = (array) $context->getData('nofollow_links', []);

        $result = [
            'nofollow_links' => ['passed' => true, 'importance' => 'low', 'value' => $nofollowCount],
        ];
        if ($nofollowCount > 0) {
            $result['nofollow_links']['passed'] = false;
            $result['nofollow_links']['errors'] = ['found' => $nofollowLinks];
        }

        return $result;
    }
}
