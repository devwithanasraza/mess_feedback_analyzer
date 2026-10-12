/**
 * Multi-Step Anonymous Student Feedback Wizard
 * Mess Menu Feedback Analyzer - B.Tech Data Science Project
 */

let currentStep = 1;
const totalSteps = 5;

let activeMenus = [];
let selectedMenuId = null;
let currentMenuDishes = [];

// Form State Data
const feedbackData = {
  menu_id: null,
  overall_rating: 0,
  hygiene_rating: 0,
  temperature: 'Acceptable',
  portion: 'Appropriate',
  wastage_level: 'None',
  wastage_reason: '',
  wasted_dish_id: null,
  comment: '',
  suggestion: '',
  items: []
};

document.addEventListener('DOMContentLoaded', async () => {
  initDateAndMealSelectors();
  initStaticStarRatings();
  initChipSelectors();
  initStepNavigation();
  initFormSubmission();

  // Check URL query parameters for pre-selected meal or date
  const urlParams = new URLSearchParams(window.location.search);
  const preSelectedMeal = urlParams.get('meal');
  const preSelectedDate = urlParams.get('date');

  if (preSelectedDate) {
    const dateInput = document.getElementById('feedback-date');
    if (dateInput) dateInput.value = preSelectedDate;
  }

  await loadMenusForSelectedDate(preSelectedMeal);
});

// Initialize Date & Load Available Meals
function initDateAndMealSelectors() {
  const dateInput = document.getElementById('feedback-date');
  if (dateInput && !dateInput.value) {
    dateInput.value = new Date().toISOString().split('T')[0];
  }

  if (dateInput) {
    dateInput.addEventListener('change', () => {
      loadMenusForSelectedDate();
    });
  }
}

// Load menus for chosen date
async function loadMenusForSelectedDate(autoSelectMeal = null) {
  const dateInput = document.getElementById('feedback-date');
  const selectedDate = dateInput ? dateInput.value : '';
  const mealContainer = document.getElementById('meal-cards-container');
  if (!mealContainer) return;

  mealContainer.innerHTML = '<div class="skeleton" style="height: 100px; width: 100%;"></div>';

  const res = await apiFetch(`api/get_menu.php?date=${selectedDate}`);
  if (res.success && res.meals && res.meals.length > 0) {
    activeMenus = res.meals;
    renderMealOptions(res.meals, autoSelectMeal);
  } else {
    mealContainer.innerHTML = `
      <div class="empty-state" style="padding: 2rem 1rem;">
        <p class="empty-desc">No meal menu has been published for ${selectedDate}. Please select another date.</p>
      </div>
    `;
    activeMenus = [];
    selectedMenuId = null;
  }
}

// Render Meal Selection Cards
function renderMealOptions(meals, autoSelectMeal) {
  const container = document.getElementById('meal-cards-container');
  container.innerHTML = '';

  let matchedMenu = null;

  meals.forEach(m => {
    const isTarget = autoSelectMeal && m.meal_type.toLowerCase() === autoSelectMeal.toLowerCase();
    if (isTarget) matchedMenu = m;

    const card = document.createElement('label');
    card.className = `chip-option`;
    card.style.flex = '1';
    card.style.minWidth = '140px';
    card.innerHTML = `
      <input type="radio" name="meal_choice" value="${m.id}" ${isTarget ? 'checked' : ''}>
      <div class="chip-label" style="display:flex; flex-direction:column; align-items:flex-start; width:100%; padding:0.9rem 1rem;">
        <span style="font-size:1.05rem; font-weight:700;">${escapeHtml(m.meal_type)}</span>
        <span style="font-size:0.75rem; color:var(--color-text-muted);">${escapeHtml(m.serving_time)}</span>
        <span style="font-size:0.75rem; margin-top:0.35rem; color:var(--color-primary);">
          ★ ${m.avg_rating > 0 ? m.avg_rating : 'New'} (${m.total_reviews} reviews)
        </span>
      </div>
    `;

    card.querySelector('input').addEventListener('change', () => {
      selectMenu(m);
    });

    container.appendChild(card);
  });

  if (matchedMenu) {
    selectMenu(matchedMenu);
  } else if (meals.length > 0) {
    // Select first meal by default
    container.querySelector('input').checked = true;
    selectMenu(meals[0]);
  }
}

// Select Menu & Render Dishes for Step 2
function selectMenu(menu) {
  selectedMenuId = menu.id;
  feedbackData.menu_id = menu.id;
  currentMenuDishes = menu.items || [];
  renderDishRatingCards(currentMenuDishes);
  populateWastedDishDropdown(currentMenuDishes);

  const selectedMealBadge = document.getElementById('selected-meal-display');
  if (selectedMealBadge) {
    selectedMealBadge.textContent = `${menu.meal_type} (${menu.serving_time})`;
  }
}

// Render Step 2 Dish Rating Cards
function renderDishRatingCards(dishes) {
  const container = document.getElementById('dishes-rating-container');
  if (!container) return;

  if (!dishes || dishes.length === 0) {
    container.innerHTML = `
      <div class="empty-state" style="padding: 2rem 1rem;">
        <p class="empty-desc">No dish items listed for this meal. You can proceed to rate the overall experience.</p>
      </div>
    `;
    feedbackData.items = [];
    return;
  }

  container.innerHTML = '';
  feedbackData.items = [];

  dishes.forEach((dish, index) => {
    // Initial state for this dish
    const dishState = {
      menu_item_id: dish.id,
      taste_rating: 4,
      quality_rating: 4,
      quantity_rating: 3,
      freshness_rating: 4,
      repeat_preference: 'Yes'
    };
    feedbackData.items.push(dishState);

    const card = document.createElement('div');
    card.className = 'dish-rating-card';
    card.innerHTML = `
      <div class="dish-card-header">
        <div class="dish-card-title">
          <span class="badge ${dish.is_veg == 1 ? 'badge-veg' : 'badge-nonveg'}">
            ${dish.is_veg == 1 ? '● Veg' : '▲ Non-Veg'}
          </span>
          <span>${escapeHtml(dish.item_name)}</span>
        </div>
        <span class="badge badge-neutral">${escapeHtml(dish.category)}</span>
      </div>

      <div class="dish-card-ratings-grid">
        <!-- Taste -->
        <div class="attribute-rating-row">
          <span class="attribute-rating-label">Taste:</span>
          <div class="star-rating-control" data-dish-idx="${index}" data-attr="taste_rating">
            ${generateInteractiveStars(4)}
          </div>
        </div>

        <!-- Quality -->
        <div class="attribute-rating-row">
          <span class="attribute-rating-label">Quality:</span>
          <div class="star-rating-control" data-dish-idx="${index}" data-attr="quality_rating">
            ${generateInteractiveStars(4)}
          </div>
        </div>

        <!-- Freshness -->
        <div class="attribute-rating-row">
          <span class="attribute-rating-label">Freshness:</span>
          <div class="star-rating-control" data-dish-idx="${index}" data-attr="freshness_rating">
            ${generateInteractiveStars(4)}
          </div>
        </div>

        <!-- Quantity -->
        <div class="attribute-rating-row">
          <span class="attribute-rating-label">Quantity:</span>
          <div class="star-rating-control" data-dish-idx="${index}" data-attr="quantity_rating">
            ${generateInteractiveStars(3)}
          </div>
        </div>

        <!-- Repeat Preference -->
        <div class="repeat-row">
          <span class="attribute-rating-label">Would you like this dish served again?</span>
          <div class="chip-group" data-dish-idx="${index}">
            <label class="chip-option">
              <input type="radio" name="repeat_${dish.id}" value="Yes" checked>
              <span class="chip-label" style="padding:0.3rem 0.75rem; font-size:0.8rem;">Yes</span>
            </label>
            <label class="chip-option">
              <input type="radio" name="repeat_${dish.id}" value="Maybe">
              <span class="chip-label" style="padding:0.3rem 0.75rem; font-size:0.8rem;">Maybe</span>
            </label>
            <label class="chip-option">
              <input type="radio" name="repeat_${dish.id}" value="No">
              <span class="chip-label" style="padding:0.3rem 0.75rem; font-size:0.8rem;">No</span>
            </label>
          </div>
        </div>
      </div>
    `;

    // Attach click events for stars in this dish card
    card.querySelectorAll('.star-rating-control').forEach(ctrl => {
      const attr = ctrl.dataset.attr;
      const stars = ctrl.querySelectorAll('.star-btn');
      stars.forEach(btn => {
        btn.addEventListener('click', (e) => {
          e.preventDefault();
          const val = parseInt(btn.dataset.val, 10);
          dishState[attr] = val;
          updateStarControlDisplay(ctrl, val);
        });
      });
    });

    // Attach repeat preference radio change
    card.querySelectorAll(`input[name="repeat_${dish.id}"]`).forEach(radio => {
      radio.addEventListener('change', () => {
        dishState.repeat_preference = radio.value;
      });
    });

    container.appendChild(card);
  });
}

function generateInteractiveStars(initialVal = 4) {
  let html = '';
  for (let i = 1; i <= 5; i++) {
    html += `<button type="button" class="star-btn ${i <= initialVal ? 'active' : ''}" data-val="${i}">★</button>`;
  }
  return html;
}

function updateStarControlDisplay(container, rating) {
  const btns = container.querySelectorAll('.star-btn');
  btns.forEach(btn => {
    const val = parseInt(btn.dataset.val, 10);
    if (val <= rating) {
      btn.classList.add('active');
    } else {
      btn.classList.remove('active');
    }
  });
}

// Populate Wasted Dish Dropdown for Step 4
function populateWastedDishDropdown(dishes) {
  const select = document.getElementById('wasted-dish-select');
  if (!select) return;

  select.innerHTML = '<option value="">-- Select Specific Dish (Optional) --</option>';
  dishes.forEach(d => {
    const opt = document.createElement('option');
    opt.value = d.id;
    opt.textContent = `${d.item_name} (${d.category})`;
    select.appendChild(opt);
  });
}

// Static Star Ratings for Step 3 (Overall & Hygiene)
function initStaticStarRatings() {
  document.querySelectorAll('.rating-block').forEach(block => {
    const field = block.dataset.field; // overall_rating or hygiene_rating
    const stars = block.querySelectorAll('.star-btn');
    stars.forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        const val = parseInt(btn.dataset.val, 10);
        feedbackData[field] = val;
        updateStarControlDisplay(block, val);

        const valDisplay = block.querySelector('.rating-val-text');
        if (valDisplay) {
          valDisplay.textContent = `${val} / 5 stars`;
        }
      });
    });
  });
}

// Radio Chip Selectors (Temperature, Portion, Wastage)
function initChipSelectors() {
  // Temperature
  document.querySelectorAll('input[name="temperature"]').forEach(radio => {
    radio.addEventListener('change', () => {
      feedbackData.temperature = radio.value;
    });
  });

  // Portion
  document.querySelectorAll('input[name="portion"]').forEach(radio => {
    radio.addEventListener('change', () => {
      feedbackData.portion = radio.value;
    });
  });

  // Wastage Level
  document.querySelectorAll('input[name="wastage_level"]').forEach(radio => {
    radio.addEventListener('change', () => {
      feedbackData.wastage_level = radio.value;
      const reasonsSection = document.getElementById('wastage-reasons-section');
      if (reasonsSection) {
        if (radio.value === 'None') {
          reasonsSection.style.display = 'none';
        } else {
          reasonsSection.style.display = 'block';
        }
      }
    });
  });

  // Wastage Reason Chips
  document.querySelectorAll('input[name="wastage_reason"]').forEach(radio => {
    radio.addEventListener('change', () => {
      feedbackData.wastage_reason = radio.value;
    });
  });

  // Wasted Dish select
  const wastedDishSelect = document.getElementById('wasted-dish-select');
  if (wastedDishSelect) {
    wastedDishSelect.addEventListener('change', () => {
      feedbackData.wasted_dish_id = wastedDishSelect.value ? parseInt(wastedDishSelect.value, 10) : null;
    });
  }
}

// Step Navigation (Next & Previous with Validation)
function initStepNavigation() {
  const nextBtn = document.getElementById('btn-next-step');
  const prevBtn = document.getElementById('btn-prev-step');

  if (nextBtn) {
    nextBtn.addEventListener('click', () => {
      if (validateCurrentStep(currentStep)) {
        if (currentStep < totalSteps) {
          goToStep(currentStep + 1);
        }
      }
    });
  }

  if (prevBtn) {
    prevBtn.addEventListener('click', () => {
      if (currentStep > 1) {
        goToStep(currentStep - 1);
      }
    });
  }
}

function goToStep(step) {
  currentStep = step;

  // Show/Hide step panes
  document.querySelectorAll('.form-step').forEach((pane, idx) => {
    if (idx + 1 === step) {
      pane.classList.add('active');
    } else {
      pane.classList.remove('active');
    }
  });

  // Update Stepper Progress Bar
  document.querySelectorAll('.step-indicator').forEach((ind, idx) => {
    const stepNum = idx + 1;
    if (stepNum === step) {
      ind.className = 'step-indicator active';
    } else if (stepNum < step) {
      ind.className = 'step-indicator completed';
    } else {
      ind.className = 'step-indicator';
    }
  });

  // Update buttons
  const prevBtn = document.getElementById('btn-prev-step');
  const nextBtn = document.getElementById('btn-next-step');
  const submitBtn = document.getElementById('btn-submit-feedback');

  if (prevBtn) {
    prevBtn.style.display = step === 1 ? 'none' : 'inline-flex';
  }

  if (nextBtn && submitBtn) {
    if (step === totalSteps) {
      nextBtn.style.display = 'none';
      submitBtn.style.display = 'inline-flex';
    } else {
      nextBtn.style.display = 'inline-flex';
      submitBtn.style.display = 'none';
    }
  }

  // Scroll smoothly to top of form wrapper
  const wrapper = document.querySelector('.feedback-wrapper');
  if (wrapper) {
    wrapper.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
}

// Validation per Step
function validateCurrentStep(step) {
  if (step === 1) {
    if (!feedbackData.menu_id) {
      showToast('Please select a meal menu for feedback.', 'error');
      return false;
    }
    return true;
  }

  if (step === 2) {
    // Dish ratings have default 4 stars so always valid
    return true;
  }

  if (step === 3) {
    if (feedbackData.overall_rating < 1) {
      showToast('Please provide an overall rating (1-5 stars).', 'error');
      return false;
    }
    if (feedbackData.hygiene_rating < 1) {
      showToast('Please rate mess dining hygiene (1-5 stars).', 'error');
      return false;
    }
    return true;
  }

  if (step === 4) {
    if (feedbackData.wastage_level !== 'None' && !feedbackData.wastage_reason) {
      // Pick first reason as default or alert
      const firstReason = document.querySelector('input[name="wastage_reason"]:checked');
      if (firstReason) {
        feedbackData.wastage_reason = firstReason.value;
      } else {
        showToast('Please select the primary reason for leaving food.', 'error');
        return false;
      }
    }
    return true;
  }

  return true;
}

// Form Submission
function initFormSubmission() {
  const form = document.getElementById('feedback-form');
  if (!form) return;

  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    // Check honeypot
    const hp = document.getElementById('website_hp');
    if (hp && hp.value !== '') {
      showToast('Spam submission detected.', 'error');
      return;
    }

    // Collect comments
    const commentInput = document.getElementById('feedback-comment');
    const suggestionInput = document.getElementById('feedback-suggestion');
    feedbackData.comment = commentInput ? commentInput.value.trim() : '';
    feedbackData.suggestion = suggestionInput ? suggestionInput.value.trim() : '';

    const submitBtn = document.getElementById('btn-submit-feedback');
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.innerHTML = 'Submitting Anonymously...';
    }

    const res = await apiFetch('api/submit_feedback.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(feedbackData)
    });

    if (res.success) {
      // Redirect to success page
      const meal = res.meal || 'Meal';
      const sentiment = res.sentiment || 'Neutral';
      window.location.href = `success.html?meal=${encodeURIComponent(meal)}&sentiment=${encodeURIComponent(sentiment)}`;
    } else {
      showToast(res.error || 'Submission failed. Please try again.', 'error');
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.innerHTML = 'Submit Anonymous Feedback';
      }
    }
  });
}
