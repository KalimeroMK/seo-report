<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Actions\Misc;

use KalimeroMK\SeoReport\Actions\AnalysisActionInterface;
use KalimeroMK\SeoReport\Actions\AnalysisContext;

/**
 * Basic accessibility checks for WCAG compliance.
 */
final class AccessibilityAction implements AnalysisActionInterface
{
    public function handle(AnalysisContext $context): array
    {
        $domDocument = $context->getDom();
        $imageAlts = (array) $context->getData('image_alts', []);

        $result = [];

        $result['form_labels'] = $this->checkFormLabels($domDocument);
        $result['skip_navigation'] = $this->checkSkipNavigation($domDocument);
        $result['aria_usage'] = $this->checkAriaUsage($domDocument);
        $result['heading_hierarchy'] = $this->checkHeadingHierarchy($context->getData('headings', []));
        $result['link_text_quality'] = $this->checkLinkTextQuality($domDocument);
        $result['table_accessibility'] = $this->checkTableAccessibility($domDocument);

        return $result;
    }

    /**
     * Check for form inputs without labels
     *
     * @return array<string, mixed>
     */
    private function checkFormLabels(\DOMDocument $domDocument): array
    {
        $inputsWithoutLabels = [];

        foreach ($domDocument->getElementsByTagName('input') as $input) {
            $type = strtolower($input->getAttribute('type'));

            if (in_array($type, ['hidden', 'submit', 'button', 'image', 'reset'], true)) {
                continue;
            }

            $id = $input->getAttribute('id');
            $ariaLabel = $input->getAttribute('aria-label');
            $ariaLabelledBy = $input->getAttribute('aria-labelledby');
            $hasLabel = false;

            if ($ariaLabel !== '' || $ariaLabelledBy !== '') {
                $hasLabel = true;
            }

            if ($id !== '' && !$hasLabel) {
                foreach ($domDocument->getElementsByTagName('label') as $label) {
                    if ($label->getAttribute('for') === $id) {
                        $hasLabel = true;
                        break;
                    }
                }
            }

            if (!$hasLabel) {
                $parent = $input->parentNode;
                if ($parent !== null && $parent->nodeName === 'label') {
                    $hasLabel = true;
                }
            }

            $placeholder = $input->getAttribute('placeholder');

            if (!$hasLabel && $placeholder === '') {
                $inputsWithoutLabels[] = [
                    'type' => $type,
                    'id' => $id ?: null,
                    'name' => $input->getAttribute('name') ?: null,
                ];
            }
        }

        foreach (['select', 'textarea'] as $tagName) {
            foreach ($domDocument->getElementsByTagName($tagName) as $element) {
                $id = $element->getAttribute('id');
                $ariaLabel = $element->getAttribute('aria-label');
                $hasLabel = $ariaLabel !== '';

                if ($id !== '' && !$hasLabel) {
                    foreach ($domDocument->getElementsByTagName('label') as $label) {
                        if ($label->getAttribute('for') === $id) {
                            $hasLabel = true;
                            break;
                        }
                    }
                }

                if (!$hasLabel) {
                    $inputsWithoutLabels[] = [
                        'type' => $tagName,
                        'id' => $id ?: null,
                        'name' => $element->getAttribute('name') ?: null,
                    ];
                }
            }
        }

        $result = [
            'passed' => count($inputsWithoutLabels) === 0,
            'importance' => 'high',
            'value' => [
                'inputs_without_labels' => count($inputsWithoutLabels),
            ],
        ];

        if (count($inputsWithoutLabels) > 0) {
            $result['errors'] = [
                'message' => 'Form inputs missing labels',
                'count' => count($inputsWithoutLabels),
                'examples' => array_slice($inputsWithoutLabels, 0, 5),
                'recommendation' => 'All form inputs must have associated labels for screen readers',
            ];
        }

        return $result;
    }

    /**
     * Check for skip navigation link
     *
     * @return array<string, mixed>
     */
    private function checkSkipNavigation(\DOMDocument $domDocument): array
    {
        $hasSkipLink = false;
        $skipLinkHref = null;

        foreach ($domDocument->getElementsByTagName('a') as $link) {
            $text = strtolower(trim($link->textContent));
            $href = $link->getAttribute('href');

            if (str_contains($text, 'skip') || 
                str_contains($text, 'jump to') ||
                str_contains($text, 'main content')) {
                $hasSkipLink = true;
                $skipLinkHref = $href;
                break;
            }
        }

        $result = [
            'passed' => $hasSkipLink,
            'importance' => 'medium',
            'value' => [
                'has_skip_link' => $hasSkipLink,
                'target' => $skipLinkHref,
            ],
        ];

        if (!$hasSkipLink) {
            $result['warnings'] = [
                'message' => 'No skip navigation link found',
                'recommendation' => 'Add a "Skip to main content" link for keyboard users',
                'example' => '<a href="#main-content">Skip to main content</a>',
            ];
        }

        return $result;
    }

    /**
     * Check ARIA usage
     *
     * @return array<string, mixed>
     */
    private function checkAriaUsage(\DOMDocument $domDocument): array
    {
        $ariaElements = [];
        $ariaIssues = [];

        $validRoles = [
            'alert', 'alertdialog', 'application', 'article', 'banner', 'button',
            'cell', 'checkbox', 'columnheader', 'combobox', 'complementary',
            'contentinfo', 'definition', 'dialog', 'directory', 'document',
            'feed', 'figure', 'form', 'grid', 'gridcell', 'group', 'heading',
            'img', 'link', 'list', 'listbox', 'listitem', 'log', 'main',
            'marquee', 'math', 'menu', 'menubar', 'menuitem', 'menuitemcheckbox',
            'menuitemradio', 'navigation', 'none', 'note', 'option', 'presentation',
            'progressbar', 'radio', 'radiogroup', 'region', 'row', 'rowgroup',
            'rowheader', 'scrollbar', 'search', 'searchbox', 'separator',
            'slider', 'spinbutton', 'status', 'switch', 'tab', 'table', 'tablist',
            'tabpanel', 'term', 'textbox', 'timer', 'toolbar', 'tooltip', 'tree',
            'treegrid', 'treeitem',
        ];

        foreach ($domDocument->getElementsByTagName('*') as $element) {
            $role = $element->getAttribute('role');
            $ariaLabel = $element->getAttribute('aria-label');
            $ariaLabelledBy = $element->getAttribute('aria-labelledby');
            $ariaHidden = $element->getAttribute('aria-hidden');

            if ($role !== '' || $ariaLabel !== '' || $ariaLabelledBy !== '' || $ariaHidden !== '') {
                $ariaElements[] = [
                    'tag' => $element->nodeName,
                    'role' => $role ?: null,
                    'aria_label' => $ariaLabel ?: null,
                ];

                // Check for invalid role
                if ($role !== '' && !in_array($role, $validRoles, true)) {
                    $ariaIssues[] = [
                        'type' => 'invalid_role',
                        'element' => $element->nodeName,
                        'role' => $role,
                    ];
                }

                // Check for empty aria-label
                if ($ariaLabel === '') {
                    $ariaIssues[] = [
                        'type' => 'empty_aria_label',
                        'element' => $element->nodeName,
                    ];
                }
            }
        }

        $result = [
            'passed' => count($ariaIssues) === 0,
            'importance' => 'low',
            'value' => [
                'aria_elements_count' => count($ariaElements),
                'issues_found' => count($ariaIssues),
            ],
        ];

        if (count($ariaIssues) > 0) {
            $result['errors'] = [
                'message' => 'ARIA usage issues detected',
                'count' => count($ariaIssues),
                'examples' => array_slice($ariaIssues, 0, 5),
            ];
        }

        return $result;
    }

    /**
     * Check heading hierarchy
     *
     * @param array<string, mixed> $headings
     * @return array<string, mixed>
     */
    private function checkHeadingHierarchy(array $headings): array
    {
        $issues = [];
        $lastLevel = 0;

        for ($i = 1; $i <= 6; $i++) {
            $levelHeadings = $headings['h' . $i] ?? [];
            $count = count($levelHeadings);

            if ($count > 0) {
                // Check for skipped levels
                if ($lastLevel > 0 && $i > $lastLevel + 1) {
                    $issues[] = "Skipped from H{$lastLevel} to H{$i}";
                }

                // Check for empty headings
                foreach ($levelHeadings as $heading) {
                    if (trim($heading) === '') {
                        $issues[] = "Empty H{$i} tag";
                    }
                }

                $lastLevel = $i;
            }
        }

        $result = [
            'passed' => count($issues) === 0,
            'importance' => 'medium',
            'value' => [
                'hierarchy_issues' => count($issues),
            ],
        ];

        if (count($issues) > 0) {
            $result['errors'] = [
                'message' => 'Heading hierarchy issues',
                'issues' => array_slice($issues, 0, 5),
                'recommendation' => 'Use proper H1-H6 hierarchy without skipping levels',
            ];
        }

        return $result;
    }

    /**
     * Check link text quality
     *
     * @return array<string, mixed>
     */
    private function checkLinkTextQuality(\DOMDocument $domDocument): array
    {
        $poorLinkTexts = [];
        $genericTexts = ['click here', 'read more', 'learn more', 'here', 'link', 'more'];

        foreach ($domDocument->getElementsByTagName('a') as $link) {
            $text = strtolower(trim($link->textContent));
            $hasImage = $link->getElementsByTagName('img')->length > 0;
            $ariaLabel = $link->getAttribute('aria-label');

            if ($ariaLabel !== '') {
                continue;
            }

            if (in_array($text, $genericTexts, true) || strlen($text) < 3) {
                $href = $link->getAttribute('href');
                $poorLinkTexts[] = [
                    'text' => $text,
                    'href' => $href,
                ];
            }

            if ($hasImage) {
                foreach ($link->getElementsByTagName('img') as $img) {
                    $alt = $img->getAttribute('alt');
                    if ($alt === '') {
                        $poorLinkTexts[] = [
                            'text' => '[image without alt]',
                            'href' => $link->getAttribute('href'),
                        ];
                    }
                }
            }
        }

        $result = [
            'passed' => count($poorLinkTexts) === 0,
            'importance' => 'medium',
            'value' => [
                'poor_link_texts' => count($poorLinkTexts),
            ],
        ];

        if (count($poorLinkTexts) > 0) {
            $result['errors'] = [
                'message' => 'Links with poor text detected',
                'count' => count($poorLinkTexts),
                'examples' => array_slice($poorLinkTexts, 0, 5),
                'recommendation' => 'Use descriptive link text instead of "click here" or "read more"',
            ];
        }

        return $result;
    }

    /**
     * Check table accessibility
     *
     * @return array<string, mixed>
     */
    private function checkTableAccessibility(\DOMDocument $domDocument): array
    {
        $issues = [];

        foreach ($domDocument->getElementsByTagName('table') as $table) {
            $hasTh = $table->getElementsByTagName('th')->length > 0;
            $hasCaption = $table->getElementsByTagName('caption')->length > 0;
            $hasScope = false;

            foreach ($table->getElementsByTagName('th') as $th) {
                if ($th->getAttribute('scope') !== '') {
                    $hasScope = true;
                    break;
                }
            }

            if (!$hasTh) {
                $issues[] = 'Table without header cells (th)';
            }

            if (!$hasCaption) {
                $issues[] = 'Table without caption';
            }

            if (!$hasScope && $hasTh) {
                $issues[] = 'Table headers without scope attribute';
            }
        }

        $result = [
            'passed' => count($issues) === 0,
            'importance' => 'medium',
            'value' => [
                'table_issues' => count($issues),
            ],
        ];

        if (count($issues) > 0) {
            $result['warnings'] = [
                'message' => 'Table accessibility issues',
                'issues' => array_slice($issues, 0, 5),
                'recommendation' => 'Use th elements, caption, and scope attributes for tables',
            ];
        }

        return $result;
    }
}
