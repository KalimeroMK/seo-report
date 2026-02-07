<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Actions\Seo;

use KalimeroMK\SeoReport\Actions\AnalysisActionInterface;
use KalimeroMK\SeoReport\Actions\AnalysisContext;

/**
 * Detect duplicate content issues on a single page and across pages (when crawling sitemaps).
 */
final class DuplicateContentAction implements AnalysisActionInterface
{
    public function handle(AnalysisContext $context): array
    {
        $title = (string) $context->getData('title', '');
        $metaDescription = (string) $context->getData('meta_description', '');
        $headings = (array) $context->getData('headings', []);
        $pageText = (string) $context->getData('page_text', '');
        $canonicalTag = $context->getData('canonical_tag');

        $result = [];

        $h1Tags = $headings['h1'] ?? [];
        $result['duplicate_h1'] = $this->checkDuplicateH1($h1Tags);
        $result['title_uniqueness'] = $this->checkTitleUniqueness($title, $headings);
        $result['thin_content'] = $this->checkThinContent($pageText);
        $result['content_uniqueness'] = $this->checkContentUniqueness($pageText);
        $result['meta_description_quality'] = $this->checkMetaDescriptionQuality($metaDescription);
        $result['canonical_effectiveness'] = $this->checkCanonicalEffectiveness($canonicalTag, $context->getUrl());

        return $result;
    }

    /**
     * Check for multiple H1 tags on the same page
     *
     * @param array<int, string> $h1Tags
     * @return array<string, mixed>
     */
    private function checkDuplicateH1(array $h1Tags): array
    {
        $count = count($h1Tags);
        $result = [
            'passed' => $count === 1,
            'importance' => 'high',
            'value' => ['h1_count' => $count, 'h1_tags' => $h1Tags],
        ];

        if ($count === 0) {
            $result['errors'] = [
                'message' => 'No H1 tag found on the page',
                'recommendation' => 'Add a single, descriptive H1 tag to each page',
            ];
        } elseif ($count > 1) {
            $result['errors'] = [
                'message' => 'Multiple H1 tags detected',
                'count' => $count,
                'h1_tags' => $h1Tags,
                'recommendation' => 'Use only one H1 tag per page. Use H2-H6 for subsections.',
            ];
        }

        return $result;
    }

    /**
     * Check if title is unique compared to headings
     *
     * @param array<string, mixed> $headings
     * @return array<string, mixed>
     */
    private function checkTitleUniqueness(string $title, array $headings): array
    {
        $titleNormalized = $this->normalizeText($title);
        $h1Tags = $headings['h1'] ?? [];
        
        // Check if title matches H1 exactly
        $titleMatchesH1 = false;
        foreach ($h1Tags as $h1) {
            if ($h1 !== null && $this->normalizeText($h1) === $titleNormalized) {
                $titleMatchesH1 = true;
                break;
            }
        }

        $result = [
            'passed' => !$titleMatchesH1,
            'importance' => 'medium',
            'value' => [
                'title' => $title,
                'matches_h1' => $titleMatchesH1,
            ],
        ];

        if ($titleMatchesH1) {
            $result['errors'] = [
                'message' => 'Title tag is identical to H1 tag',
                'recommendation' => 'Title and H1 should be similar but not identical for better SEO',
            ];
        }

        return $result;
    }

    /**
     * Check for thin content (low word count)
     *
     * @return array<string, mixed>
     */
    private function checkThinContent(string $pageText): array
    {
        $wordCount = $this->countWords($pageText);
        $minWords = 300;

        $result = [
            'passed' => $wordCount >= $minWords,
            'importance' => 'high',
            'value' => [
                'word_count' => $wordCount,
                'min_recommended' => $minWords,
            ],
        ];

        if ($wordCount < $minWords) {
            $result['errors'] = [
                'message' => 'Thin content detected',
                'word_count' => $wordCount,
                'min_recommended' => $minWords,
                'recommendation' => 'Add more valuable content (aim for at least 300 words)',
            ];
        }

        return $result;
    }

    /**
     * Check content uniqueness ratio (unique vs boilerplate content)
     *
     * @return array<string, mixed>
     */
    private function checkContentUniqueness(string $pageText): array
    {
        $totalLength = strlen($pageText);

        $boilerplatePatterns = [
            '/\b(copyright|all rights reserved|©)\b/i',
            '/\b(privacy policy|terms of service|terms and conditions)\b/i',
            '/\b(follow us on|connect with us|find us on)\s+(facebook|twitter|instagram|linkedin)\b/i',
            '/\b(subscribe|sign up|newsletter)\b/i',
            '/\b(cookie policy|we use cookies)\b/i',
        ];

        $boilerplateLength = 0;
        foreach ($boilerplatePatterns as $pattern) {
            if (preg_match_all($pattern, $pageText, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    $boilerplateLength += strlen($match[0]);
                }
            }
        }

        $uniqueRatio = $totalLength > 0 ? (($totalLength - $boilerplateLength) / $totalLength) * 100 : 0;
        $minUniqueRatio = 70; // At least 70% should be unique content

        $result = [
            'passed' => $uniqueRatio >= $minUniqueRatio,
            'importance' => 'medium',
            'value' => [
                'unique_ratio' => round($uniqueRatio, 2),
                'total_chars' => $totalLength,
                'boilerplate_estimate' => $boilerplateLength,
            ],
        ];

        if ($uniqueRatio < $minUniqueRatio) {
            $result['errors'] = [
                'message' => 'High ratio of boilerplate content detected',
                'unique_ratio' => round($uniqueRatio, 2),
                'recommendation' => 'Increase unique content to at least 70% of page',
            ];
        }

        return $result;
    }

    /**
     * Check meta description quality and duplicates
     *
     * @return array<string, mixed>
     */
    private function checkMetaDescriptionQuality(string $metaDescription): array
    {
        $length = strlen($metaDescription);
        $wordCount = $this->countWords($metaDescription);
        
        $lowQualityPatterns = [
            '/^\s*$/', // Empty
            '/^\.{3,}$/', // Just dots
            '/^home\s*$/i', // Just "Home"
            '/^page\s*\d*\s*$/i', // Just "Page X"
            '/(welcome to|click here|read more|learn more).*$/i', // Generic endings
        ];

        $isLowQuality = false;
        foreach ($lowQualityPatterns as $pattern) {
            if (preg_match($pattern, $metaDescription)) {
                $isLowQuality = true;
                break;
            }
        }

        $result = [
            'passed' => !$isLowQuality && $wordCount >= 5,
            'importance' => 'medium',
            'value' => [
                'length' => $length,
                'word_count' => $wordCount,
                'low_quality_detected' => $isLowQuality,
            ],
        ];

        if ($isLowQuality) {
            $result['errors'] = [
                'message' => 'Low-quality or generic meta description detected',
                'recommendation' => 'Write unique, descriptive meta descriptions (120-160 characters)',
            ];
        } elseif ($wordCount < 5) {
            $result['errors'] = [
                'message' => 'Meta description too short',
                'word_count' => $wordCount,
                'recommendation' => 'Expand meta description to at least 5-10 words',
            ];
        }

        return $result;
    }

    /**
     * Check if canonical tag is properly implemented
     *
     * @return array<string, mixed>
     */
    private function checkCanonicalEffectiveness(?string $canonicalTag, string $currentUrl): array
    {
        $hasCanonical = $canonicalTag !== null && $canonicalTag !== '';
        
        $result = [
            'passed' => $hasCanonical,
            'importance' => 'high',
            'value' => [
                'has_canonical' => $hasCanonical,
                'canonical_url' => $canonicalTag,
                'current_url' => $currentUrl,
            ],
        ];

        if (!$hasCanonical) {
            $result['errors'] = [
                'message' => 'No canonical tag found',
                'recommendation' => 'Add a self-referencing canonical tag to prevent duplicate content issues',
            ];
        } elseif ($this->normalizeUrl($canonicalTag) !== $this->normalizeUrl($currentUrl)) {
                $result['warnings'] = [
                'message' => 'Canonical tag points to different URL',
                'canonical' => $canonicalTag,
                'current' => $currentUrl,
                'note' => 'This may be intentional if this is a duplicate page',
            ];
        }

        return $result;
    }

    /**
     * Count words in text
     */
    private function countWords(string $text): int
    {
        $text = trim($text);
        if ($text === '') {
            return 0;
        }
        return str_word_count($text);
    }

    /**
     * Normalize text for comparison
     */
    private function normalizeText(?string $text): string
    {
        $normalized = preg_replace('/\s+/', ' ', (string) $text);
        if ($normalized === null) {
            return '';
        }
        return strtolower(trim($normalized));
    }

    /**
     * Normalize URL for comparison
     */
    private function normalizeUrl(string $url): string
    {
        $parsed = parse_url($url);
        if ($parsed === false) {
            return strtolower($url);
        }
        
        $scheme = ($parsed['scheme'] ?? 'https');
        $host = strtolower($parsed['host'] ?? '');
        $path = $parsed['path'] ?? '/';
        
        // Remove trailing slash for comparison
        $path = rtrim($path, '/');
        if ($path === '') {
            $path = '/';
        }
        
        return $scheme . '://' . $host . $path;
    }
}
