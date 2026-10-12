/**
 * Admin Dashboard Controller
 * Mess Menu Feedback Analyzer - B.Tech Data Science Project
 * Handles live KPI loading, 7 Chart.js visualizations, and Actionable Insights.
 */

let chartInstances = {};

document.addEventListener('DOMContentLoaded', () => {
  initDashboardRangeControls();
  loadDashboardData(30);
});

function initDashboardRangeControls() {
  const rangeBtns = document.querySelectorAll('.range-filter-btn');
  rangeBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      rangeBtns.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      const days = parseInt(btn.dataset.days, 10) || 30;
      loadDashboardData(days);
    });
  });
}

async function loadDashboardData(days = 30) {
  const res = await apiFetch(`../api/dashboard_data.php?days=${days}`);
  if (!res.success) {
    showToast(res.error || 'Failed to load dashboard data.', 'error');
    return;
  }

  updateKpiCards(res.kpis);
  renderAllCharts(res.charts);
  renderActionableInsights(res.actionable_insights);
}

// 1. Update KPI Cards
function updateKpiCards(kpis) {
  if (!kpis) return;

  const setEl = (id, val) => {
    const el = document.getElementById(id);
    if (el) el.textContent = val;
  };

  setEl('kpi-total-feedback', kpis.total_feedback.toLocaleString());
  setEl('kpi-avg-rating', `${kpis.average_rating} / 5.0`);
  setEl('kpi-satisfaction-score', `${kpis.satisfaction_score}%`);
  setEl('kpi-wastage-rate', `${kpis.wastage_rate}%`);
  setEl('kpi-positive-feedback', `${kpis.positive_count} (${kpis.positive_percentage}%)`);
  setEl('kpi-negative-feedback', `${kpis.negative_count} (${kpis.negative_percentage}%)`);

  // Subtext updates
  const satSub = document.getElementById('kpi-satisfaction-sub');
  if (satSub) {
    satSub.textContent = `Weighted: Taste ${kpis.attributes.taste}, Quality ${kpis.attributes.quality}`;
  }
}

// 2. Render 7 Chart.js Charts
function renderAllCharts(charts) {
  if (!charts || typeof Chart === 'undefined') return;

  // Global Chart.js styling defaults
  Chart.defaults.font.family = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
  Chart.defaults.color = '#64748B';

  // Destroy previous instances to prevent canvas reuse errors
  Object.keys(chartInstances).forEach(key => {
    if (chartInstances[key]) chartInstances[key].destroy();
  });

  // Chart 1: Average Rating by Meal Type (Bar Chart)
  const ctx1 = document.getElementById('chart-meal-ratings')?.getContext('2d');
  if (ctx1 && charts.meal_ratings) {
    chartInstances.mealRatings = new Chart(ctx1, {
      type: 'bar',
      data: {
        labels: charts.meal_ratings.labels,
        datasets: [{
          label: 'Average Rating (1-5)',
          data: charts.meal_ratings.ratings,
          backgroundColor: '#2E7D5B',
          borderRadius: 6,
          maxBarThickness: 45
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          y: { min: 0, max: 5, grid: { color: '#F1F5F9' } },
          x: { grid: { display: false } }
        }
      }
    });
  }

  // Chart 2: Weekly Satisfaction Trend (Line Chart)
  const ctx2 = document.getElementById('chart-satisfaction-trend')?.getContext('2d');
  if (ctx2 && charts.satisfaction_trend) {
    chartInstances.satisfactionTrend = new Chart(ctx2, {
      type: 'line',
      data: {
        labels: charts.satisfaction_trend.labels,
        datasets: [{
          label: 'Student Satisfaction (%)',
          data: charts.satisfaction_trend.satisfaction_pct,
          borderColor: '#1F4D3A',
          backgroundColor: 'rgba(31, 77, 58, 0.08)',
          fill: true,
          tension: 0.35,
          pointRadius: 3,
          pointHoverRadius: 6
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          y: { min: 0, max: 100, grid: { color: '#F1F5F9' } },
          x: { grid: { display: false } }
        }
      }
    });
  }

  // Chart 3: Food Wastage Trend (Line/Bar Chart)
  const ctx3 = document.getElementById('chart-wastage-trend')?.getContext('2d');
  if (ctx3 && charts.wastage_trend) {
    chartInstances.wastageTrend = new Chart(ctx3, {
      type: 'line',
      data: {
        labels: charts.wastage_trend.labels,
        datasets: [{
          label: 'Reported Wastage Rate (%)',
          data: charts.wastage_trend.waste_percentage,
          borderColor: '#DC2626',
          backgroundColor: 'rgba(220, 38, 38, 0.08)',
          fill: true,
          tension: 0.35,
          pointRadius: 3
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          y: { min: 0, max: 100, grid: { color: '#F1F5F9' } },
          x: { grid: { display: false } }
        }
      }
    });
  }

  // Chart 4: Top 5 Most Liked Dishes (Horizontal Bar)
  const ctx4 = document.getElementById('chart-top-dishes')?.getContext('2d');
  if (ctx4 && charts.top_dishes) {
    chartInstances.topDishes = new Chart(ctx4, {
      type: 'bar',
      data: {
        labels: charts.top_dishes.map(d => d.item_name),
        datasets: [{
          label: 'Score (out of 5)',
          data: charts.top_dishes.map(d => parseFloat(d.score)),
          backgroundColor: '#10B981',
          borderRadius: 4
        }]
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          x: { min: 0, max: 5, grid: { color: '#F1F5F9' } },
          y: { grid: { display: false } }
        }
      }
    });
  }

  // Chart 5: Top 5 Least Liked Dishes (Horizontal Bar)
  const ctx5 = document.getElementById('chart-bottom-dishes')?.getContext('2d');
  if (ctx5 && charts.bottom_dishes) {
    chartInstances.bottomDishes = new Chart(ctx5, {
      type: 'bar',
      data: {
        labels: charts.bottom_dishes.map(d => d.item_name),
        datasets: [{
          label: 'Score (out of 5)',
          data: charts.bottom_dishes.map(d => parseFloat(d.score)),
          backgroundColor: '#F87171',
          borderRadius: 4
        }]
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          x: { min: 0, max: 5, grid: { color: '#F1F5F9' } },
          y: { grid: { display: false } }
        }
      }
    });
  }

  // Chart 6: Feedback Sentiment (Doughnut Chart)
  const ctx6 = document.getElementById('chart-sentiment')?.getContext('2d');
  if (ctx6 && charts.sentiment) {
    chartInstances.sentiment = new Chart(ctx6, {
      type: 'doughnut',
      data: {
        labels: charts.sentiment.labels,
        datasets: [{
          data: charts.sentiment.counts,
          backgroundColor: ['#2E7D5B', '#94A3B8', '#DC2626'],
          hoverOffset: 6
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'bottom' }
        },
        cutout: '68%'
      }
    });
  }

  // Chart 7: Meal-wise Satisfaction (Bar Chart)
  const ctx7 = document.getElementById('chart-meal-satisfaction')?.getContext('2d');
  if (ctx7 && charts.meal_satisfaction) {
    chartInstances.mealSatisfaction = new Chart(ctx7, {
      type: 'bar',
      data: {
        labels: charts.meal_satisfaction.labels,
        datasets: [{
          label: 'Satisfaction (%)',
          data: charts.meal_satisfaction.percentages,
          backgroundColor: '#F4B942',
          borderRadius: 6,
          maxBarThickness: 45
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          y: { min: 0, max: 100, grid: { color: '#F1F5F9' } },
          x: { grid: { display: false } }
        }
      }
    });
  }
}

// 3. Render Real Actionable Insights
function renderActionableInsights(insights) {
  const container = document.getElementById('actionable-insights-container');
  if (!container) return;

  if (!insights || insights.length === 0) {
    container.innerHTML = `<p class="empty-desc">No specific anomalies detected for this period.</p>`;
    return;
  }

  let html = '';
  insights.forEach(item => {
    html += `
      <div class="insight-item ${item.type}">
        <div class="insight-icon">
          ${item.type === 'danger' ? '⚠️' : (item.type === 'warning' ? '⚡' : (item.type === 'success' ? '✓' : 'ℹ'))}
        </div>
        <div class="insight-content">
          <h4>${escapeHtml(item.category)}</h4>
          <p>${escapeHtml(item.text)}</p>
        </div>
      </div>
    `;
  });

  container.innerHTML = html;
}
