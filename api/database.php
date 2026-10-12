<?php
/**
 * Central Database Configuration and Helper Functions
 * Mess Menu Feedback Analyzer - B.Tech Data Science Project
 */

declare(strict_types=1);

// Start PHP session securely if not already started
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

// Database Credentials with Environment Variable support (for Vercel & Cloud DBs)
define('DB_HOST', getenv('DB_HOST') ?: 'sql12.freesqldatabase.com');
define('DB_USER', getenv('DB_USER') ?: 'sql12838781');
define('DB_PASS', getenv('DB_PASS') ?: '66lmY5hzBS');
define('DB_NAME', getenv('DB_NAME') ?: 'sql12838781');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_CHARSET', 'utf8mb4');

/**
 * Returns a singleton PDO database connection.
 * Uses prepared statements and exception handling.
 */
function getDbConnection(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $port = DB_PORT;
        $dsn = "mysql:host=" . DB_HOST . ";port=" . $port . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 15,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error'   => 'Database connection failed. Please ensure MySQL is accessible.',
                'details' => $e->getMessage()
            ]);
            exit;
        }
    }
    return $pdo;
}

/**
 * Serverless JWT/HMAC token generator for Admin sessions
 */
function generateAdminToken(array $admin): string {
    $payload = [
        'id'       => $admin['id'],
        'username' => $admin['username'],
        'name'     => $admin['full_name'],
        'role'     => $admin['role'],
        'exp'      => time() + (86400 * 7)
    ];
    $json = base64_encode(json_encode($payload));
    $secret = getenv('APP_SECRET') ?: 'mess_feedback_analyzer_secret_2026';
    $sig = hash_hmac('sha256', $json, $secret);
    return $json . '.' . $sig;
}

/**
 * Serverless token verification
 */
function verifyAdminToken(?string $token): ?array {
    if (empty($token) || !str_contains($token, '.')) {
        return null;
    }
    [$json, $sig] = explode('.', $token, 2);
    $secret = getenv('APP_SECRET') ?: 'mess_feedback_analyzer_secret_2026';
    if (!hash_equals(hash_hmac('sha256', $json, $secret), $sig)) {
        return null;
    }
    $payload = json_decode(base64_decode($json), true);
    if (!$payload || !isset($payload['exp']) || $payload['exp'] < time()) {
        return null;
    }
    return $payload;
}

/**
 * Extract bearer or cookie token
 */
function getRequestAdminToken(): ?string {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $auth = $headers['Authorization'] ?? $headers['authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+(.*)$/i', $auth, $matches)) {
        return trim($matches[1]);
    }
    return $_COOKIE['admin_auth_token'] ?? null;
}

/**
 * Output JSON response with appropriate headers
 */
function jsonResponse(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Output JSON error response
 */
function jsonError(string $message, int $statusCode = 400, array $extra = []): void {
    jsonResponse(array_merge([
        'success' => false,
        'error'   => $message
    ], $extra), $statusCode);
}

/**
 * Sanitize text input to prevent XSS
 */
function sanitizeInput(?string $data): string {
    if ($data === null) {
        return '';
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Transparent B.Tech Data Science Sentiment Classifier
 * Analyzes polarity of student text using dictionary keyword scoring.
 * 
 * Formula:
 * Polarity Score = (Positive Matches - Negative Matches) / (Total Matches + 1)
 * Normalized to [-1.0, 1.0] range
 */
function classifyFeedbackSentiment(string $text): array {
    $positiveKeywords = [
        'good', 'great', 'tasty', 'fresh', 'excellent', 'delicious',
        'nice', 'amazing', 'superb', 'crispy', 'soft', 'flavorful',
        'satisfying', 'perfect', 'clean', 'hygienic', 'love', 'best', 'well'
    ];

    $negativeKeywords = [
        'bad', 'poor', 'cold', 'oily', 'salty', 'bland', 'tasteless',
        'dirty', 'unhealthy', 'waste', 'sour', 'hard', 'stale', 'smelly',
        'delayed', 'watery', 'undercooked', 'burnt', 'insects', 'hair', 'horrible'
    ];

    $cleanText = strtolower($text);
    $matchedPositive = [];
    $matchedNegative = [];

    foreach ($positiveKeywords as $word) {
        if (preg_match('/\b' . preg_quote($word, '/') . '\b/i', $cleanText)) {
            $matchedPositive[] = $word;
        }
    }

    foreach ($negativeKeywords as $word) {
        if (preg_match('/\b' . preg_quote($word, '/') . '\b/i', $cleanText)) {
            $matchedNegative[] = $word;
        }
    }

    $posCount = count($matchedPositive);
    $negCount = count($matchedNegative);
    $totalMatches = $posCount + $negCount;

    if ($posCount > $negCount) {
        $sentiment = 'Positive';
        $score = min(1.0, round(($posCount - $negCount) / max(1, $totalMatches) * 0.8 + 0.2, 2));
    } elseif ($negCount > $posCount) {
        $sentiment = 'Negative';
        $score = max(-1.0, round(($posCount - $negCount) / max(1, $totalMatches) * 0.8 - 0.2, 2));
    } else {
        $sentiment = 'Neutral';
        $score = 0.00;
    }

    $allMatched = array_merge($matchedPositive, $matchedNegative);

    return [
        'sentiment'        => $sentiment,
        'sentiment_score'  => $score,
        'positive_matches' => $matchedPositive,
        'negative_matches' => $matchedNegative,
        'matched_keywords' => !empty($allMatched) ? implode(', ', $allMatched) : 'None'
    ];
}

/**
 * Data Science Weighted Satisfaction Score Formula:
 * Overall Rating  × 40%
 * Taste           × 20%
 * Quality         × 15%
 * Hygiene         × 10%
 * Freshness       × 10%
 * Quantity        ×  5%
 * 
 * Result normalized to percentage (0 - 100%)
 */
function calculateWeightedSatisfaction(
    float $overall,
    float $taste,
    float $quality,
    float $hygiene,
    float $freshness,
    float $quantity
): float {
    $weightedSum = (
        ($overall   * 0.40) +
        ($taste     * 0.20) +
        ($quality   * 0.15) +
        ($hygiene   * 0.10) +
        ($freshness * 0.10) +
        ($quantity  * 0.05)
    );

    // Normalize out of 5 stars into percentage 0 - 100%
    $percentage = ($weightedSum / 5.0) * 100.0;
    return round(min(100.0, max(0.0, $percentage)), 1);
}

/**
 * Verify CSRF Token for security
 */
function verifyCsrfToken(?string $token): bool {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Get or initialize CSRF Token
 */
function getCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
