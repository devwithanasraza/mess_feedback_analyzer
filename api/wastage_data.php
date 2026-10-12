<?php
/**
 * Food Wastage Analytics API Endpoint
 * Mess Menu Feedback Analyzer - B.Tech Data Science Project
 * Performs dedicated wastage aggregation, dish waste attribution, and kitchen planning insights.
 */

declare(strict_types=1);
require_once __DIR__ . '/database.php';

header('Content-Type: application/json; charset=utf-8');

$pdo = getDbConnection();
$days = isset($_GET['days']) ? (int)$_GET['days'] : 30;
if ($days <= 0 || $days > 90) $days = 30;

try {
    // 1. Core Wastage Metrics
    $kpiStmt = $pdo->prepare("
        SELECT 
            COUNT(f.id) as total_meals_reviewed,
            SUM(CASE WHEN f.wastage_level != 'None' THEN 1 ELSE 0 END) as reported_waste_incidents,
            SUM(CASE WHEN f.wastage_level = 'None' THEN 1 ELSE 0 END) as zero_waste_count,
            SUM(CASE WHEN f.wastage_level = 'Very Little' THEN 1 ELSE 0 END) as minor_waste_count,
            SUM(CASE WHEN f.wastage_level = 'Some' THEN 1 ELSE 0 END) as moderate_waste_count,
            SUM(CASE WHEN f.wastage_level = 'A lot' THEN 1 ELSE 0 END) as severe_waste_count,
            ROUND(AVG(
                CASE 
                    WHEN f.wastage_level = 'None' THEN 0
                    WHEN f.wastage_level = 'Very Little' THEN 1
                    WHEN f.wastage_level = 'Some' THEN 2
                    WHEN f.wastage_level = 'A lot' THEN 3
                END
            ), 2) as avg_wastage_index
        FROM feedback f
        WHERE f.created_at >= (NOW() - INTERVAL ? DAY)
    ");
    $kpiStmt->execute([$days]);
    $kpi = $kpiStmt->fetch();

    $totalReviews = (int)($kpi['total_meals_reviewed'] ?? 0);
    $wasteIncidents = (int)($kpi['reported_waste_incidents'] ?? 0);
    $wasteRate = $totalReviews > 0 ? round(($wasteIncidents / $totalReviews) * 100, 1) : 0.0;
    $avgWasteIndex = (float)($kpi['avg_wastage_index'] ?? 0.0);

    // Wastage severity label based on index (0 to 3 scale)
    $severityLabel = 'Low';
    if ($avgWasteIndex >= 1.5) $severityLabel = 'High';
    elseif ($avgWasteIndex >= 0.8) $severityLabel = 'Moderate';

    // 2. Highest Wastage Meal Type
    $mealWasteStmt = $pdo->prepare("
        SELECT 
            m.meal_type,
            COUNT(f.id) as total_feedback,
            SUM(CASE WHEN f.wastage_level IN ('Some', 'A lot') THEN 1 ELSE 0 END) as high_waste_count,
            ROUND(AVG(
                CASE 
                    WHEN f.wastage_level = 'None' THEN 0
                    WHEN f.wastage_level = 'Very Little' THEN 1
                    WHEN f.wastage_level = 'Some' THEN 2
                    WHEN f.wastage_level = 'A lot' THEN 3
                END
            ), 2) as waste_score
        FROM feedback f
        JOIN menus m ON m.id = f.menu_id
        WHERE f.created_at >= (NOW() - INTERVAL ? DAY)
        GROUP BY m.meal_type
        ORDER BY FIELD(m.meal_type, 'Breakfast', 'Lunch', 'Snacks', 'Dinner')
    ");
    $mealWasteStmt->execute([$days]);
    $mealWasteList = $mealWasteStmt->fetchAll();

    $highestWasteMeal = 'None';
    $highestScore = -1;
    $mealWasteChart = [
        'labels' => [],
        'scores' => [],
        'counts' => []
    ];
    foreach ($mealWasteList as $mw) {
        $mealWasteChart['labels'][] = $mw['meal_type'];
        $score = (float)$mw['waste_score'];
        $mealWasteChart['scores'][] = $score;
        $mealWasteChart['counts'][] = (int)$mw['high_waste_count'];
        if ($score > $highestScore) {
            $highestScore = $score;
            $highestWasteMeal = $mw['meal_type'];
        }
    }

    // 3. Most Wasted Dish (From Wastage Records & Feedback Items)
    $dishWasteStmt = $pdo->prepare("
        SELECT 
            mi.item_name,
            mi.category,
            COUNT(wr.id) as waste_count,
            SUM(CASE WHEN wr.wastage_level = 'A lot' THEN 2 ELSE 1 END) as severity_weight
        FROM wastage_records wr
        JOIN menu_items mi ON mi.id = wr.menu_item_id
        JOIN feedback f ON f.id = wr.feedback_id
        WHERE f.created_at >= (NOW() - INTERVAL ? DAY)
        GROUP BY mi.item_name, mi.category
        ORDER BY severity_weight DESC, waste_count DESC
        LIMIT 6
    ");
    $dishWasteStmt->execute([$days]);
    $dishWasteList = $dishWasteStmt->fetchAll();

    $mostWastedDish = !empty($dishWasteList) ? $dishWasteList[0]['item_name'] : 'N/A';

    // 4. Wastage Reasons Distribution
    $reasonsStmt = $pdo->prepare("
        SELECT 
            wr.wastage_reason,
            COUNT(wr.id) as reason_count
        FROM wastage_records wr
        JOIN feedback f ON f.id = wr.feedback_id
        WHERE f.created_at >= (NOW() - INTERVAL ? DAY) 
          AND wr.wastage_reason IS NOT NULL 
          AND wr.wastage_reason != ''
        GROUP BY wr.wastage_reason
        ORDER BY reason_count DESC
    ");
    $reasonsStmt->execute([$days]);
    $reasonsList = $reasonsStmt->fetchAll();

    $mainWastageReason = !empty($reasonsList) ? $reasonsList[0]['wastage_reason'] : 'Not Specified';

    $reasonChart = [
        'labels' => [],
        'counts' => []
    ];
    foreach ($reasonsList as $r) {
        $reasonChart['labels'][] = $r['wastage_reason'];
        $reasonChart['counts'][] = (int)$r['reason_count'];
    }

    // 5. Daily Wastage Trend
    $trendStmt = $pdo->prepare("
        SELECT 
            DATE(f.created_at) as fb_date,
            ROUND(AVG(
                CASE 
                    WHEN f.wastage_level = 'None' THEN 0
                    WHEN f.wastage_level = 'Very Little' THEN 1
                    WHEN f.wastage_level = 'Some' THEN 2
                    WHEN f.wastage_level = 'A lot' THEN 3
                END
            ), 2) as daily_waste_score,
            SUM(CASE WHEN f.wastage_level IN ('Some', 'A lot') THEN 1 ELSE 0 END) as significant_waste
        FROM feedback f
        WHERE f.created_at >= (NOW() - INTERVAL ? DAY)
        GROUP BY DATE(f.created_at)
        ORDER BY DATE(f.created_at) ASC
    ");
    $trendStmt->execute([$days]);
    $trendRaw = $trendStmt->fetchAll();

    $dailyTrend = [
        'labels' => [],
        'scores' => [],
        'counts' => []
    ];
    foreach ($trendRaw as $t) {
        $dailyTrend['labels'][] = date('M d', strtotime($t['fb_date']));
        $dailyTrend['scores'][] = (float)$t['daily_waste_score'];
        $dailyTrend['counts'][] = (int)$t['significant_waste'];
    }

    // 6. Portion Correlation Analysis (Portion served vs reported waste)
    $portionCorrStmt = $pdo->prepare("
        SELECT 
            `portion`,
            COUNT(*) as count,
            SUM(CASE WHEN wastage_level IN ('Some', 'A lot') THEN 1 ELSE 0 END) as wasted_count
        FROM feedback
        WHERE created_at >= (NOW() - INTERVAL ? DAY)
        GROUP BY `portion`
    ");
    $portionCorrStmt->execute([$days]);
    $portionCorr = $portionCorrStmt->fetchAll();

    // 7. Dynamic Data-Driven Actionable Insights
    $actionableInsights = [];

    // Highest wastage meal insight
    if ($highestWasteMeal !== 'None') {
        $actionableInsights[] = [
            'icon' => 'calendar-alert',
            'level' => 'high',
            'title' => "Highest Wastage in {$highestWasteMeal}",
            'text' => "{$highestWasteMeal} recorded an average wastage index of {$highestScore}/3.0. Adjusting preparation batch volume by 10-15% during this meal is recommended."
        ];
    }

    // Most wasted dish insight
    if (!empty($dishWasteList)) {
        $topWasted = $dishWasteList[0];
        $actionableInsights[] = [
            'icon' => 'utensils',
            'level' => 'high',
            'title' => "High Plate Waste for {$topWasted['item_name']}",
            'text' => "'{$topWasted['item_name']}' ({$topWasted['category']}) accounted for {$topWasted['waste_count']} reported plate wastage instances. Re-evaluate student spice preferences or portion sizes."
        ];
    }

    // Main wastage reason insight
    if (!empty($reasonsList)) {
        $topR = $reasonsList[0];
        $actionableInsights[] = [
            'icon' => 'alert-triangle',
            'level' => 'medium',
            'title' => "Root Driver: {$topR['wastage_reason']}",
            'text' => "Students cited '{$topR['wastage_reason']}' most frequently ({$topR['reason_count']} reports). Training mess servers to provide half-portion options on request directly tackles this."
        ];
    }

    // Friday / Day of Week Wastage Check
    $dayOfWeekStmt = $pdo->prepare("
        SELECT 
            DAYNAME(f.created_at) as day_name,
            ROUND(AVG(CASE WHEN f.wastage_level IN ('Some', 'A lot') THEN 1 ELSE 0 END) * 100, 1) as waste_pct
        FROM feedback f
        WHERE f.created_at >= (NOW() - INTERVAL ? DAY)
        GROUP BY DAYNAME(f.created_at)
        ORDER BY waste_pct DESC
        LIMIT 1
    ");
    $dayOfWeekStmt->execute([$days]);
    $worstDay = $dayOfWeekStmt->fetch();
    if ($worstDay) {
        $actionableInsights[] = [
            'icon' => 'trending-up',
            'level' => 'info',
            'title' => "Peak Day Wastage on {$worstDay['day_name']}",
            'text' => "{$worstDay['day_name']} records the highest wastage proportion at {$worstDay['waste_pct']}%. Many students dine out or have weekend plans, leading to over-preparation."
        ];
    }

    // Portion mismatch insight
    foreach ($portionCorr as $pc) {
        if ($pc['portion'] === 'Too Much' && (int)$pc['count'] > 0) {
            $wasteInTooMuch = round(((int)$pc['wasted_count'] / (int)$pc['count']) * 100);
            $actionableInsights[] = [
                'icon' => 'check-circle',
                'level' => 'info',
                'title' => "Portion Sizing Opportunity",
                'text' => "{$wasteInTooMuch}% of students who noted 'Too Much' food left significant plate waste. Standardized ladle measurements will curb preparation excess."
            ];
            break;
        }
    }

    jsonResponse([
        'success' => true,
        'days' => $days,
        'kpis' => [
            'total_reviews'       => $totalReviews,
            'waste_incidents'     => $wasteIncidents,
            'waste_rate'          => $wasteRate,
            'avg_wastage_index'   => $avgWasteIndex,
            'severity_label'      => $severityLabel,
            'highest_waste_meal'  => $highestWasteMeal,
            'most_wasted_dish'    => $mostWastedDish,
            'main_wastage_reason' => $mainWastageReason,
            'level_breakdown'     => [
                'zero'     => (int)($kpi['zero_waste_count'] ?? 0),
                'minor'    => (int)($kpi['minor_waste_count'] ?? 0),
                'moderate' => (int)($kpi['moderate_waste_count'] ?? 0),
                'severe'   => (int)($kpi['severe_waste_count'] ?? 0)
            ]
        ],
        'charts' => [
            'meal_waste'     => $mealWasteChart,
            'dish_waste'     => [
                'labels' => array_column($dishWasteList, 'item_name'),
                'counts' => array_column($dishWasteList, 'waste_count')
            ],
            'reasons'        => $reasonChart,
            'daily_trend'    => $dailyTrend,
            'portion_impact' => $portionCorr
        ],
        'actionable_insights' => $actionableInsights
    ]);

} catch (Exception $e) {
    jsonError('Failed to fetch wastage analytics: ' . $e->getMessage(), 500);
}
