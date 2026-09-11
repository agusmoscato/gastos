<?php
require_once __DIR__ . '/includes/functions.php';
require_login();
$userEmail = current_user_email();
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>mi libreta</title>
<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#26362B">
<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" type="image/png" sizes="32x32" href="/assets/icons/favicon-32.png">
<link rel="icon" type="image/png" sizes="16x16" href="/assets/icons/favicon-16.png">
<link rel="apple-touch-icon" href="/assets/icons/apple-touch-icon.png">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<link rel="stylesheet" href="/assets/style.css?v=<?= APP_VERSION ?>">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
</head>
<body>

<div id="loading-screen" class="loading-screen">
  <svg class="spin" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#F5F0E1" stroke-width="2.5" stroke-linecap="round">
    <path d="M21 12a9 9 0 1 1-6.219-8.56"/>
  </svg>
</div>

<div id="app-root" class="desk" style="display:none">
  <div class="frame">

    <div class="app-header">
      <div class="brand">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2F6F4E" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/><path d="M18 12a2 2 0 0 0 0 4h4v-4Z"/></svg>
        <span class="brand-text">mi libreta</span>
      </div>
      <div class="month-nav">
        <button type="button" class="nav-btn" id="month-prev" aria-label="Mes anterior">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#6B6250" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
        </button>
        <span class="month-label" id="month-label">&nbsp;</span>
        <button type="button" class="nav-btn" id="month-next" aria-label="Mes siguiente">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#6B6250" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
        </button>
      </div>
      <div class="header-right">
        <button type="button" class="year-btn" id="settings-btn" title="Configuración" aria-label="Configuración">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
        </button>
        <button type="button" class="year-btn" id="year-summary-btn" title="Resumen anual" aria-label="Resumen anual">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        </button>
        <span class="save-indicator" id="save-indicator"></span>
        <a class="logout-btn" href="/logout.php" title="<?= htmlspecialchars($userEmail) ?> · Cerrar sesión">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        </a>
      </div>
    </div>

    <div id="banner-slot"></div>

    <div class="ticket" id="ticket">
      <div class="ticket-row">
        <span class="ticket-label">Ingreso</span>
        <button type="button" class="link-amount" id="ticket-income">$ 0</button>
      </div>
      <div class="ticket-row">
        <span class="ticket-label">Gastado</span>
        <span class="ticket-amount" style="color:var(--red)" id="ticket-spent">$ 0</span>
      </div>
      <div class="dashed-line"></div>
      <div class="ticket-row">
        <span class="ticket-label" style="font-weight:700">Te queda</span>
        <span class="ticket-big" id="ticket-rest">$ 0</span>
      </div>
    </div>

    <div class="card" data-section="duedates">
      <div class="card-header" data-toggle tabindex="0" role="button">
        <span class="card-title" style="margin-bottom:0">Vencimientos</span>
        <div style="display:flex;align-items:center;gap:10px">
          <button type="button" class="small-link" id="add-duedate-btn" data-no-toggle>+ vencimiento</button>
          <svg class="chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
        </div>
      </div>
      <div class="card-body">
        <div id="duedates-list"></div>
      </div>
    </div>

    <div class="card" id="pie-card" data-section="pie">
      <div class="card-header" data-toggle tabindex="0" role="button">
        <span class="card-title" style="margin-bottom:0">En qué se fue</span>
        <svg class="chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
      </div>
      <div class="card-body">
        <div class="chart-flex">
          <div class="chart-canvas-wrap"><canvas id="pie-canvas"></canvas></div>
          <div class="legend" id="pie-legend"></div>
        </div>
      </div>
    </div>

    <div class="card" data-section="months-chart">
      <div class="card-header" data-toggle tabindex="0" role="button">
        <span class="card-title" style="margin-bottom:0">Últimos meses</span>
        <svg class="chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
      </div>
      <div class="card-body">
        <div class="months-chart-wrap"><canvas id="bar-canvas"></canvas></div>
      </div>
    </div>

    <div class="card" data-section="templates">
      <div class="card-header" data-toggle tabindex="0" role="button">
        <span class="card-title" style="margin-bottom:0">Frecuentes</span>
        <svg class="chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
      </div>
      <div class="card-body">
        <div class="template-row" id="template-row"></div>
      </div>
    </div>

    <div class="card" id="installments-card" data-section="installments">
      <div class="card-header" data-toggle tabindex="0" role="button">
        <span class="card-title" style="margin-bottom:0">Compras en cuotas</span>
        <div style="display:flex;align-items:center;gap:10px">
          <button type="button" class="small-link" id="add-installment-btn" data-no-toggle>+ cuota</button>
          <svg class="chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
        </div>
      </div>
      <div class="card-body">
        <div id="installments-list"></div>
      </div>
    </div>

    <div class="card" data-section="recurring">
      <div class="card-header" data-toggle tabindex="0" role="button">
        <span class="card-title" style="margin-bottom:0">Gastos fijos</span>
        <div style="display:flex;align-items:center;gap:10px">
          <button type="button" class="small-link" id="add-recurring-btn" data-no-toggle>+ fijo</button>
          <svg class="chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
        </div>
      </div>
      <div class="card-body">
        <div id="recurring-list"></div>
      </div>
    </div>

    <div class="card" data-section="recurring-incomes">
      <div class="card-header" data-toggle tabindex="0" role="button">
        <span class="card-title" style="margin-bottom:0">Ingresos fijos</span>
        <div style="display:flex;align-items:center;gap:10px">
          <button type="button" class="small-link" id="add-recurring-income-btn" data-no-toggle>+ fijo</button>
          <svg class="chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
        </div>
      </div>
      <div class="card-body">
        <div id="recurring-incomes-list"></div>
      </div>
    </div>

    <div class="card" data-section="budgets">
      <div class="card-header" data-toggle tabindex="0" role="button">
        <span class="card-title" style="margin-bottom:0">Presupuestos</span>
        <div style="display:flex;align-items:center;gap:10px">
          <button type="button" class="small-link" id="manage-categories-link" data-no-toggle>administrar</button>
          <button type="button" class="small-link" id="add-category-link" data-no-toggle>+ categoría</button>
          <svg class="chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
        </div>
      </div>
      <div class="card-body">
        <div id="budget-list"></div>
      </div>
    </div>

    <div class="card" data-section="expenses">
      <div class="card-header" data-toggle tabindex="0" role="button">
        <span class="card-title" style="margin-bottom:0">Movimientos</span>
        <svg class="chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
      </div>
      <div class="card-body">
        <div class="search-row">
          <input class="search-input" id="search-input" type="text" placeholder="Buscar en descripciones...">
          <select class="filter-select" id="category-filter"><option value="">Todas</option></select>
        </div>
        <div id="expense-list"></div>
      </div>
    </div>



    <div class="fab-row">
      <button type="button" class="fab" id="fab-add-expense" aria-label="Agregar gasto">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#F5F0E1" stroke-width="2.5" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      </button>
    </div>

  </div>
</div>

<script src="/assets/app.js?v=<?= APP_VERSION ?>"></script>
<script>
  if ("serviceWorker" in navigator) {
    window.addEventListener("load", () => {
      navigator.serviceWorker.register("/service-worker.js").catch(() => {});
    });
  }
</script>
</body>
</html>
