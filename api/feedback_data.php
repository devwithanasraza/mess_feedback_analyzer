<?php
/**
 * Anonymous Feedback Records API Endpoint
 * Mess Menu Feedback Analyzer - B.Tech Data Science Project
 * Provides filterable, searchable, paginated feedback records.
 */

declare(strict_types=1);
require_once __DIR__ . '/database.php';

header('Content-Type: application/json; charset=utf-8');

$pdo = getDbConnection();

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = max(5, min(100, (int)($_GET['limit'] ?? 15)));
$offset = ($page - 1) * $limit;

$dateFrom = $_GET['date_from'] ?? null;
$dateTo   = $_GET['date_to'] ?? null;
$mealType = $_GET['meal_type'] ?? null;
$sentiment = $_GET['sentiment'] ?? null;
$wastageLevel = $_GET['wastage_level'] ?? null;
$minRating = isset($_GET['rating']) && $_GET['rating'] !== '' ? (int)$_GET['rating'] : null;
$search = trim($_GET['search'] ?? '');
$feedbackId = isset($_GET['id']) ? (int)$_GET['id'] : null;

try {
    // If specific feedback ID requested for detailed inspection
    if ($feedbackId) {
        $stmt = $pdo->prepare("
            SELECT f.*, m.menu_date, m.meal_type, m.serving_time
            FROM feedback f
            JOIN menus m ON m.id = f.menu_id
            WHERE f.id = ?
            LIMIT 1
        ");
        $stmt->execute([$feedbackId]);
        $fb = $stmt->fetch();
        if (!$fb) {
            jsonError('Feedback record not found.', 404);
        }

        // Get dish items
        $itemStmt = $pdo->prepare("
            SELECT fi.*, mi.item_name, mi.category, mi.is_veg
            FROM feedback_items fi
            JOIN menu_items mi ON mi.id = fi.menu_item_id
            WHERE fi.feedback_id = ?
            ORDER BY mi.category ASC, mi.id ASC
        ");
        $itemStmt->execute([$feedbackId]);
        $fb['items'] = $itemStmt->fetchAll();

        // Get sentiment record
        $sentStmt = $pdo->prepare("SELECT * FROM feedback_sentiment WHERE feedback_id = ? LIMIT 1");
        $sentStmt->execute([$feedbackId]);
        $fb['sentiment_analysis'] = $sentStmt->fetch();

        // Get wastage record
        $wasteStmt = $pdo->prepare("
            SELECT wr.*, mi.item_name
            FROM wastage_records wr
            LEFT JOIN menu_items mi ON mi.id = wr.menu_item_id
            WHERE wr.feedback_id = ?
            LIMIT 1
        ");
        $wasteStmt->execute([$feedbackId]);
        $fb['wastage_detail'] = $wasteStmt->fetch();

        jsonResponse([
            'success'  => true,
            'feedback' => $fb
        ]);
    }

    // Build query with filters
    $where = ["1=1"];
    $params = [];

    if ($dateFrom) {
        $where[] = "DATE(f.created_at) >= ?";
        $params[] = $dateFrom;
    }
    if ($dateTo) {
        $where[] = "DATE(f.created_at) <= ?";
        $params[] = $dateTo;
    }
    if ($mealType && $mealType !== 'All') {
        $where[] = "m.meal_type = ?";
        $params[] = $mealType;
    }
    if ($sentiment && $sentiment !== 'All') {
        $where[] = "f.sentiment = ?";
        $params[] = $sentiment;
    }
    if ($wastageLevel && $wastageLevel !== 'All') {
        $where[] = "f.wastage_level = ?";
        $params[] = $wastageLevel;
    }
    if ($minRating !== null && $minRating > 0) {
        $where[] = "f.overall_rating = ?";
        $params[] = $minRating;
    }
    if ($search !== '') {
        $where[] = "(f.comment LIKE ? OR f.suggestion LIKE ? OR f.wastage_reason LIKE ?)";
        $term = "%{$search}%";
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
    }

    $whereSql = implode(' AND ', $where);

    // Count total matching
    $countStmt = $pdo->prepare("
        SELECT COUNT(f.id)
        FROM feedback f
        JOIN menus m ON m.id = f.menu_id
        WHERE {$whereSql}
    ");
    $countStmt->execute($params);
    $totalRecords = (int)$countStmt->fetchColumn();

    // Fetch records
    $dataStmt = $pdo->prepare("
        SELECT 
            f.id,
            f.menu_id,
            m.menu_date,
            m.meal_type,
            f.overall_rating,
            f.hygiene_rating,
            f.temperature,
            f.portion,
            f.wastage_level,
            f.wastage_reason,
            f.comment,
            f.suggestion,
            f.sentiment,
            f.sentiment_score,
            f.created_at,
            (SELECT ROUND(AVG(fi.taste_rating), 1) FROM feedback_items fi WHERE fi.feedback_id = f.id) as avg_taste,
            (SELECT ROUND(AVG(fi.quality_rating), 1) FROM feedback_items fi WHERE fi.feedback_id = f.id) as avg_quality,
            (SELECT ROUND(AVG(fi.freshness_rating), 1) FROM feedback_items fi WHERE fi.feedback_id = f.id) as avg_freshness,
            (SELECT COUNT(*) FROM feedback_items fi WHERE fi.feedback_id = f.id) as dish_count
        FROM feedback f
        JOIN menus m ON m.id = f.menu_id
        WHERE {$whereSql}
        ORDER BY f.created_at DESC
        LIMIT {$limit} OFFSET {$offset}
    ");
    $dataStmt->execute($params);
    $records = $dataStmt->fetchAll();

    jsonResponse([
        'success'       => true,
        'records'       => $records,
        'pagination'    => [
            'total'        => $totalRecords,
            'page'         => $page,
            'limit'        => $limit,
            'total_pages'  => ceil($totalRecords / $limit) ?: 1
        ]
    ]);

} catch (Exception $e) {
    jsonError('Failed to fetch feedback records: ' . $e->getMessage(), 500);
}
