<?php
/**
 * Reports and CSV Export API Endpoint
 * Mess Menu Feedback Analyzer - B.Tech Data Science Project
 * Generates Daily, Weekly, and Monthly analytical reports and anonymous CSV exports.
 */

declare(strict_types=1);
require_once __DIR__ . '/database.php';

$pdo = getDbConnection();

$type = $_GET['type'] ?? 'monthly'; // 'daily', 'weekly', 'monthly'
$date = $_GET['date'] ?? date('Y-m-d');
$isExport = isset($_GET['export']) && $_GET['export'] === 'csv';

// Determine date range based on report type
if ($type === 'daily') {
    $startDate = $date;
    $endDate = $date;
    $periodLabel = "Daily Report (" . date('d M Y', strtotime($date)) . ")";
} elseif ($type === 'weekly') {
    $startDate = date('Y-m-d', strtotime('-6 days', strtotime($date)));
    $endDate = $date;
    $periodLabel = "Weekly Report (" . date('d M Y', strtotime($startDate)) . " to " . date('d M Y', strtotime($endDate)) . ")";
} else {
    // monthly (default 30 days)
    $startDate = date('Y-m-d', strtotime('-29 days', strtotime($date)));
    $endDate = $date;
    $periodLabel = "Monthly Report (" . date('d M Y', strtotime($startDate)) . " to " . date('d M Y', strtotime($endDate)) . ")";
}

// ------------------------------------------------------------------------
// CASE 1: CSV Export of Anonymous Feedback Dataset
// ------------------------------------------------------------------------
if ($isExport) {
    $stmt = $pdo->prepare("
        SELECT 
            f.id as feedback_id,
            m.menu_date,
            m.meal_type,
            f.overall_rating,
            f.hygiene_rating,
            f.temperature,
            f.portion,
            f.wastage_level,
            f.wastage_reason,
            f.sentiment,
            f.sentiment_score,
            f.comment,
            f.suggestion,
            f.created_at
        FROM feedback f
        JOIN menus m ON m.id = f.menu_id
        WHERE DATE(f.created_at) BETWEEN ? AND ?
        ORDER BY f.created_at DESC
    ");
    $stmt->execute([$startDate, $endDate]);
    $rows = $stmt->fetchAll();

    $filename = "mess_anonymous_feedback_" . $type . "_" . date('Ymd') . ".csv";

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');

    // CSV Header row
    fputcsv($out, [
        'Feedback ID (Anon)',
        'Date',
        'Meal Type',
        'Overall Rating (1-5)',
        'Hygiene Rating (1-5)',
        'Food Temperature',
        'Portion Size',
        'Wastage Level',
        'Wastage Reason',
        'Sentiment Classification',
        'Sentiment Score (-1 to 1)',
        'Student Comment',
        'Student Suggestion',
        'Timestamp'
    ]);

    foreach ($rows as $r) {
        fputcsv($out, [
            'FB-' . str_pad((string)$r['feedback_id'], 5, '0', STR_PAD_LEFT),
            $r['menu_date'],
            $r['meal_type'],
            $r['overall_rating'],
            $r['hygiene_rating'],
            $r['temperature'],
            $r['portion'],
            $r['wastage_level'],
            $r['wastage_reason'] ?? 'None',
            $r['sentiment'],
            $r['sentiment_score'],
            $r['comment'] ?? '',
            $r['suggestion'] ?? '',
            $r['created_at']
        ]);
    }

    fclose($out);
    exit;
}

// ------------------------------------------------------------------------
// CASE 2: JSON Response for Interactive Reports Page
// ------------------------------------------------------------------------
header('Content-Type: application/json; charset=utf-8');

try {
    // 1. Executive Summary
    $summaryStmt = $pdo->prepare("
        SELECT 
            COUNT(f.id) as total_feedback,
            ROUND(AVG(f.overall_rating), 2) as avg_rating,
            ROUND(AVG(f.hygiene_rating), 2) as avg_hygiene,
            SUM(CASE WHEN f.wastage_level IN ('Some', 'A lot') THEN 1 ELSE 0 END) as significant_waste_count,
            SUM(CASE WHEN f.sentiment = 'Positive' THEN 1 ELSE 0 END) as pos_count,
            SUM(CASE WHEN f.sentiment = 'Neutral' THEN 1 ELSE 0 END) as neu_count,
            SUM(CASE WHEN f.sentiment = 'Negative' THEN 1 ELSE 0 END) as neg_count
        FROM feedback f
        WHERE DATE(f.created_at) BETWEEN ? AND ?
    ");
    $summaryStmt->execute([$startDate, $endDate]);
    $summary = $summaryStmt->fetch();

    $totalFb = (int)($summary['total_feedback'] ?? 0);
    $avgRating = (float)($summary['avg_rating'] ?? 0.0);
    $avgHygiene = (float)($summary['avg_hygiene'] ?? 0.0);
    $wasteCount = (int)($summary['significant_waste_count'] ?? 0);
    $posCount = (int)($summary['pos_count'] ?? 0);
    $neuCount = (int)($summary['neu_count'] ?? 0);
    $negCount = (int)($summary['neg_count'] ?? 0);

    // Attribute averages
    $attrStmt = $pdo->prepare("
        SELECT 
            ROUND(AVG(fi.taste_rating), 2) as avg_taste,
            ROUND(AVG(fi.quality_rating), 2) as avg_quality,
            ROUND(AVG(fi.quantity_rating), 2) as avg_quantity,
            ROUND(AVG(fi.freshness_rating), 2) as avg_freshness
        FROM feedback_items fi
        JOIN feedback f ON f.id = fi.feedback_id
        WHERE DATE(f.created_at) BETWEEN ? AND ?
    ");
    $attrStmt->execute([$startDate, $endDate]);
    $attrs = $attrStmt->fetch();

    $avgTaste = (float)($attrs['avg_taste'] ?? $avgRating);
    $avgQuality = (float)($attrs['avg_quality'] ?? $avgRating);
    $avgQuant = (float)($attrs['avg_quantity'] ?? 3.0);
    $avgFresh = (float)($attrs['avg_freshness'] ?? $avgRating);

    $satisfaction = calculateWeightedSatisfaction($avgRating, $avgTaste, $avgQuality, $avgHygiene, $avgFresh, $avgQuant);
    $wastageRate = $totalFb > 0 ? round(($wasteCount / $totalFb) * 100, 1) : 0.0;

    // 2. Most Liked Dish
    $topDishStmt = $pdo->prepare("
        SELECT mi.item_name, ROUND(AVG((fi.taste_rating + fi.quality_rating) / 2), 2) as score, COUNT(fi.id) as reviews
        FROM feedback_items fi
        JOIN menu_items mi ON mi.id = fi.menu_item_id
        JOIN feedback f ON f.id = fi.feedback_id
        WHERE DATE(f.created_at) BETWEEN ? AND ?
        GROUP BY mi.item_name
        ORDER BY score DESC, reviews DESC
        LIMIT 1
    ");
    $topDishStmt->execute([$startDate, $endDate]);
    $mostLikedDish = $topDishStmt->fetch();

    // 3. Least Liked Dish
    $bottomDishStmt = $pdo->prepare("
        SELECT mi.item_name, ROUND(AVG((fi.taste_rating + fi.quality_rating) / 2), 2) as score, COUNT(fi.id) as reviews
        FROM feedback_items fi
        JOIN menu_items mi ON mi.id = fi.menu_item_id
        JOIN feedback f ON f.id = fi.feedback_id
        WHERE DATE(f.created_at) BETWEEN ? AND ?
        GROUP BY mi.item_name
        ORDER BY score ASC, reviews DESC
        LIMIT 1
    ");
    $bottomDishStmt->execute([$startDate, $endDate]);
    $leastLikedDish = $bottomDishStmt->fetch();

    // 4. Main Complaints / Wastage Reasons
    $complaintsStmt = $pdo->prepare("
        SELECT wr.wastage_reason, COUNT(*) as incident_count
        FROM wastage_records wr
        JOIN feedback f ON f.id = wr.feedback_id
        WHERE DATE(f.created_at) BETWEEN ? AND ?
          AND wr.wastage_reason IS NOT NULL AND wr.wastage_reason != ''
        GROUP BY wr.wastage_reason
        ORDER BY incident_count DESC
        LIMIT 5
    ");
    $complaintsStmt->execute([$startDate, $endDate]);
    $mainComplaints = $complaintsStmt->fetchAll();

    // 5. Meal Breakdown
    $mealBreakdownStmt = $pdo->prepare("
        SELECT 
            m.meal_type,
            COUNT(f.id) as reviews,
            ROUND(AVG(f.overall_rating), 2) as avg_rating,
            SUM(CASE WHEN f.wastage_level IN ('Some', 'A lot') THEN 1 ELSE 0 END) as high_waste
        FROM feedback f
        JOIN menus m ON m.id = f.menu_id
        WHERE DATE(f.created_at) BETWEEN ? AND ?
        GROUP BY m.meal_type
        ORDER BY FIELD(m.meal_type, 'Breakfast', 'Lunch', 'Snacks', 'Dinner')
    ");
    $mealBreakdownStmt->execute([$startDate, $endDate]);
    $mealBreakdown = $mealBreakdownStmt->fetchAll();

    // 6. Recent Constructive Suggestions
    $suggStmt = $pdo->prepare("
        SELECT f.suggestion, f.sentiment, m.meal_type, DATE(f.created_at) as fb_date
        FROM feedback f
        JOIN menus m ON m.id = f.menu_id
        WHERE DATE(f.created_at) BETWEEN ? AND ?
          AND f.suggestion IS NOT NULL AND f.suggestion != ''
        ORDER BY f.created_at DESC
        LIMIT 6
    ");
    $suggStmt->execute([$startDate, $endDate]);
    $suggestions = $suggStmt->fetchAll();

    jsonResponse([
        'success'      => true,
        'period_label' => $periodLabel,
        'type'         => $type,
        'start_date'   => $startDate,
        'end_date'     => $endDate,
        'summary'      => [
            'total_feedback'       => $totalFb,
            'average_rating'       => $avgRating,
            'satisfaction_pct'     => $satisfaction,
            'wastage_rate'         => $wastageRate,
            'significant_waste'    => $wasteCount,
            'most_liked_dish'      => $mostLikedDish ? $mostLikedDish['item_name'] : 'N/A',
            'most_liked_score'     => $mostLikedDish ? (float)$mostLikedDish['score'] : 0.0,
            'least_liked_dish'     => $leastLikedDish ? $leastLikedDish['item_name'] : 'N/A',
            'least_liked_score'    => $leastLikedDish ? (float)$leastLikedDish['score'] : 0.0,
            'sentiment' => [
                'positive' => $posCount,
                'neutral'  => $neuCount,
                'negative' => $negCount,
                'pos_pct'  => $totalFb > 0 ? round(($posCount / $totalFb) * 100, 1) : 0,
                'neu_pct'  => $totalFb > 0 ? round(($neuCount / $totalFb) * 100, 1) : 0,
                'neg_pct'  => $totalFb > 0 ? round(($negCount / $totalFb) * 100, 1) : 0,
            ]
        ],
        'meal_breakdown'  => $mealBreakdown,
        'main_complaints' => $mainComplaints,
        'suggestions'     => $suggestions
    ]);

} catch (Exception $e) {
    jsonError('Failed to generate report: ' . $e->getMessage(), 500);
}
