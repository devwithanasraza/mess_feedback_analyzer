/**
 * Today's Menu Page Script
 * Mess Menu Feedback Analyzer - B.Tech Data Science Project
 */

let currentMeals = [];
let activeMealTab = 'Breakfast';

document.addEventListener('DOMContentLoaded', () => {
  initDateSwitcher();
  loadMenuData();
});

function initDateSwitcher() {
  const dateInput = document.getElementById('menu-date-input');
  if (dateInput) {
    dateInput.value = new Date().toISOString().split('T')[0];
    dateInput.addEventListener('change', () => {
      loadMenuData(dateInput.value);
    });
  }

  // Quick date buttons
  const btnToday = document.getElementById('btn-date-today');
  if (btnToday) {
    btnToday.addEventListener('click', () => {
      const today = new Date().toISOString().split('T')[0];
      if (dateInput) dateInput.value = today;
      loadMenuData(today);
    });
  }
}

async function loadMenuData(selectedDate = null) {
  const container = document.getElementById('menu-content-area');
  if (!container) return;

  container.innerHTML = `
    <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(300px, 1fr)); gap:1.5rem; margin-top:2rem;">
      <div class="skeleton" style="height: 180px;"></div>
      <div class="skeleton" style="height: 180px;"></div>
      <div class="skeleton" style="height: 180px;"></div>
    </div>
  `;

  const url = selectedDate ? `api/get_menu.php?date=${selectedDate}` : 'api/get_menu.php';
  const res = await apiFetch(url);

  if (res.success && res.meals && res.meals.length > 0) {
    currentMeals = res.meals;

    // Update date picker value if server adjusted to closest date
    const dateInput = document.getElementById('menu-date-input');
    if (dateInput && res.date) {
      dateInput.value = res.date;
    }

    // Populate available dates dropdown if present
    const dateSelect = document.getElementById('available-dates-select');
    if (dateSelect && res.available_dates) {
      dateSelect.innerHTML = '';
      res.available_dates.forEach(d => {
        const opt = document.createElement('option');
        opt.value = d;
        opt.textContent = `${formatDate(d)} ${d === res.date ? '(Selected)' : ''}`;
        if (d === res.date) opt.selected = true;
        dateSelect.appendChild(opt);
      });
      dateSelect.onchange = () => loadMenuData(dateSelect.value);
    }

    renderMealTabs();
    renderActiveMealContent();
  } else {
    container.innerHTML = `
      <div class="empty-state">
        <div class="empty-icon">🍽️</div>
        <h3 class="empty-title">No Menu Published Yet</h3>
        <p class="empty-desc">There is no mess menu scheduled for the selected date. Please check another date or contact mess administration.</p>
        <a href="feedback.html" class="btn btn-primary">Give Feedback on Previous Meal</a>
      </div>
    `;
  }
}

function renderMealTabs() {
  const tabsContainer = document.getElementById('meal-tabs-container');
  if (!tabsContainer) return;

  tabsContainer.innerHTML = '';

  const order = ['Breakfast', 'Lunch', 'Snacks', 'Dinner'];
  order.forEach(mealType => {
    const mealData = currentMeals.find(m => m.meal_type.toLowerCase() === mealType.toLowerCase());
    if (!mealData) return;

    const btn = document.createElement('button');
    btn.className = `meal-tab ${mealType === activeMealTab ? 'active' : ''}`;
    btn.textContent = `${mealType}`;
    btn.addEventListener('click', () => {
      activeMealTab = mealType;
      document.querySelectorAll('.meal-tab').forEach(t => t.classList.remove('active'));
      btn.classList.add('active');
      renderActiveMealContent();
    });
    tabsContainer.appendChild(btn);
  });
}

function renderActiveMealContent() {
  const container = document.getElementById('menu-content-area');
  if (!container) return;

  const meal = currentMeals.find(m => m.meal_type.toLowerCase() === activeMealTab.toLowerCase());
  if (!meal) {
    // If activeMealTab is not in current meals, select first available
    if (currentMeals.length > 0) {
      activeMealTab = currentMeals[0].meal_type;
      renderActiveMealContent();
    }
    return;
  }

  const dateVal = document.getElementById('menu-date-input')?.value || '';

  let html = `
    <div class="meal-banner">
      <div class="meal-banner-info">
        <h2>${escapeHtml(meal.meal_type)}</h2>
        <p>Serving Schedule: <strong>${escapeHtml(meal.serving_time)}</strong></p>
      </div>
      <div style="display:flex; align-items:center; gap:1.25rem;">
        <div style="text-align:right;">
          <div style="font-size:1.15rem; font-weight:800; color:var(--color-primary-dark);">
            ★ ${meal.avg_rating > 0 ? meal.avg_rating : 'New'} <span style="font-size:0.85rem; font-weight:normal; color:var(--color-text-muted);">/ 5.0</span>
          </div>
          <div style="font-size:0.78rem; color:var(--color-text-muted);">${meal.total_reviews} verified reviews</div>
        </div>
        <a href="feedback.html?meal=${encodeURIComponent(meal.meal_type)}&date=${encodeURIComponent(dateVal)}" class="btn btn-accent">
          Rate This Meal
        </a>
      </div>
    </div>
  `;

  if (!meal.items || meal.items.length === 0) {
    html += `
      <div class="empty-state">
        <p class="empty-desc">No dish items listed for this meal schedule.</p>
      </div>
    `;
  } else {
    html += `<div class="dishes-grid">`;
    meal.items.forEach(dish => {
      html += `
        <div class="dish-card">
          <div class="dish-top">
            <span class="badge ${dish.is_veg == 1 ? 'badge-veg' : 'badge-nonveg'}">
              ${dish.is_veg == 1 ? '● Veg' : '▲ Non-Veg'}
            </span>
            <span class="badge badge-neutral">${escapeHtml(dish.category)}</span>
          </div>
          
          <h3 class="dish-name">${escapeHtml(dish.item_name)}</h3>
          <p class="dish-desc">${escapeHtml(dish.description || 'Prepared fresh daily by mess chefs.')}</p>

          <div class="dish-bottom">
            <div class="dish-rating-preview">
              <span>★</span>
              <span>${dish.avg_rating > 0 ? dish.avg_rating : 'New'}</span>
              <span class="dish-reviews-count">(${dish.review_count} ratings)</span>
            </div>
            <a href="feedback.html?meal=${encodeURIComponent(meal.meal_type)}&date=${encodeURIComponent(dateVal)}" class="btn btn-sm btn-outline">
              Rate Dish
            </a>
          </div>
        </div>
      `;
    });
    html += `</div>`;
  }

  container.innerHTML = html;
}
