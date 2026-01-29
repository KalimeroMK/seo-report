<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Actions\Seo;

use KalimeroMK\SeoReport\Actions\AnalysisActionInterface;
use KalimeroMK\SeoReport\Actions\AnalysisContext;

final class TwitterCardsAction implements AnalysisActionInterface
{
    public function handle(AnalysisContext $context): array
    {
        $twitterData = (array) $context->getData('twitter_data', []);
        $twitterRequired = ['twitter:card', 'twitter:title', 'twitter:description', 'twitter:image'];
        $twitterMissing = array_values(array_diff($twitterRequired, array_keys($twitterData)));

        $result = [
            'twitter_cards' => ['passed' => true, 'importance' => 'low', 'value' => $twitterData],
        ];
        if ($twitterMissing !== []) {
            $result['twitter_cards']['passed'] = false;
            $result['twitter_cards']['errors'] = ['missing' => $twitterMissing];
        }

        return $result;
    }
}
