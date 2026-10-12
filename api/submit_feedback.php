<?php
/**
 * Student Anonymous Feedback Submission Endpoint
 * Mess Menu Feedback Analyzer - B.Tech Data Science Project
 */

declare(strict_types=1);
require_once __DIR__ . '/database.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Only POST method is allowed.', 405);
}

// Read JSON payload or form POST
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

// 1. Anti-Spam Honeypot Verification
if (!empty($input['website_hp']) || !empty($input['student_email_hp'])) {
    // Bot detected silently
    jsonResponse(['success' => true, 'message' => 'Feedback submitted successfully.']);
}

// 2. Extract & Validate Core Fields
$menuId = isset($input['menu_id']) ? (int)$input['menu_id'] : 0;
$overallRating = isset($input['overall_rating']) ? (int)$input['overall_rating'] : 0;
$hygieneRating = isset($input['hygiene_rating']) ? (int)$input['hygiene_rating'] : 0;
$temperature = $input['temperature'] ?? '';
$portion = $input['portion'] ?? '';
$wastageLevel = $input['wastage_level'] ?? '';
$wastageReason = sanitizeInput($input['wastage_reason'] ?? '');
$comment = sanitizeInput($input['comment'] ?? '');
$suggestion = sanitizeInput($input['suggestion'] ?? '');
$items = isset($input['items']) && is_array($input['items']) ? $input['items'] : [];
$wastedDishId = !empty($input['wasted_dish_id']) ? (int)$input['wasted_dish_id'] : null;

// Validation rules
if ($menuId <= 0) {
    jsonError('Please select a valid meal.');
}

if ($overallRating < 1 || $overallRating > 5) {
    jsonError('Please provide an overall rating between 1 and 5 stars.');
}

if ($hygieneRating < 1 || $hygieneRating > 5) {
    jsonError('Please provide a mess hygiene rating between 1 and 5 stars.');
}

$validTemperatures = ['Too Cold', 'Acceptable', 'Hot/Fresh'];
if (!in_array($temperature, $validTemperatures, true)) {
    $temperature = 'Acceptable';
}

$validPortions = ['Too Little', 'Appropriate', 'Too Much'];
if (!in_array($portion, $validPortions, true)) {
    $portion = 'Appropriate';
}

$validWastage = ['None', 'Very Little', 'Some', 'A lot'];
if (!in_array($wastageLevel, $validWastage, true)) {
    $wastageLevel = 'None';
}

// 3. Anonymous Session Rate Limiting Check (Duplicate Protection)
$sessionToken = $_COOKIE['anon_session_token'] ?? null;
if (!$sessionToken) {
    $sessionToken = bin2hex(random_bytes(16));
    setcookie('anon_session_token', $sessionToken, [
        'expires'  => time() + 86400 * 7,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}
// Cryptographic one-way hash of session token + menuId
$sessionHash = hash('sha256', $sessionToken . '_' . $menuId);

$pdo = getDbConnection();

// Check if already submitted for this specific menu by this session today
$dupCheck = $pdo->prepare("SELECT id FROM feedback WHERE menu_id = ? AND session_hash = ? AND created_at >= (NOW() - INTERVAL 12 HOUR) LIMIT 1");
$dupCheck->execute([$menuId, $sessionHash]);
if ($dupCheck->fetch()) {
    jsonError('You have already submitted anonymous feedback for this meal today. Thank you for contributing!', 429);
}

// Verify menu exists
$menuStmt = $pdo->prepare("SELECT id, menu_date, meal_type FROM menus WHERE id = ?");
$menuStmt->execute([$menuId]);
$menu = $menuStmt->fetch();
if (!$menu) {
    jsonError('Selected menu does not exist.', 404);
}

// 4. Data Science Sentiment Analysis on Comments
$fullText = trim($comment . ' ' . $suggestion);
$sentimentResult = classifyFeedbackSentiment($fullText);

// 5. Begin Transaction
try {
    $pdo->beginTransaction();

    // Insert into feedback table
    $fbStmt = $pdo->prepare("
        INSERT INTO `feedback` (
            `menu_id`, `overall_rating`, `hygiene_rating`, `temperature`,
            `portion`, `wastage_level`, `wastage_reason`, `comment`,
            `suggestion`, `sentiment`, `sentiment_score`, `session_hash`, `created_at`
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    $fbStmt->execute([
        $menuId,
        $overallRating,
        $hygieneRating,
        $temperature,
        $portion,
        $wastageLevel,
        $wastageReason ?: null,
        $comment ?: null,
        $suggestion ?: null,
        $sentimentResult['sentiment'],
        $sentimentResult['sentiment_score'],
        $sessionHash
    ]);

    $feedbackId = (int)$pdo->lastInsertId();

    // Insert dish-level feedback items
    $fbItemStmt = $pdo->prepare("
        INSERT INTO `feedback_items` (
            `feedback_id`, `menu_item_id`, `taste_rating`,
            `quality_rating`, `quantity_rating`, `freshness_rating`,
            `repeat_preference`, `created_at`
        ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    $validRepeat = ['Yes', 'Maybe', 'No'];

    foreach ($items as $item) {
        $dishId = isset($item['menu_item_id']) ? (int)$item['menu_item_id'] : 0;
        if ($dishId <= 0) continue;

        $tasteRating = min(5, max(1, (int)($item['taste_rating'] ?? $overallRating)));
        $qualRating  = min(5, max(1, (int)($item['quality_rating'] ?? $overallRating)));
        $quanRating  = min(5, max(1, (int)($item['quantity_rating'] ?? 3)));
        $freshRating = min(5, max(1, (int)($item['freshness_rating'] ?? $overallRating)));
        $repeatPref  = in_array($item['repeat_preference'] ?? '', $validRepeat, true) ? $item['repeat_preference'] : 'Yes';

        $fbItemStmt->execute([
            $feedbackId,
            $dishId,
            $tasteRating,
            $qualRating,
            $quanRating,
            $freshRating,
            $repeatPref
        ]);
    }

    // Insert wastage record if wastage occurred
    if ($wastageLevel !== 'None') {
        $wStmt = $pdo->prepare("
            INSERT INTO `wastage_records` (
                `feedback_id`, `menu_item_id`, `wastage_level`, `wastage_reason`, `created_at`
            ) VALUES (?, ?, ?, ?, NOW())
        ");
        $wStmt->execute([
            $feedbackId,
            $wastedDishId,
            $wastageLevel,
            $wastageReason ?: 'Not specified'
        ]);
    }

    // Insert sentiment record
    $sentStmt = $pdo->prepare("
        INSERT INTO `feedback_sentiment` (
            `feedback_id`, `sentiment`, `score`, `matched_keywords`, `created_at`
        ) VALUES (?, ?, ?, ?, NOW())
    ");
    $sentStmt->execute([
        $feedbackId,
        $sentimentResult['sentiment'],
        $sentimentResult['sentiment_score'],
        $sentimentResult['matched_keywords']
    ]);

    $pdo->commit();

    jsonResponse([
        'success'         => true,
        'message'         => 'Thank you! Your feedback has been recorded anonymously.',
        'feedback_id'     => $feedbackId,
        'sentiment'       => $sentimentResult['sentiment'],
        'sentiment_score' => $sentimentResult['sentiment_score'],
        'meal'            => $menu['meal_type'],
        'date'            => $menu['menu_date']
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    jsonError('Failed to record feedback: ' . $e->getMessage(), 500);
}
