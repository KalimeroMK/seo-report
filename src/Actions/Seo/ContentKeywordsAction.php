<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Actions\Seo;

use KalimeroMK\SeoReport\Actions\AnalysisActionInterface;
use KalimeroMK\SeoReport\Actions\AnalysisContext;

final class ContentKeywordsAction implements AnalysisActionInterface
{
    public function handle(AnalysisContext $context): array
    {
        $titleKeywords = (array) $context->getData('title_keywords', []);
        $bodyKeywords = $context->getBodyKeywords();
        $keywordConsistency = (array) $context->getData('keyword_consistency', []);
        $keywordsMissingInMeta = (array) ($keywordConsistency['missing_in_meta'] ?? []);
        $keywordsMissingInHeadings = (array) ($keywordConsistency['missing_in_headings'] ?? []);

        $intersection = array_intersect($titleKeywords, $bodyKeywords);
        $result = [
            'content_keywords' => ['passed' => true, 'importance' => 'high', 'value' => $intersection],
        ];
        if ($intersection === []) {
            $result['content_keywords']['passed'] = false;
            $result['content_keywords']['errors'] = ['missing' => $titleKeywords];
        }

        $result['keyword_consistency'] = ['passed' => true, 'importance' => 'medium', 'value' => $keywordConsistency];
        if ($titleKeywords !== []) {
            $keywordConsistencyErrors = [];
            if (($keywordConsistency['in_meta_description'] ?? []) === []) {
                $result['keyword_consistency']['passed'] = false;
                $keywordConsistencyErrors['no_title_keywords_in_meta'] = $keywordsMissingInMeta;
            }
            if (($keywordConsistency['in_headings'] ?? []) === []) {
                $result['keyword_consistency']['passed'] = false;
                $keywordConsistencyErrors['no_title_keywords_in_headings'] = $keywordsMissingInHeadings;
            }
            if ($keywordConsistencyErrors !== []) {
                $result['keyword_consistency']['errors'] = $keywordConsistencyErrors;
            }
        }

        return $result;
    }
}
