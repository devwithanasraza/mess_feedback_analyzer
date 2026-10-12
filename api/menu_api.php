<?php
/**
 * Admin Menu Management API
 * Mess Menu Feedback Analyzer - B.Tech Data Science Project
 * Handles full CRUD operations for daily meal schedules and dish items.
 */

declare(strict_types=1);
require_once __DIR__ . '/database.php';

header('Content-Type: application/json; charset=utf-8');

$pdo = getDbConnection();
$method = $_SERVER['REQUEST_METHOD'];

// Helper to check admin authentication for mutation actions
function requireAdminAuth(): void {
    if (empty($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        jsonError('Unauthorized. Admin session required.', 401);
    }
}

// 1. GET: Fetch Menus for admin view
if ($method === 'GET') {
    $date = $_GET['date'] ?? date('Y-m-d');
    $menuId = isset($_GET['id']) ? (int)$_GET['id'] : null;

    try {
        if ($menuId) {
            $stmt = $pdo->prepare("SELECT * FROM menus WHERE id = ?");
            $stmt->execute([$menuId]);
            $menu = $stmt->fetch();
            if (!$menu) jsonError('Menu not found.', 404);

            $itemStmt = $pdo->prepare("SELECT * FROM menu_items WHERE menu_id = ? ORDER BY id ASC");
            $itemStmt->execute([$menuId]);
            $menu['items'] = $itemStmt->fetchAll();

            jsonResponse(['success' => true, 'menu' => $menu]);
        }

        // List all 4 meals for the selected date
        $stmt = $pdo->prepare("
            SELECT m.*, 
                   (SELECT COUNT(*) FROM menu_items mi WHERE mi.menu_id = m.id) as item_count,
                   (SELECT COUNT(*) FROM feedback f WHERE f.menu_id = m.id) as feedback_count,
                   (SELECT ROUND(AVG(f.overall_rating), 1) FROM feedback f WHERE f.menu_id = m.id) as avg_rating
            FROM menus m
            WHERE m.menu_date = ?
            ORDER BY FIELD(m.meal_type, 'Breakfast', 'Lunch', 'Snacks', 'Dinner')
        ");
        $stmt->execute([$date]);
        $menus = $stmt->fetchAll();

        // Attach items to each menu
        $itemStmt = $pdo->prepare("SELECT * FROM menu_items WHERE menu_id = ? ORDER BY id ASC");
        foreach ($menus as &$m) {
            $itemStmt->execute([$m['id']]);
            $m['items'] = $itemStmt->fetchAll();
        }

        jsonResponse([
            'success' => true,
            'date'    => $date,
            'menus'   => $menus
        ]);
    } catch (Exception $e) {
        jsonError('Failed to fetch menus: ' . $e->getMessage(), 500);
    }
}

// 2. POST: Admin Mutations (Create, Update, Delete, Add Dish, Toggle)
if ($method === 'POST') {
    requireAdminAuth();

    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $action = $input['action'] ?? $_GET['action'] ?? '';

    try {
        // ACTION: Create Menu
        if ($action === 'create_menu') {
            $menuDate = $input['menu_date'] ?? '';
            $mealType = $input['meal_type'] ?? '';
            $servingTime = sanitizeInput($input['serving_time'] ?? '');
            $items = $input['items'] ?? [];

            if (empty($menuDate) || empty($mealType) || empty($servingTime)) {
                jsonError('Menu date, meal type, and serving time are required.');
            }

            // Check if already exists for date + meal
            $dupCheck = $pdo->prepare("SELECT id FROM menus WHERE menu_date = ? AND meal_type = ?");
            $dupCheck->execute([$menuDate, $mealType]);
            if ($dupCheck->fetch()) {
                jsonError("A {$mealType} menu already exists for {$menuDate}. You can edit it instead.");
            }

            $pdo->beginTransaction();
            $ins = $pdo->prepare("INSERT INTO menus (menu_date, meal_type, serving_time, status) VALUES (?, ?, ?, 'Active')");
            $ins->execute([$menuDate, $mealType, $servingTime]);
            $newMenuId = (int)$pdo->lastInsertId();

            if (!empty($items) && is_array($items)) {
                $itemIns = $pdo->prepare("
                    INSERT INTO menu_items (menu_id, item_name, category, is_veg, description, is_active)
                    VALUES (?, ?, ?, ?, ?, 1)
                ");
                foreach ($items as $item) {
                    $name = sanitizeInput($item['name'] ?? '');
                    $cat = sanitizeInput($item['category'] ?? 'Main Course');
                    $isVeg = isset($item['is_veg']) ? (int)$item['is_veg'] : 1;
                    $desc = sanitizeInput($item['description'] ?? '');
                    if (!empty($name)) {
                        $itemIns->execute([$newMenuId, $name, $cat, $isVeg, $desc]);
                    }
                }
            }
            $pdo->commit();

            jsonResponse([
                'success' => true,
                'message' => "{$mealType} menu created successfully for {$menuDate}.",
                'menu_id' => $newMenuId
            ]);
        }

        // ACTION: Update Menu details (serving time or status)
        if ($action === 'update_menu') {
            $menuId = (int)($input['menu_id'] ?? 0);
            $servingTime = sanitizeInput($input['serving_time'] ?? '');
            $status = $input['status'] ?? 'Active';

            if ($menuId <= 0) jsonError('Invalid menu ID.');

            $upd = $pdo->prepare("UPDATE menus SET serving_time = ?, status = ? WHERE id = ?");
            $upd->execute([$servingTime, $status, $menuId]);

            jsonResponse(['success' => true, 'message' => 'Menu details updated successfully.']);
        }

        // ACTION: Add Item to Menu
        if ($action === 'add_item') {
            $menuId = (int)($input['menu_id'] ?? 0);
            $itemName = sanitizeInput($input['item_name'] ?? '');
            $category = sanitizeInput($input['category'] ?? 'Main Course');
            $isVeg = isset($input['is_veg']) ? (int)$input['is_veg'] : 1;
            $desc = sanitizeInput($input['description'] ?? '');

            if ($menuId <= 0 || empty($itemName)) {
                jsonError('Valid Menu ID and dish name are required.');
            }

            $stmt = $pdo->prepare("
                INSERT INTO menu_items (menu_id, item_name, category, is_veg, description, is_active)
                VALUES (?, ?, ?, ?, ?, 1)
            ");
            $stmt->execute([$menuId, $itemName, $category, $isVeg, $desc]);

            jsonResponse([
                'success' => true,
                'message' => "Dish '{$itemName}' added to menu.",
                'item_id' => (int)$pdo->lastInsertId()
            ]);
        }

        // ACTION: Update Item
        if ($action === 'update_item') {
            $itemId = (int)($input['item_id'] ?? 0);
            $itemName = sanitizeInput($input['item_name'] ?? '');
            $category = sanitizeInput($input['category'] ?? 'Main Course');
            $isVeg = isset($input['is_veg']) ? (int)$input['is_veg'] : 1;
            $desc = sanitizeInput($input['description'] ?? '');
            $isActive = isset($input['is_active']) ? (int)$input['is_active'] : 1;

            if ($itemId <= 0 || empty($itemName)) {
                jsonError('Valid item ID and dish name are required.');
            }

            $stmt = $pdo->prepare("
                UPDATE menu_items 
                SET item_name = ?, category = ?, is_veg = ?, description = ?, is_active = ?
                WHERE id = ?
            ");
            $stmt->execute([$itemName, $category, $isVeg, $desc, $isActive, $itemId]);

            jsonResponse(['success' => true, 'message' => "Dish '{$itemName}' updated successfully."]);
        }

        // ACTION: Delete Item
        if ($action === 'delete_item') {
            $itemId = (int)($input['item_id'] ?? 0);
            if ($itemId <= 0) jsonError('Invalid item ID.');

            $stmt = $pdo->prepare("DELETE FROM menu_items WHERE id = ?");
            $stmt->execute([$itemId]);

            jsonResponse(['success' => true, 'message' => 'Dish deleted from menu.']);
        }

        // ACTION: Delete entire Menu
        if ($action === 'delete_menu') {
            $menuId = (int)($input['menu_id'] ?? 0);
            if ($menuId <= 0) jsonError('Invalid menu ID.');

            $stmt = $pdo->prepare("DELETE FROM menus WHERE id = ?");
            $stmt->execute([$menuId]);

            jsonResponse(['success' => true, 'message' => 'Menu and all associated dishes deleted.']);
        }

        // ACTION: Toggle Status
        if ($action === 'toggle_status') {
            $menuId = (int)($input['menu_id'] ?? 0);
            $newStatus = ($input['status'] === 'Active') ? 'Active' : 'Inactive';

            $stmt = $pdo->prepare("UPDATE menus SET status = ? WHERE id = ?");
            $stmt->execute([$newStatus, $menuId]);

            jsonResponse(['success' => true, 'message' => "Menu status changed to {$newStatus}."]);
        }

        jsonError('Unknown action requested.');

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        jsonError('Menu operation failed: ' . $e->getMessage(), 500);
    }
}
