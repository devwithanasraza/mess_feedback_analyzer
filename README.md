# Mess Menu Feedback Analyzer

> **B.Tech Data Science 1st-Year Academic Project**  
> An anonymous student dining feedback and plate-wastage analysis system designed for college mess operations.

---

## 1. Project Overview & Problem Statement

College hostellers often experience fluctuations in mess food quality, repetitive recipes, incorrect meal temperatures, and unbalanced portions. However, students rarely report these issues due to fears of contractor friction, complicated administrative processes, or loss of privacy. 

Concurrently, college dining administrations struggle with severe **food wastage**—often preparing excess food that gets discarded—because they lack real-time data regarding student dish preferences and plate-waste root causes.

The **Mess Menu Feedback Analyzer** bridges this gap:
1. Provides students with a **100% anonymous, multi-step feedback form** to review meals honestly without revealing their name, roll number, or email.
2. Implements **transparent Data Science aggregation algorithms** to compute weighted satisfaction scores, lexicon-based sentiment polarities, and plate-waste indices.
3. Equips mess management with an **Operations Dashboard**, interactive Chart.js visualizations, automated kitchen recommendations, and anonymous CSV exports.

---

## 2. Technology Stack

- **Frontend:** HTML5, CSS3 (Custom responsive design system, university portal aesthetic; no Tailwind or Bootstrap frameworks), Vanilla JavaScript (ES6+).
- **Backend:** PHP 8.2+ (Object-Oriented PDO, prepared SQL statements, session authentication, REST-style JSON endpoints, input sanitization, CSRF & anti-spam honeypot defense).
- **Database:** MySQL 8+ / MariaDB (Normalized relational schema, foreign key cascades, B-Tree indexes, multi-table transactions).
- **Data Visualizations:** Chart.js (via CDN).
- **Data Science Modeling:** Lexicon keyword sentiment scoring, multi-attribute weighted linear combinations, and dish performance scoring.

---

## 3. Key Features

### Student / Public Portal
- **Modern Landing Page (`index.html`):** Hero section with *"Make Every Meal Better"*, dynamic campus mess pulse preview, feature cards, and 4-step workflow.
- **Daily Menu Viewer (`menu.html`):** Displays Breakfast, Lunch, Snacks, and Dinner with dish categories, dietary indicators (Veg / Non-Veg), serving schedules, peer ratings, and direct *"Rate This Meal"* links.
- **Multi-Step Feedback Form (`feedback.html`):** 5-step intuitive wizard (`1 ━━━ 2 ━━━ 3 ━━━ 4 ━━━ 5`):
  1. *Select Meal & Date:* Automatic date selector and meal session selector.
  2. *Rate Dishes:* Multi-attribute star ratings for Taste, Quality, Quantity, Freshness, and Repeat Preference (*Yes / Maybe / No*).
  3. *Overall Experience:* Overall meal rating (1-5), Mess hall hygiene rating (1-5), Temperature chips (*Too Cold / Acceptable / Hot & Fresh*), and Portion chips (*Too Little / Appropriate / Too Much*).
  4. *Plate Wastage Analysis:* Amount left (*None / Very Little / Some / A lot*), conditional root cause selector (*Taste, Quality, Quantity too much, Food was cold, Repetitive, Hygiene, Personal preference, Other*), and specific dish identification.
  5. *Comments & Suggestions:* Textareas for feedback and constructive advice.
- **Confirmation Page (`success.html`):** Confirmation card summarizing recorded meal and sentiment classification.
- **Data Science Documentation (`about.html`):** Complete academic presentation guide with formulas, pipeline diagrams, privacy architecture, and viva questions.

### Admin Operations Portal
- **Secure Admin Authentication (`admin/login.html`):** Password hashing (`PASSWORD_DEFAULT`), PHP session cookies (`HttpOnly`, `SameSite`), rate-limiting, and demo credentials notice.
- **Admin Dashboard (`admin/dashboard.html`):** 6 live KPI cards, time range filter (7, 14, 30 days), dynamically calculated Actionable Insights, and 7 responsive Chart.js visualizations:
  1. *Average Rating by Meal* (Bar Chart)
  2. *Weekly Satisfaction Trend* (Line Chart)
  3. *Food Wastage Trend Over Time* (Line Chart)
  4. *Top 5 Most Liked Dishes* (Horizontal Bar Chart)
  5. *Top 5 Least Liked Dishes* (Horizontal Bar Chart)
  6. *Feedback Sentiment Breakdown* (Doughnut Chart)
  7. *Meal-wise Satisfaction Comparison* (Bar Chart)
- **Menu Management (`admin/menu.html`):** Full CRUD interface to schedule daily meals, add dishes with dietary and category tags, modify items, and delete menus.
- **Food Quality Analytics (`admin/analytics.html`):** Attribute breakdown (Taste, Quality, Hygiene, Freshness, Quantity), Dish Performance Score table with tier badges (*Excellent, Good, Average, Needs Improvement*), and temperature/portion distribution charts.
- **Wastage Analytics (`admin/wastage.html`):** Dedicated plate-waste auditing with severity indices, meal breakdowns, dish attribution, reasons distribution, and portion mismatch correlation.
- **Feedback Records Table (`admin/feedback.html`):** Anonymous feedback records with multi-filter search (date range, meal, rating, sentiment, wastage level) and detail inspection modal.
- **Executive Reports & CSV Export (`admin/reports.html`):** Daily, Weekly, and Monthly aggregated audit reports with instant anonymous CSV dataset download.

---

## 4. Data Science Methodology

### A. Weighted Satisfaction Score
Simple averages misrepresent dining quality because taste and hygiene impact student health far more than serving size. We implement a multi-attribute weighted linear combination model:

$$\text{Weighted Satisfaction (\%)} = \frac{(\text{Overall} \times 0.40) + (\text{Taste} \times 0.20) + (\text{Quality} \times 0.15) + (\text{Hygiene} \times 0.10) + (\text{Freshness} \times 0.10) + (\text{Quantity} \times 0.05)}{5.0} \times 100$$

### B. Rule-Based Lexicon Sentiment Classifier
To remain explainable without heavy deep-learning dependencies, student text comments are classified using a keyword dictionary:
- **Positive Keywords:** `good`, `great`, `tasty`, `fresh`, `excellent`, `delicious`, `nice`, `amazing`, `superb`, `crispy`, `soft`, `flavorful`, `satisfying`, `perfect`, `clean`, `hygienic`, `love`, `best`, `well`
- **Negative Keywords:** `bad`, `poor`, `cold`, `oily`, `salty`, `bland`, `tasteless`, `dirty`, `unhealthy`, `waste`, `sour`, `hard`, `stale`, `smelly`, `delayed`, `watery`, `undercooked`, `burnt`, `insects`, `hair`, `horrible`
- **Polarity Formula:**
  $$\text{Score} = \frac{\text{Positive Count} - \text{Negative Count}}{\text{Total Matches} + 1}$$
  Scores $> 0$ are classified as **Positive**, $< 0$ as **Negative**, and equal counts as **Neutral**.

### C. Menu Performance Score (MPS) & Wastage Penalty
Every dish receives a composite score to guide mess procurement:
$$\text{Dish Score} = \text{Avg Taste} + \text{Avg Quality} + \text{Avg Freshness} + \text{Repeat Bonus} - \text{Wastage Penalty}$$
- **Repeat Bonus:** $+1.0$ if $\ge 70\%$ say *Yes*, $+0.5$ if $45-69\%$, $-0.5$ if $<45\%$.
- **Wastage Penalty:** Proportional to the ratio of waste reports for that dish.
- **Tiers:** $\ge 12.0$ (*Excellent*), $10.0-11.9$ (*Good*), $8.0-9.9$ (*Average*), $<8.0$ (*Needs Improvement*).

---

## 5. Why MySQL?

> *"Why MySQL instead of NoSQL or raw CSV storage?"*

1. **Relational Integrity:** A single daily schedule has multiple meals, each meal has multiple dishes, and each dish receives ratings and wastage logs from hundreds of anonymous students over time. Foreign keys (`ON DELETE CASCADE`) prevent orphan data.
2. **High-Performance Aggregations:** Statistical functions like `AVG()`, `COUNT()`, and `GROUP BY` run in milliseconds with indexed keys (`menu_date`, `meal_type`, `created_at`).
3. **ACID Transactions:** Feedback submission atomically writes to `feedback`, `feedback_items`, `wastage_records`, and `feedback_sentiment` in a single transaction, guaranteeing data consistency.

---

## 6. Database Schema

The database `mess_feedback_analyzer` contains 7 relational tables:
1. `admins`: ID, username, password hash, email, full name, role, last login.
2. `menus`: ID, menu date, meal type (`Breakfast`, `Lunch`, `Snacks`, `Dinner`), serving time, status.
3. `menu_items`: ID, menu ID (FK), item name, category, is_veg, description, is_active.
4. `feedback`: ID, menu ID (FK), overall rating, hygiene rating, temperature, portion, wastage level, wastage reason, comment, suggestion, sentiment, sentiment score, session hash, timestamp.
5. `feedback_items`: ID, feedback ID (FK), menu item ID (FK), taste rating, quality rating, quantity rating, freshness rating, repeat preference (`Yes`, `Maybe`, `No`).
6. `wastage_records`: ID, feedback ID (FK), menu item ID (FK), wastage level, wastage reason, timestamp.
7. `feedback_sentiment`: ID, feedback ID (FK), sentiment, score, matched keywords, timestamp.

---

## 7. Installation & Running Locally

### Prerequisites
- PHP 8.0 or higher
- MySQL 8.0+ or MariaDB 10.4+ (e.g., via XAMPP, Laragon, or standalone service)

### Step 1: Clone or Navigate to Directory
```bash
cd d:/Mess-Feedback-Analyzier
```

### Step 2: Database Initialization & Seeding
Ensure your MySQL service is running on `localhost:3306` with user `root` (default XAMPP configuration). Then run:
```bash
php database/seed.php
```
This script will:
- Create database `mess_feedback_analyzer` if it does not exist.
- Apply `database/schema.sql`.
- Create default administrator (`admin` / `admin123`).
- Populate 30 days of 4 meals/day with realistic college dishes.
- Seed 150+ realistic anonymous feedback records with item ratings, wastage data, and sentiment scores.

### Step 3: Start the Web Server
You can run PHP's built-in development web server directly:
```bash
php -S 127.0.0.1:8000
```
Or place the project directory inside `htdocs` in XAMPP.

### Step 4: Access the Application
- **Student Public Portal:** [http://localhost:8000/index.html](http://localhost:8000/index.html)
- **Today's Menu:** [http://localhost:8000/menu.html](http://localhost:8000/menu.html)
- **Give Feedback:** [http://localhost:8000/feedback.html](http://localhost:8000/feedback.html)
- **Methodology & Viva Guide:** [http://localhost:8000/about.html](http://localhost:8000/about.html)
- **Admin Portal Sign In:** [http://localhost:8000/admin/login.html](http://localhost:8000/admin/login.html)
  - **Username:** `admin`
  - **Password:** `admin123`

---

## 7.1 Vercel Deployment Guide (100% Tech Stack Preserved)

You can deploy this full PHP + MySQL application directly to Vercel without changing any HTML, CSS, JavaScript, or PHP code.

### How it works on Vercel:
1. **`vercel.json`:** Configured with `vercel-php@0.7.3` serverless runtime for all endpoints in `/api/*.php`.
2. **Serverless Session Persistence:** Includes HMAC token authentication with HTTPOnly cookies and bearer token fallback so admin sessions persist across serverless functions.
3. **Remote MySQL Cloud Database:** The FreeSQLDatabase instance (`sql12.freesqldatabase.com`) is already connected and seeded.

### Deployment Steps:
1. **Push your code to GitHub:**
   ```bash
   git init
   git add .
   git commit -m "Mess Menu Feedback Analyzer ready for Vercel"
   git branch -M main
   git remote add origin https://github.com/YOUR_USERNAME/Mess-Feedback-Analyzer.git
   git push -u origin main
   ```
2. **Deploy on Vercel:**
   - Go to [vercel.com](https://vercel.com) and log in.
   - Click **"Add New"** → **"Project"**.
   - Import your GitHub repository.
   - (Optional) In **Environment Variables**, you can set:
     - `DB_HOST`: `sql12.freesqldatabase.com`
     - `DB_USER`: `sql12838781`
     - `DB_PASS`: `66lmY5hzBS`
     - `DB_NAME`: `sql12838781`
     - `DB_PORT`: `3306`
     - `APP_SECRET`: `mess_feedback_analyzer_secret_2026`
   - Click **"Deploy"**.

3. **Enjoy your live deployment!**
   - Vercel will serve your static frontend HTML/CSS/JS and run all PHP REST API endpoints automatically.

---

## 8. Privacy Architecture & Anti-Spam Defense

- **No Identity Retained:** The application never asks for or stores Student Name, Roll Number, Phone Number, Email Address, or IP Address.
- **Session Hash Protection:** Daily submission rate-limiting uses a one-way cryptographic SHA-256 hash of a browser cookie and meal ID (`hash('sha256', $token . '_' . $menuId)`), preventing duplicate spam while preserving student anonymity.
- **Honeypot Fields:** Invisible input fields detect and silently drop automated bot spam.
- **SQL Injection Prevention:** 100% of queries use PDO prepared statements with parameter binding.

---

## 9. Future Scope

1. **Machine Learning Food Demand Forecasting:** Use historical turnout and weather patterns to forecast daily student count.
2. **NLP-Based Fine-Grained Sentiment Analysis:** Transition from dictionary lexicons to transformer-based aspect sentiment models (e.g., BERT for food reviews).
3. **Computer Vision Plate-Waste Estimation:** Mount cameras over tray return counters to automatically calculate percentage of food left on plates.
4. **Automated Recipe Recommendation Engine:** Dynamically recommend menu swaps when dish performance scores drop below acceptable thresholds.

---

## 10. Academic Viva Q&A Quick Reference

| Viva Question | Concise Answer |
|---|---|
| **What is the objective of this project?** | To provide an anonymous feedback channel for hostel students while giving mess management data-backed insights on dish ratings and food wastage root causes. |
| **Why is the satisfaction score weighted?** | Dining satisfaction is multi-dimensional. Overall meal rating (40%), Taste (20%), Quality (15%), Hygiene (10%), Freshness (10%), and Quantity (5%) balance sensory satisfaction with health safety. |
| **How does sentiment classification work?** | It uses dictionary tokenization in PHP to calculate polarity scores from student comments without requiring external heavy AI APIs. |
| **How does the system ensure student privacy?** | It requests zero personal identifiers (no names, roll numbers, or emails) and stores zero IP addresses. Rate-limiting is handled via anonymous session tokens. |
| **Why use MySQL instead of Excel or NoSQL?** | Relational integrity ensures foreign key cascades, transactional consistency during multi-table writes, and rapid statistical aggregations over large datasets. |

---

**Developed for B.Tech Data Science 1st-Year Academic Project.**
