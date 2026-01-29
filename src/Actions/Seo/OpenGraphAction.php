<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Actions\Seo;

use KalimeroMK\SeoReport\Actions\AnalysisActionInterface;
use KalimeroMK\SeoReport\Actions\AnalysisContext;

final class OpenGraphAction implements AnalysisActionInterface
{
    public function handle(AnalysisContext $context): array
    {
        $openGraphData = (array) $context->getData('open_graph_data', []);
        $openGraphRequired = ['og:title', 'og:description', 'og:image'];
        $openGraphMissing = array_values(array_diff($openGraphRequired, array_keys($openGraphData)));

        $result = [
            'open_graph' => ['passed' => true, 'importance' => 'low', 'value' => $openGraphData],
        ];
        if ($openGraphMissing !== []) {
            $result['open_graph']['passed'] = false;
            $result['open_graph']['errors'] = ['missing' => $openGraphMissing];
        }

        return $result;
    }
}
