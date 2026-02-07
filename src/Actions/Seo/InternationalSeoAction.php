<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Actions\Seo;

use KalimeroMK\SeoReport\Actions\AnalysisActionInterface;
use KalimeroMK\SeoReport\Actions\AnalysisContext;

/**
 * International SEO checks for multilingual websites.
 */
final class InternationalSeoAction implements AnalysisActionInterface
{
    public function handle(AnalysisContext $context): array
    {
        $hreflang = (array) $context->getData('hreflang', []);
        $htmlLang = (string) $context->getData('language', '');
        $domDocument = $context->getDom();

        $result = [];

        $result['hreflang_validation'] = $this->validateHreflang($hreflang);
        $result['html_lang'] = $this->checkHtmlLang($htmlLang);
        $result['language_consistency'] = $this->checkLanguageConsistency($htmlLang, $hreflang);
        $result['x_default_hreflang'] = $this->checkXDefaultHreflang($hreflang);
        $result['localization_indicators'] = $this->detectLocalization($context->getPageText());

        return $result;
    }

    /**
     * Validate hreflang tags
     *
     * @param array<int, array<string, mixed>> $hreflang
     * @return array<string, mixed>
     */
    private function validateHreflang(array $hreflang): array
    {
        if (empty($hreflang)) {
            return [
                'passed' => true,
                'importance' => 'low',
                'value' => ['has_hreflang' => false],
            ];
        }

        $validLanguageCodes = $this->getValidLanguageCodes();
        $invalidCodes = [];
        $validCodes = [];

        foreach ($hreflang as $item) {
            $lang = strtolower($item['lang'] ?? '');
            $baseLang = explode('-', $lang)[0];
            
            if (!in_array($baseLang, $validLanguageCodes, true)) {
                $invalidCodes[] = [
                    'code' => $lang,
                    'url' => $item['url'] ?? '',
                ];
            } else {
                $validCodes[] = $lang;
            }
        }

        $result = [
            'passed' => empty($invalidCodes),
            'importance' => 'medium',
            'value' => [
                'hreflang_count' => count($hreflang),
                'valid_codes' => $validCodes,
            ],
        ];

        if (!empty($invalidCodes)) {
            $result['errors'] = [
                'message' => 'Invalid hreflang language codes',
                'invalid_codes' => $invalidCodes,
                'recommendation' => 'Use valid ISO 639-1 language codes',
            ];
        }

        return $result;
    }

    /**
     * Check HTML lang attribute
     *
     * @return array<string, mixed>
     */
    private function checkHtmlLang(string $htmlLang): array
    {
        $validCodes = $this->getValidLanguageCodes();
        $lang = strtolower($htmlLang);
        
        $isValid = $lang === '' || in_array($lang, $validCodes, true);

        $result = [
            'passed' => $isValid,
            'importance' => 'high',
            'value' => ['lang' => $htmlLang],
        ];

        if ($lang === '') {
            $result['passed'] = false;
            $result['errors'] = [
                'message' => 'HTML lang attribute missing',
                'recommendation' => 'Add lang attribute to html tag (e.g., <html lang="en">)',
            ];
        } elseif (!$isValid) {
            $result['errors'] = [
                'message' => 'Invalid HTML lang attribute',
                'value' => $htmlLang,
                'recommendation' => 'Use valid ISO 639-1 language code',
            ];
        }

        return $result;
    }

    /**
     * Check language consistency between HTML lang and hreflang
     *
     * @param array<int, array<string, mixed>> $hreflang
     * @return array<string, mixed>
     */
    private function checkLanguageConsistency(string $htmlLang, array $hreflang): array
    {
        if (empty($hreflang) || $htmlLang === '') {
            return [
                'passed' => true,
                'importance' => 'low',
                'value' => ['can_check' => false],
            ];
        }

        $htmlLangLower = strtolower($htmlLang);
        $matchingHreflang = false;

        foreach ($hreflang as $item) {
            $itemLang = strtolower($item['lang'] ?? '');
            if ($itemLang === $htmlLangLower || str_starts_with($itemLang, $htmlLangLower . '-')) {
                $matchingHreflang = true;
                break;
            }
        }

        $result = [
            'passed' => $matchingHreflang,
            'importance' => 'medium',
            'value' => [
                'html_lang' => $htmlLang,
                'hreflang_matches' => $matchingHreflang,
            ],
        ];

        if (!$matchingHreflang) {
            $result['warnings'] = [
                'message' => 'HTML lang and hreflang mismatch',
                'html_lang' => $htmlLang,
                'recommendation' => 'Ensure hreflang includes the language specified in HTML lang',
            ];
        }

        return $result;
    }

    /**
     * Check for x-default hreflang
     *
     * @param array<int, array<string, mixed>> $hreflang
     * @return array<string, mixed>
     */
    private function checkXDefaultHreflang(array $hreflang): array
    {
        if (empty($hreflang)) {
            return [
                'passed' => true,
                'importance' => 'low',
                'value' => ['has_hreflang' => false],
            ];
        }

        $hasXDefault = false;
        foreach ($hreflang as $item) {
            if (strtolower($item['lang'] ?? '') === 'x-default') {
                $hasXDefault = true;
                break;
            }
        }

        $result = [
            'passed' => $hasXDefault,
            'importance' => 'medium',
            'value' => ['has_x_default' => $hasXDefault],
        ];

        if (!$hasXDefault) {
            $result['warnings'] = [
                'message' => 'Missing x-default hreflang',
                'recommendation' => 'Add x-default hreflang to specify fallback page',
                'example' => '<link rel="alternate" hreflang="x-default" href="https://example.com/">',
            ];
        }

        return $result;
    }

    /**
     * Detect localization indicators in content
     *
     * @return array<string, mixed>
     */
    private function detectLocalization(string $pageText): array
    {
        $indicators = [];

        $currencyPattern = '/[$€£¥₹₽₩฿]/u';
        if (preg_match_all($currencyPattern, $pageText, $matches)) {
            $indicators['currency_symbols'] = array_unique($matches[0]);
        }

        $datePatterns = [
            'US' => '/\b\d{1,2}\/\d{1,2}\/\d{2,4}\b/', // MM/DD/YYYY
            'EU' => '/\b\d{1,2}\.\d{1,2}\.\d{2,4}\b/', // DD.MM.YYYY
            'ISO' => '/\b\d{4}-\d{2}-\d{2}\b/', // YYYY-MM-DD
        ];

        foreach ($datePatterns as $format => $pattern) {
            if (preg_match($pattern, $pageText)) {
                $indicators['date_formats'][] = $format;
            }
        }

        $phonePattern = '/\b(\+\d[\d\s\-\(\)]{7,20})\b/';
        if (preg_match_all($phonePattern, $pageText, $matches)) {
            $indicators['phone_numbers'] = count($matches[0]);
        }

        $addressIndicators = ['street', 'avenue', 'road', 'blvd', 'zip code', 'postal code'];
        foreach ($addressIndicators as $indicator) {
            if (stripos($pageText, $indicator) !== false) {
                $indicators['address_keywords'][] = $indicator;
            }
        }

        return [
            'passed' => true,
            'importance' => 'low',
            'value' => $indicators,
        ];
    }

    /**
     * Get valid ISO 639-1 language codes
     *
     * @return array<int, string>
     */
    private function getValidLanguageCodes(): array
    {
        return [
            'aa', 'ab', 'ae', 'af', 'ak', 'am', 'an', 'ar', 'as', 'av',
            'ay', 'az', 'ba', 'be', 'bg', 'bh', 'bi', 'bm', 'bn', 'bo',
            'br', 'bs', 'ca', 'ce', 'ch', 'co', 'cr', 'cs', 'cu', 'cv',
            'cy', 'da', 'de', 'dv', 'dz', 'ee', 'el', 'en', 'eo', 'es',
            'et', 'eu', 'fa', 'ff', 'fi', 'fj', 'fo', 'fr', 'fy', 'ga',
            'gd', 'gl', 'gn', 'gu', 'gv', 'ha', 'he', 'hi', 'ho', 'hr',
            'ht', 'hu', 'hy', 'hz', 'ia', 'id', 'ie', 'ig', 'ii', 'ik',
            'io', 'is', 'it', 'iu', 'ja', 'jv', 'ka', 'kg', 'ki', 'kj',
            'kk', 'kl', 'km', 'kn', 'ko', 'kr', 'ks', 'ku', 'kv', 'kw',
            'ky', 'la', 'lb', 'lg', 'li', 'ln', 'lo', 'lt', 'lu', 'lv',
            'mg', 'mh', 'mi', 'mk', 'ml', 'mn', 'mr', 'ms', 'mt', 'my',
            'na', 'nb', 'nd', 'ne', 'ng', 'nl', 'nn', 'no', 'nr', 'nv',
            'ny', 'oc', 'oj', 'om', 'or', 'os', 'pa', 'pi', 'pl', 'ps',
            'pt', 'qu', 'rm', 'rn', 'ro', 'ru', 'rw', 'sa', 'sc', 'sd',
            'se', 'sg', 'si', 'sk', 'sl', 'sm', 'sn', 'so', 'sq', 'sr',
            'ss', 'st', 'su', 'sv', 'sw', 'ta', 'te', 'tg', 'th', 'ti',
            'tk', 'tl', 'tn', 'to', 'tr', 'ts', 'tt', 'tw', 'ty', 'ug',
            'uk', 'ur', 'uz', 've', 'vi', 'vo', 'wa', 'wo', 'xh', 'yi',
            'yo', 'za', 'zh', 'zu',
        ];
    }
}
