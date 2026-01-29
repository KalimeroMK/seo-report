<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Actions;

interface AnalysisActionInterface
{
    /** @return array<string, mixed> */
    public function handle(AnalysisContext $context): array;
}
