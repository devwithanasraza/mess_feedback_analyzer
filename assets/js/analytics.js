/**
 * Food Analytics & Dish Performance Controller
 * Mess Menu Feedback Analyzer - B.Tech Data Science Project
 */

let allDishesPerformance = [];
let analyticsCharts = {};

document.addEventListener('DOMContentLoaded', () => {
  loadFoodAnalytics(30);

  // Time range filters
  document.querySelectorAll('.analytics-range-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.analytics-range-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      const days = parseInt(btn.dataset.days, 10) || 30;
      loadFoodAnalytics(days);
    });
  });

  // Table search & category filter
  const searchInput = document.getElementById('dish-search-input');
  if (searchInput) {
    searchInput.addEventListener('input', filterDishTable);
  }

  const categorySelect = document.getElementById('dish-category-filter');
  if (categorySelect) {
    categorySelect.addEventListener('change', filterDishTable);
  }
});

async function loadFoodAnalytics(days = 30) {
  const res = await apiFetch(`../api/analytics_data.php?days=${days}`);
  if (!res.success) {
    showToast(res.error || 'Failed to load food analytics.', 'error');
    return;
  }

  renderAttributeScores(res.attribute_scores);
  allDishesPerformance = res.dish_performance || [];
  renderDishPerformanceTable(allDishesPerformance);
  renderSecondaryCharts(res.temperature_dist, res.portion_dist, res.category_ratings);
}

function renderAttributeScores(attrs) {
  if (!attrs) return;

  const setEl = (id, val) => {
    const el = document.getElementById(id);
    if (el) el.textContent = val;
  };

  setEl('attr-overall', attrs.overall);
  setEl('attr-hygiene', attrs.hygiene);
  setEl('attr-taste', attrs.taste);
  setEl('attr-quality', attrs.quality);
  setEl('attr-freshness', attrs.freshness);
  setEl('attr-quantity', attrs.quantity);
  setEl('attr-weighted-satisfaction', `${attrs.weighted_satisfaction}%`);
}

function renderDishPerformanceTable(dishes) {
  const tbody = document.getElementById('dish-performance-tbody');
  if (!tbody) return;

  if (!dishes || dishes.length === 0) {
    tbody.innerHTML = `<tr><td colspan="9" style="text-align:center; padding:2rem; color:var(--color-text-muted);">No dish evaluations match the criteria.</td></tr>`;
    return;
  }

  let html = '';
  dishes.forEach(d => {
    let tierClass = 'badge-success';
    if (d.tier === 'Good') tierClass = 'badge-info';
    else if (d.tier === 'Average') tierClass = 'badge-warning';
    else if (d.tier === 'Needs Improvement') tierClass = 'badge-danger';

    html += `
      <tr>
        <td>
          <strong>${escapeHtml(d.item_name)}</strong>
          <span style="display:block; font-size:0.75rem; color:var(--color-text-muted);">${d.is_veg == 1 ? '● Pure Veg' : '▲ Non-Veg'}</span>
        </td>
        <td><span class="badge badge-neutral">${escapeHtml(d.category)}</span></td>
        <td>${d.review_count}</td>
        <td>${d.avg_taste} / 5</td>
        <td>${d.avg_quality} / 5</td>
        <td>${d.avg_freshness} / 5</td>
        <td>${d.repeat_yes_pct}%</td>
        <td>
          <strong>${d.composite_score}</strong>
          <span style="font-size:0.72rem; color:var(--color-text-muted); display:block;">(Wastage: -${d.waste_penalty})</span>
        </td>
        <td><span class="badge ${tierClass}">${escapeHtml(d.tier)}</span></td>
      </tr>
    `;
  });

  tbody.innerHTML = html;
}

function filterDishTable() {
  const searchVal = document.getElementById('dish-search-input')?.value.toLowerCase().trim() || '';
  const catVal = document.getElementById('dish-category-filter')?.value || 'All';

  const filtered = allDishesPerformance.filter(d => {
    const matchesSearch = d.item_name.toLowerCase().includes(searchVal);
    const matchesCat = (catVal === 'All' || d.category === catVal);
    return matchesSearch && matchesCat;
  });

  renderDishPerformanceTable(filtered);
}

function renderSecondaryCharts(tempDist, portionDist, categoryRatings) {
  if (typeof Chart === 'undefined') return;

  // Temperature Doughnut
  const tempCtx = document.getElementById('chart-temperature')?.getContext('2d');
  if (tempCtx && tempDist) {
    if (analyticsCharts.temp) analyticsCharts.temp.destroy();
    analyticsCharts.temp = new Chart(tempCtx, {
      type: 'doughnut',
      data: {
        labels: ['Too Cold', 'Acceptable', 'Hot/Fresh'],
        datasets: [{
          data: [tempDist['Too Cold'], tempDist['Acceptable'], tempDist['Hot/Fresh']],
          backgroundColor: ['#EF4444', '#3B82F6', '#10B981']
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom' } }
      }
    });
  }

  // Portion Doughnut
  const portionCtx = document.getElementById('chart-portion')?.getContext('2d');
  if (portionCtx && portionDist) {
    if (analyticsCharts.portion) analyticsCharts.portion.destroy();
    analyticsCharts.portion = new Chart(portionCtx, {
      type: 'doughnut',
      data: {
        labels: ['Too Little', 'Appropriate', 'Too Much'],
        datasets: [{
          data: [portionDist['Too Little'], portionDist['Appropriate'], portionDist['Too Much']],
          backgroundColor: ['#F59E0B', '#10B981', '#6366F1']
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom' } }
      }
    });
  }
}
