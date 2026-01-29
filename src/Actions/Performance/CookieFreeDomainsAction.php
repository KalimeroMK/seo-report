<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Actions\Performance;

use KalimeroMK\SeoReport\Actions\AnalysisActionInterface;
use KalimeroMK\SeoReport\Actions\AnalysisContext;

final class CookieFreeDomainsAction implements AnalysisActionInterface
{
    public function handle(AnalysisContext $context): array
    {
        $mainHost = (string) $context->getData('main_host', '');
        $cookieDomainHits = (array) $context->getData('cookie_domain_hits', []);

        $result = [
            'cookie_free_domains' => ['passed' => true, 'importance' => 'low', 'value' => $mainHost],
        ];
        if ($cookieDomainHits !== []) {
            $result['cookie_free_domains']['passed'] = false;
            $result['cookie_free_domains']['errors'] = ['cookies_on_static' => $cookieDomainHits];
        }

        return $result;
    }
}
