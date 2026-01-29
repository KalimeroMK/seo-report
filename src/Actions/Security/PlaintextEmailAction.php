<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Actions\Security;

use KalimeroMK\SeoReport\Actions\AnalysisActionInterface;
use KalimeroMK\SeoReport\Actions\AnalysisContext;

final class PlaintextEmailAction implements AnalysisActionInterface
{
    public function handle(AnalysisContext $context): array
    {
        $plaintextEmails = (array) $context->getData('plaintext_emails', []);

        $result = [
            'plaintext_email' => ['passed' => true, 'importance' => 'low', 'value' => null],
        ];
        if ($plaintextEmails !== []) {
            $result['plaintext_email']['passed'] = false;
            $result['plaintext_email']['errors'] = ['failed' => $plaintextEmails];
        }

        return $result;
    }
}
