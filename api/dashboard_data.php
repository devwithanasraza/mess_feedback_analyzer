<?php
/**
 * Admin Dashboard Analytics API Endpoint
 * Mess Menu Feedback Analyzer - B.Tech Data Science Project
 * Calculates live database aggregates, Chart.js datasets, and actionable insights.
 */

declare(strict_types=1);
require_once __DIR__ . '/database.php';

header('Content-Type: application/json; charset=utf-8');

$pdo = getDbConnection();

// Days filter (default 30)
$days = isset($_GET['days']) ? (int)$_GET['days'] : 30;
if ($days <= 0 || $days > 90) $days = 30;

try {
    // 1. KPI Aggregates
    $kpiStmt = $pdo->prepare("
        SELECT 
            COUNT(f.id) as total_feedback,
            ROUND(AVG(f.overall_rating), 2) as avg_rating,
            ROUND(AVG(f.hygiene_rating), 2) as avg_hygiene,
            SUM(CASE WHEN f.wastage_level IN ('Some', 'A lot') THEN 1 ELSE 0 END) as wasted_count,
            SUM(CASE WHEN f.sentiment = 'Positive' THEN 1 ELSE 0 END) as positive_count,
            SUM(CASE WHEN f.sentiment = 'Neutral' THEN 1 ELSE 0 END) as neutral_count,
            SUM(CASE WHEN f.sentiment = 'Negative' THEN 1 ELSE 0 END) as negative_count
        FROM feedback f
        WHERE f.created_at >= (NOW() - INTERVAL ? DAY)
    ");
    $kpiStmt->execute([$days]);
    $kpi = $kpiStmt->fetch();

    $totalFb = (int)($kpi['total_feedback'] ?? 0);
    $avgRating = (float)($kpi['avg_rating'] ?? 0.0);
    $avgHygiene = (float)($kpi['avg_hygiene'] ?? 0.0);
    $wastedCount = (int)($kpi['wasted_count'] ?? 0);
    $posCount = (int)($kpi['positive_count'] ?? 0);
    $neuCount = (int)($kpi['neutral_count'] ?? 0);
    $negCount = (int)($kpi['negative_count'] ?? 0);

    $wastagePercentage = $totalFb > 0 ? round(($wastedCount / $totalFb) * 100, 1) : 0.0;
    $posPercentage = $totalFb > 0 ? round(($posCount / $totalFb) * 100, 1) : 0.0;
    $negPercentage = $totalFb > 0 ? round(($negCount / $totalFb) * 100, 1) : 0.0;

    // Attribute averages for Weighted Satisfaction Score
    $attrStmt = $pdo->prepare("
        SELECT 
            ROUND(AVG(fi.taste_rating), 2) as avg_taste,
            ROUND(AVG(fi.quality_rating), 2) as avg_quality,
            ROUND(AVG(fi.quantity_rating), 2) as avg_quantity,
            ROUND(AVG(fi.freshness_rating), 2) as avg_freshness
        FROM feedback_items fi
        JOIN feedback f ON f.id = fi.feedback_id
        WHERE f.created_at >= (NOW() - INTERVAL ? DAY)
    ");
    $attrStmt->execute([$days]);
    $attr = $attrStmt->fetch();

    $avgTaste = (float)($attr['avg_taste'] ?? $avgRating);
    $avgQuality = (float)($attr['avg_quality'] ?? $avgRating);
    $avgQuantity = (float)($attr['avg_quantity'] ?? 3.0);
    $avgFreshness = (float)($attr['avg_freshness'] ?? $avgRating);

    $satisfactionScore = calculateWeightedSatisfaction(
        $avgRating,
        $avgTaste,
        $avgQuality,
        $avgHygiene,
        $avgFreshness,
        $avgQuantity
    );

    // 2. Chart 1: Average Rating by Meal Type
    $mealRatingStmt = $pdo->prepare("
        SELECT 
            m.meal_type,
            ROUND(AVG(f.overall_rating), 2) as avg_rating,
            COUNT(f.id) as count
        FROM feedback f
        JOIN menus m ON m.id = f.menu_id
        WHERE f.created_at >= (NOW() - INTERVAL ? DAY)
        GROUP BY m.meal_type
        ORDER BY FIELD(m.meal_type, 'Breakfast', 'Lunch', 'Snacks', 'Dinner')
    ");
    $mealRatingStmt->execute([$days]);
    $mealRatingsRaw = $mealRatingStmt->fetchAll();

    $mealRatings = [
        'labels' => ['Breakfast', 'Lunch', 'Snacks', 'Dinner'],
        'ratings' => [0, 0, 0, 0],
        'counts' => [0, 0, 0, 0]
    ];
    foreach ($mealRatingsRaw as $mr) {
        $idx = array_search($mr['meal_type'], $mealRatings['labels'], true);
        if ($idx !== false) {
            $mealRatings['ratings'][$idx] = (float)$mr['avg_rating'];
            $mealRatings['counts'][$idx] = (int)$mr['count'];
        }
    }

    // 3. Chart 2: Satisfaction Trend Over Time (Daily)
    $trendStmt = $pdo->prepare("
        SELECT 
            DATE(f.created_at) as fb_date,
            ROUND(AVG(f.overall_rating), 2) as daily_rating,
            COUNT(f.id) as total_entries
        FROM feedback f
        WHERE f.created_at >= (NOW() - INTERVAL ? DAY)
        GROUP BY DATE(f.created_at)
        ORDER BY DATE(f.created_at) ASC
    ");
    $trendStmt->execute([$days]);
    $trendRaw = $trendStmt->fetchAll();

    $trendData = [
        'labels' => [],
        'satisfaction_pct' => [],
        'counts' => []
    ];
    foreach ($trendRaw as $tr) {
        $trendData['labels'][] = date('M d', strtotime($tr['fb_date']));
        // Daily satisfaction estimate normalized to %
        $trendData['satisfaction_pct'][] = round(((float)$tr['daily_rating'] / 5.0) * 100, 1);
        $trendData['counts'][] = (int)$tr['total_entries'];
    }

    // 4. Chart 3: Food Wastage Trend
    $wasteTrendStmt = $pdo->prepare("
        SELECT 
            DATE(f.created_at) as fb_date,
            COUNT(f.id) as total_fb,
            SUM(CASE WHEN f.wastage_level IN ('Some', 'A lot') THEN 1 ELSE 0 END) as high_waste
        FROM feedback f
        WHERE f.created_at >= (NOW() - INTERVAL ? DAY)
        GROUP BY DATE(f.created_at)
        ORDER BY DATE(f.created_at) ASC
    ");
    $wasteTrendStmt->execute([$days]);
    $wasteTrendRaw = $wasteTrendStmt->fetchAll();

    $wasteTrend = [
        'labels' => [],
        'waste_percentage' => [],
        'waste_counts' => []
    ];
    foreach ($wasteTrendRaw as $wtr) {
        $tot = (int)$wtr['total_fb'];
        $high = (int)$wtr['high_waste'];
        $wasteTrend['labels'][] = date('M d', strtotime($wtr['fb_date']));
        $wasteTrend['waste_percentage'][] = $tot > 0 ? round(($high / $tot) * 100, 1) : 0;
        $wasteTrend['waste_counts'][] = $high;
    }

    // 5. Chart 4: Top 5 Most Liked Dishes
    $topDishesStmt = $pdo->prepare("
        SELECT 
            mi.item_name,
            ROUND(AVG((fi.taste_rating + fi.quality_rating + fi.freshness_rating) / 3), 2) as score,
            COUNT(fi.id) as reviews
        FROM feedback_items fi
        JOIN menu_items mi ON mi.id = fi.menu_item_id
        JOIN feedback f ON f.id = fi.feedback_id
        WHERE f.created_at >= (NOW() - INTERVAL ? DAY)
        GROUP BY mi.item_name
        HAVING reviews >= 2
        ORDER BY score DESC, reviews DESC
        LIMIT 5
    ");
    $topDishesStmt->execute([$days]);
    $topDishes = $topDishesStmt->fetchAll();

    // 6. Chart 5: Top 5 Least Liked Dishes
    $bottomDishesStmt = $pdo->prepare("
        SELECT 
            mi.item_name,
            ROUND(AVG((fi.taste_rating + fi.quality_rating + fi.freshness_rating) / 3), 2) as score,
            COUNT(fi.id) as reviews
        FROM feedback_items fi
        JOIN menu_items mi ON mi.id = fi.menu_item_id
        JOIN feedback f ON f.id = fi.feedback_id
        WHERE f.created_at >= (NOW() - INTERVAL ? DAY)
        GROUP BY mi.item_name
        HAVING reviews >= 2
        ORDER BY score ASC, reviews DESC
        LIMIT 5
    ");
    $bottomDishesStmt->execute([$days]);
    $bottomDishes = $bottomDishesStmt->fetchAll();

    // 7. Chart 6: Sentiment Breakdown
    $sentimentChart = [
        'labels' => ['Positive', 'Neutral', 'Negative'],
        'counts' => [$posCount, $neuCount, $negCount],
        'percentages' => [$posPercentage, ($totalFb > 0 ? round(($neuCount / $totalFb) * 100, 1) : 0.0), $negPercentage]
    ];

    // 8. Chart 7: Meal-wise Satisfaction (%)
    $mealSatisfaction = [
        'labels' => ['Breakfast', 'Lunch', 'Snacks', 'Dinner'],
        'percentages' => []
    ];
    foreach ($mealRatings['ratings'] as $rt) {
        $mealSatisfaction['percentages'][] = round(($rt / 5.0) * 100, 1);
    }

    // 9. Real Actionable Insights Generator (Based strictly on DB calculations)
    $insights = [];

    // Check highest & lowest rated meal
    if (!empty($mealRatingsRaw)) {
        usort($mealRatingsRaw, fn($a, $b) => $b['avg_rating'] <=> $a['avg_rating']);
        $bestMeal = $mealRatingsRaw[0];
        $worstMeal = end($mealRatingsRaw);

        if ($bestMeal['meal_type'] !== $worstMeal['meal_type']) {
            $insights[] = [
                'type' => 'success',
                'category' => 'Satisfaction Leader',
                'text' => "{$bestMeal['meal_type']} achieves the highest average rating at {$bestMeal['avg_rating']}/5.0 ({$bestMeal['count']} reviews)."
            ];
            $insights[] = [
                'type' => 'warning',
                'category' => 'Quality Review',
                'text' => "{$worstMeal['meal_type']} has the lowest average rating at {$worstMeal['avg_rating']}/5.0. Focus kitchen review on this meal."
            ];
        }
    }

    // Check dish with highest reported wastage
    $wastedDishStmt = $pdo->prepare("
        SELECT mi.item_name, COUNT(wr.id) as waste_count
        FROM wastage_records wr
        JOIN menu_items mi ON mi.id = wr.menu_item_id
        JOIN feedback f ON f.id = wr.feedback_id
        WHERE f.created_at >= (NOW() - INTERVAL ? DAY)
        GROUP BY mi.item_name
        ORDER BY waste_count DESC
        LIMIT 1
    ");
    $wastedDishStmt->execute([$days]);
    $wastedDish = $wastedDishStmt->fetch();
    if ($wastedDish) {
        $insights[] = [
            'type' => 'danger',
            'category' => 'Food Wastage Alert',
            'text' => "'{$wastedDish['item_name']}' received the highest number of wastage complaints ({$wastedDish['waste_count']} reports). Consider adjusting batch portions or recipe."
        ];
    }

    // Check top wastage reason
    $wasteReasonStmt = $pdo->prepare("
        SELECT wastage_reason, COUNT(*) as cnt
        FROM wastage_records
        WHERE created_at >= (NOW() - INTERVAL ? DAY) AND wastage_reason IS NOT NULL AND wastage_reason != ''
        GROUP BY wastage_reason
        ORDER BY cnt DESC
        LIMIT 1
    ");
    $wasteReasonStmt->execute([$days]);
    $topReason = $wasteReasonStmt->fetch();
    if ($topReason) {
        $insights[] = [
            'type' => 'info',
            'category' => 'Root Cause',
            'text' => "Primary driver for student plate wastage is '{$topReason['wastage_reason']}' ({$topReason['cnt']} reported incidents)."
        ];
    }

    // Check hygiene score
    if ($avgHygiene >= 4.0) {
        $insights[] = [
            'type' => 'success',
            'category' => 'Hygiene Standards',
            'text' => "Mess dining hall hygiene remains well-rated at {$avgHygiene}/5.0 across student reviews."
        ];
    } elseif ($avgHygiene < 3.0 && $avgHygiene > 0) {
        $insights[] = [
            'type' => 'danger',
            'category' => 'Hygiene Concern',
            'text' => "Hygiene rating is critically low at {$avgHygiene}/5.0. Immediate mess counter sanitization and inspection required."
        ];
    }

    // Check temperature feedback
    $tempStmt = $pdo->prepare("
        SELECT temperature, COUNT(*) as cnt
        FROM feedback
        WHERE created_at >= (NOW() - INTERVAL ? DAY)
        GROUP BY temperature
        ORDER BY cnt DESC
    ");
    $tempStmt->execute([$days]);
    $temps = $tempStmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $coldCnt = (int)($temps['Too Cold'] ?? 0);
    if ($totalFb > 0 && ($coldCnt / $totalFb) >= 0.25) {
        $coldPct = round(($coldCnt / $totalFb) * 100);
        $insights[] = [
            'type' => 'warning',
            'category' => 'Food Temperature',
            'text' => "{$coldPct}% of reviews noted food was 'Too Cold' at serving counters. Recommend checking bain-marie warmer temperatures."
        ];
    }

    jsonResponse([
        'success' => true,
        'days' => $days,
        'kpis' => [
            'total_feedback'      => $totalFb,
            'average_rating'      => $avgRating,
            'satisfaction_score'  => $satisfactionScore,
            'wastage_rate'        => $wastagePercentage,
            'positive_count'      => $posCount,
            'positive_percentage' => $posPercentage,
            'negative_count'      => $negCount,
            'negative_percentage' => $negPercentage,
            'neutral_count'       => $neuCount,
            'attributes' => [
                'taste'     => $avgTaste,
                'quality'   => $avgQuality,
                'hygiene'   => $avgHygiene,
                'freshness' => $avgFreshness,
                'quantity'  => $avgQuantity
            ]
        ],
        'charts' => [
            'meal_ratings'      => $mealRatings,
            'satisfaction_trend'=> $trendData,
            'wastage_trend'     => $wasteTrend,
            'top_dishes'        => $topDishes,
            'bottom_dishes'     => $bottomDishes,
            'sentiment'         => $sentimentChart,
            'meal_satisfaction' => $mealSatisfaction
        ],
        'actionable_insights' => $insights
    ]);

} catch (Exception $e) {
    jsonError('Failed to load dashboard data: ' . $e->getMessage(), 500);
}
