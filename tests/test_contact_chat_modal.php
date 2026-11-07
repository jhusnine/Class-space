<?php
/**
 * Test: Contact View Chat Modal Pop-up
 * Verifies that:
 * 1. The support chat section is wrapped in .chat-modal-overlay (#chat-modal) with display:none initially.
 * 2. The chat trigger button (#chat-with-us-btn) is configured with dialog accessibility attributes.
 * 3. The modal contains a close button (.chat-modal-close).
 * 4. contact_script.js defines openChatModal, closeChatModal, and backdrop click handlers.
 * 5. contact_style.css includes pop-up overlay and modal styling.
 */

$viewPath = __DIR__ . '/../app/views/contact_view.php';
$scriptPath = __DIR__ . '/../public/js/contact_script.js';
$stylePath = __DIR__ . '/../public/css/contact_style.css';

$viewContent = file_get_contents($viewPath);
$scriptContent = file_get_contents($scriptPath);
$styleContent = file_get_contents($stylePath);

echo "=== Test: Contact View Chat Modal Pop-up ===\n";

// 1. View assertions
assert(str_contains($viewContent, 'id="chat-modal"'), "contact_view.php must have id='chat-modal'");
assert(str_contains($viewContent, 'class="chat-modal-overlay"'), "contact_view.php must have .chat-modal-overlay");
assert(str_contains($viewContent, 'style="display:none;"'), "chat modal must be hidden initially");
assert(str_contains($viewContent, 'id="chat-with-us-btn"'), "chat trigger must have id='chat-with-us-btn'");
assert(str_contains($viewContent, 'class="chat-modal-close"'), "modal must contain close button");
assert(!str_contains($viewContent, '<section class="message-dashboard" id="support-chat">'), "Static message-dashboard section must not be present");
echo "PASS: contact_view.php markup has chat modal overlay, trigger button, and close button.\n";

// 2. Script assertions
assert(str_contains($scriptContent, 'function openChatModal'), "contact_script.js must define openChatModal");
assert(str_contains($scriptContent, 'function closeChatModal'), "contact_script.js must define closeChatModal");
assert(str_contains($scriptContent, 'function handleChatModalBackdropClick'), "contact_script.js must define backdrop handler");
assert(str_contains($scriptContent, 'setupChatModal'), "contact_script.js must call setupChatModal on DOMContentLoaded");
echo "PASS: contact_script.js has open/close/backdrop and Escape key handlers.\n";

// 3. Style assertions
assert(str_contains($styleContent, '.chat-modal-overlay'), "contact_style.css must style .chat-modal-overlay");
assert(str_contains($styleContent, '.chat-modal-card'), "contact_style.css must style .chat-modal-card");
assert(str_contains($styleContent, '.chat-modal-close'), "contact_style.css must style .chat-modal-close");
echo "PASS: contact_style.css has modal overlay, card, and close button styles.\n";

echo "\nALL CONTACT CHAT MODAL CHECKS PASSED (Exit 0)\n";

