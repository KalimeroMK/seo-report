<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Actions\Seo;

use KalimeroMK\SeoReport\Actions\AnalysisActionInterface;
use KalimeroMK\SeoReport\Actions\AnalysisContext;

/**
 * Analyze content quality including readability, keyword stuffing, and engagement factors.
 */
final class ContentQualityAction implements AnalysisActionInterface
{
    public function handle(AnalysisContext $context): array
    {
        $pageText = (string) $context->getData('page_text', '');
        $title = (string) $context->getData('title', '');
        $metaDescription = (string) $context->getData('meta_description', '');
        $bodyKeywords = (array) $context->getData('body_keywords', []);
        $headings = (array) $context->getData('headings', []);

        $result = [];

        $result['readability_score'] = $this->calculateReadability($pageText);
        $result['keyword_stuffing'] = $this->detectKeywordStuffing($bodyKeywords, $pageText);
        $result['title_quality'] = $this->analyzeTitleQuality($title);
        $result['content_freshness'] = $this->checkContentFreshness($pageText, $context->getDom());
        $result['content_structure'] = $this->analyzeContentStructure($pageText, $headings);
        $result['link_density'] = $this->analyzeLinkDensity($pageText, $context->getData('page_links', []));

        return $result;
    }

    /**
     * Calculate Flesch Reading Ease Score
     * Formula: 206.835 - (1.015 × average sentence length) - (84.6 × average syllables per word)
     * Score: 90-100 = Very Easy, 60-70 = Standard, 0-30 = Very Difficult
     *
     * @return array<string, mixed>
     */
    private function calculateReadability(string $text): array
    {
        $sentences = $this->splitSentences($text);
        $words = $this->extractWords($text);

        $sentenceCount = count($sentences);
        $wordCount = count($words);

        if ($sentenceCount === 0 || $wordCount === 0) {
            return [
                'passed' => false,
                'importance' => 'medium',
                'value' => [
                    'score' => 0,
                    'level' => 'unknown',
                    'word_count' => 0,
                ],
                'errors' => [
                    'message' => 'Not enough content to calculate readability',
                ],
            ];
        }

        $syllableCount = 0;
        foreach ($words as $word) {
            $syllableCount += $this->countSyllables($word);
        }

        $avgSentenceLength = $wordCount / $sentenceCount;
        $avgSyllablesPerWord = $syllableCount / $wordCount;

        $score = 206.835 - (1.015 * $avgSentenceLength) - (84.6 * $avgSyllablesPerWord);
        $score = max(0, min(100, $score)); // Clamp between 0-100

        $level = $this->getReadabilityLevel($score);
        $optimalRange = $score >= 50 && $score <= 80;

        $result = [
            'passed' => $optimalRange,
            'importance' => 'medium',
            'value' => [
                'score' => round($score, 2),
                'level' => $level,
                'avg_sentence_length' => round($avgSentenceLength, 2),
                'avg_syllables_per_word' => round($avgSyllablesPerWord, 2),
                'sentence_count' => $sentenceCount,
                'word_count' => $wordCount,
            ],
        ];

        if ($score < 30) {
            $result['errors'] = [
                'message' => 'Content very difficult to read',
                'score' => round($score, 2),
                'level' => $level,
                'recommendation' => 'Simplify language: use shorter sentences and simpler words',
            ];
        } elseif ($score > 90) {
            $result['warnings'] = [
                'message' => 'Content very easy (may be too simplistic)',
                'score' => round($score, 2),
                'level' => $level,
                'recommendation' => 'Consider adding more depth for better engagement',
            ];
        }

        return $result;
    }

    /**
     * Detect keyword stuffing
     *
     * @param array<int, string> $bodyKeywords
     * @return array<string, mixed>
     */
    private function detectKeywordStuffing(array $bodyKeywords, string $pageText): array
    {
        $wordCount = count($bodyKeywords);
        
        if ($wordCount === 0) {
            return [
                'passed' => true,
                'importance' => 'high',
                'value' => ['keywords_found' => 0],
            ];
        }

        $keywordFreq = array_count_values($bodyKeywords);
        arsort($keywordFreq);

        $stuffedKeywords = [];
        $totalWords = str_word_count($pageText);

        foreach ($keywordFreq as $keyword => $count) {
            $density = ($count / $totalWords) * 100;
            
            if ($density > 3.0) {
                $stuffedKeywords[] = [
                    'keyword' => $keyword,
                    'count' => $count,
                    'density' => round($density, 2),
                ];
            }
        }

        $result = [
            'passed' => count($stuffedKeywords) === 0,
            'importance' => 'high',
            'value' => [
                'top_keywords' => array_slice($keywordFreq, 0, 5, true),
                'stuffed_keywords' => $stuffedKeywords,
                'total_unique_keywords' => count($keywordFreq),
            ],
        ];

        if (count($stuffedKeywords) > 0) {
            $result['errors'] = [
                'message' => 'Potential keyword stuffing detected',
                'stuffed_keywords' => $stuffedKeywords,
                'recommendation' => 'Keep keyword density below 2-3%. Use synonyms and natural language.',
            ];
        }

        return $result;
    }

    /**
     * Analyze title quality
     *
     * @return array<string, mixed>
     */
    private function analyzeTitleQuality(string $title): array
    {
        $length = strlen($title);
        $wordCount = str_word_count($title);
        
        $issues = [];

        if ($title === strtoupper($title) && strlen($title) > 3) {
            $issues[] = 'All caps title';
        }

        if (substr_count($title, '!') > 1 || substr_count($title, '?') > 1) {
            $issues[] = 'Excessive punctuation';
        }

        $hasSeparator = str_contains($title, '|') || str_contains($title, '-') || str_contains($title, '–');

        $stopWords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for'];
        $firstWord = strtolower((string) strtok($title, ' '));
        $startsWithStopWord = in_array($firstWord, $stopWords, true);

        $hasNumbers = preg_match('/\d/', $title);

        $powerWords = ['best', 'top', 'guide', 'ultimate', 'complete', 'free', 'new', 'easy', 'quick'];
        $hasPowerWords = false;
        foreach ($powerWords as $word) {
            if (stripos($title, $word) !== false) {
                $hasPowerWords = true;
                break;
            }
        }

        $passed = $length >= 30 && $length <= 60 && count($issues) === 0 && !$startsWithStopWord;

        $result = [
            'passed' => $passed,
            'importance' => 'high',
            'value' => [
                'length' => $length,
                'word_count' => $wordCount,
                'has_separator' => $hasSeparator,
                'has_numbers' => (bool) $hasNumbers,
                'has_power_words' => $hasPowerWords,
                'starts_with_stop_word' => $startsWithStopWord,
            ],
        ];

        if (count($issues) > 0) {
            $result['errors'] = [
                'message' => 'Title quality issues',
                'issues' => $issues,
                'recommendation' => 'Fix title formatting issues for better CTR',
            ];
        }

        if ($startsWithStopWord) {
            $result['warnings'] = [
                'message' => 'Title starts with a stop word',
                'word' => $firstWord,
                'recommendation' => 'Start title with important keywords',
            ];
        }

        return $result;
    }

    /**
     * Check content freshness indicators
     *
     * @return array<string, mixed>
     */
    private function checkContentFreshness(string $pageText, \DOMDocument $domDocument): array
    {
        $freshnessIndicators = [];

        $datePatterns = [
            '/\b(January|February|March|April|May|June|July|August|September|October|November|December)\s+\d{1,2},?\s+\d{4}\b/i',
            '/\b\d{1,2}\s+(January|February|March|April|May|June|July|August|September|October|November|December)\s+\d{4}\b/i',
            '/\b\d{4}-\d{2}-\d{2}\b/',
            '/\b\d{1,2}\/\d{1,2}\/\d{4}\b/',
            '/\b(updated?|published?|modified?)\s*:?\s*\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4}\b/i',
        ];

        $foundDates = [];
        foreach ($datePatterns as $pattern) {
            if (preg_match_all($pattern, $pageText, $matches)) {
                $foundDates = array_merge($foundDates, $matches[0]);
            }
        }

        foreach ($domDocument->getElementsByTagName('meta') as $meta) {
            $name = strtolower($meta->getAttribute('name'));
            $property = strtolower($meta->getAttribute('property'));
            
            if (in_array($name, ['date', 'last-modified', 'pubdate'], true) || 
                in_array($property, ['article:published_time', 'article:modified_time'], true)) {
                $freshnessIndicators[] = 'Last modified meta found';
            }
        }

        $currentYear = (int) date('Y');
        $hasCurrentYear = str_contains($pageText, (string) $currentYear);

        $result = [
            'passed' => !empty($foundDates) || !empty($freshnessIndicators),
            'importance' => 'low',
            'value' => [
                'dates_found' => array_slice($foundDates, 0, 5),
                'freshness_indicators' => $freshnessIndicators,
                'mentions_current_year' => $hasCurrentYear,
            ],
        ];

        if (empty($foundDates) && empty($freshnessIndicators)) {
            $result['warnings'] = [
                'message' => 'No content freshness indicators found',
                'recommendation' => 'Add publication/update dates to show content is current',
            ];
        }

        return $result;
    }

    /**
     * Analyze content structure
     *
     * @param array<string, mixed> $headings
     * @return array<string, mixed>
     */
    private function analyzeContentStructure(string $pageText, array $headings): array
    {
        $paragraphs = $this->splitParagraphs($pageText);
        $avgParagraphLength = count($paragraphs) > 0 ? strlen($pageText) / count($paragraphs) : 0;

        $hierarchyIssues = [];
        $lastLevel = 0;
        for ($i = 1; $i <= 6; $i++) {
            $levelHeadings = $headings['h' . $i] ?? [];
            if (!empty($levelHeadings)) {
                if ($lastLevel > 0 && $i > $lastLevel + 1) {
                    $hierarchyIssues[] = "Skipped from H{$lastLevel} to H{$i}";
                }
                $lastLevel = $i;
            }
        }

        $longParagraphs = 0;
        foreach ($paragraphs as $para) {
            if (strlen($para) > 1000) {
                $longParagraphs++;
            }
        }

        $result = [
            'passed' => count($hierarchyIssues) === 0 && $longParagraphs <= 2,
            'importance' => 'medium',
            'value' => [
                'paragraph_count' => count($paragraphs),
                'avg_paragraph_length' => round($avgParagraphLength, 2),
                'long_paragraphs' => $longParagraphs,
                'heading_hierarchy_ok' => count($hierarchyIssues) === 0,
            ],
        ];

        if (count($hierarchyIssues) > 0) {
            $result['errors'] = [
                'message' => 'Heading hierarchy issues',
                'issues' => $hierarchyIssues,
                'recommendation' => 'Use proper heading hierarchy (H1 → H2 → H3) without skipping levels',
            ];
        }

        if ($longParagraphs > 2) {
            $result['warnings'] = [
                'message' => 'Long paragraphs detected (text walls)',
                'count' => $longParagraphs,
                'recommendation' => 'Break long paragraphs into smaller chunks for better readability',
            ];
        }

        return $result;
    }

    /**
     * Analyze link density (too many links can be spammy)
     *
     * @param array<string, mixed> $pageLinks
     * @return array<string, mixed>
     */
    private function analyzeLinkDensity(string $pageText, array $pageLinks): array
    {
        $totalLinks = count($pageLinks['internal'] ?? []) + count($pageLinks['external'] ?? []);
        $wordCount = str_word_count($pageText);
        
        if ($wordCount === 0) {
            return [
                'passed' => true,
                'importance' => 'low',
                'value' => ['links_per_100_words' => 0],
            ];
        }

        $linksPer100Words = ($totalLinks / $wordCount) * 100;
        $excessive = $linksPer100Words > 5; // More than 5 links per 100 words

        $result = [
            'passed' => !$excessive,
            'importance' => 'medium',
            'value' => [
                'total_links' => $totalLinks,
                'word_count' => $wordCount,
                'links_per_100_words' => round($linksPer100Words, 2),
            ],
        ];

        if ($excessive) {
            $result['errors'] = [
                'message' => 'Excessive link density detected',
                'links_per_100_words' => round($linksPer100Words, 2),
                'recommendation' => 'Reduce link density (aim for less than 3-5 links per 100 words)',
            ];
        }

        return $result;
    }

    /**
     * Split text into sentences
     *
     * @return array<int, string>
     */
    private function splitSentences(string $text): array
    {
        $sentences = preg_split('/[.!?]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        if ($sentences === false) {
            $sentences = [];
        }
        return array_filter(array_map('trim', $sentences));
    }

    /**
     * Split text into paragraphs
     *
     * @return array<int, string>
     */
    private function splitParagraphs(string $text): array
    {
        $paragraphs = preg_split('/\n\s*\n/', $text, -1, PREG_SPLIT_NO_EMPTY);
        if ($paragraphs === false) {
            $paragraphs = [];
        }
        return array_filter(array_map('trim', $paragraphs));
    }

    /**
     * Extract words from text
     *
     * @return array<int, string>
     */
    private function extractWords(string $text): array
    {
        return str_word_count(strtolower($text), 1);
    }

    /**
     * Count syllables in a word (English approximation)
     */
    private function countSyllables(string $word): int
    {
        $word = strtolower($word);
        
        // Remove non-alphabetic characters
        $word = preg_replace('/[^a-z]/', '', $word);
        
        if (empty($word)) {
            return 0;
        }

        preg_match_all('/[aeiouy]+/', $word, $matches);
        $syllables = count($matches[0]);

        if (strlen($word) > 2 && str_ends_with($word, 'e') && !str_ends_with($word, 'le')) {
            $syllables--;
        }

        return max(1, $syllables);
    }

    /**
     * Get readability level description
     */
    private function getReadabilityLevel(float $score): string
    {
        if ($score >= 90) return 'Very Easy';
        if ($score >= 80) return 'Easy';
        if ($score >= 70) return 'Fairly Easy';
        if ($score >= 60) return 'Standard';
        if ($score >= 50) return 'Fairly Difficult';
        if ($score >= 30) return 'Difficult';
        return 'Very Difficult';
    }
}
