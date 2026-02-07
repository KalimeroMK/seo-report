<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Actions\Misc;

use KalimeroMK\SeoReport\Actions\AnalysisActionInterface;
use KalimeroMK\SeoReport\Actions\AnalysisContext;

/**
 * Advanced structured data validation without external API.
 * Validates JSON-LD syntax and required properties for common schemas.
 */
final class StructuredDataValidationAction implements AnalysisActionInterface
{
    /** @var array<string, array<int, string>> */
    private array $schemaRequirements = [
        'Article' => ['headline', 'author', 'datePublished'],
        'NewsArticle' => ['headline', 'author', 'datePublished', 'dateModified'],
        'BlogPosting' => ['headline', 'author', 'datePublished'],
        'Product' => ['name', 'offers', 'description'],
        'LocalBusiness' => ['name', 'address', 'telephone'],
        'Organization' => ['name', 'url', 'logo'],
        'Person' => ['name'],
        'Event' => ['name', 'startDate', 'location'],
        'Recipe' => ['name', 'recipeIngredient', 'recipeInstructions'],
        'FAQPage' => ['mainEntity'],
        'HowTo' => ['name', 'step'],
        'BreadcrumbList' => ['itemListElement'],
        'WebSite' => ['name', 'url'],
        'WebPage' => ['name'],
        'VideoObject' => ['name', 'thumbnailUrl', 'contentUrl'],
        'ImageObject' => ['url'],
    ];

    public function handle(AnalysisContext $context): array
    {
        $structuredData = (array) $context->getData('structured_data', []);
        $domDocument = $context->getDom();

        $result = [];

        $jsonLdData = $this->extractJsonLd($domDocument);
        $result['json_ld_validation'] = $this->validateJsonLdSyntax($jsonLdData);
        $result['schema_requirements'] = $this->validateSchemaRequirements($jsonLdData);
        $result['duplicate_ids'] = $this->checkDuplicateIds($jsonLdData);
        $result['structured_data_images'] = $this->validateImageUrls($jsonLdData);
        $result['schema_context'] = $this->checkSchemaContext($jsonLdData);
        $result['structured_data_quality'] = $this->assessOverallQuality($jsonLdData, $structuredData);

        return $result;
    }

    /**
     * Extract JSON-LD scripts from the document
     *
     * @return array<int, array<string, mixed>>
     */
    private function extractJsonLd(\DOMDocument $domDocument): array
    {
        $scripts = [];
        
        foreach ($domDocument->getElementsByTagName('script') as $script) {
            if ($script->getAttribute('type') === 'application/ld+json') {
                $content = trim($script->textContent);
                if (!empty($content)) {
                    $decoded = json_decode($content, true);
                    if ($decoded !== null) {
                        $scripts[] = $decoded;
                    } else {
                        $scripts[] = ['_raw' => $content, '_error' => json_last_error_msg()];
                    }
                }
            }
        }

        return $scripts;
    }

    /**
     * Validate JSON-LD syntax
     *
     * @param array<int, array<string, mixed>> $jsonLdData
     * @return array<string, mixed>
     */
    private function validateJsonLdSyntax(array $jsonLdData): array
    {
        $validCount = 0;
        $invalidCount = 0;
        $errors = [];

        foreach ($jsonLdData as $index => $data) {
            if (isset($data['_error'])) {
                $invalidCount++;
                $errors[] = [
                    'index' => $index,
                    'error' => $data['_error'],
                    'preview' => substr($data['_raw'] ?? '', 0, 100),
                ];
            } else {
                $validCount++;
            }
        }

        $total = $validCount + $invalidCount;

        $result = [
            'passed' => $invalidCount === 0 && $total > 0,
            'importance' => 'high',
            'value' => [
                'total_scripts' => $total,
                'valid' => $validCount,
                'invalid' => $invalidCount,
            ],
        ];

        if ($total === 0) {
            $result['passed'] = false;
            $result['errors'] = [
                'message' => 'No JSON-LD structured data found',
                'recommendation' => 'Add JSON-LD structured data for better search visibility',
            ];
        } elseif ($invalidCount > 0) {
            $result['errors'] = [
                'message' => 'Invalid JSON-LD syntax detected',
                'count' => $invalidCount,
                'errors' => $errors,
                'recommendation' => 'Fix JSON syntax errors in structured data',
            ];
        }

        return $result;
    }

    /**
     * Validate schema requirements
     *
     * @param array<int, array<string, mixed>> $jsonLdData
     * @return array<string, mixed>
     */
    private function validateSchemaRequirements(array $jsonLdData): array
    {
        $schemaChecks = [];
        $totalIssues = 0;

        foreach ($jsonLdData as $data) {
            if (isset($data['_error'])) {
                continue;
            }

            $types = $this->getSchemaTypes($data);
            
            foreach ($types as $type) {
                if (!isset($this->schemaRequirements[$type])) {
                    continue; // Unknown schema type
                }

                $required = $this->schemaRequirements[$type];
                $missing = [];

                foreach ($required as $property) {
                    if (!$this->hasProperty($data, $property)) {
                        $missing[] = $property;
                    }
                }

                if (!empty($missing)) {
                    $totalIssues++;
                    $schemaChecks[] = [
                        'type' => $type,
                        'missing_properties' => $missing,
                    ];
                }
            }
        }

        $result = [
            'passed' => $totalIssues === 0,
            'importance' => 'medium',
            'value' => [
                'schemas_checked' => count($schemaChecks),
                'issues_found' => $totalIssues,
            ],
        ];

        if ($totalIssues > 0) {
            $result['errors'] = [
                'message' => 'Missing required schema properties',
                'issues' => $schemaChecks,
                'recommendation' => 'Add missing required properties for complete schema markup',
            ];
        }

        return $result;
    }

    /**
     * Check for duplicate @id values
     *
     * @param array<int, array<string, mixed>> $jsonLdData
     * @return array<string, mixed>
     */
    private function checkDuplicateIds(array $jsonLdData): array
    {
        $ids = [];
        $duplicates = [];

        foreach ($jsonLdData as $index => $data) {
            if (isset($data['_error'])) {
                continue;
            }

            $this->collectIds($data, $ids, $duplicates);
        }

        $result = [
            'passed' => empty($duplicates),
            'importance' => 'medium',
            'value' => [
                'total_ids' => count($ids),
                'duplicates_found' => count($duplicates),
            ],
        ];

        if (!empty($duplicates)) {
            $result['errors'] = [
                'message' => 'Duplicate @id values detected',
                'duplicates' => $duplicates,
                'recommendation' => 'Each structured data item should have a unique @id',
            ];
        }

        return $result;
    }

    /**
     * Validate image URLs in structured data
     *
     * @param array<int, array<string, mixed>> $jsonLdData
     * @return array<string, mixed>
     */
    private function validateImageUrls(array $jsonLdData): array
    {
        $imageIssues = [];
        $totalImages = 0;

        foreach ($jsonLdData as $data) {
            if (isset($data['_error'])) {
                continue;
            }

            $images = $this->findImageUrls($data);
            $totalImages += count($images);

            foreach ($images as $image) {
                if (!$this->isValidImageUrl($image)) {
                    $imageIssues[] = $image;
                }
            }
        }

        $result = [
            'passed' => empty($imageIssues),
            'importance' => 'low',
            'value' => [
                'total_images' => $totalImages,
                'invalid_urls' => count($imageIssues),
            ],
        ];

        if (!empty($imageIssues)) {
            $result['errors'] = [
                'message' => 'Invalid image URLs in structured data',
                'count' => count($imageIssues),
                'examples' => array_slice($imageIssues, 0, 5),
                'recommendation' => 'Use valid, absolute URLs for images in structured data',
            ];
        }

        return $result;
    }

    /**
     * Check @context validity
     *
     * @param array<int, array<string, mixed>> $jsonLdData
     * @return array<string, mixed>
     */
    private function checkSchemaContext(array $jsonLdData): array
    {
        $validContexts = ['https://schema.org', 'http://schema.org', 'schema.org'];
        $missingContext = 0;
        $invalidContext = 0;

        foreach ($jsonLdData as $data) {
            if (isset($data['_error'])) {
                continue;
            }

            $context = $data['@context'] ?? null;

            if ($context === null) {
                $missingContext++;
            } else {
                $isValid = false;
                foreach ($validContexts as $valid) {
                    if (str_contains((string) $context, $valid)) {
                        $isValid = true;
                        break;
                    }
                }
                if (!$isValid) {
                    $invalidContext++;
                }
            }
        }

        $total = count($jsonLdData);
        $passed = $total > 0 && $missingContext === 0 && $invalidContext === 0;

        $result = [
            'passed' => $passed,
            'importance' => 'high',
            'value' => [
                'total_scripts' => $total,
                'missing_context' => $missingContext,
                'invalid_context' => $invalidContext,
            ],
        ];

        if ($missingContext > 0) {
            $result['errors'] = [
                'message' => 'Missing @context in structured data',
                'count' => $missingContext,
                'recommendation' => 'Add "@context": "https://schema.org" to all JSON-LD scripts',
            ];
        }

        if ($invalidContext > 0) {
            $result['warnings'] = [
                'message' => 'Non-standard @context detected',
                'count' => $invalidContext,
                'recommendation' => 'Use "https://schema.org" for standard schema.org markup',
            ];
        }

        return $result;
    }

    /**
     * Assess overall structured data quality
     *
     * @param array<int, array<string, mixed>> $jsonLdData
     * @param array<string, mixed> $structuredData
     * @return array<string, mixed>
     */
    private function assessOverallQuality(array $jsonLdData, array $structuredData): array
    {
        $hasJsonLd = count($jsonLdData) > 0;
        $hasOpenGraph = !empty($structuredData['Open Graph'] ?? []);
        $hasTwitter = !empty($structuredData['Twitter'] ?? []);
        $hasSchemaOrg = !empty($structuredData['Schema.org'] ?? []);

        $score = 0;
        if ($hasJsonLd) $score += 3;
        if ($hasOpenGraph) $score += 1;
        if ($hasTwitter) $score += 1;
        if ($hasSchemaOrg) $score += 1;

        $result = [
            'passed' => $score >= 3,
            'importance' => 'high',
            'value' => [
                'score' => $score,
                'has_json_ld' => $hasJsonLd,
                'has_open_graph' => $hasOpenGraph,
                'has_twitter' => $hasTwitter,
                'has_schema_org' => $hasSchemaOrg,
            ],
        ];

        if ($score < 3) {
            $result['errors'] = [
                'message' => 'Limited structured data implementation',
                'score' => $score,
                'recommendation' => 'Implement JSON-LD structured data for better search visibility',
            ];
        }

        return $result;
    }

    /**
     * Get schema types from data (handles @graph and single type)
     *
     * @param array<string, mixed> $data
     * @return array<int, string>
     */
    private function getSchemaTypes(array $data): array
    {
        $types = [];

        // Handle @graph
        if (isset($data['@graph']) && is_array($data['@graph'])) {
            foreach ($data['@graph'] as $item) {
                if (isset($item['@type'])) {
                    $types[] = $item['@type'];
                }
            }
        }

        // Handle single type
        if (isset($data['@type'])) {
            $types[] = $data['@type'];
        }

        return $types;
    }

    /**
     * Check if data has a property (handles nested)
     *
     * @param array<string, mixed> $data
     */
    private function hasProperty(array $data, string $property): bool
    {
        // Check @graph first
        if (isset($data['@graph']) && is_array($data['@graph'])) {
            foreach ($data['@graph'] as $item) {
                if (isset($item[$property])) {
                    return true;
                }
            }
        }

        return isset($data[$property]);
    }

    /**
     * Collect all @id values recursively
     *
     * @param array<string, mixed> $data
     * @param array<string, true> $ids
     * @param array<int, string> $duplicates
     * @param-out array<string, true> $ids
     * @param-out array<int, string> $duplicates
     */
    private function collectIds(array $data, array &$ids, array &$duplicates): void
    {
        if (isset($data['@id']) && is_string($data['@id'])) {
            $id = $data['@id'];
            if (isset($ids[$id])) {
                $duplicates[] = $id;
            } else {
                $ids[$id] = true;
            }
        }

        // Recurse into nested objects
        foreach ($data as $value) {
            if (is_array($value)) {
                $this->collectIds($value, $ids, $duplicates);
            }
        }
    }

    /**
     * Find all image URLs in structured data
     *
     * @param array<string, mixed> $data
     * @return array<int, string>
     */
    private function findImageUrls(array $data): array
    {
        $images = [];
        $imageProperties = ['image', 'thumbnailUrl', 'logo', 'photo', 'contentUrl'];

        foreach ($imageProperties as $prop) {
            if (isset($data[$prop])) {
                $value = $data[$prop];
                if (is_string($value)) {
                    $images[] = $value;
                } elseif (is_array($value) && isset($value['url'])) {
                    $images[] = $value['url'];
                }
            }
        }

        // Recurse
        foreach ($data as $value) {
            if (is_array($value)) {
                $images = array_merge($images, $this->findImageUrls($value));
            }
        }

        return $images;
    }

    /**
     * Validate image URL
     */
    private function isValidImageUrl(string $url): bool
    {
        // Check if it's an absolute URL
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return true;
        }

        // Data URIs are valid
        if (str_starts_with($url, 'data:image/')) {
            return true;
        }

        // Relative URLs are acceptable but not ideal
        if (str_starts_with($url, '/')) {
            return true;
        }

        return false;
    }
}
