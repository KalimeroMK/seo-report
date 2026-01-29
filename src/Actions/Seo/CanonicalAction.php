<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Actions\Seo;

use KalimeroMK\SeoReport\Actions\AnalysisActionInterface;
use KalimeroMK\SeoReport\Actions\AnalysisContext;

final class CanonicalAction implements AnalysisActionInterface
{
    public function handle(AnalysisContext $context): array
    {
        $canonicalTag = $context->getData('canonical_tag');
        $canonicalTags = (array) $context->getData('canonical_tags', []);
        $currentUrl = (string) $context->getData('current_url', $context->getUrl());

        $result = [
            'canonical_tag' => ['passed' => true, 'importance' => 'medium', 'value' => $canonicalTag],
        ];
        if ($canonicalTag === null) {
            $result['canonical_tag']['passed'] = false;
            $result['canonical_tag']['errors'] = ['missing' => null];
        }

        $canonicalErrors = [];
        if ($canonicalTag === null) {
            $canonicalErrors['missing'] = null;
        } else {
            $normalizedCanonical = $context->normalizeUrlForCanonical((string) $canonicalTag);
            $normalizedCurrent = $context->normalizeUrlForCanonical($currentUrl);
            if ($normalizedCanonical !== $normalizedCurrent) {
                $canonicalErrors['not_self_reference'] = [
                    'current' => $currentUrl,
                    'canonical' => $canonicalTag,
                ];
            }
            if (count($canonicalTags) > 1) {
                $canonicalErrors['duplicates'] = array_values(array_unique($canonicalTags));
            }
        }
        $result['canonical_self_reference'] = ['passed' => true, 'importance' => 'low', 'value' => $canonicalTag];
        if ($canonicalErrors !== []) {
            $result['canonical_self_reference']['passed'] = false;
            $result['canonical_self_reference']['errors'] = $canonicalErrors;
        }

        return $result;
    }
}
