<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Actions\Seo;

use KalimeroMK\SeoReport\Actions\AnalysisActionInterface;
use KalimeroMK\SeoReport\Actions\AnalysisContext;

/**
 * Pagination SEO checks for paginated content.
 */
final class PaginationAction implements AnalysisActionInterface
{
    public function handle(AnalysisContext $context): array
    {
        $domDocument = $context->getDom();
        $canonicalTag = $context->getData('canonical_tag');

        $result = [];

        $result['pagination_detection'] = $this->detectPagination($domDocument);
        $result['rel_next_prev'] = $this->checkRelNextPrev($domDocument);
        $result['infinite_scroll'] = $this->detectInfiniteScroll($domDocument);
        $result['view_all_page'] = $this->detectViewAllPage($domDocument, $canonicalTag);
        $result['pagination_links'] = $this->analyzePaginationLinks($domDocument);

        return $result;
    }

    /**
     * Detect pagination indicators on the page
     *
     * @return array<string, mixed>
     */
    private function detectPagination(\DOMDocument $domDocument): array
    {
        $indicators = [];

        $paginationSelectors = [
            '.pagination',
            '#pagination',
            '.pager',
            '.pages',
            '.page-numbers',
            '[class*="pagination"]',
            '[class*="page-"]',
        ];

        foreach ($paginationSelectors as $selector) {
            // Simple attribute-based check
            if (str_starts_with($selector, '.')) {
                $className = substr($selector, 1);
                foreach ($domDocument->getElementsByTagName('*') as $element) {
                    $class = $element->getAttribute('class');
                    if (str_contains($class, $className)) {
                        $indicators[] = 'class: ' . $className;
                        break 2;
                    }
                }
            } elseif (str_starts_with($selector, '#')) {
                $id = substr($selector, 1);
                foreach ($domDocument->getElementsByTagName('*') as $element) {
                    if ($element->getAttribute('id') === $id) {
                        $indicators[] = 'id: ' . $id;
                        break 2;
                    }
                }
            }
        }

        $pageNumberPattern = '/^\d+$/';
        $numberLinks = [];
        foreach ($domDocument->getElementsByTagName('a') as $link) {
            $text = trim($link->textContent);
            if (preg_match($pageNumberPattern, $text) && (int) $text < 100) {
                $numberLinks[] = (int) $text;
            }
        }

        if (count($numberLinks) >= 2) {
            $indicators[] = 'numeric page links: ' . implode(', ', array_slice($numberLinks, 0, 5));
        }

        $bodyText = $domDocument->textContent;
        if (preg_match('/page\s*\d+\s*of\s*\d+/i', $bodyText)) {
            $indicators[] = '"Page X of Y" indicator';
        }

        $isPaginated = !empty($indicators);

        return [
            'passed' => true,
            'importance' => 'low',
            'value' => [
                'is_paginated' => $isPaginated,
                'indicators' => $indicators,
            ],
        ];
    }

    /**
     * Check for rel="next" and rel="prev" (deprecated but still used)
     *
     * @return array<string, mixed>
     */
    private function checkRelNextPrev(\DOMDocument $domDocument): array
    {
        $hasNext = false;
        $hasPrev = false;
        $nextUrl = null;
        $prevUrl = null;

        foreach ($domDocument->getElementsByTagName('link') as $link) {
            $rel = strtolower($link->getAttribute('rel'));
            $href = $link->getAttribute('href');

            if ($rel === 'next') {
                $hasNext = true;
                $nextUrl = $href;
            } elseif ($rel === 'prev' || $rel === 'previous') {
                $hasPrev = true;
                $prevUrl = $href;
            }
        }

        // Also check regular links with rel attribute
        foreach ($domDocument->getElementsByTagName('a') as $link) {
            $rel = strtolower($link->getAttribute('rel'));
            if (str_contains($rel, 'next')) {
                $hasNext = true;
            }
            if (str_contains($rel, 'prev')) {
                $hasPrev = true;
            }
        }

        $result = [
            'passed' => true, // This is deprecated so it's not a failure
            'importance' => 'low',
            'value' => [
                'has_rel_next' => $hasNext,
                'has_rel_prev' => $hasPrev,
                'next_url' => $nextUrl,
                'prev_url' => $prevUrl,
            ],
        ];

        if ($hasNext || $hasPrev) {
            $result['info'] = [
                'message' => 'Page uses rel="next/prev" (deprecated by Google but still useful)',
                'note' => 'Google no longer uses rel=next/prev but other search engines might',
            ];
        }

        return $result;
    }

    /**
     * Detect infinite scroll implementation
     *
     * @return array<string, mixed>
     */
    private function detectInfiniteScroll(\DOMDocument $domDocument): array
    {
        $indicators = [];

        $scriptContent = '';
        foreach ($domDocument->getElementsByTagName('script') as $script) {
            $scriptContent .= $script->textContent . ' ';
        }

        $infiniteScrollKeywords = [
            'infinite scroll',
            'infinitescroll',
            'load more',
            'lazyload',
            'endless scroll',
            'autopager',
        ];

        foreach ($infiniteScrollKeywords as $keyword) {
            if (stripos($scriptContent, $keyword) !== false) {
                $indicators[] = $keyword;
            }
        }

        foreach ($domDocument->getElementsByTagName('button') as $button) {
            $text = strtolower($button->textContent);
            if (str_contains($text, 'load more') || str_contains($text, 'show more')) {
                $indicators[] = '"load more" button';
                break;
            }
        }

        foreach ($domDocument->getElementsByTagName('a') as $link) {
            $text = strtolower($link->textContent);
            if (str_contains($text, 'load more') || str_contains($text, 'show more')) {
                $indicators[] = '"load more" link';
                break;
            }
        }

        $hasInfiniteScroll = !empty($indicators);

        $result = [
            'passed' => true,
            'importance' => 'low',
            'value' => [
                'has_infinite_scroll' => $hasInfiniteScroll,
                'indicators' => $indicators,
            ],
        ];

        if ($hasInfiniteScroll) {
            $result['recommendation'] = [
                'message' => 'Infinite scroll detected',
                'recommendation' => 'Ensure proper pagination fallback for SEO and accessibility',
                'best_practice' => 'Use ?page=X parameter or pushState for crawlable pagination',
            ];
        }

        return $result;
    }

    /**
     * Detect "View All" page
     *
     * @return array<string, mixed>
     */
    private function detectViewAllPage(\DOMDocument $domDocument, ?string $canonicalTag): array
    {
        $isViewAll = false;
        $viewAllLink = null;

        foreach ($domDocument->getElementsByTagName('a') as $link) {
            $text = strtolower(trim($link->textContent));
            $href = $link->getAttribute('href');

            if ($text === 'view all' || $text === 'see all' || $text === 'show all') {
                $viewAllLink = $href;
                break;
            }
        }

        $bodyText = strtolower($domDocument->textContent);
        $hasViewAllTitle = str_contains($bodyText, 'view all') || str_contains($bodyText, 'all products');

        $canonicalIsViewAll = $canonicalTag !== null &&
            (str_contains($canonicalTag, 'view-all') || str_contains($canonicalTag, 'all-products'));

        $isViewAll = $canonicalIsViewAll || $hasViewAllTitle;

        $result = [
            'passed' => true,
            'importance' => 'low',
            'value' => [
                'is_view_all_page' => $isViewAll,
                'has_view_all_link' => $viewAllLink !== null,
                'view_all_link' => $viewAllLink,
            ],
        ];

        if ($isViewAll) {
            $result['info'] = [
                'message' => 'This appears to be a "View All" page',
                'note' => 'View All pages should have self-referencing canonical tags',
            ];
        }

        return $result;
    }

    /**
     * Analyze pagination link structure
     *
     * @return array<string, mixed>
     */
    private function analyzePaginationLinks(\DOMDocument $domDocument): array
    {
        $issues = [];
        $firstPage = null;
        $lastPage = null;

        $pageLinks = [];
        foreach ($domDocument->getElementsByTagName('a') as $link) {
            $text = trim($link->textContent);
            if (preg_match('/^\d+$/', $text)) {
                $pageNum = (int) $text;
                $pageLinks[] = [
                    'number' => $pageNum,
                    'href' => $link->getAttribute('href'),
                ];

                if ($firstPage === null || $pageNum < $firstPage) {
                    $firstPage = $pageNum;
                }
                if ($lastPage === null || $pageNum > $lastPage) {
                    $lastPage = $pageNum;
                }
            }
        }

        if ($firstPage !== null && $lastPage !== null && count($pageLinks) > 1) {
            $pageNumbers = array_column($pageLinks, 'number');
            sort($pageNumbers);

            $expectedCount = $lastPage - $firstPage + 1;
            if (count($pageNumbers) < $expectedCount && $expectedCount <= 10) {
                $missing = [];
                for ($i = $firstPage; $i <= $lastPage; $i++) {
                    if (!in_array($i, $pageNumbers, true)) {
                        $missing[] = $i;
                    }
                }
                if (!empty($missing)) {
                    $issues[] = 'Missing page numbers: ' . implode(', ', $missing);
                }
            }
        }

        $hasNextLink = false;
        $hasPrevLink = false;

        foreach ($domDocument->getElementsByTagName('a') as $link) {
            $text = strtolower(trim($link->textContent));
            $rel = strtolower($link->getAttribute('rel'));

            if ($text === 'next' || $text === '»' || $text === '&raquo;' || str_contains($rel, 'next')) {
                $hasNextLink = true;
            }
            if ($text === 'previous' || $text === 'prev' || $text === '«' || $text === '&laquo;' || str_contains($rel, 'prev')) {
                $hasPrevLink = true;
            }
        }

        $result = [
            'passed' => empty($issues),
            'importance' => 'low',
            'value' => [
                'page_range' => $firstPage !== null ? "{$firstPage}-{$lastPage}" : null,
                'total_pages_linked' => count($pageLinks),
                'has_next_link' => $hasNextLink,
                'has_prev_link' => $hasPrevLink,
            ],
        ];

        if (!empty($issues)) {
            $result['warnings'] = [
                'message' => 'Pagination structure issues',
                'issues' => $issues,
            ];
        }

        return $result;
    }
}
