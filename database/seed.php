<?php
/**
 * Database Seeder Script
 * Mess Menu Feedback Analyzer - B.Tech Data Science Project
 * Generates schema and 30 days of realistic menu, dish, feedback, wastage, and sentiment data.
 */

declare(strict_types=1);

$dbHost = getenv('DB_HOST') ?: 'sql12.freesqldatabase.com';
$dbUser = getenv('DB_USER') ?: 'sql12838781';
$dbPass = getenv('DB_PASS') ?: '66lmY5hzBS';
$dbName = getenv('DB_NAME') ?: 'sql12838781';
$dbPort = getenv('DB_PORT') ?: '3306';

echo "=== MESS MENU FEEDBACK ANALYZER SEEDER ===\n";
echo "Target Host: {$dbHost} | Database: {$dbName}\n";

try {
    // 1. Connection directly to target database
    $pdo = new PDO("mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_TIMEOUT => 20,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    echo "[1/4] Connected to database '{$dbName}' successfully.\n";

    // 2. Run schema file
    echo "[2/5] Applying schema from database/schema.sql...\n";
    $schemaSql = file_get_contents(__DIR__ . '/schema.sql');
    // Strip database creation commands from schema to avoid re-run conflict
    $pdo->exec($schemaSql);

    // Ensure admin user exists with password 'admin123'
    $adminPassHash = password_hash('admin123', PASSWORD_DEFAULT);
    $adminStmt = $pdo->prepare("
        INSERT INTO admins (username, password_hash, full_name, email, role)
        VALUES ('admin', ?, 'Mess Head Administrator', 'messadmin@campus.edu', 'super_admin')
        ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), full_name = VALUES(full_name)
    ");
    $adminStmt->execute([$adminPassHash]);
    echo "  - Admin account configured (Username: admin, Password: admin123)\n";

    // Clear existing sample menus and feedbacks for fresh consistent seed
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $pdo->exec("TRUNCATE TABLE feedback_sentiment;");
    $pdo->exec("TRUNCATE TABLE wastage_records;");
    $pdo->exec("TRUNCATE TABLE feedback_items;");
    $pdo->exec("TRUNCATE TABLE feedback;");
    $pdo->exec("TRUNCATE TABLE menu_items;");
    $pdo->exec("TRUNCATE TABLE menus;");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

    // 3. Define Dish Pools for Menus
    $dishPools = [
        'Breakfast' => [
            ['name' => 'Poha with Roasted Peanuts & Sev', 'cat' => 'Main Course', 'veg' => 1, 'desc' => 'Traditional tempered flattened rice with mustard seeds and peanuts'],
            ['name' => 'Masala Dosa with Sambar & Chutney', 'cat' => 'Main Course', 'veg' => 1, 'desc' => 'Crispy fermented crepe with potato masala filling'],
            ['name' => 'Steamed Idli with Coconut Chutney', 'cat' => 'Main Course', 'veg' => 1, 'desc' => 'Soft fluffy steamed rice and lentil cakes'],
            ['name' => 'Aloo Paratha with Fresh Curd', 'cat' => 'Main Course', 'veg' => 1, 'desc' => 'Whole wheat flatbread stuffed with spiced potato mash'],
            ['name' => 'Upma with Mixed Vegetables', 'cat' => 'Main Course', 'veg' => 1, 'desc' => 'Semolina cooked with mustard seeds, curry leaves, and veggies'],
            ['name' => 'Puri with Spiced Aloo Sabzi', 'cat' => 'Main Course', 'veg' => 1, 'desc' => 'Crispy fried puffed bread served with potato curry'],
            ['name' => 'Boiled Eggs / Bread Omelette', 'cat' => 'Main Course', 'veg' => 0, 'desc' => 'Protein option served with toasted whole wheat bread'],
            ['name' => 'Hot Ginger Chai', 'cat' => 'Beverage', 'veg' => 1, 'desc' => 'Fresh brewed milk tea with crushed ginger and cardamom'],
            ['name' => 'Filter Coffee', 'cat' => 'Beverage', 'veg' => 1, 'desc' => 'South Indian style chicory-blended filter coffee'],
            ['name' => 'Fresh Seasonal Fruits', 'cat' => 'Salad/Side', 'veg' => 1, 'desc' => 'Bananas, papaya, and watermelon slices']
        ],
        'Lunch' => [
            ['name' => 'Dal Tadka (Yellow Lentils)', 'cat' => 'Dal/Curry', 'veg' => 1, 'desc' => 'Toor dal tempered with garlic, cumin, and dried red chilies'],
            ['name' => 'Dal Makhani', 'cat' => 'Dal/Curry', 'veg' => 1, 'desc' => 'Slow-cooked black lentils in creamy butter tomato gravy'],
            ['name' => 'Paneer Butter Masala', 'cat' => 'Main Course', 'veg' => 1, 'desc' => 'Cottage cheese cubes in rich tomato, cashew, and butter gravy'],
            ['name' => 'Aloo Gobi Matar', 'cat' => 'Main Course', 'veg' => 1, 'desc' => 'Stir-fried potatoes, cauliflower, and green peas with cumin'],
            ['name' => 'Chole Masala (Chickpeas)', 'cat' => 'Main Course', 'veg' => 1, 'desc' => 'Peshawari style spiced chickpeas curry'],
            ['name' => 'Rajma Masala (Red Kidney Beans)', 'cat' => 'Main Course', 'veg' => 1, 'desc' => 'North Indian kidney bean curry in onion-tomato sauce'],
            ['name' => 'Steamed Basmati Rice', 'cat' => 'Bread/Rice', 'veg' => 1, 'desc' => 'Fluffy aromatic long-grain steamed rice'],
            ['name' => 'Jeera Pulao', 'cat' => 'Bread/Rice', 'veg' => 1, 'desc' => 'Fragrant basmati rice tossed with whole roasted cumin seeds'],
            ['name' => 'Phulka Roti with Ghee', 'cat' => 'Bread/Rice', 'veg' => 1, 'desc' => 'Fresh whole wheat puffed rotis brushed with desi ghee'],
            ['name' => 'Fresh Homemade Curd', 'cat' => 'Salad/Side', 'veg' => 1, 'desc' => 'Chilled plain probiotic set yogurt'],
            ['name' => 'Cucumber Tomato Green Salad', 'cat' => 'Salad/Side', 'veg' => 1, 'desc' => 'Fresh sliced cucumbers, tomatoes, carrots, and lemon'],
            ['name' => 'Roasted Papad & Pickle', 'cat' => 'Salad/Side', 'veg' => 1, 'desc' => 'Crisp roasted urad dal papad with mango pickle']
        ],
        'Snacks' => [
            ['name' => 'Crispy Vegetable Samosa', 'cat' => 'Snack', 'veg' => 1, 'desc' => 'Deep-fried triangular pastry stuffed with spiced potatoes and peas'],
            ['name' => 'Mumbai Pav Bhaji', 'cat' => 'Snack', 'veg' => 1, 'desc' => 'Spiced mashed vegetable curry served with toasted buttered pavs'],
            ['name' => 'Crispy Onion Pakoda', 'cat' => 'Snack', 'veg' => 1, 'desc' => 'Chickpea flour fritters with thinly sliced onions and spices'],
            ['name' => 'Vegetable Cutlet with Mint Chutney', 'cat' => 'Snack', 'veg' => 1, 'desc' => 'Pan-fried potato and beet cutlets coated with breadcrumbs'],
            ['name' => 'Sweet Corn Chaat', 'cat' => 'Snack', 'veg' => 1, 'desc' => 'Steamed sweet corn tossed with butter, chaat masala, and lemon'],
            ['name' => 'Masala Chai', 'cat' => 'Beverage', 'veg' => 1, 'desc' => 'Strong spiced campus special evening tea'],
            ['name' => 'Cold Coffee', 'cat' => 'Beverage', 'veg' => 1, 'desc' => 'Chilled blended milk coffee with chocolate drizzle'],
            ['name' => 'Marie & Glucose Biscuits', 'cat' => 'Snack', 'veg' => 1, 'desc' => 'Assorted tea-time snack biscuits']
        ],
        'Dinner' => [
            ['name' => 'Vegetable Dum Biryani', 'cat' => 'Main Course', 'veg' => 1, 'desc' => 'Layered fragrant basmati rice cooked with marinated vegetables and spices'],
            ['name' => 'Egg Curry / Paneer Do Pyaza', 'cat' => 'Main Course', 'veg' => 0, 'desc' => 'Boiled eggs or cottage cheese in rich onion gravy'],
            ['name' => 'Mixed Seasonal Veg Curry', 'cat' => 'Main Course', 'veg' => 1, 'desc' => 'Carrots, beans, peas, and cauliflower simmered in home-style gravy'],
            ['name' => 'Yellow Moong Dal Fry', 'cat' => 'Dal/Curry', 'veg' => 1, 'desc' => 'Comforting light yellow moong dal cooked with tomatoes and ghee'],
            ['name' => 'Tawa Phulka Roti', 'cat' => 'Bread/Rice', 'veg' => 1, 'desc' => 'Soft whole wheat rotis cooked fresh on iron tawa'],
            ['name' => 'Steamed Long Grain Rice', 'cat' => 'Bread/Rice', 'veg' => 1, 'desc' => 'Fresh hot steamed rice'],
            ['name' => 'Boondi Raita', 'cat' => 'Salad/Side', 'veg' => 1, 'desc' => 'Whisked chilled spiced yogurt with crispy gram flour pearls'],
            ['name' => 'Hot Gulab Jamun', 'cat' => 'Dessert', 'veg' => 1, 'desc' => 'Golden fried milk-solid dumplings soaked in rose sugar syrup'],
            ['name' => 'Rice Kheer (Payasam)', 'cat' => 'Dessert', 'veg' => 1, 'desc' => 'Slow-cooked milk pudding with basmati rice, cardamom, and almonds'],
            ['name' => 'Vanilla Ice Cream Cup', 'cat' => 'Dessert', 'veg' => 1, 'desc' => 'Classic chilled vanilla dessert cup']
        ]
    ];

    $servingTimes = [
        'Breakfast' => '07:30 AM - 09:30 AM',
        'Lunch' => '12:30 PM - 02:30 PM',
        'Snacks' => '05:00 PM - 06:30 PM',
        'Dinner' => '08:00 PM - 10:00 PM'
    ];

    echo "[3/5] Generating 30 days of menus & dishes...\n";
    
    // We will generate for the past 28 days, today (day 0), and tomorrow (+1 day)
    $today = new DateTime();
    $startDate = (clone $today)->modify('-28 days');
    $endDate = (clone $today)->modify('+1 day');

    $menuInsertStmt = $pdo->prepare("
        INSERT INTO menus (menu_date, meal_type, serving_time, status)
        VALUES (?, ?, ?, 'Active')
    ");

    $itemInsertStmt = $pdo->prepare("
        INSERT INTO menu_items (menu_id, item_name, category, is_veg, description, is_active)
        VALUES (?, ?, ?, ?, ?, 1)
    ");

    $createdMenus = []; // Store mapping for feedback generation

    $curDate = clone $startDate;
    while ($curDate <= $endDate) {
        $dateStr = $curDate->format('Y-m-d');
        $dayOfWeek = (int)$curDate->format('w'); // 0=Sun, 6=Sat

        foreach (['Breakfast', 'Lunch', 'Snacks', 'Dinner'] as $meal) {
            $servingTime = $servingTimes[$meal];
            $menuInsertStmt->execute([$dateStr, $meal, $servingTime]);
            $menuId = (int)$pdo->lastInsertId();

            // Select 4-5 dishes from pool based on day variation
            $pool = $dishPools[$meal];
            $selectedDishes = [];

            if ($meal === 'Breakfast') {
                // Main course varies by day
                $mainIdx = ($dayOfWeek + (int)$curDate->format('j')) % 6;
                $selectedDishes[] = $pool[$mainIdx];
                $selectedDishes[] = $pool[6]; // Egg or bread
                $selectedDishes[] = $pool[7]; // Chai
                $selectedDishes[] = $pool[9]; // Fruits
            } elseif ($meal === 'Lunch') {
                $dalIdx = ($dayOfWeek % 2 == 0) ? 0 : 1; // Dal Tadka or Dal Makhani
                $vegIdx = 2 + (($dayOfWeek + (int)$curDate->format('j')) % 4); // Paneer, Aloo Gobi, Chole, Rajma
                $selectedDishes[] = $pool[$dalIdx];
                $selectedDishes[] = $pool[$vegIdx];
                $selectedDishes[] = $pool[6]; // Steamed rice
                $selectedDishes[] = $pool[8]; // Phulka Roti
                $selectedDishes[] = $pool[9]; // Curd
                $selectedDishes[] = $pool[10]; // Salad
            } elseif ($meal === 'Snacks') {
                $snackIdx = ($dayOfWeek + (int)$curDate->format('j')) % 5;
                $selectedDishes[] = $pool[$snackIdx];
                $selectedDishes[] = $pool[5]; // Masala chai
                $selectedDishes[] = $pool[7]; // Biscuits
            } elseif ($meal === 'Dinner') {
                $mainIdx = ($dayOfWeek === 0 || $dayOfWeek === 6) ? 0 : 2; // Biryani on weekends, Mixed Veg on weekdays
                $selectedDishes[] = $pool[$mainIdx];
                $selectedDishes[] = $pool[3]; // Yellow Dal
                $selectedDishes[] = $pool[4]; // Tawa Roti
                $selectedDishes[] = $pool[5]; // Steamed Rice
                $selectedDishes[] = $pool[6]; // Boondi Raita
                $sweetIdx = 7 + ($dayOfWeek % 3); // Sweet
                $selectedDishes[] = $pool[$sweetIdx];
            }

            $itemsForMenu = [];
            foreach ($selectedDishes as $dish) {
                $itemInsertStmt->execute([
                    $menuId,
                    $dish['name'],
                    $dish['cat'],
                    $dish['veg'],
                    $dish['desc']
                ]);
                $itemsForMenu[] = [
                    'id' => (int)$pdo->lastInsertId(),
                    'name' => $dish['name'],
                    'cat' => $dish['cat']
                ];
            }

            $createdMenus[] = [
                'id' => $menuId,
                'date' => $dateStr,
                'meal' => $meal,
                'items' => $itemsForMenu
            ];
        }

        $curDate->modify('+1 day');
    }
    echo "  - Generated " . count($createdMenus) . " menus with dishes successfully.\n";

    // 4. Sentiment Classifier Helper for Seeding
    function analyzeSentimentKeywords(string $text): array {
        $positiveWords = ['good', 'great', 'tasty', 'fresh', 'excellent', 'delicious', 'nice', 'amazing', 'superb', 'crispy', 'soft', 'flavorful', 'satisfying', 'perfect'];
        $negativeWords = ['bad', 'poor', 'cold', 'oily', 'salty', 'bland', 'tasteless', 'dirty', 'unhealthy', 'waste', 'sour', 'hard', 'stale', 'smelly', 'delayed', 'watery'];

        $lower = strtolower($text);
        $posMatches = [];
        $negMatches = [];

        foreach ($positiveWords as $pw) {
            if (preg_match('/\b' . preg_quote($pw, '/') . '\b/i', $lower)) {
                $posMatches[] = $pw;
            }
        }
        foreach ($negativeWords as $nw) {
            if (preg_match('/\b' . preg_quote($nw, '/') . '\b/i', $lower)) {
                $negMatches[] = $nw;
            }
        }

        $posCount = count($posMatches);
        $negCount = count($negMatches);

        if ($posCount > $negCount) {
            $sentiment = 'Positive';
            $score = min(1.0, 0.4 + ($posCount - $negCount) * 0.2);
            $matched = implode(', ', $posMatches);
        } elseif ($negCount > $posCount) {
            $sentiment = 'Negative';
            $score = max(-1.0, -0.4 - ($negCount - $posCount) * 0.2);
            $matched = implode(', ', $negMatches);
        } else {
            $sentiment = 'Neutral';
            $score = 0.0;
            $matched = (!empty($posMatches) || !empty($negMatches)) ? implode(', ', array_merge($posMatches, $negMatches)) : 'None';
        }

        return [
            'sentiment' => $sentiment,
            'score' => round($score, 2),
            'matched' => $matched
        ];
    }

    // Realistic Comment & Suggestion templates
    $studentFeedbackTemplates = [
        'Positive' => [
            ['c' => 'The Dal Tadka was very tasty and fresh today, great improvement in spice level!', 's' => 'Please continue this exact recipe for lunches.'],
            ['c' => 'Paneer butter masala was delicious with soft fresh rotis. Excellent meal.', 's' => 'Can we get this dish on Wednesday lunches too?'],
            ['c' => 'Breakfast poha was fresh, light and very nicely roasted. Coffee was great too.', 's' => 'More roasted peanuts would make it even better.'],
            ['c' => 'Hot Gulab Jamun at dinner was amazing, overall food quality was high.', 's' => 'Keep the serving counters well-replenished.'],
            ['c' => 'Masala Dosa was crispy and chutney was fresh. Really enjoyed breakfast today.', 's' => 'Great work by mess staff today!'],
            ['c' => 'Very good dinner tonight. Vegetable biryani was flavorful and not too oily.', 's' => 'Boondi raita quantity was perfect.'],
            ['c' => 'Snacks today were fresh and piping hot. Samosas were nice and crisp.', 's' => 'Chai counter flow was fast and efficient.']
        ],
        'Neutral' => [
            ['c' => 'The meal was acceptable, but rotis could have been warmer.', 's' => 'Please keep rotis in thermal containers.'],
            ['c' => 'Standard lunch today. Curd was fresh but dal felt a bit thin.', 's' => 'Make dal slightly thicker like home style.'],
            ['c' => 'Breakfast upma was okay, decent taste and normal portion size.', 's' => 'Add some more chopped vegetables into upma.'],
            ['c' => 'Food was decent today, no complaints regarding hygiene.', 's' => 'Temperature at counter 3 was slightly lukewarm.'],
            ['c' => 'Average dinner. Rice and dal were fine, sweet dish ran out a bit early.', 's' => 'Ensure enough sweet portions for late diners.'],
            ['c' => 'Snacks were moderate. Cutlet was fine, tea was slightly too sweet.', 's' => 'Provide separate sugar option for tea.']
        ],
        'Negative' => [
            ['c' => 'Rice was cold and watery, dal was very salty today. Hard to finish.', 's' => 'Please check salt level before serving hundreds of students.'],
            ['c' => 'The vegetable curry had poor taste and felt stale. Very disappointed with dinner.', 's' => 'Ensure fresh vegetables are used daily.'],
            ['c' => 'Food was cold by 1:30 PM and curry had excessive oil floating on top.', 's' => 'Use warmers to keep lunch hot until 2:30 PM.'],
            ['c' => 'Food has been repetitive this entire week. Same potato sabzi everywhere.', 's' => 'Introduce more variety in vegetables and curries.'],
            ['c' => 'Dirty trays noticed near counter 2 and drinking water glasses were not clean.', 's' => 'Strict hygiene monitoring needed in cleaning area.'],
            ['c' => 'Portion was too little initially and vegetables were bland and tasteless.', 's' => 'Improve seasoning and food presentation.'],
            ['c' => 'High food waste today because the curry was cold and tasteless.', 's' => 'Review the cook recipe for yellow dal.']
        ]
    ];

    echo "[4/5] Generating 140+ student feedback records with ratings, wastage, and sentiment...\n";

    $feedbackStmt = $pdo->prepare("
        INSERT INTO `feedback` (`menu_id`, `overall_rating`, `hygiene_rating`, `temperature`, `portion`, `wastage_level`, `wastage_reason`, `comment`, `suggestion`, `sentiment`, `sentiment_score`, `session_hash`, `created_at`)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $feedbackItemStmt = $pdo->prepare("
        INSERT INTO `feedback_items` (`feedback_id`, `menu_item_id`, `taste_rating`, `quality_rating`, `quantity_rating`, `freshness_rating`, `repeat_preference`, `created_at`)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $wastageRecordStmt = $pdo->prepare("
        INSERT INTO `wastage_records` (`feedback_id`, `menu_item_id`, `wastage_level`, `wastage_reason`, `created_at`)
        VALUES (?, ?, ?, ?, ?)
    ");

    $sentimentRecordStmt = $pdo->prepare("
        INSERT INTO `feedback_sentiment` (`feedback_id`, `sentiment`, `score`, `matched_keywords`, `created_at`)
        VALUES (?, ?, ?, ?, ?)
    ");

    $feedbackCount = 0;
    $temperatures = ['Too Cold', 'Acceptable', 'Hot/Fresh'];
    $portions = ['Too Little', 'Appropriate', 'Too Much'];
    $wastageLevels = ['None', 'Very Little', 'Some', 'A lot'];
    $wastageReasons = [
        'Taste was poor',
        'Quantity too much',
        'Food was cold',
        'Food was repetitive',
        'Hygiene concern',
        'Quality was poor',
        'Personal preference',
        'Other'
    ];

    // Filter menus to past 28 days + today
    $pastMenus = array_filter($createdMenus, function($m) use ($today) {
        return $m['date'] <= $today->format('Y-m-d');
    });

    foreach ($pastMenus as $menu) {
        // Average 1 to 2 feedbacks per menu, heavier on Lunch and Dinner, lighter on Snacks
        $numSubmissions = 1;
        if (in_array($menu['meal'], ['Lunch', 'Dinner'])) {
            $numSubmissions = (rand(1, 10) > 3) ? 2 : 1;
        }

        for ($s = 0; $s < $numSubmissions; $s++) {
            // Determine sentiment profile realistically:
            // Biryani/Paneer days lean positive, cold/rainy/repetitive lean negative, most lean neutral to positive
            $randSentimentRoll = rand(1, 100);
            if ($randSentimentRoll <= 45) {
                $category = 'Positive';
                $overallRating = rand(4, 5);
                $hygieneRating = rand(4, 5);
                $temp = 'Hot/Fresh';
                $portion = 'Appropriate';
                $wastage = (rand(1, 10) > 3) ? 'None' : 'Very Little';
                $wastageReason = ($wastage === 'None') ? null : 'Quantity too much';
            } elseif ($randSentimentRoll <= 75) {
                $category = 'Neutral';
                $overallRating = 3;
                $hygieneRating = rand(3, 4);
                $temp = (rand(1, 10) > 4) ? 'Acceptable' : 'Too Cold';
                $portion = (rand(1, 10) > 3) ? 'Appropriate' : ((rand(1, 2) === 1) ? 'Too Little' : 'Too Much');
                $wastage = (rand(1, 10) > 5) ? 'Very Little' : 'Some';
                $wastageReason = ($wastage === 'None') ? null : $wastageReasons[array_rand($wastageReasons)];
            } else {
                $category = 'Negative';
                $overallRating = rand(1, 2);
                $hygieneRating = rand(1, 3);
                $temp = (rand(1, 10) > 3) ? 'Too Cold' : 'Acceptable';
                $portion = (rand(1, 10) > 5) ? 'Too Little' : 'Too Much';
                $wastage = (rand(1, 10) > 3) ? 'Some' : 'A lot';
                $wastageReason = $wastageReasons[array_rand($wastageReasons)];
            }

            $commentTemplate = $studentFeedbackTemplates[$category][array_rand($studentFeedbackTemplates[$category])];
            $commentText = $commentTemplate['c'];
            $suggestionText = $commentTemplate['s'];

            // Run keyword sentiment classifier
            $sentimentAnalysis = analyzeSentimentKeywords($commentText . ' ' . $suggestionText);

            // Construct timestamp distributed throughout meal hours
            $hour = ($menu['meal'] === 'Breakfast') ? rand(8, 9) :
                    (($menu['meal'] === 'Lunch') ? rand(13, 14) :
                    (($menu['meal'] === 'Snacks') ? 17 : rand(20, 21)));
            $minute = rand(5, 55);
            $createdAt = sprintf("%s %02d:%02d:%02d", $menu['date'], $hour, $minute, rand(10, 50));
            $sessionHash = hash('sha256', "anon_student_" . $menu['id'] . "_{$s}_" . $createdAt);

            $feedbackStmt->execute([
                $menu['id'],
                $overallRating,
                $hygieneRating,
                $temp,
                $portion,
                $wastage,
                $wastageReason,
                $commentText,
                $suggestionText,
                $sentimentAnalysis['sentiment'],
                $sentimentAnalysis['score'],
                $sessionHash,
                $createdAt
            ]);
            $feedbackId = (int)$pdo->lastInsertId();
            $feedbackCount++;

            // Insert sentiment record
            $sentimentRecordStmt->execute([
                $feedbackId,
                $sentimentAnalysis['sentiment'],
                $sentimentAnalysis['score'],
                $sentimentAnalysis['matched'],
                $createdAt
            ]);

            // Insert dish-level feedback items
            $mostWastedDishId = null;
            foreach ($menu['items'] as $item) {
                // Bias dish ratings close to overall rating +/- 1
                $base = $overallRating;
                if ($category === 'Positive') {
                    $tRating = min(5, max(3, $base + rand(-1, 1)));
                    $qRating = min(5, max(3, $base + rand(-1, 0)));
                    $qnRating = min(5, max(3, $base + rand(-1, 1)));
                    $fRating = min(5, max(4, $base + rand(0, 1)));
                    $repeat = (rand(1, 10) > 2) ? 'Yes' : 'Maybe';
                } elseif ($category === 'Neutral') {
                    $tRating = min(4, max(2, $base + rand(-1, 1)));
                    $qRating = min(4, max(2, $base + rand(-1, 1)));
                    $qnRating = min(5, max(2, $base + rand(-1, 1)));
                    $fRating = min(4, max(2, $base + rand(-1, 1)));
                    $repeat = (rand(1, 10) > 4) ? 'Maybe' : 'Yes';
                } else {
                    $tRating = min(3, max(1, $base + rand(-1, 1)));
                    $qRating = min(3, max(1, $base + rand(-1, 1)));
                    $qnRating = min(4, max(1, $base + rand(-1, 1)));
                    $fRating = min(3, max(1, $base + rand(-1, 1)));
                    $repeat = (rand(1, 10) > 2) ? 'No' : 'Maybe';
                    if ($mostWastedDishId === null && str_contains(strtolower($item['name']), 'rice') || str_contains(strtolower($item['name']), 'curry') || str_contains(strtolower($item['name']), 'dal')) {
                        $mostWastedDishId = $item['id'];
                    }
                }

                $feedbackItemStmt->execute([
                    $feedbackId,
                    $item['id'],
                    $tRating,
                    $qRating,
                    $qnRating,
                    $fRating,
                    $repeat,
                    $createdAt
                ]);
            }

            // Insert Wastage Record if wastage occurred
            if ($wastage !== 'None') {
                if (!$mostWastedDishId && !empty($menu['items'])) {
                    $mostWastedDishId = $menu['items'][array_rand($menu['items'])]['id'];
                }
                $wastageRecordStmt->execute([
                    $feedbackId,
                    $mostWastedDishId,
                    $wastage,
                    $wastageReason,
                    $createdAt
                ]);
            }
        }
    }

    echo "  - Generated {$feedbackCount} anonymous feedback entries with multi-attribute ratings.\n";

    echo "[5/5] Checking Data Integrity & Aggregates...\n";
    $stats = $pdo->query("
        SELECT 
            (SELECT COUNT(*) FROM menus) as total_menus,
            (SELECT COUNT(*) FROM menu_items) as total_items,
            (SELECT COUNT(*) FROM feedback) as total_feedback,
            (SELECT COUNT(*) FROM wastage_records) as total_wastage_records,
            (SELECT ROUND(AVG(overall_rating), 2) FROM feedback) as avg_rating,
            (SELECT COUNT(*) FROM feedback WHERE sentiment = 'Positive') as pos_sentiment,
            (SELECT COUNT(*) FROM feedback WHERE sentiment = 'Negative') as neg_sentiment,
            (SELECT COUNT(*) FROM feedback WHERE sentiment = 'Neutral') as neu_sentiment
    ")->fetch();

    echo "=== SEEDING COMPLETED SUCCESSFULLY ===\n";
    echo "Summary:\n";
    echo "  - Total Menus Created: " . $stats['total_menus'] . "\n";
    echo "  - Total Menu Items: " . $stats['total_items'] . "\n";
    echo "  - Total Feedbacks: " . $stats['total_feedback'] . "\n";
    echo "  - Wastage Incidents: " . $stats['total_wastage_records'] . "\n";
    echo "  - Overall Avg Rating: " . $stats['avg_rating'] . " / 5.0\n";
    echo "  - Sentiment Distribution: Positive={$stats['pos_sentiment']}, Neutral={$stats['neu_sentiment']}, Negative={$stats['neg_sentiment']}\n";
    echo "  - Admin Access: http://localhost:8000/admin/login.html (User: admin | Pass: admin123)\n";

} catch (Exception $e) {
    echo "ERROR during database seeding: " . $e->getMessage() . "\n";
    exit(1);
}
