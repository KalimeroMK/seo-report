<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Extractors\Page;

final class HeadingExtractor
{
    /**
     * @return array{
     *     headings: array<string, array<int, string>>,
     *     h1_count: int,
     *     secondary_heading_usage: array<string, int>,
     *     secondary_heading_levels: int
     * }
     */
    public function extract(\DOMDocument $domDocument): array
    {
        $headings = [];
        foreach (['h1', 'h2', 'h3', 'h4', 'h5', 'h6'] as $heading) {
            foreach ($domDocument->getElementsByTagName($heading) as $node) {
                $headings[$heading][] = seo_report_clean_tag_text($node->textContent);
            }
        }
        $h1Count = isset($headings['h1']) ? count($headings['h1']) : 0;
        $secondaryHeadingUsage = [];
        $secondaryHeadingLevels = 0;
        foreach (['h2', 'h3', 'h4', 'h5', 'h6'] as $heading) {
            $count = isset($headings[$heading]) ? count($headings[$heading]) : 0;
            $secondaryHeadingUsage[$heading] = $count;
            if ($count > 0) {
                $secondaryHeadingLevels++;
            }
        }

        return [
            'headings' => $headings,
            'h1_count' => $h1Count,
            'secondary_heading_usage' => $secondaryHeadingUsage,
            'secondary_heading_levels' => $secondaryHeadingLevels,
        ];
    }
}
