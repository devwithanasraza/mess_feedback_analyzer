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

// Database Credentials
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'mess_feedback_analyzer');
define('DB_CHARSET', 'utf8mb4');

/**
 * Returns a singleton PDO database connection.
 * Uses prepared statements and exception handling.
 */
function getDbConnection(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Friendly error message without leaking sensitive server credentials
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error'   => 'Database connection failed. Please ensure MySQL is running and seed.php has been executed.',
                'details' => $e->getMessage()
            ]);
            exit;
        }
    }
    return $pdo;
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
