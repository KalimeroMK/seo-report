<?php

declare(strict_types=1);

if (!function_exists('seo_report_clean_url')) {
    /**
     * Remove the http and www prefixes from an URL string (normalized for storage).
     */
    function seo_report_clean_url(string $url): string
    {
        $parsed = parse_url($url, PHP_URL_PATH);
        $base = ($parsed === '/' || $parsed === null) ? rtrim($url, '/') : $url;

        return str_replace(['https://www.', 'http://www.', 'https://', 'http://'], '', $base);
    }
}

if (!function_exists('seo_report_clean_tag_text')) {
    /**
     * Clean whitespace in text extracted from HTML tags.
     */
    function seo_report_clean_tag_text(?string $string): string
    {
        if ($string === null || $string === '') {
            return '';
        }

        $replaced = preg_replace('/(?:\s{2,}+|[^\S ])/', ' ', $string);

        return trim(is_string($replaced) ? $replaced : '');
    }
}
