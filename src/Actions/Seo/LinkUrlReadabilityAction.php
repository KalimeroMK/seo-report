<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Actions\Seo;

use KalimeroMK\SeoReport\Actions\AnalysisActionInterface;
use KalimeroMK\SeoReport\Actions\AnalysisContext;

final class LinkUrlReadabilityAction implements AnalysisActionInterface
{
    public function handle(AnalysisContext $context): array
    {
        $unfriendlyLinkUrls = (array) $context->getData('unfriendly_link_urls', []);

        $result = [
            'link_url_readability' => ['passed' => true, 'importance' => 'low', 'value' => null],
        ];
        if ($unfriendlyLinkUrls !== []) {
            $result['link_url_readability']['passed'] = false;
            $result['link_url_readability']['errors'] = ['unfriendly_urls' => $unfriendlyLinkUrls];
        }

        return $result;
    }
}
