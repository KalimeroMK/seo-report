<?php

declare(strict_types=1);

/**
 * Default config for SEO Report (framework-agnostic).
 * In Laravel/Yii, merge with your config and replace env() values.
 */
return [
    'request_timeout' => 5,
    'request_http_version' => '1.1',
    'request_user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36',
    'request_proxy' => null,

    'sitemap_links' => -1,

    'report_limit_min_title' => 1,
    'report_limit_max_title' => 60,
    'report_limit_min_words' => 500,
    'report_limit_min_text_ratio' => 10,
    'report_limit_max_links' => 150,
    'report_limit_load_time' => 2,
    'report_limit_page_size' => 330000,
    'report_limit_http_requests' => 50,
    'report_limit_max_dom_nodes' => 1500,
    'report_limit_image_formats' => "AVIF\nWebP",
    'report_limit_deprecated_html_tags' => "acronym\napplet\nbasefont\nbig\ncenter\ndir\nfont\nframe\nframeset\nisindex\nnoframes\ns\nstrike\ntt\nu",

    'report_score_high' => 10,
    'report_score_medium' => 5,
    'report_score_low' => 0,
];
