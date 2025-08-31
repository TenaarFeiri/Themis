<?php
declare(strict_types=1);
namespace Themis\System;

// Essential imports
use Themis\System\ThemisContainer;
use Themis\System\DataContainer;
use Themis\System\DatabaseOperator; // Should be set up in ThemisContainer already.

class Dialogs
{
    private ?ThemisContainer $container = null;
    private ?DataContainer $dataContainer = null;
    private ?DatabaseOperator $dbOperator = null;

    private const DIALOG_MENUS_DIRECTORY = '/Dialogs/';

    public function __construct(ThemisContainer $container) {
        $this->container = $container;
        $this->dataContainer = $container->get('dataContainer');
        $this->dbOperator = $container->get('dbOperator');
    }

    /**
     * Prepare menu options for the viewer.
     *
     * Accepts $options where each element is either a string label or an array
     * with keys ['label' => string, 'action' => string]. Returns an array with:
     *  - 'labels' => sequential array of viewer-ready labels (ordered for the viewer grid)
     *  - 'mapping' => [ token => action ] map for server-side resolution
     *
     * @param bool $dedupe If true (default) remove duplicate visible labels using sanitizeLabel();
     *                    keep the first occurrence. Set false to preserve duplicates.
     */
    public function formatForViewer(array $options, string $tokenPrefix = '', int $page = 0, bool $dedupe = true): array
    {
        // Viewer index mapping (visual order -> viewer index)
        $visualToViewerIndex = [9, 10, 11, 6, 7, 8, 3, 4, 5, 0, 1, 2];
        $maxButtons = count($visualToViewerIndex); // 12

        // Optionally deduplicate options by sanitized label (LSL uses string matching).
        // Keep first occurrence when deduplication is enabled.
        if ($dedupe) {
            $seen = [];
            $deduped = [];
            foreach ($options as $opt) {
                if (is_string($opt)) {
                    $label = $opt;
                } elseif (is_array($opt)) {
                    $label = $opt['label'] ?? '';
                } else {
                    $label = (string)$opt;
                }
                $slabel = $this->sanitizeLabel($label);
                if (isset($seen[$slabel])) {
                    continue; // skip duplicates
                }
                $seen[$slabel] = true;
                $deduped[] = $opt;
            }
        } else {
            $deduped = $options;
        }

        // Pagination settings: 9 items per page, bottom row reserved for controls
        $itemsPerPage = 9;
        $totalItems = count($deduped);
        $totalPages = (int)ceil($totalItems / $itemsPerPage);
        if ($totalPages < 1) $totalPages = 1;
        // clamp page
        $page = max(0, min($page, $totalPages - 1));

        // slice options for this page
        $start = $page * $itemsPerPage;
        $pageItems = array_slice($deduped, $start, $itemsPerPage);
        $count = count($pageItems);

        // token width (zero-pad) based on itemsPerPage
        $tokenWidth = max(2, strlen((string)($itemsPerPage - 1)));

        // prepare viewer labels array (always 12 entries)
        $viewerLabels = array_fill(0, $maxButtons, ' ');
        $mapping = [];

        // place page items into viewer slots (visual order) using visualToViewerIndex
        foreach (array_values($pageItems) as $i => $opt) {
            if (is_string($opt)) {
                $label = $opt;
                $action = null;
            } elseif (is_array($opt)) {
                $label = $opt['label'] ?? '';
                $action = $opt['action'] ?? null;
            } else {
                $label = (string)$opt;
                $action = null;
            }

            $token = str_pad((string)$i, $tokenWidth, '0', STR_PAD_LEFT);
            if ($tokenPrefix !== '') {
                $token = $tokenPrefix . $token;
            }

            $display = $token . '|' . $this->sanitizeLabel($label);

            $viewerIndex = $visualToViewerIndex[$i] ?? null;
            if ($viewerIndex === null) {
                continue;
            }
            $viewerLabels[$viewerIndex] = $display;
            $mapping[$token] = $action;
        }

        // Build pagination controls on bottom row (viewer indices 0,1,2)
        // Left control: previous page or boxed empty
        $leftLabel = $page > 0 ? '<-' : '[ ]';
        $centerLabel = 'Cancel';
        $rightLabel = $page < $totalPages - 1 ? '->' : '[ ]';

        // tokens for pagination controls
        $prevToken = ($tokenPrefix !== '' ? $tokenPrefix : '') . 'PGL';
        $cancelToken = ($tokenPrefix !== '' ? $tokenPrefix : '') . 'PGC';
        $nextToken = ($tokenPrefix !== '' ? $tokenPrefix : '') . 'PGR';

        $viewerLabels[0] = $prevToken . '|' . $leftLabel;
        $viewerLabels[1] = $cancelToken . '|' . $centerLabel;
        $viewerLabels[2] = $nextToken . '|' . $rightLabel;

    // map pagination actions (by token)
    // Return deltas so LSL can increment/decrement client-side and server
    // can clamp to the nearest valid page if needed.
    $mapping[$prevToken] = $page > 0 ? "page_delta:-1" : null;
    $mapping[$cancelToken] = 'cancel';
    $mapping[$nextToken] = $page < $totalPages - 1 ? "page_delta:+1" : null;

    // Also map by visible label text so a simple POST of the displayed label
    // (for example "<-" or "->" or "Cancel") will resolve correctly.
    // This keeps POST-based viewers simple and avoids overcomplicating tokens.
    $mapping[$leftLabel] = $mapping[$prevToken];
    $mapping[$centerLabel] = $mapping[$cancelToken];
    $mapping[$rightLabel] = $mapping[$nextToken];

        return ['labels' => $viewerLabels, 'mapping' => $mapping, 'page' => $page, 'totalPages' => $totalPages];
    }

    /**
     * Resolve a returned viewer label against a mapping produced by formatForViewer().
     * Returns the action string or null if not found.
     */
    public function resolveSelection(string $returnedLabel, array $mapping): ?string
    {
        // Extract left-anchored token: e.g., "01|Alice"
        if (preg_match('/^([A-Za-z0-9_-]{2,8})\|/', $returnedLabel, $m)) {
            $token = $m[1];
            return $mapping[$token] ?? null;
        }

        // If we didn't get a token, try to extract the label portion after the pipe
        // e.g. "PGL|<-" -> "<-" and look it up in the mapping (we populate label keys).
        if (preg_match('/\|(.+)$/u', $returnedLabel, $m)) {
            $labelPart = trim($m[1]);
            // direct lookup by label
            if (array_key_exists($labelPart, $mapping)) {
                return $mapping[$labelPart];
            }
            // try sanitized label
            $s = $this->sanitizeLabel($labelPart);
            if (array_key_exists($s, $mapping)) {
                return $mapping[$s];
            }
        }

        // Final attempt: exact match of whole returnedLabel (some viewers may omit the token)
        $r = trim($returnedLabel);
        if (array_key_exists($r, $mapping)) {
            return $mapping[$r];
        }

        return null;
    }

    private function sanitizeLabel(string $label): string
    {
        $label = preg_replace('/[\x00-\x1F\x7F]+/u', '', $label);
        $label = preg_replace('/\s+/', ' ', $label);
        $label = trim($label);
        // limit to avoid viewer truncation
        if (function_exists('mb_substr')) {
            return mb_substr($label, 0, 48);
        }
        return substr($label, 0, 48);
    }
}
