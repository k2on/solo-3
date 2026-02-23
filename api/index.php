<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ============================================================
// DATABASE CONNECTION (PostgreSQL via environment variable)
// ============================================================
function getDb() {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    // Try multiple env var names (Vercel uses various conventions)
    $url = getenv('POSTGRES_URL')
        ?: getenv('DATABASE_URL')
        ?: getenv('POSTGRES_URL_UNPOOLED')
        ?: getenv('DATABASE_URL_UNPOOLED')
        ?: getenv('SOLO3_DATABASE_URL_UNPOOLED');

    if (!$url) {
        http_response_code(500);
        echo json_encode(['error' => 'Database connection string not configured.']);
        exit;
    }

    // Normalize postgresql:// to postgres:// for parse_url compatibility
    $url = preg_replace('/^postgresql:\/\//', 'postgres://', $url);

    $parsed = parse_url($url);
    $host = $parsed['host'] ?? 'localhost';
    $port = $parsed['port'] ?? 5432;
    $dbname = ltrim($parsed['path'] ?? '/postgres', '/');
    $user = $parsed['user'] ?? 'postgres';
    $pass = $parsed['pass'] ?? '';

    // Parse query string for sslmode
    $queryParams = [];
    if (isset($parsed['query'])) {
        parse_str($parsed['query'], $queryParams);
    }
    $sslmode = $queryParams['sslmode'] ?? 'require';

    // Extract Neon endpoint ID from hostname (e.g. ep-rapid-credit-a41dk64z from ep-rapid-credit-a41dk64z.us-east-1.aws.neon.tech)
    $endpointId = explode('.', $host)[0];
    $options = "endpoint={$endpointId}";

    $dsn = "pgsql:host={$host};port={$port};dbname={$dbname};sslmode={$sslmode};options={$options}";
    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (PDOException $ex) {
        http_response_code(500);
        echo json_encode(['error' => 'Database connection failed: ' . $ex->getMessage()]);
        exit;
    }
    return $pdo;
}

// ============================================================
// SCHEMA & SEED
// ============================================================
function ensureSchema() {
    $db = getDb();

    $db->exec("
        CREATE TABLE IF NOT EXISTS entries (
            id SERIAL PRIMARY KEY,
            date BIGINT NOT NULL,
            book_index INTEGER NOT NULL CHECK (book_index >= 0 AND book_index <= 65),
            chapter INTEGER NOT NULL CHECK (chapter >= 1),
            image_url TEXT NOT NULL DEFAULT '',
            created_at TIMESTAMP DEFAULT NOW(),
            updated_at TIMESTAMP DEFAULT NOW()
        );
    ");

    // Check if seed data is needed
    $count = $db->query("SELECT COUNT(*) FROM entries")->fetchColumn();
    if ((int)$count === 0) {
        seedData($db);
    }
}

function seedData($db) {
    $BOOKS = getBooks();
    $CHAPTER_MAP = getChapterMap();

    // Bible-themed image URLs (Unsplash)
    $IMAGES = [
        'https://images.unsplash.com/photo-1504052434569-70ad5836ab65?w=400&h=300&fit=crop',
        'https://images.unsplash.com/photo-1507842217343-583bb7270b66?w=400&h=300&fit=crop',
        'https://images.unsplash.com/photo-1529070538774-1f31faa38bcb?w=400&h=300&fit=crop',
        'https://images.unsplash.com/photo-1490730141103-6cac27aaab94?w=400&h=300&fit=crop',
        'https://images.unsplash.com/photo-1473448912268-2022ce9509d8?w=400&h=300&fit=crop',
        'https://images.unsplash.com/photo-1510172951991-856a654063f9?w=400&h=300&fit=crop',
        'https://images.unsplash.com/photo-1585829365295-ab7cd400c167?w=400&h=300&fit=crop',
        'https://images.unsplash.com/photo-1544027993-37dbfe43562a?w=400&h=300&fit=crop',
        'https://images.unsplash.com/photo-1509021436665-8f07dbf5bf1d?w=400&h=300&fit=crop',
        'https://images.unsplash.com/photo-1455390582262-044cdead277a?w=400&h=300&fit=crop',
        'https://images.unsplash.com/photo-1516979187457-637abb4f9353?w=400&h=300&fit=crop',
        'https://images.unsplash.com/photo-1532012197267-da84d127e765?w=400&h=300&fit=crop',
        'https://images.unsplash.com/photo-1476234251651-f353703a034d?w=400&h=300&fit=crop',
        'https://images.unsplash.com/photo-1501281668745-f7f57925c3b4?w=400&h=300&fit=crop',
        'https://images.unsplash.com/photo-1508963493744-76fce69379c0?w=400&h=300&fit=crop',
    ];

    $startDate = strtotime("2025-11-01");
    $stmt = $db->prepare("INSERT INTO entries (date, book_index, chapter, image_url) VALUES (:date, :book, :chapter, :image)");

    for ($i = 0; $i < 30; $i++) {
        $bookIndex = $i % count($BOOKS);
        $maxChapter = $CHAPTER_MAP[$bookIndex];
        $chapter = rand(1, $maxChapter);
        $dateMs = ($startDate + (86400 * $i)) * 1000;
        $image = $IMAGES[$i % count($IMAGES)];

        $stmt->execute([
            ':date' => $dateMs,
            ':book' => $bookIndex,
            ':chapter' => $chapter,
            ':image' => $image,
        ]);
    }
}

// ============================================================
// CONSTANTS
// ============================================================
function getBooks() {
    return [
        'Genesis','Exodus','Leviticus','Numbers','Deuteronomy','Joshua','Judges','Ruth',
        '1 Samuel','2 Samuel','1 Kings','2 Kings','1 Chronicles','2 Chronicles','Ezra',
        'Nehemiah','Esther','Job','Psalms','Proverbs','Ecclesiastes','Song of Solomon',
        'Isaiah','Jeremiah','Lamentations','Ezekiel','Daniel','Hosea','Joel','Amos',
        'Obadiah','Jonah','Micah','Nahum','Habakkuk','Zephaniah','Haggai','Zechariah',
        'Malachi','Matthew','Mark','Luke','John','Acts','Romans','1 Corinthians',
        '2 Corinthians','Galatians','Ephesians','Philippians','Colossians',
        '1 Thessalonians','2 Thessalonians','1 Timothy','2 Timothy','Titus','Philemon',
        'Hebrews','James','1 Peter','2 Peter','1 John','2 John','3 John','Jude','Revelation'
    ];
}

function getChapterMap() {
    return [50,40,27,36,34,24,21,4,31,24,22,25,29,36,10,13,10,42,150,31,12,8,66,52,5,48,12,14,3,9,1,4,7,3,3,3,2,14,4,28,16,24,21,28,16,16,13,6,6,4,4,5,3,6,4,3,1,13,5,5,3,5,1,1,1,22];
}

// ============================================================
// VALIDATION
// ============================================================
function validateEntry($data) {
    $errors = [];
    $BOOKS = getBooks();
    $CHAPTER_MAP = getChapterMap();

    if (!isset($data['date']) || !is_numeric($data['date'])) {
        $errors[] = "A valid date is required.";
    }

    if (!isset($data['start']) || !is_array($data['start'])) {
        $errors[] = "Start location is required.";
    } else {
        if (!isset($data['start']['book']) || $data['start']['book'] === "" || !is_numeric($data['start']['book'])) {
            $errors[] = "A valid book selection is required.";
        } else {
            $bookIndex = intval($data['start']['book']);
            if ($bookIndex < 0 || $bookIndex > 65) {
                $errors[] = "Book index must be between 0 and 65.";
            }
        }

        if (!isset($data['start']['chapter']) || !is_numeric($data['start']['chapter'])) {
            $errors[] = "A valid chapter number is required.";
        } else {
            $chapter = intval($data['start']['chapter']);
            if ($chapter < 1) {
                $errors[] = "Chapter must be at least 1.";
            }
            if (isset($data['start']['book']) && is_numeric($data['start']['book'])) {
                $bi = intval($data['start']['book']);
                if ($bi >= 0 && $bi <= 65 && $chapter > $CHAPTER_MAP[$bi]) {
                    $errors[] = "{$BOOKS[$bi]} only has {$CHAPTER_MAP[$bi]} chapters.";
                }
            }
        }
    }

    // image_url is optional — we allow empty string
    if (isset($data['image_url']) && strlen($data['image_url']) > 2048) {
        $errors[] = "Image URL is too long (max 2048 characters).";
    }

    return $errors;
}

// ============================================================
// ROUTING
// ============================================================
ensureSchema();

$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$BOOKS = getBooks();
$CHAPTER_MAP = getChapterMap();

// ---- GET /api/stats ----
if (preg_match('#^/api/stats$#', $uri)) {
    if ($method === 'GET') {
        $db = getDb();

        $total = (int)$db->query("SELECT COUNT(*) FROM entries")->fetchColumn();

        // Unique books
        $uniqueBooks = (int)$db->query("SELECT COUNT(DISTINCT book_index) FROM entries")->fetchColumn();

        // OT (book_index < 39) vs NT
        $otCount = (int)$db->query("SELECT COUNT(*) FROM entries WHERE book_index < 39")->fetchColumn();
        $ntCount = $total - $otCount;

        // Chapters read estimation (sum of differences between consecutive entries by date)
        $rows = $db->query("SELECT book_index, chapter, date FROM entries ORDER BY date DESC")->fetchAll();
        $totalChapters = 0;
        if (count($rows) > 1) {
            for ($i = 0; $i < count($rows) - 1; $i++) {
                $currAbs = 0;
                for ($j = 0; $j < intval($rows[$i]['book_index']); $j++) {
                    $currAbs += $CHAPTER_MAP[$j];
                }
                $currAbs += intval($rows[$i]['chapter']);

                $nextAbs = 0;
                for ($j = 0; $j < intval($rows[$i + 1]['book_index']); $j++) {
                    $nextAbs += $CHAPTER_MAP[$j];
                }
                $nextAbs += intval($rows[$i + 1]['chapter']);

                $totalChapters += $currAbs - $nextAbs;
            }
        }

        // Longest streak
        $dateRows = $db->query("SELECT DISTINCT TO_CHAR(TO_TIMESTAMP(date / 1000), 'YYYY-MM-DD') AS day FROM entries ORDER BY day")->fetchAll();
        $maxStreak = 0;
        $currentStreak = 1;
        for ($i = 1; $i < count($dateRows); $i++) {
            $prev = strtotime($dateRows[$i - 1]['day']);
            $curr = strtotime($dateRows[$i]['day']);
            if (($curr - $prev) === 86400) {
                $currentStreak++;
            } else {
                if ($currentStreak > $maxStreak) $maxStreak = $currentStreak;
                $currentStreak = 1;
            }
        }
        if ($currentStreak > $maxStreak) $maxStreak = $currentStreak;

        // Average chapters per entry (domain-specific stat)
        $avgChapter = $total > 0 ? round((float)$db->query("SELECT AVG(chapter) FROM entries")->fetchColumn(), 1) : 0;

        echo json_encode([
            'totalEntries' => $total,
            'totalChapters' => $totalChapters,
            'uniqueBooks' => $uniqueBooks,
            'otEntries' => $otCount,
            'ntEntries' => $ntCount,
            'longestStreak' => $maxStreak,
            'avgChapter' => $avgChapter,
        ]);
        exit;
    }
}

// ---- /api/entries ----
elseif (preg_match('#^/api/entries(?:/(\d+))?$#', $uri, $matches)) {
    $id = isset($matches[1]) ? intval($matches[1]) : null;
    $db = getDb();

    switch ($method) {

        // ---- GET (list or single) ----
        case 'GET':
            if ($id !== null) {
                $stmt = $db->prepare("SELECT * FROM entries WHERE id = :id");
                $stmt->execute([':id' => $id]);
                $row = $stmt->fetch();
                if (!$row) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Entry not found']);
                    exit;
                }
                echo json_encode(formatRow($row));
                exit;
            }

            // List with paging, search, sort, page size
            $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
            $perPage = isset($_GET['perPage']) ? intval($_GET['perPage']) : 10;
            $allowedSizes = [5, 10, 20, 50];
            if (!in_array($perPage, $allowedSizes)) $perPage = 10;

            $search = isset($_GET['search']) ? trim($_GET['search']) : '';
            $sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'date_desc';

            // Build WHERE clause for search
            $where = '';
            $params = [];
            if ($search !== '') {
                // Search by book name: match against book index
                $matchedBooks = [];
                foreach ($BOOKS as $idx => $name) {
                    if (stripos($name, $search) !== false) {
                        $matchedBooks[] = $idx;
                    }
                }
                if (count($matchedBooks) > 0) {
                    $placeholders = implode(',', $matchedBooks);
                    $where = "WHERE book_index IN ({$placeholders})";
                } else {
                    // Try matching chapter number
                    if (is_numeric($search)) {
                        $where = "WHERE chapter = :searchChapter";
                        $params[':searchChapter'] = intval($search);
                    } else {
                        // No match — return empty
                        $where = "WHERE 1=0";
                    }
                }
            }

            // Sort
            $orderBy = "ORDER BY date DESC";
            switch ($sortBy) {
                case 'date_asc': $orderBy = "ORDER BY date ASC"; break;
                case 'date_desc': $orderBy = "ORDER BY date DESC"; break;
                case 'book_asc': $orderBy = "ORDER BY book_index ASC, chapter ASC"; break;
                case 'book_desc': $orderBy = "ORDER BY book_index DESC, chapter DESC"; break;
                case 'chapter_asc': $orderBy = "ORDER BY chapter ASC"; break;
                case 'chapter_desc': $orderBy = "ORDER BY chapter DESC"; break;
            }

            // Count
            $countSql = "SELECT COUNT(*) FROM entries {$where}";
            $countStmt = $db->prepare($countSql);
            $countStmt->execute($params);
            $totalEntries = (int)$countStmt->fetchColumn();

            $totalPages = max(1, ceil($totalEntries / $perPage));
            $page = min($page, $totalPages);
            $offset = ($page - 1) * $perPage;

            $sql = "SELECT * FROM entries {$where} {$orderBy} LIMIT :limit OFFSET :offset";
            $stmt = $db->prepare($sql);
            foreach ($params as $k => $v) {
                $stmt->bindValue($k, $v);
            }
            $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll();

            $entries = array_map('formatRow', $rows);

            echo json_encode([
                'entries' => $entries,
                'page' => $page,
                'perPage' => $perPage,
                'totalPages' => $totalPages,
                'totalEntries' => $totalEntries,
            ]);
            exit;

        // ---- POST (create) ----
        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            $errors = validateEntry($input);
            if (count($errors) > 0) {
                http_response_code(400);
                echo json_encode(['errors' => $errors]);
                exit;
            }

            $imageUrl = isset($input['image_url']) && $input['image_url'] !== '' ? $input['image_url'] : '';

            $stmt = $db->prepare("INSERT INTO entries (date, book_index, chapter, image_url) VALUES (:date, :book, :chapter, :image) RETURNING *");
            $stmt->execute([
                ':date' => intval($input['date']),
                ':book' => intval($input['start']['book']),
                ':chapter' => intval($input['start']['chapter']),
                ':image' => $imageUrl,
            ]);
            $row = $stmt->fetch();

            http_response_code(201);
            echo json_encode(formatRow($row));
            exit;

        // ---- PUT (update) ----
        case 'PUT':
            if ($id === null) {
                http_response_code(400);
                echo json_encode(['error' => 'Entry ID required']);
                exit;
            }
            $input = json_decode(file_get_contents('php://input'), true);
            $errors = validateEntry($input);
            if (count($errors) > 0) {
                http_response_code(400);
                echo json_encode(['errors' => $errors]);
                exit;
            }

            $imageUrl = isset($input['image_url']) ? $input['image_url'] : '';

            $stmt = $db->prepare("UPDATE entries SET date = :date, book_index = :book, chapter = :chapter, image_url = :image, updated_at = NOW() WHERE id = :id RETURNING *");
            $stmt->execute([
                ':date' => intval($input['date']),
                ':book' => intval($input['start']['book']),
                ':chapter' => intval($input['start']['chapter']),
                ':image' => $imageUrl,
                ':id' => $id,
            ]);
            $row = $stmt->fetch();

            if (!$row) {
                http_response_code(404);
                echo json_encode(['error' => 'Entry not found']);
                exit;
            }

            echo json_encode(formatRow($row));
            exit;

        // ---- DELETE ----
        case 'DELETE':
            if ($id === null) {
                http_response_code(400);
                echo json_encode(['error' => 'Entry ID required']);
                exit;
            }
            $stmt = $db->prepare("DELETE FROM entries WHERE id = :id");
            $stmt->execute([':id' => $id]);
            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(['error' => 'Entry not found']);
                exit;
            }
            echo json_encode(['success' => true]);
            exit;
    }
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Route not found']);
}

// ============================================================
// HELPERS
// ============================================================
function formatRow($row) {
    return [
        'id' => (int)$row['id'],
        'date' => (int)$row['date'],
        'start' => [
            'book' => (int)$row['book_index'],
            'chapter' => (int)$row['chapter'],
        ],
        'image_url' => $row['image_url'] ?? '',
    ];
}
