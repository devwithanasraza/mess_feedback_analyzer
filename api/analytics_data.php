<?php
/**
 * Food Quality & Rating Analytics API Endpoint
 * Mess Menu Feedback Analyzer - B.Tech Data Science Project
 * Implements attribute decomposition, Menu Performance Scoring formula, and tier classification.
 */

declare(strict_types=1);
require_once __DIR__ . '/database.php';

header('Content-Type: application/json; charset=utf-8');

$pdo = getDbConnection();
$days = isset($_GET['days']) ? (int)$_GET['days'] : 30;
if ($days <= 0 || $days > 90) $days = 30;

try {
    // 1. Overall Attribute Scores
    $attrStmt = $pdo->prepare("
        SELECT 
            ROUND(AVG(f.overall_rating), 2) as avg_overall,
            ROUND(AVG(f.hygiene_rating), 2) as avg_hygiene,
            ROUND(AVG(fi.taste_rating), 2) as avg_taste,
            ROUND(AVG(fi.quality_rating), 2) as avg_quality,
            ROUND(AVG(fi.freshness_rating), 2) as avg_freshness,
            ROUND(AVG(fi.quantity_rating), 2) as avg_quantity,
            COUNT(DISTINCT f.id) as total_feedback,
            COUNT(fi.id) as total_dish_evaluations
        FROM feedback f
        LEFT JOIN feedback_items fi ON fi.feedback_id = f.id
        WHERE f.created_at >= (NOW() - INTERVAL ? DAY)
    ");
    $attrStmt->execute([$days]);
    $attrs = $attrStmt->fetch();

    $overall = (float)($attrs['avg_overall'] ?? 0.0);
    $hygiene = (float)($attrs['avg_hygiene'] ?? 0.0);
    $taste   = (float)($attrs['avg_taste'] ?? 0.0);
    $quality = (float)($attrs['avg_quality'] ?? 0.0);
    $fresh   = (float)($attrs['avg_freshness'] ?? 0.0);
    $quant   = (float)($attrs['avg_quantity'] ?? 0.0);

    $weightedSatisfaction = calculateWeightedSatisfaction($overall, $taste, $quality, $hygiene, $fresh, $quant);

    // 2. Menu Performance Score (MPS) per Dish
    // Dish Score = Avg Taste + Avg Quality + Avg Freshness + Repeat Bonus - Wastage Penalty
    $dishScoreStmt = $pdo->prepare("
        SELECT 
            mi.id as item_id,
            mi.item_name,
            mi.category,
            mi.is_veg,
            COUNT(fi.id) as review_count,
            ROUND(AVG(fi.taste_rating), 2) as avg_taste,
            ROUND(AVG(fi.quality_rating), 2) as avg_quality,
            ROUND(AVG(fi.freshness_rating), 2) as avg_freshness,
            ROUND(AVG(fi.quantity_rating), 2) as avg_quantity,
            ROUND(SUM(CASE WHEN fi.repeat_preference = 'Yes' THEN 1 ELSE 0 END) / COUNT(fi.id) * 100, 1) as repeat_yes_pct,
            (SELECT COUNT(*) FROM wastage_records wr WHERE wr.menu_item_id = mi.id) as total_waste_reports
        FROM menu_items mi
        JOIN feedback_items fi ON fi.menu_item_id = mi.id
        JOIN feedback f ON f.id = fi.feedback_id
        WHERE f.created_at >= (NOW() - INTERVAL ? DAY)
        GROUP BY mi.id, mi.item_name, mi.category, mi.is_veg
        HAVING review_count >= 2
        ORDER BY mi.item_name ASC
    ");
    $dishScoreStmt->execute([$days]);
    $dishRows = $dishScoreStmt->fetchAll();

    $performanceTable = [];
    foreach ($dishRows as $d) {
        $t = (float)$d['avg_taste'];
        $q = (float)$d['avg_quality'];
        $f = (float)$d['avg_freshness'];
        $repeatPct = (float)$d['repeat_yes_pct'];
        $wasteReports = (int)$d['total_waste_reports'];
        $reviews = (int)$d['review_count'];

        // Repeat Bonus calculation:
        $repeatBonus = 0.0;
        if ($repeatPct >= 70.0) $repeatBonus = 1.0;
        elseif ($repeatPct >= 45.0) $repeatBonus = 0.5;
        else $repeatBonus = -0.5;

        // Wastage Penalty calculation:
        $wasteRatio = $reviews > 0 ? ($wasteReports / $reviews) : 0.0;
        $wastePenalty = round(min(2.5, $wasteRatio * 2.0), 2);

        // Raw Dish Score = Taste (up to 5) + Quality (up to 5) + Freshness (up to 5) + Repeat Bonus - Waste Penalty
        $rawScore = round($t + $q + $f + $repeatBonus - $wastePenalty, 2);

        // Performance Tier:
        if ($rawScore >= 12.0) $tier = 'Excellent';
        elseif ($rawScore >= 10.0) $tier = 'Good';
        elseif ($rawScore >= 8.0) $tier = 'Average';
        else $tier = 'Needs Improvement';

        $performanceTable[] = [
            'item_id'         => $d['item_id'],
            'item_name'       => $d['item_name'],
            'category'        => $d['category'],
            'is_veg'          => (int)$d['is_veg'],
            'review_count'    => $reviews,
            'avg_taste'       => $t,
            'avg_quality'     => $q,
            'avg_freshness'   => $f,
            'avg_quantity'    => (float)$d['avg_quantity'],
            'repeat_yes_pct'  => $repeatPct,
            'repeat_bonus'    => $repeatBonus,
            'waste_reports'   => $wasteReports,
            'waste_penalty'   => $wastePenalty,
            'composite_score' => $rawScore,
            'tier'            => $tier
        ];
    }

    // Sort by composite score descending
    usort($performanceTable, fn($a, $b) => $b['composite_score'] <=> $a['composite_score']);

    // 3. Temperature Distribution
    $tempStmt = $pdo->prepare("
        SELECT temperature, COUNT(*) as count
        FROM feedback
        WHERE created_at >= (NOW() - INTERVAL ? DAY)
        GROUP BY temperature
    ");
    $tempStmt->execute([$days]);
    $tempRows = $tempStmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // 4. Portion Sizing Distribution
    $portionStmt = $pdo->prepare("
        SELECT `portion`, COUNT(*) as count
        FROM feedback
        WHERE created_at >= (NOW() - INTERVAL ? DAY)
        GROUP BY `portion`
    ");
    $portionStmt->execute([$days]);
    $portionRows = $portionStmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // 5. Category-wise Performance
    $catStmt = $pdo->prepare("
        SELECT 
            mi.category,
            ROUND(AVG(fi.taste_rating), 2) as avg_taste,
            ROUND(AVG(fi.quality_rating), 2) as avg_quality,
            COUNT(fi.id) as total_ratings
        FROM feedback_items fi
        JOIN menu_items mi ON mi.id = fi.menu_item_id
        JOIN feedback f ON f.id = fi.feedback_id
        WHERE f.created_at >= (NOW() - INTERVAL ? DAY)
        GROUP BY mi.category
        ORDER BY avg_taste DESC
    ");
    $catStmt->execute([$days]);
    $categoryRatings = $catStmt->fetchAll();

    jsonResponse([
        'success' => true,
        'days' => $days,
        'attribute_scores' => [
            'overall'              => $overall,
            'hygiene'              => $hygiene,
            'taste'                => $taste,
            'quality'              => $quality,
            'freshness'            => $fresh,
            'quantity'             => $quant,
            'weighted_satisfaction'=> $weightedSatisfaction,
            'total_feedback'       => (int)($attrs['total_feedback'] ?? 0),
            'total_evaluations'    => (int)($attrs['total_dish_evaluations'] ?? 0)
        ],
        'dish_performance'  => $performanceTable,
        'temperature_dist'  => [
            'Too Cold'   => (int)($tempRows['Too Cold'] ?? 0),
            'Acceptable' => (int)($tempRows['Acceptable'] ?? 0),
            'Hot/Fresh'  => (int)($tempRows['Hot/Fresh'] ?? 0)
        ],
        'portion_dist'      => [
            'Too Little'  => (int)($portionRows['Too Little'] ?? 0),
            'Appropriate' => (int)($portionRows['Appropriate'] ?? 0),
            'Too Much'    => (int)($portionRows['Too Much'] ?? 0)
        ],
        'category_ratings'  => $categoryRatings
    ]);

} catch (Exception $e) {
    jsonError('Failed to fetch food analytics: ' . $e->getMessage(), 500);
}
