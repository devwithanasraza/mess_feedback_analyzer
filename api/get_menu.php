<?php
/**
 * Public Menu API Endpoint
 * Mess Menu Feedback Analyzer
 * Provides dynamic menu details, dish items, ratings, and meal schedules.
 */

declare(strict_types=1);
require_once __DIR__ . '/database.php';

header('Content-Type: application/json; charset=utf-8');

$pdo = getDbConnection();

$date = $_GET['date'] ?? date('Y-m-d');
$meal = $_GET['meal'] ?? null;
$menuId = isset($_GET['menu_id']) ? (int)$_GET['menu_id'] : null;

try {
    // If specific menu_id requested
    if ($menuId) {
        $stmt = $pdo->prepare("
            SELECT m.*, 
                   COUNT(f.id) as total_reviews,
                   ROUND(AVG(f.overall_rating), 1) as avg_rating
            FROM menus m
            LEFT JOIN feedback f ON f.menu_id = m.id
            WHERE m.id = ?
            GROUP BY m.id
            LIMIT 1
        ");
        $stmt->execute([$menuId]);
        $menu = $stmt->fetch();

        if (!$menu) {
            jsonError('Menu not found.', 404);
        }

        // Fetch dishes for this menu
        $itemsStmt = $pdo->prepare("
            SELECT mi.*,
                   ROUND(AVG(fi.taste_rating), 1) as avg_taste,
                   ROUND(AVG(fi.quality_rating), 1) as avg_quality,
                   ROUND(AVG(fi.quantity_rating), 1) as avg_quantity,
                   ROUND(AVG(fi.freshness_rating), 1) as avg_freshness,
                   COUNT(fi.id) as review_count
            FROM menu_items mi
            LEFT JOIN feedback_items fi ON fi.menu_item_id = mi.id
            WHERE mi.menu_id = ? AND mi.is_active = 1
            GROUP BY mi.id
            ORDER BY mi.category ASC, mi.id ASC
        ");
        $itemsStmt->execute([$menuId]);
        $menu['items'] = $itemsStmt->fetchAll();

        jsonResponse([
            'success' => true,
            'menu'    => $menu
        ]);
    }

    // Otherwise, fetch all meals for the given date (or fallback to latest date with menus)
    $dateCheckStmt = $pdo->prepare("SELECT COUNT(*) FROM menus WHERE menu_date = ?");
    $dateCheckStmt->execute([$date]);
    if ((int)$dateCheckStmt->fetchColumn() === 0) {
        // Find closest date with menus
        $closestDate = $pdo->query("SELECT menu_date FROM menus ORDER BY ABS(DATEDIFF(menu_date, CURRENT_DATE)) ASC LIMIT 1")->fetchColumn();
        if ($closestDate) {
            $date = $closestDate;
        }
    }

    $query = "
        SELECT m.id, m.menu_date, m.meal_type, m.serving_time, m.status,
               COUNT(DISTINCT f.id) as total_reviews,
               ROUND(AVG(f.overall_rating), 1) as avg_rating
        FROM menus m
        LEFT JOIN feedback f ON f.menu_id = m.id
        WHERE m.menu_date = ?
    ";
    $params = [$date];

    if ($meal) {
        $query .= " AND m.meal_type = ?";
        $params[] = $meal;
    }

    $query .= " GROUP BY m.id ORDER BY FIELD(m.meal_type, 'Breakfast', 'Lunch', 'Snacks', 'Dinner')";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $menus = $stmt->fetchAll();

    // Attach items with dish ratings to each meal
    $itemQuery = $pdo->prepare("
        SELECT mi.id, mi.menu_id, mi.item_name, mi.category, mi.is_veg, mi.description,
               ROUND(AVG(fi.taste_rating), 1) as avg_taste,
               ROUND(AVG(fi.quality_rating), 1) as avg_quality,
               ROUND(AVG(fi.quantity_rating), 1) as avg_quantity,
               ROUND(AVG(fi.freshness_rating), 1) as avg_freshness,
               ROUND((AVG(fi.taste_rating) + AVG(fi.quality_rating) + AVG(fi.freshness_rating)) / 3, 1) as avg_rating,
               COUNT(fi.id) as review_count
        FROM menu_items mi
        LEFT JOIN feedback_items fi ON fi.menu_item_id = mi.id
        WHERE mi.menu_id = ? AND mi.is_active = 1
        GROUP BY mi.id
        ORDER BY FIELD(mi.category, 'Main Course', 'Dal/Curry', 'Bread/Rice', 'Snack', 'Salad/Side', 'Dessert', 'Beverage'), mi.id ASC
    ");

    foreach ($menus as &$m) {
        $itemQuery->execute([$m['id']]);
        $m['items'] = $itemQuery->fetchAll();
        $m['avg_rating'] = $m['avg_rating'] ? (float)$m['avg_rating'] : 0.0;
        $m['total_reviews'] = (int)$m['total_reviews'];
    }

    // Available dates for selector
    $dates = $pdo->query("SELECT DISTINCT menu_date FROM menus ORDER BY menu_date DESC LIMIT 30")->fetchAll(PDO::FETCH_COLUMN);

    jsonResponse([
        'success'         => true,
        'date'            => $date,
        'is_today'        => ($date === date('Y-m-d')),
        'available_dates' => $dates,
        'meals'           => $menus
    ]);

} catch (PDOException $e) {
    jsonError('Failed to fetch menu: ' . $e->getMessage(), 500);
}
