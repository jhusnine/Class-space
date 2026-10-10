<?php
/**
 * Test: ClassSpace Homepage Redesign Smoke & Verification Suite
 * Verifies CSS tokens, status edge rules, view markup elements,
 * JS preserved handlers, and functionality matrix items.
 */

echo "=== Homepage Redesign Smoke Test Suite ===\n";

// --- Section 1: CSS Design Tokens & Status Edges ---
$css = file_get_contents(__DIR__ . '/../public/css/homepage_style.css');

$requiredCssSnippets = [
    '#3D9B68',                  // Available institutional green
    '#7B8798',                  // Unavailable slate gray
    'border-left: 4px solid',   // 4px signature status edge
    'repeat(3, 1fr)',           // Predictable 3-column grid
    'aspect-ratio: 16 / 9',     // 16:9 photography ratio
    '.room-status-legend',      // Status legend indicator
    '.quick-reserve-drawer'     // In-page reservation drawer
];

foreach ($requiredCssSnippets as $snippet) {
    if (!str_contains($css, $snippet)) {
        throw new RuntimeException("FAIL [CSS]: Missing required snippet: " . $snippet);
    }
}
echo "PASS: Task 1 CSS tokens and status edges verified.\n";
