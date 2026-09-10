(function () {
  "use strict";

  const API = "/api/";
  const MESES = ["Enero","Febrero","Marzo","Abril","Mayo","Junio","Julio","Agosto","Septiembre","Octubre","Noviembre","Diciembre"];
  const PALETTE = ["#2F6F4E", "#AE4B3C", "#C99A3B", "#3B7A8C", "#7A5C8C", "#B5651D", "#4A6B8A", "#6B8E4E"];

  const ICONS = {
    x: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
    trash: '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>',
    pencil: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>',
    plus: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>',
    download: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>',
  };

  // ---------------------------------------------------------------------
  // Estado
  // ---------------------------------------------------------------------
  let CSRF = "";
  let categories = [];
  let templates = [];
  let installments = [];
  let recurring = [];
  let dueDates = [];
  let settings = { hiddenSections: [], notificationsEnabled: false };
  let recaptchaSiteKey = null;
  const notifiedThisSession = new Set();
  const TOGGLEABLE_SECTIONS = [
    { key: "duedates", label: "Vencimientos" },
    { key: "pie", label: "En qué se fue" },
    { key: "months-chart", label: "Últimos meses" },
    { key: "templates", label: "Frecuentes" },
    { key: "installments", label: "Compras en cuotas" },
    { key: "recurring", label: "Gastos fijos" },
    { key: "budgets", label: "Presupuestos" },
    { key: "expenses", label: "Movimientos" },
  ];
  let activeMonth = new Date().toISOString().slice(0, 7);
  let monthData = { income: 0, incomeEntries: [], budgets: {}, expenses: [] };
  let searchQuery = "";
  let categoryFilter = "";
  let pieChart = null;
  let barChart = null;
  let yearChart = null;
  let searchDebounce = null;
  const dismissedCopySuggestion = new Set();

  // ---------------------------------------------------------------------
  // Helpers
  // ---------------------------------------------------------------------
  function monthLabel(id) {
    const [y, m] = id.split("-").map(Number);
    return MESES[m - 1] + " " + y;
  }
  function shiftMonth(id, delta) {
    const [y, m] = id.split("-").map(Number);
    const d = new Date(y, m - 1 + delta, 1);
    return d.getFullYear() + "-" + String(d.getMonth() + 1).padStart(2, "0");
  }
  function todayISO() {
    const d = new Date();
    return d.getFullYear() + "-" + String(d.getMonth() + 1).padStart(2, "0") + "-" + String(d.getDate()).padStart(2, "0");
  }
  function yesterdayISO() {
    const d = new Date();
    d.setDate(d.getDate() - 1);
    return d.getFullYear() + "-" + String(d.getMonth() + 1).padStart(2, "0") + "-" + String(d.getDate()).padStart(2, "0");
  }
  function fmtMoney(n) {
    const v = Math.round(Number(n) || 0);
    return "$ " + v.toLocaleString("es-AR");
  }
  function fmtDate(iso) {
    if (iso === todayISO()) return "Hoy";
    if (iso === yesterdayISO()) return "Ayer";
    const [, m, d] = iso.split("-");
    return `${d}/${m}`;
  }
  function esc(s) {
    const div = document.createElement("div");
    div.textContent = s == null ? "" : String(s);
    return div.innerHTML;
  }
  function ticketClipPath(teeth = 16, depth = 5.5) {
    const step = 100 / teeth;
    const pts = ["0% 0%", "100% 0%", `100% ${100 - depth}%`];
    for (let i = teeth; i >= 0; i--) {
      const x = (i * step).toFixed(2);
      const y = i % 2 === 0 ? 100 : 100 - depth;
      pts.push(`${x}% ${y}%`);
    }
    pts.push(`0% ${100 - depth}%`);
    return `polygon(${pts.join(",")})`;
  }
  function catById(id) {
    return categories.find((c) => c.id === Number(id));
  }

  // ---------------------------------------------------------------------
  // API
  // ---------------------------------------------------------------------
  async function apiGet(path) {
    const res = await fetch(API + path, { headers: { Accept: "application/json" } });
    if (res.status === 401) { window.location.href = "/login.php"; throw new Error("unauth"); }
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.error || "Error de red");
    return data;
  }
  async function apiPost(path, body) {
    const res = await fetch(API + path, {
      method: "POST",
      headers: { "Content-Type": "application/json", "X-CSRF-Token": CSRF },
      body: JSON.stringify(body || {}),
    });
    if (res.status === 401) { window.location.href = "/login.php"; throw new Error("unauth"); }
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.error || "Error de red");
    return data;
  }

  function showSaveIndicator(state) {
    const el = document.getElementById("save-indicator");
    if (!el) return;
    if (state === "saving") {
      el.innerHTML = '<span class="spin-slow" style="color:#B9C7B9;display:flex">' + spinnerSVG() + "</span>";
    } else if (state === "saved") {
      el.innerHTML = '<span style="color:#8FD9A8;display:flex">' + checkSVG() + "</span>";
      setTimeout(() => { if (el.innerHTML.includes("8FD9A8")) el.innerHTML = ""; }, 1000);
    } else {
      el.innerHTML = "";
    }
  }
  function spinnerSVG() {
    return '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>';
  }
  function checkSVG() {
    return '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';
  }

  function showToast(message, actionLabel, onAction) {
    const old = document.querySelector(".toast");
    if (old) old.remove();
    const t = document.createElement("div");
    t.className = "toast";
    t.innerHTML = `<span>${esc(message)}</span>` + (actionLabel ? `<button type="button">${esc(actionLabel)}</button>` : "");
    document.body.appendChild(t);
    if (actionLabel && onAction) {
      t.querySelector("button").addEventListener("click", () => { t.remove(); onAction(); });
    }
    setTimeout(() => t.remove(), 6000);
  }

  // ---------------------------------------------------------------------
  // Carga de datos
  // ---------------------------------------------------------------------
  async function init() {
    try {
      const boot = await apiGet("bootstrap.php");
      categories = boot.categories;
      templates = boot.templates;
      CSRF = boot.csrf;
      recaptchaSiteKey = boot.recaptchaSiteKey || null;
      if (boot.settings) settings = { hiddenSections: boot.settings.hiddenSections || [], notificationsEnabled: !!boot.settings.notificationsEnabled };
      await loadMonth();
      await loadMonthsChart();
      applyCollapsedState();
      applyPanelVisibility();
      document.getElementById("loading-screen").style.display = "none";
      document.getElementById("app-root").style.display = "";
      bindStaticEvents();
      bindCollapsibles();
    } catch (e) {
      document.getElementById("loading-screen").innerHTML =
        '<div style="color:#F5F0E1;font-size:13px;text-align:center;padding:20px">No se pudo conectar con la base de datos.<br>Revisá includes/config.php.</div>';
      return;
    }
    // Estas dos van aparte: si sus tablas todavía no existen (falta correr
    // una migración), que se vea el aviso solo en su tarjeta y no se caiga
    // el resto de la app.
    try {
      await loadInstallments();
    } catch (e) {
      console.error("Error cargando cuotas:", e);
      const wrap = document.getElementById("installments-list");
      if (wrap) wrap.innerHTML = '<div class="empty-hint" style="color:var(--red)">No se pudo cargar (¿corriste las migraciones de sql/?). Detalle: ' + esc(e.message || "") + '</div>';
    }
    try {
      await loadRecurring();
    } catch (e) {
      console.error("Error cargando gastos fijos:", e);
      const wrap = document.getElementById("recurring-list");
      if (wrap) wrap.innerHTML = '<div class="empty-hint" style="color:var(--red)">No se pudo cargar (¿corriste sql/upgrade_v5.sql?). Detalle: ' + esc(e.message || "") + '</div>';
    }
    try {
      await loadDueDates();
    } catch (e) {
      console.error("Error cargando vencimientos:", e);
      const wrap = document.getElementById("duedates-list");
      if (wrap) wrap.innerHTML = '<div class="empty-hint" style="color:var(--red)">No se pudo cargar (¿corriste sql/upgrade_v6.sql?). Detalle: ' + esc(e.message || "") + '</div>';
    }
  }

  async function loadDueDates() {
    const data = await apiGet("duedates_list.php");
    dueDates = data.dueDates;
    renderDueDates();
  }

  async function loadRecurring() {
    const data = await apiGet("recurring_list.php");
    recurring = data.recurring;
    renderRecurring();
  }

  async function loadInstallments() {
    const data = await apiGet("installments_list.php");
    installments = data.installments;
    renderInstallments();
  }

  async function loadMonth() {
    const params = new URLSearchParams({ month: activeMonth });
    if (searchQuery) params.set("q", searchQuery);
    if (categoryFilter) params.set("category_id", categoryFilter);
    monthData = await apiGet("month.php?" + params.toString());
    renderMonthLabel();
    renderTicket();
    renderPieChart();
    renderBudgets();
    renderExpenses();
    await renderBanners();
  }

  async function loadMonthsChart() {
    const data = await apiGet("months_summary.php?month=" + activeMonth + "&count=6");
    renderBarChart(data.months);
  }

  // ---------------------------------------------------------------------
  // Banners: copiar presupuesto del mes anterior / aviso de límite cercano
  // ---------------------------------------------------------------------
  async function renderBanners() {
    const slot = document.getElementById("banner-slot");
    const parts = [];

    // Aviso de categorías cerca del límite (>=90% y no pasadas todavía)
    const map = {};
    monthData.expenses.forEach((e) => { map[e.categoryId] = (map[e.categoryId] || 0) + Number(e.amount || 0); });
    const near = categories
      .map((c) => ({ ...c, spent: map[c.id] || 0, budget: Number(monthData.budgets[c.id]) || 0 }))
      .filter((c) => c.budget > 0 && c.spent >= c.budget * 0.9 && c.spent < c.budget);
    if (near.length > 0) {
      const names = near.map((c) => `${c.name} (${Math.round((c.spent / c.budget) * 100)}%)`).join(", ");
      parts.push(`<div class="banner"><div class="banner-text"><b>Te estás por pasar del presupuesto</b>${esc(names)}</div></div>`);
      maybeNotifyNearLimit(near);
    }

    // Sugerencia de copiar presupuestos del mes anterior si este mes no tiene ninguno cargado
    const hasBudgets = Object.values(monthData.budgets).some((v) => Number(v) > 0);
    if (!hasBudgets && !dismissedCopySuggestion.has(activeMonth)) {
      const prevMonth = shiftMonth(activeMonth, -1);
      try {
        const prev = await apiGet("month.php?month=" + prevMonth);
        const prevHasBudgets = Object.values(prev.budgets || {}).some((v) => Number(v) > 0);
        if (prevHasBudgets) {
          parts.push(`
            <div class="banner">
              <div class="banner-text"><b>¿Copiar presupuestos?</b>Podés repetir los de ${esc(monthLabel(prevMonth))} en vez de cargarlos de nuevo.</div>
              <button type="button" class="banner-action" id="copy-budgets-btn">Copiar</button>
              <button type="button" class="banner-dismiss" id="dismiss-copy-btn" aria-label="Cerrar">${ICONS.x}</button>
            </div>`);
        }
      } catch (e) { /* si falla, simplemente no mostramos la sugerencia */ }
    }

    slot.innerHTML = parts.join("");

    const copyBtn = document.getElementById("copy-budgets-btn");
    if (copyBtn) {
      copyBtn.addEventListener("click", async () => {
        showSaveIndicator("saving");
        await apiPost("budgets_copy.php", { month: activeMonth, fromMonth: shiftMonth(activeMonth, -1), csrf: CSRF });
        showSaveIndicator("saved");
        await loadMonth();
      });
    }
    const dismissBtn = document.getElementById("dismiss-copy-btn");
    if (dismissBtn) {
      dismissBtn.addEventListener("click", () => {
        dismissedCopySuggestion.add(activeMonth);
        renderBanners();
      });
    }
  }

  // ---------------------------------------------------------------------
  // Render: header / ticket
  // ---------------------------------------------------------------------
  function renderMonthLabel() {
    document.getElementById("month-label").textContent = monthLabel(activeMonth);
  }

  function renderTicket() {
    const spent = monthData.expenses.reduce((s, e) => s + Number(e.amount || 0), 0);
    const income = Number(monthData.income) || 0;
    const rest = income - spent;
    const ticket = document.getElementById("ticket");
    ticket.style.clipPath = ticketClipPath();
    document.getElementById("ticket-income").textContent = fmtMoney(income);
    document.getElementById("ticket-spent").textContent = fmtMoney(spent);
    const restEl = document.getElementById("ticket-rest");
    restEl.textContent = (rest < 0 ? "-" : "") + fmtMoney(Math.abs(rest));
    restEl.style.color = rest < 0 ? "var(--red)" : "var(--green)";
  }

  // ---------------------------------------------------------------------
  // Render: gráfico de torta (categorías del mes)
  // ---------------------------------------------------------------------
  function renderPieChart() {
    const map = {};
    monthData.expenses.forEach((e) => { map[e.categoryId] = (map[e.categoryId] || 0) + Number(e.amount || 0); });
    const items = categories
      .map((c) => ({ ...c, spent: map[c.id] || 0 }))
      .filter((c) => c.spent > 0)
      .sort((a, b) => b.spent - a.spent);

    const card = document.getElementById("pie-card");
    if (items.length === 0) { card.classList.add("no-data"); return; }
    card.classList.remove("no-data");

    const total = items.reduce((s, c) => s + c.spent, 0);
    const legend = document.getElementById("pie-legend");
    legend.innerHTML = items.map((c) => `
      <div class="legend-row">
        <span class="dot" style="background:${c.color}"></span>
        <span class="legend-name">${esc(c.name)}</span>
        <span class="legend-val">${Math.round((c.spent / total) * 100)}%</span>
      </div>`).join("");

    const ctx = document.getElementById("pie-canvas").getContext("2d");
    if (pieChart) pieChart.destroy();
    pieChart = new Chart(ctx, {
      type: "doughnut",
      data: { labels: items.map((c) => c.name), datasets: [{ data: items.map((c) => c.spent), backgroundColor: items.map((c) => c.color), borderWidth: 0 }] },
      options: {
        responsive: true, maintainAspectRatio: false, cutout: "62%",
        plugins: { legend: { display: false }, tooltip: { callbacks: { label: (ctx) => " " + fmtMoney(ctx.parsed) } } },
      },
    });
  }

  // ---------------------------------------------------------------------
  // Render: comparativa de últimos meses (funcionalidad nueva)
  // ---------------------------------------------------------------------
  function renderBarChart(months) {
    const ctx = document.getElementById("bar-canvas").getContext("2d");
    if (barChart) barChart.destroy();
    barChart = new Chart(ctx, {
      type: "bar",
      data: {
        labels: months.map((m) => m.label.slice(0, 3)),
        datasets: [{ data: months.map((m) => m.spent), backgroundColor: months.map((m) => (m.month === activeMonth ? "#2F6F4E" : "#D8D0B4")), borderRadius: 5, maxBarThickness: 26 }],
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false }, tooltip: { callbacks: { label: (ctx) => " " + fmtMoney(ctx.parsed.y) } } },
        scales: { y: { display: false, beginAtZero: true }, x: { grid: { display: false }, ticks: { font: { size: 10.5, family: "Inter" }, color: "#8B8368" } } },
      },
    });
  }

  // ---------------------------------------------------------------------
  // Render: plantillas de gasto rápido (funcionalidad nueva)
  // ---------------------------------------------------------------------
  function renderTemplates() {
    const row = document.getElementById("template-row");
    row.innerHTML = templates.map((t) => {
      const cat = catById(t.category_id);
      return `<button type="button" class="template-chip" data-tpl="${t.id}">
        <span class="dot" style="background:${cat ? cat.color : "#8B7355"}"></span>
        ${esc(t.name)} · ${fmtMoney(t.amount)}
        <span class="template-del" data-tpl-del="${t.id}" title="Borrar plantilla">${ICONS.x}</span>
      </button>`;
    }).join("") + `<button type="button" class="template-add-btn" id="add-template-btn">${ICONS.plus} frecuente</button>`;

    row.querySelectorAll("[data-tpl]").forEach((btn) => {
      btn.addEventListener("click", (e) => {
        if (e.target.closest("[data-tpl-del]")) return;
        const t = templates.find((x) => x.id === Number(btn.dataset.tpl));
        confirmQuickAdd(t);
      });
    });
    row.querySelectorAll("[data-tpl-del]").forEach((btn) => {
      btn.addEventListener("click", async (e) => {
        e.stopPropagation();
        const id = Number(btn.dataset.tplDel);
        templates = templates.filter((t) => t.id !== id);
        renderTemplates();
        await apiPost("template_delete.php", { id, csrf: CSRF });
      });
    });
    document.getElementById("add-template-btn").addEventListener("click", openTemplateModal);
  }

  function confirmQuickAdd(t) {
    const cat = catById(t.category_id);
    const html = `
      <div class="modal-head"><span class="modal-title">Confirmar gasto</span><button type="button" class="icon-btn" data-close>${ICONS.x}</button></div>
      <div class="empty-hint" style="padding:0 0 16px;font-size:14px;color:var(--ink)">
        ¿Querés agregar un movimiento de <b>${esc(t.name)}</b> por <b>${fmtMoney(t.amount)}</b>${cat ? ` en <b>${esc(cat.name)}</b>` : ""}?
      </div>
      <button type="button" class="primary-btn" id="confirm-quickadd-yes">Sí, agregar</button>
      <button type="button" class="secondary-btn" data-close>Cancelar</button>
    `;
    openOverlay(html, (ov) => {
      ov.querySelector("#confirm-quickadd-yes").addEventListener("click", async () => {
        ov.closest(".overlay").remove();
        await quickAddFromTemplate(t);
      });
    });
  }

  async function quickAddFromTemplate(t) {
    showSaveIndicator("saving");
    await apiPost("expense_add.php", { amount: t.amount, categoryId: t.category_id, desc: t.name, date: todayISO(), month: activeMonth, csrf: CSRF });
    showSaveIndicator("saved");
    await loadMonth();
    await loadMonthsChart();
  }

  // ---------------------------------------------------------------------
  // Render: vencimientos
  // ---------------------------------------------------------------------
  const DUEDATE_STATUS_LABEL = {
    overdue: (d) => `Vencido hace ${Math.abs(d.daysUntil)} día${Math.abs(d.daysUntil) === 1 ? "" : "s"}`,
    soon: (d) => (d.daysUntil === 0 ? "Vence hoy" : `Vence en ${d.daysUntil} día${d.daysUntil === 1 ? "" : "s"}`),
    upcoming: (d) => "Vence el " + fmtDate(d.dueDate),
    paid: () => "Pagado",
  };

  function renderDueDates() {
    const wrap = document.getElementById("duedates-list");
    if (!wrap) return;
    if (dueDates.length === 0) {
      wrap.innerHTML = '<div class="empty-hint">Todavía no tenés vencimientos cargados (ej: tarjeta, alquiler, seguro).</div>';
      return;
    }
    wrap.innerHTML = dueDates.map((d) => `
      <div class="installment-row">
        <div class="budget-top">
          <span class="dot" style="background:${d.categoryColor}"></span>
          <span class="budget-name">${esc(d.name)}</span>
          <span class="duedate-badge ${d.status}">${DUEDATE_STATUS_LABEL[d.status](d)}</span>
        </div>
        <div class="budget-foot">
          <span>${d.amount != null ? fmtMoney(d.amount) : "monto variable"}${d.recurring ? "" : " · único"}</span>
          <span style="display:flex;gap:10px;align-items:center">
            ${d.status !== "paid" ? `<button type="button" class="duedate-pay-btn" data-due-pay="${d.id}">Marcar pagado</button>` : ""}
            <button type="button" class="icon-mini-btn" data-due-del="${d.id}" style="color:var(--red)" title="Borrar vencimiento">${ICONS.trash}</button>
          </span>
        </div>
      </div>`).join("");

    wrap.querySelectorAll("[data-due-pay]").forEach((btn) => {
      btn.addEventListener("click", () => {
        const d = dueDates.find((x) => x.id === Number(btn.dataset.duePay));
        openMarkPaidModal(d);
      });
    });
    wrap.querySelectorAll("[data-due-del]").forEach((btn) => {
      btn.addEventListener("click", async () => {
        const id = Number(btn.dataset.dueDel);
        if (!confirm("¿Borrar este vencimiento? No borra los gastos que ya se hayan cargado a partir de él.")) return;
        await apiPost("duedates_delete.php", { id, csrf: CSRF });
        dueDates = dueDates.filter((d) => d.id !== id);
        renderDueDates();
      });
    });
  }

  function openDueDateModal() {
    let selectedCat = categories[0]?.id;
    let recurringMode = true;
    const html = `
      <div class="modal-head"><span class="modal-title">Nuevo vencimiento</span><button type="button" class="icon-btn" data-close>${ICONS.x}</button></div>
      <div class="field"><label class="label">Nombre</label><input class="text-input" id="due-name" placeholder="ej: Tarjeta Visa, Alquiler, Seguro auto"></div>
      <div class="field"><label class="label">Monto (opcional si varía cada vez)</label>
        <div class="amount-input-wrap"><span class="amount-prefix">$</span><input class="amount-input" id="due-amount" inputmode="numeric" placeholder="0"></div>
      </div>
      <div class="field"><label class="label">Categoría</label><div class="chip-wrap" id="due-chips">${categoryChipsHTML(selectedCat, false)}</div></div>
      <div class="field">
        <label class="label">Repite todos los meses</label>
        <div class="chip-wrap">
          <button type="button" class="chip" id="due-mode-recurring" style="border-color:var(--green);background:var(--green);color:#fff">Sí, mensual</button>
          <button type="button" class="chip" id="due-mode-onetime" style="border-color:var(--border)">No, una vez</button>
        </div>
      </div>
      <div class="field"><label class="label">Día del mes en que vence</label>
        <input class="text-input" id="due-day" inputmode="numeric" placeholder="ej: 15" value="${new Date().getDate()}">
      </div>
      <div class="field" id="due-onetime-month-field" style="display:none">
        <label class="label">Mes en que vence</label>
        <input type="month" class="text-input" id="due-onetime-month" value="${activeMonth}">
      </div>
      <button type="button" class="primary-btn" id="due-save">Guardar vencimiento</button>
    `;
    const overlay = openOverlay(html, (ov) => {
      function bindChips() {
        ov.querySelectorAll("#due-chips [data-chip]").forEach((chip) => chip.addEventListener("click", () => {
          selectedCat = Number(chip.dataset.chip);
          ov.querySelector("#due-chips").innerHTML = categoryChipsHTML(selectedCat, false);
          bindChips();
        }));
      }
      bindChips();
      ov.querySelector("#due-amount").addEventListener("input", (e) => { e.target.value = e.target.value.replace(/[^0-9]/g, ""); });
      ov.querySelector("#due-day").addEventListener("input", (e) => { e.target.value = e.target.value.replace(/[^0-9]/g, ""); });

      const recBtn = ov.querySelector("#due-mode-recurring");
      const oneBtn = ov.querySelector("#due-mode-onetime");
      const monthField = ov.querySelector("#due-onetime-month-field");
      function setMode(isRecurring) {
        recurringMode = isRecurring;
        recBtn.style.background = isRecurring ? "var(--green)" : "transparent";
        recBtn.style.color = isRecurring ? "#fff" : "var(--ink)";
        oneBtn.style.background = !isRecurring ? "var(--green)" : "transparent";
        oneBtn.style.color = !isRecurring ? "#fff" : "var(--ink)";
        oneBtn.style.borderColor = !isRecurring ? "var(--green)" : "var(--border)";
        monthField.style.display = isRecurring ? "none" : "";
      }
      recBtn.addEventListener("click", () => setMode(true));
      oneBtn.addEventListener("click", () => setMode(false));

      ov.querySelector("#due-save").addEventListener("click", async () => {
        const name = ov.querySelector("#due-name").value.trim();
        const amount = ov.querySelector("#due-amount").value;
        const dueDay = Number(ov.querySelector("#due-day").value) || 0;
        const oneTimeMonth = ov.querySelector("#due-onetime-month").value;
        if (!name) { showInstError(ov, "Falta el nombre."); return; }
        if (dueDay < 1 || dueDay > 31) { showInstError(ov, "El día tiene que ser entre 1 y 31."); return; }
        const saveBtn = ov.querySelector("#due-save");
        saveBtn.disabled = true;
        try {
          await apiPost("duedates_add.php", {
            name, amount: amount || "", categoryId: selectedCat, dueDay,
            recurring: recurringMode, oneTimeMonth, csrf: CSRF,
          });
          overlay.remove();
          await loadDueDates();
        } catch (err) {
          showInstError(ov, err.message || "No se pudo guardar.");
          saveBtn.disabled = false;
        }
      });
    });
  }

  function openMarkPaidModal(d) {
    let selectedCat = d.categoryId || categories[0]?.id;
    const html = `
      <div class="modal-head"><span class="modal-title">Marcar como pagado</span><button type="button" class="icon-btn" data-close>${ICONS.x}</button></div>
      <div class="empty-hint" style="padding:0 0 12px">${esc(d.name)}</div>
      <div class="field"><label class="label">Monto pagado</label>
        <div class="amount-input-wrap"><span class="amount-prefix">$</span><input class="amount-input" id="pay-amount" inputmode="numeric" value="${d.amount != null ? d.amount : ""}" placeholder="0"></div>
      </div>
      <div class="field"><label class="label">Categoría</label><div class="chip-wrap" id="pay-chips">${categoryChipsHTML(selectedCat, false)}</div></div>
      <div class="field"><label class="label">Fecha</label><input type="date" class="text-input" id="pay-date" value="${todayISO()}"></div>
      <label class="remember-row"><input type="checkbox" id="pay-create-expense" checked><span>También cargar este gasto en Movimientos</span></label>
      <button type="button" class="primary-btn" id="pay-save">Confirmar</button>
    `;
    const overlay = openOverlay(html, (ov) => {
      function bindChips() {
        ov.querySelectorAll("#pay-chips [data-chip]").forEach((chip) => chip.addEventListener("click", () => {
          selectedCat = Number(chip.dataset.chip);
          ov.querySelector("#pay-chips").innerHTML = categoryChipsHTML(selectedCat, false);
          bindChips();
        }));
      }
      bindChips();
      ov.querySelector("#pay-amount").addEventListener("input", (e) => { e.target.value = e.target.value.replace(/[^0-9]/g, ""); });

      ov.querySelector("#pay-save").addEventListener("click", async () => {
        const createExpense = ov.querySelector("#pay-create-expense").checked;
        const amount = ov.querySelector("#pay-amount").value;
        const date = ov.querySelector("#pay-date").value || todayISO();
        if (createExpense && (!amount || Number(amount) <= 0)) { showInstError(ov, "Poné un monto o destildá cargar el gasto."); return; }
        const saveBtn = ov.querySelector("#pay-save");
        saveBtn.disabled = true;
        try {
          await apiPost("duedates_mark_paid.php", {
            id: d.id, amount: amount || "", categoryId: selectedCat, date, createExpense, csrf: CSRF,
          });
          overlay.remove();
          showToast("Marcado como pagado.");
          await loadDueDates();
          if (createExpense) { await loadMonth(); await loadMonthsChart(); }
        } catch (err) {
          showInstError(ov, err.message || "No se pudo guardar.");
          saveBtn.disabled = false;
        }
      });
    });
  }

  // ---------------------------------------------------------------------
  // Render: compras en cuotas
  // ---------------------------------------------------------------------
  function renderInstallments() {
    const wrap = document.getElementById("installments-list");
    if (installments.length === 0) {
      wrap.innerHTML = '<div class="empty-hint">Todavía no tenés compras en cuotas cargadas.</div>';
      return;
    }

    const totalComprometido = installments.reduce((sum, p) => {
      const remaining = Math.max(0, p.numInstallments - p.paidCount);
      return sum + remaining * (p.totalAmount / p.numInstallments);
    }, 0);
    const summaryHTML = totalComprometido > 0
      ? `<div class="empty-hint" style="padding-top:0;padding-bottom:10px">Comprometido a futuro: <b style="color:var(--ink)">${fmtMoney(totalComprometido)}</b></div>`
      : "";

    wrap.innerHTML = summaryHTML + installments.map((p) => {
      const remaining = p.numInstallments - p.paidCount;
      const pct = Math.min(100, Math.round((p.paidCount / p.numInstallments) * 100));
      const finished = p.paidCount >= p.numInstallments;
      return `
      <div class="installment-row">
        <div class="budget-top">
          <span class="dot" style="background:${p.categoryColor}"></span>
          <span class="budget-name">${esc(p.desc)}</span>
          ${!finished ? `<button type="button" class="icon-mini-btn" data-inst-cancel="${p.id}" title="Cancelar cuotas restantes (las que ya pasaron quedan)">${ICONS.pencil}</button>` : ""}
          <button type="button" class="icon-mini-btn" data-inst-del="${p.id}" style="color:var(--red)" title="Borrar todas las cuotas, incluidas las pasadas" aria-label="Borrar compra en cuotas">${ICONS.trash}</button>
        </div>
        <div class="budget-bar-track"><div class="budget-bar-fill" style="width:${pct}%;background:${finished ? "#B0A98A" : "var(--green)"}"></div></div>
        <div class="budget-foot">
          <span>${fmtMoney(p.totalAmount)} total · cuota ${fmtMoney(p.totalAmount / p.numInstallments)}</span>
          <span>${finished ? "pagada" : `cuota ${Math.min(p.paidCount + 1, p.numInstallments)}/${p.numInstallments} · faltan ${remaining}`}</span>
        </div>
      </div>`;
    }).join("");

    wrap.querySelectorAll("[data-inst-cancel]").forEach((btn) => {
      btn.addEventListener("click", async () => {
        const id = Number(btn.dataset.instCancel);
        if (!confirm("¿Cancelar las cuotas que todavía no llegaron? Las que ya se cargaron quedan en tu historial.")) return;
        await apiPost("installment_cancel_remaining.php", { id, csrf: CSRF });
        await loadInstallments();
        await loadMonth(); await loadMonthsChart();
      });
    });
    wrap.querySelectorAll("[data-inst-del]").forEach((btn) => {
      btn.addEventListener("click", async () => {
        const id = Number(btn.dataset.instDel);
        if (!confirm("¿Borrar esta compra en cuotas? Se van a borrar todas sus cuotas, incluidas las que ya pasaron.")) return;
        await apiPost("installment_delete.php", { id, csrf: CSRF });
        installments = installments.filter((p) => p.id !== id);
        renderInstallments();
        await loadMonth(); await loadMonthsChart();
      });
    });
  }

  // ---------------------------------------------------------------------
  // Render: gastos fijos mensuales
  // ---------------------------------------------------------------------
  function renderRecurring() {
    const wrap = document.getElementById("recurring-list");
    if (!wrap) return;
    if (recurring.length === 0) {
      wrap.innerHTML = '<div class="empty-hint">Todavía no tenés gastos fijos cargados (ej: Netflix, gimnasio, alquiler).</div>';
      return;
    }
    wrap.innerHTML = recurring.map((r) => `
      <div class="installment-row" style="opacity:${r.active ? 1 : 0.5}">
        <div class="budget-top">
          <span class="dot" style="background:${r.categoryColor}"></span>
          <span class="budget-name">${esc(r.name)}</span>
          <span class="expense-amount" style="font-size:12.5px">${fmtMoney(r.amount)}/mes</span>
        </div>
        <div class="budget-foot">
          <span>${r.active ? "activo desde " + esc(monthLabel(r.startMonth)) : "pausado"}</span>
          <span style="display:flex;gap:10px">
            <button type="button" class="budget-set-link" data-rec-toggle="${r.id}">${r.active ? "pausar" : "reactivar"}</button>
            <button type="button" class="budget-set-link" data-rec-del="${r.id}" style="color:var(--red)">borrar</button>
          </span>
        </div>
      </div>`).join("");

    wrap.querySelectorAll("[data-rec-toggle]").forEach((btn) => {
      btn.addEventListener("click", async () => {
        const id = Number(btn.dataset.recToggle);
        const item = recurring.find((r) => r.id === id);
        await apiPost("recurring_toggle.php", { id, active: !item.active, csrf: CSRF });
        await loadRecurring();
      });
    });
    wrap.querySelectorAll("[data-rec-del]").forEach((btn) => {
      btn.addEventListener("click", async () => {
        const id = Number(btn.dataset.recDel);
        if (!confirm("¿Borrar este gasto fijo? Los gastos que ya generó quedan en tu historial, solo se deja de generar nuevos.")) return;
        await apiPost("recurring_delete.php", { id, csrf: CSRF });
        recurring = recurring.filter((r) => r.id !== id);
        renderRecurring();
      });
    });
  }

  // ---------------------------------------------------------------------
  // Render: presupuestos
  // ---------------------------------------------------------------------
  function renderBudgets() {
    const map = {};
    monthData.expenses.forEach((e) => { map[e.categoryId] = (map[e.categoryId] || 0) + Number(e.amount || 0); });
    const items = categories.map((c) => ({ ...c, spent: map[c.id] || 0, budget: Number(monthData.budgets[c.id]) || 0 })).filter((c) => c.spent > 0 || c.budget > 0);

    const wrap = document.getElementById("budget-list");
    if (items.length === 0) {
      wrap.innerHTML = '<div class="empty-hint">Todavía no cargaste gastos ni presupuestos este mes.</div>';
      return;
    }
    wrap.innerHTML = items.map((c) => {
      const hasBudget = c.budget > 0;
      const pct = hasBudget ? Math.min(100, (c.spent / c.budget) * 100) : (c.spent > 0 ? 100 : 0);
      const over = hasBudget && c.spent > c.budget;
      const barColor = !hasBudget ? "#D8D0B4" : over ? "var(--red)" : pct > 75 ? "var(--gold)" : c.color;
      return `
      <div class="budget-row">
        <div class="budget-top">
          <span class="dot" style="background:${c.color}"></span>
          <span class="budget-name">${esc(c.name)}</span>
          <button type="button" class="budget-set-link" data-budget-cat="${c.id}" data-current="${c.budget}">
            ${hasBudget ? "presup. " + fmtMoney(c.budget) : "sin presupuesto"}
          </button>
        </div>
        <div class="budget-bar-track"><div class="budget-bar-fill" style="width:${pct}%;background:${barColor}"></div></div>
        <div class="budget-foot">
          <span>${fmtMoney(c.spent)} gastado</span>
          ${hasBudget ? `<span style="color:${over ? "var(--red)" : "var(--muted)"}">${over ? "te pasaste " : "quedan "}${fmtMoney(Math.abs(c.budget - c.spent))}</span>` : "<span></span>"}
        </div>
      </div>`;
    }).join("");

    wrap.querySelectorAll("[data-budget-cat]").forEach((btn) => {
      btn.addEventListener("click", () => {
        const catId = Number(btn.dataset.budgetCat);
        const current = btn.dataset.current;
        btn.outerHTML = `<input type="text" inputmode="numeric" class="budget-input" data-budget-input="${catId}" value="${current > 0 ? current : ""}" placeholder="0">`;
        const input = wrap.querySelector(`[data-budget-input="${catId}"]`);
        input.focus();
        const commit = async () => {
          const amount = Number(input.value.replace(/[^0-9]/g, "")) || 0;
          showSaveIndicator("saving");
          await apiPost("budget_set.php", { month: activeMonth, categoryId: catId, amount, csrf: CSRF });
          showSaveIndicator("saved");
          await loadMonth();
        };
        input.addEventListener("blur", commit);
        input.addEventListener("keydown", (e) => { if (e.key === "Enter") input.blur(); });
        input.addEventListener("input", () => { input.value = input.value.replace(/[^0-9]/g, ""); });
      });
    });
  }

  // ---------------------------------------------------------------------
  // Render: lista de movimientos
  // ---------------------------------------------------------------------
  function renderExpenses() {
    const groups = {};
    monthData.expenses.forEach((e) => { (groups[e.date] = groups[e.date] || []).push(e); });
    const dates = Object.keys(groups).sort((a, b) => (a < b ? 1 : -1));

    const wrap = document.getElementById("expense-list");
    if (dates.length === 0) {
      wrap.innerHTML = '<div class="empty-hint">' + (searchQuery || categoryFilter ? "No hay movimientos que coincidan con el filtro." : "Nada cargado todavía. Tocá el + para anotar tu primer gasto del mes.") + "</div>";
      return;
    }
    wrap.innerHTML = dates.map((date) => {
      const rows = groups[date].map((e) => {
        const cat = catById(e.categoryId) || { name: "Otros", color: "#8B7355" };
        const badge = e.installmentNo ? `<span class="installment-badge">cuota ${e.installmentNo}/${e.installmentTotal}</span>` : "";
        return `<button type="button" class="expense-row" data-expense="${e.id}">
          <span class="dot" style="background:${cat.color}"></span>
          <div style="flex:1;min-width:0">
            <div class="expense-desc">${esc(e.desc || cat.name)}${badge}</div>
            <div class="expense-cat">${esc(cat.name)}</div>
          </div>
          <div class="expense-amount">${fmtMoney(e.amount)}</div>
        </button>`;
      }).join("");
      return `<div class="date-header">${fmtDate(date)}</div>${rows}`;
    }).join("");

    wrap.querySelectorAll("[data-expense]").forEach((btn) => {
      btn.addEventListener("click", () => {
        const id = Number(btn.dataset.expense);
        const exp = monthData.expenses.find((e) => e.id === id);
        openExpenseModal(exp);
      });
    });
  }

  // ---------------------------------------------------------------------
  // Modales
  // ---------------------------------------------------------------------
  function openOverlay(innerHTML, onMount) {
    const overlay = document.createElement("div");
    overlay.className = "overlay";
    overlay.innerHTML = `<div class="modal">${innerHTML}</div>`;
    document.body.appendChild(overlay);
    overlay.addEventListener("click", (e) => { if (e.target === overlay) overlay.remove(); });
    overlay.querySelectorAll("[data-close]").forEach((b) => b.addEventListener("click", () => overlay.remove()));
    if (onMount) onMount(overlay);
    return overlay;
  }

  function categoryChipsHTML(selectedId, includeAdd = true) {
    return categories.map((c) => `
      <button type="button" class="chip" data-chip="${c.id}"
        style="border-color:${c.color};background:${Number(selectedId) === c.id ? c.color : "transparent"};color:${Number(selectedId) === c.id ? "#FBF8EF" : "#3A3423"}">
        ${esc(c.name)}
      </button>`).join("") + (includeAdd ? `<button type="button" class="chip-add" id="chip-add-category">${ICONS.plus} nueva</button>` : "");
  }

  function openExpenseModal(existing) {
    const isEdit = !!existing;
    const html = `
      <div class="modal-head">
        <span class="modal-title">${isEdit ? "Editar gasto" : "Nuevo gasto"}</span>
        <div style="display:flex;gap:8px">
          ${isEdit ? `<button type="button" class="icon-btn" id="delete-expense-btn" style="color:var(--red)">${ICONS.trash}</button>` : ""}
          <button type="button" class="icon-btn" data-close>${ICONS.x}</button>
        </div>
      </div>
      <div class="field">
        <label class="label">Monto</label>
        <div class="amount-input-wrap"><span class="amount-prefix">$</span>
          <input class="amount-input" id="exp-amount" inputmode="numeric" placeholder="0" value="${isEdit ? existing.amount : ""}">
        </div>
      </div>
      <div class="field">
        <label class="label">En qué lo gastaste</label>
        <input class="text-input" id="exp-desc" placeholder="ej: fernet juntada, turno padel..." value="${isEdit ? esc(existing.desc) : ""}">
      </div>
      <div class="field">
        <label class="label">Categoría</label>
        <div class="chip-wrap" id="exp-chips">${categoryChipsHTML(isEdit ? existing.categoryId : categories[0]?.id)}</div>
      </div>
      <div class="field">
        <label class="label">Fecha</label>
        <input type="date" class="text-input" id="exp-date" value="${isEdit ? existing.date : todayISO()}">
      </div>
      <button type="button" class="primary-btn" id="exp-save">${isEdit ? "Guardar cambios" : "Anotar gasto"}</button>
    `;
    let selectedCat = isEdit ? existing.categoryId : categories[0]?.id;
    const overlay = openOverlay(html, (ov) => {
      ov.querySelectorAll("#exp-chips [data-chip]").forEach((chip) => {
        chip.addEventListener("click", () => {
          selectedCat = Number(chip.dataset.chip);
          ov.querySelector("#exp-chips").innerHTML = categoryChipsHTML(selectedCat);
          rebindChipAdd(ov);
        });
      });
      rebindChipAdd(ov);
      ov.querySelector("#exp-amount").addEventListener("input", (e) => { e.target.value = e.target.value.replace(/[^0-9]/g, ""); });

      if (isEdit) {
        ov.querySelector("#delete-expense-btn").addEventListener("click", async () => {
          overlay.remove();
          showSaveIndicator("saving");
          const res = await apiPost("expense_delete.php", { id: existing.id, csrf: CSRF });
          showSaveIndicator("saved");
          await loadMonth(); await loadMonthsChart();
          showToast("Gasto eliminado", "Deshacer", async () => {
            const d = res.deleted;
            await apiPost("expense_add.php", { amount: d.amount, categoryId: d.categoryId, desc: d.desc, date: d.date, month: d.month, csrf: CSRF });
            await loadMonth(); await loadMonthsChart();
          });
        });
      }

      ov.querySelector("#exp-save").addEventListener("click", async () => {
        const amount = Number(ov.querySelector("#exp-amount").value) || 0;
        if (amount <= 0) return;
        const desc = ov.querySelector("#exp-desc").value.trim();
        const date = ov.querySelector("#exp-date").value || todayISO();
        overlay.remove();
        showSaveIndicator("saving");
        if (isEdit) {
          await apiPost("expense_update.php", { id: existing.id, amount, desc, categoryId: selectedCat, date, csrf: CSRF });
        } else {
          await apiPost("expense_add.php", { amount, desc, categoryId: selectedCat, date, month: activeMonth, csrf: CSRF });
        }
        showSaveIndicator("saved");
        await loadMonth(); await loadMonthsChart();
      });
    });

    function rebindChipAdd(ov) {
      const btn = ov.querySelector("#chip-add-category");
      if (btn) btn.addEventListener("click", () => { overlay.remove(); openCategoryModal(() => openExpenseModal(existing)); });
    }
  }

  function openIncomeListModal() {
    const entries = monthData.incomeEntries || [];
    const total = entries.reduce((s, e) => s + e.amount, 0);
    const rowsHTML = entries.length
      ? entries.map((e) => `
          <div class="expense-row" data-income-row="${e.id}" style="cursor:pointer">
            <span class="dot" style="background:${e.categoryColor || "var(--green)"}"></span>
            <div style="flex:1;min-width:0">
              <div class="expense-desc">${esc(e.desc || "Ingreso")}</div>
              <div class="expense-cat">${fmtDate(e.date)}${e.categoryName ? " · " + esc(e.categoryName) : ""}</div>
            </div>
            <div class="expense-amount" style="color:var(--green)">${fmtMoney(e.amount)}</div>
          </div>`).join("")
      : '<div class="empty-hint">Todavía no cargaste ningún ingreso este mes.</div>';

    const html = `
      <div class="modal-head"><span class="modal-title">Ingresos de ${esc(monthLabel(activeMonth))}</span><button type="button" class="icon-btn" data-close>${ICONS.x}</button></div>
      <div class="ticket-row" style="padding-bottom:14px;margin-bottom:10px;border-bottom:1px dashed var(--border)">
        <span class="ticket-label" style="font-weight:700">Total del mes</span>
        <span class="ticket-big" style="font-size:22px;color:var(--green)">${fmtMoney(total)}</span>
      </div>
      <div id="income-list-rows">${rowsHTML}</div>
      <button type="button" class="primary-btn" id="income-add-btn" style="margin-top:14px">+ agregar ingreso</button>
    `;
    const overlay = openOverlay(html, (ov) => {
      ov.querySelectorAll("[data-income-row]").forEach((row) => {
        row.addEventListener("click", () => {
          const id = Number(row.dataset.incomeRow);
          const entry = entries.find((e) => e.id === id);
          overlay.remove();
          openIncomeEntryModal(entry, openIncomeListModal);
        });
      });
      ov.querySelector("#income-add-btn").addEventListener("click", () => {
        overlay.remove();
        openIncomeEntryModal(null, openIncomeListModal);
      });
    });
  }

  function incomeCategoryChipsHTML(selectedId) {
    const noneSel = selectedId == null;
    return `<button type="button" class="chip" data-chip="0"
        style="border-color:var(--border);background:${noneSel ? "#6B6250" : "transparent"};color:${noneSel ? "#FBF8EF" : "#3A3423"}">
        sin categoría
      </button>` + categories.map((c) => `
      <button type="button" class="chip" data-chip="${c.id}"
        style="border-color:${c.color};background:${Number(selectedId) === c.id ? c.color : "transparent"};color:${Number(selectedId) === c.id ? "#FBF8EF" : "#3A3423"}">
        ${esc(c.name)}
      </button>`).join("") + `<button type="button" class="chip-add" id="chip-add-category">${ICONS.plus} nueva</button>`;
  }

  function openIncomeEntryModal(existing, onDone) {
    const isEdit = !!existing;
    let selectedCat = isEdit && existing.categoryId != null ? existing.categoryId : null;
    const html = `
      <div class="modal-head">
        <span class="modal-title">${isEdit ? "Editar ingreso" : "Nuevo ingreso"}</span>
        <div style="display:flex;gap:8px">
          ${isEdit ? `<button type="button" class="icon-btn" id="income-delete-btn" style="color:var(--red)">${ICONS.trash}</button>` : ""}
          <button type="button" class="icon-btn" data-close>${ICONS.x}</button>
        </div>
      </div>
      <div class="field"><label class="label">Monto</label>
        <div class="amount-input-wrap"><span class="amount-prefix">$</span>
          <input class="amount-input" id="income-amount" inputmode="numeric" value="${isEdit ? existing.amount : ""}" placeholder="0">
        </div>
      </div>
      <div class="field"><label class="label">De dónde vino (opcional)</label>
        <input class="text-input" id="income-desc" placeholder="ej: sueldo, freelance, venta..." value="${isEdit ? esc(existing.desc) : ""}">
      </div>
      <div class="field"><label class="label">Categoría (opcional)</label>
        <div class="chip-wrap" id="income-chips">${incomeCategoryChipsHTML(selectedCat)}</div>
      </div>
      <div class="field"><label class="label">Fecha</label>
        <input type="date" class="text-input" id="income-date" value="${isEdit ? existing.date : todayISO()}">
      </div>
      <button type="button" class="primary-btn" id="income-save">${isEdit ? "Guardar cambios" : "Agregar ingreso"}</button>
    `;
    const overlay = openOverlay(html, (ov) => {
      ov.querySelector("#income-amount").addEventListener("input", (e) => { e.target.value = e.target.value.replace(/[^0-9]/g, ""); });

      function bindIncomeChips() {
        ov.querySelectorAll("#income-chips [data-chip]").forEach((chip) => {
          chip.addEventListener("click", () => {
            const v = Number(chip.dataset.chip);
            selectedCat = v === 0 ? null : v;
            ov.querySelector("#income-chips").innerHTML = incomeCategoryChipsHTML(selectedCat);
            bindIncomeChips();
          });
        });
        const addBtn = ov.querySelector("#chip-add-category");
        if (addBtn) addBtn.addEventListener("click", () => { overlay.remove(); openCategoryModal(() => openIncomeEntryModal(existing, onDone)); });
      }
      bindIncomeChips();

      if (isEdit) {
        ov.querySelector("#income-delete-btn").addEventListener("click", async () => {
          if (!confirm("¿Borrar este ingreso?")) return;
          overlay.remove();
          showSaveIndicator("saving");
          await apiPost("income_delete.php", { id: existing.id, csrf: CSRF });
          showSaveIndicator("saved");
          await loadMonth(); await loadMonthsChart();
          if (onDone) onDone();
        });
      }

      ov.querySelector("#income-save").addEventListener("click", async () => {
        const amount = Number(ov.querySelector("#income-amount").value) || 0;
        if (amount <= 0) { showInstError(ov, "El monto tiene que ser mayor a cero."); return; }
        const desc = ov.querySelector("#income-desc").value.trim();
        const date = ov.querySelector("#income-date").value || todayISO();
        const saveBtn = ov.querySelector("#income-save");
        saveBtn.disabled = true;
        try {
          if (isEdit) {
            await apiPost("income_update.php", { id: existing.id, amount, desc, categoryId: selectedCat, date, csrf: CSRF });
          } else {
            await apiPost("income_add.php", { amount, desc, categoryId: selectedCat, date, csrf: CSRF });
          }
          overlay.remove();
          showSaveIndicator("saved");
          await loadMonth(); await loadMonthsChart();
          if (onDone) onDone();
        } catch (err) {
          showInstError(ov, err.message || "No se pudo guardar.");
          saveBtn.disabled = false;
        }
      });
    });
  }

  function openCategoryModal(onDone) {
    const used = categories.map((c) => c.color);
    const suggested = PALETTE.find((c) => !used.includes(c)) || PALETTE[0];
    let color = suggested;
    const html = `
      <div class="modal-head"><span class="modal-title">Nueva categoría</span><button type="button" class="icon-btn" data-close>${ICONS.x}</button></div>
      <div class="field"><label class="label">Nombre</label><input class="text-input" id="cat-name" placeholder="ej: Padel, Vacaciones..."></div>
      <div class="field"><label class="label">Color</label><div class="chip-wrap" id="cat-colors">
        ${PALETTE.map((c) => `<button type="button" class="color-swatch" data-color="${c}" style="background:${c};outline:${c === color ? "2px solid #24301F" : "none"};outline-offset:2px"></button>`).join("")}
      </div></div>
      <button type="button" class="primary-btn" id="cat-save">Crear categoría</button>`;
    const overlay = openOverlay(html, (ov) => {
      ov.querySelectorAll("[data-color]").forEach((sw) => sw.addEventListener("click", () => {
        color = sw.dataset.color;
        ov.querySelectorAll("[data-color]").forEach((s) => s.style.outline = s.dataset.color === color ? "2px solid #24301F" : "none");
      }));
      ov.querySelector("#cat-save").addEventListener("click", async () => {
        const name = ov.querySelector("#cat-name").value.trim();
        if (!name) return;
        overlay.remove();
        const cat = await apiPost("category_add.php", { name, color, csrf: CSRF });
        categories.push({ id: cat.id, name: cat.name, color: cat.color });
        renderTemplates();
        if (onDone) onDone();
      });
    });
  }

  function openManageCategoriesModal() {
    function rowsHTML() {
      return categories.map((c) => `
        <div class="category-manage-row">
          <span class="dot" style="background:${c.color}"></span>
          <span class="category-manage-name">${esc(c.name)}</span>
          <button type="button" class="icon-mini-btn" data-manage-edit="${c.id}">${ICONS.pencil}</button>
          <button type="button" class="icon-mini-btn" data-manage-del="${c.id}" style="color:var(--red)">${ICONS.trash}</button>
        </div>`).join("");
    }
    const html = `
      <div class="modal-head"><span class="modal-title">Tus categorías</span><button type="button" class="icon-btn" data-close>${ICONS.x}</button></div>
      <div id="manage-list">${rowsHTML()}</div>
      <button type="button" class="secondary-btn" id="manage-add">+ nueva categoría</button>`;
    const overlay = openOverlay(html, (ov) => {
      function bind() {
        ov.querySelectorAll("[data-manage-edit]").forEach((btn) => btn.addEventListener("click", () => {
          const cat = catById(btn.dataset.manageEdit);
          const row = btn.closest(".category-manage-row");
          row.innerHTML = `
            <input class="text-input" style="flex:1;padding:6px 8px;font-size:13px" value="${esc(cat.name)}" data-edit-name>
            <button type="button" class="icon-mini-btn" data-edit-save="${cat.id}" style="color:var(--green)">${checkSVG()}</button>`;
          row.querySelector("[data-edit-save]").addEventListener("click", async () => {
            const name = row.querySelector("[data-edit-name]").value.trim();
            if (!name) return;
            await apiPost("category_update.php", { id: cat.id, name, color: cat.color, csrf: CSRF });
            cat.name = name;
            ov.querySelector("#manage-list").innerHTML = rowsHTML();
            bind();
            renderBudgets(); renderExpenses(); renderPieChart(); renderTemplates();
          });
        }));
        ov.querySelectorAll("[data-manage-del]").forEach((btn) => btn.addEventListener("click", async () => {
          const id = Number(btn.dataset.manageDel);
          if (!confirm("¿Borrar esta categoría? Los gastos que tenga pasan a \"Otros\".")) return;
          await apiPost("category_delete.php", { id, csrf: CSRF });
          categories = categories.filter((c) => c.id !== id);
          templates = templates.filter((t) => t.category_id !== id);
          ov.querySelector("#manage-list").innerHTML = rowsHTML();
          bind();
          await loadMonth(); renderTemplates();
        }));
      }
      bind();
      ov.querySelector("#manage-add").addEventListener("click", () => { overlay.remove(); openCategoryModal(openManageCategoriesModal); });
    });
  }

  function openInstallmentModal() {
    let selectedCat = categories[0]?.id;
    const html = `
      <div class="modal-head"><span class="modal-title">Compra en cuotas</span><button type="button" class="icon-btn" data-close>${ICONS.x}</button></div>
      <div class="field"><label class="label">Qué compraste</label><input class="text-input" id="inst-desc" placeholder="ej: Notebook, TV..."></div>
      <div class="field"><label class="label">Monto total</label>
        <div class="amount-input-wrap"><span class="amount-prefix">$</span><input class="amount-input" id="inst-total" inputmode="numeric" placeholder="0"></div>
      </div>
      <div class="field"><label class="label">Cantidad de cuotas</label>
        <input class="text-input" id="inst-count" inputmode="numeric" placeholder="ej: 12" value="12">
      </div>
      <div class="field" id="inst-preview" style="display:none"></div>
      <div class="field"><label class="label">Categoría</label><div class="chip-wrap" id="inst-chips">${categoryChipsHTML(selectedCat, false)}</div></div>
      <div class="field"><label class="label">Primera cuota</label>
        <input type="month" class="text-input" id="inst-month" value="${activeMonth}">
      </div>
      <button type="button" class="primary-btn" id="inst-save">Cargar compra en cuotas</button>`;
    const overlay = openOverlay(html, (ov) => {
      function bindChips() {
        ov.querySelectorAll("#inst-chips [data-chip]").forEach((chip) => chip.addEventListener("click", () => {
          selectedCat = Number(chip.dataset.chip);
          ov.querySelector("#inst-chips").innerHTML = categoryChipsHTML(selectedCat, false);
          bindChips();
        }));
      }
      bindChips();
      function updatePreview() {
        const total = Number(ov.querySelector("#inst-total").value) || 0;
        const count = Number(ov.querySelector("#inst-count").value) || 0;
        const preview = ov.querySelector("#inst-preview");
        if (total > 0 && count >= 2) {
          preview.style.display = "";
          preview.innerHTML = `<div class="empty-hint" style="padding:0">${count} cuotas de ${fmtMoney(total / count)} aprox., empezando en ${esc(monthLabel(ov.querySelector("#inst-month").value || activeMonth))}.</div>`;
        } else {
          preview.style.display = "none";
        }
      }
      ov.querySelector("#inst-total").addEventListener("input", (e) => { e.target.value = e.target.value.replace(/[^0-9]/g, ""); updatePreview(); });
      ov.querySelector("#inst-count").addEventListener("input", (e) => { e.target.value = e.target.value.replace(/[^0-9]/g, ""); updatePreview(); });
      ov.querySelector("#inst-month").addEventListener("change", updatePreview);

      ov.querySelector("#inst-save").addEventListener("click", async () => {
        const desc = ov.querySelector("#inst-desc").value.trim();
        const totalAmount = Number(ov.querySelector("#inst-total").value) || 0;
        const numInstallments = Number(ov.querySelector("#inst-count").value) || 0;
        const firstMonth = ov.querySelector("#inst-month").value || activeMonth;
        if (!desc) { showInstError(ov, "Falta la descripción."); return; }
        if (totalAmount <= 0) { showInstError(ov, "El monto total tiene que ser mayor a cero."); return; }
        if (numInstallments < 2) { showInstError(ov, "Tiene que ser 2 cuotas o más."); return; }

        const saveBtn = ov.querySelector("#inst-save");
        saveBtn.disabled = true;
        saveBtn.textContent = "Guardando...";
        try {
          await apiPost("installment_add.php", { desc, totalAmount, numInstallments, categoryId: selectedCat, firstMonth, day: new Date().getDate(), csrf: CSRF });
          overlay.remove();
          showSaveIndicator("saved");
          await loadMonth(); await loadMonthsChart(); await loadInstallments();
        } catch (err) {
          showInstError(ov, err.message || "No se pudo guardar. Probá de nuevo.");
          saveBtn.disabled = false;
          saveBtn.textContent = "Cargar compra en cuotas";
        }
      });
    });
  }

  function showInstError(ov, message) {
    let box = ov.querySelector("#inst-error");
    if (!box) {
      box = document.createElement("div");
      box.id = "inst-error";
      box.className = "auth-error";
      box.style.marginBottom = "12px";
      ov.querySelector(".modal-head").insertAdjacentElement("afterend", box);
    }
    box.textContent = message;
  }


  function openImportModal() {
    const html = `
      <div class="modal-head"><span class="modal-title">Importar movimientos</span><button type="button" class="icon-btn" data-close>${ICONS.x}</button></div>
      <div class="empty-hint" style="padding-bottom:10px">
        Subí un archivo CSV con columnas <b>Fecha;Categoría;Descripción;Monto</b>
        (fecha en formato AAAA-MM-DD). Si una categoría no existe todavía, se crea sola.
      </div>
      <div class="field">
        <input type="file" id="import-file" accept=".csv,text/csv">
      </div>
      <div id="import-status" class="empty-hint" style="display:none"></div>
      <button type="button" class="primary-btn" id="import-save" disabled>Importar</button>
    `;
    const overlay = openOverlay(html, (ov) => {
      let parsedRows = null;
      const fileInput = ov.querySelector("#import-file");
      const statusBox = ov.querySelector("#import-status");
      const saveBtn = ov.querySelector("#import-save");

      fileInput.addEventListener("change", async () => {
        const file = fileInput.files[0];
        if (!file) return;
        try {
          const text = await file.text();
          parsedRows = parseImportCSV(text);
          statusBox.style.display = "";
          statusBox.textContent = `${parsedRows.length} filas listas para importar.`;
          saveBtn.disabled = parsedRows.length === 0;
        } catch (err) {
          statusBox.style.display = "";
          statusBox.style.color = "var(--red)";
          statusBox.textContent = "No se pudo leer el archivo: " + err.message;
          saveBtn.disabled = true;
        }
      });

      saveBtn.addEventListener("click", async () => {
        if (!parsedRows || parsedRows.length === 0) return;
        saveBtn.disabled = true;
        saveBtn.textContent = "Importando...";
        try {
          const res = await apiPost("import_bulk.php", { rows: parsedRows, csrf: CSRF });
          overlay.remove();
          let msg = `Se importaron ${res.imported} movimientos.`;
          if (res.skipped) msg += ` Se salteó ${res.skipped} por datos inválidos.`;
          if (res.createdCategories && res.createdCategories.length) msg += ` Categorías nuevas: ${res.createdCategories.join(", ")}.`;
          showToast(msg);
          const boot = await apiGet("bootstrap.php");
          categories = boot.categories;
          renderCategoryFilterOptions();
          await loadMonth(); await loadMonthsChart(); await loadInstallments();
        } catch (err) {
          statusBox.style.display = "";
          statusBox.style.color = "var(--red)";
          statusBox.textContent = err.message || "No se pudo importar.";
          saveBtn.disabled = false;
          saveBtn.textContent = "Importar";
        }
      });
    });
  }

  // Parser de CSV simple, soporta ; o , como separador y campos entre comillas.
  function parseImportCSV(text) {
    const clean = text.replace(/^\uFEFF/, "").trim();
    const lines = clean.split(/\r\n|\n|\r/).filter((l) => l.trim() !== "");
    if (lines.length === 0) return [];
    const delimiter = lines[0].includes(";") ? ";" : ",";

    function splitLine(line) {
      const cells = [];
      let cur = "";
      let inQuotes = false;
      for (let i = 0; i < line.length; i++) {
        const ch = line[i];
        if (inQuotes) {
          if (ch === '"' && line[i + 1] === '"') { cur += '"'; i++; }
          else if (ch === '"') { inQuotes = false; }
          else { cur += ch; }
        } else if (ch === '"') {
          inQuotes = true;
        } else if (ch === delimiter) {
          cells.push(cur); cur = "";
        } else {
          cur += ch;
        }
      }
      cells.push(cur);
      return cells.map((c) => c.trim());
    }

    let rows = lines.map(splitLine);
    const header = rows[0].map((h) => h.toLowerCase());
    const looksLikeHeader = header.some((h) => h.includes("fecha") || h.includes("monto") || h.includes("categor"));
    if (looksLikeHeader) rows = rows.slice(1);

    return rows
      .filter((r) => r.length >= 4 && r.some((c) => c !== ""))
      .map((r) => ({
        date: normalizeDate(r[0]),
        category: r[1] || "",
        desc: r[2] || "",
        amount: Number(String(r[3]).replace(/\./g, "").replace(",", ".")) || Number(r[3]) || 0,
      }))
      .filter((r) => r.date && r.amount > 0);
  }

  function normalizeDate(s) {
    s = (s || "").trim();
    if (/^\d{4}-\d{2}-\d{2}$/.test(s)) return s;
    const m = s.match(/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/);
    if (m) return `${m[3]}-${m[2].padStart(2, "0")}-${m[1].padStart(2, "0")}`;
    return null;
  }


  function openRecurringModal() {
    let selectedCat = categories[0]?.id;
    const html = `
      <div class="modal-head"><span class="modal-title">Gasto fijo mensual</span><button type="button" class="icon-btn" data-close>${ICONS.x}</button></div>
      <div class="field"><label class="label">Nombre</label><input class="text-input" id="rec-name" placeholder="ej: Netflix, gimnasio, alquiler"></div>
      <div class="field"><label class="label">Monto por mes</label>
        <div class="amount-input-wrap"><span class="amount-prefix">$</span><input class="amount-input" id="rec-amount" inputmode="numeric" placeholder="0"></div>
      </div>
      <div class="field"><label class="label">Categoría</label><div class="chip-wrap" id="rec-chips">${categoryChipsHTML(selectedCat, false)}</div></div>
      <div class="field"><label class="label">Empieza en</label>
        <input type="month" class="text-input" id="rec-month" value="${activeMonth}">
      </div>
      <button type="button" class="primary-btn" id="rec-save">Guardar gasto fijo</button>
    `;
    const overlay = openOverlay(html, (ov) => {
      function bindChips() {
        ov.querySelectorAll("#rec-chips [data-chip]").forEach((chip) => chip.addEventListener("click", () => {
          selectedCat = Number(chip.dataset.chip);
          ov.querySelector("#rec-chips").innerHTML = categoryChipsHTML(selectedCat, false);
          bindChips();
        }));
      }
      bindChips();
      ov.querySelector("#rec-amount").addEventListener("input", (e) => { e.target.value = e.target.value.replace(/[^0-9]/g, ""); });

      ov.querySelector("#rec-save").addEventListener("click", async () => {
        const name = ov.querySelector("#rec-name").value.trim();
        const amount = Number(ov.querySelector("#rec-amount").value) || 0;
        const startMonth = ov.querySelector("#rec-month").value || activeMonth;
        if (!name) { showInstError(ov, "Falta el nombre."); return; }
        if (amount <= 0) { showInstError(ov, "El monto tiene que ser mayor a cero."); return; }
        const saveBtn = ov.querySelector("#rec-save");
        saveBtn.disabled = true;
        try {
          await apiPost("recurring_add.php", { name, amount, categoryId: selectedCat, startMonth, dayOfMonth: new Date().getDate(), csrf: CSRF });
          overlay.remove();
          await loadRecurring();
          await loadMonth(); await loadMonthsChart();
        } catch (err) {
          showInstError(ov, err.message || "No se pudo guardar.");
          saveBtn.disabled = false;
        }
      });
    });
  }


  // NOTA: esta firma se perdio 3 veces por errores de reemplazo de texto -
  // NO TOCAR esta linea sin verificar despues con node --check.
  function openTemplateModal() {
    let selectedCat = categories[0]?.id;
    const html = `
      <div class="modal-head"><span class="modal-title">Gasto frecuente</span><button type="button" class="icon-btn" data-close>${ICONS.x}</button></div>
      <div class="field"><label class="label">Nombre</label><input class="text-input" id="tpl-name" placeholder="ej: Turno padel"></div>
      <div class="field"><label class="label">Monto</label>
        <div class="amount-input-wrap"><span class="amount-prefix">$</span><input class="amount-input" id="tpl-amount" inputmode="numeric" placeholder="0"></div>
      </div>
      <div class="field"><label class="label">Categoría</label><div class="chip-wrap" id="tpl-chips">${categoryChipsHTML(selectedCat)}</div></div>
      <button type="button" class="primary-btn" id="tpl-save">Guardar como frecuente</button>`;
    const overlay = openOverlay(html, (ov) => {
      ov.querySelectorAll("#tpl-chips [data-chip]").forEach((chip) => chip.addEventListener("click", () => {
        selectedCat = Number(chip.dataset.chip);
        ov.querySelector("#tpl-chips").innerHTML = categoryChipsHTML(selectedCat);
      }));
      ov.querySelector("#tpl-amount").addEventListener("input", (e) => { e.target.value = e.target.value.replace(/[^0-9]/g, ""); });
      ov.querySelector("#tpl-save").addEventListener("click", async () => {
        const name = ov.querySelector("#tpl-name").value.trim();
        const amount = Number(ov.querySelector("#tpl-amount").value) || 0;
        if (!name || amount <= 0) return;
        overlay.remove();
        const t = await apiPost("template_add.php", { name, amount, categoryId: selectedCat, csrf: CSRF });
        templates.push({ id: t.id, name, amount, category_id: selectedCat });
        renderTemplates();
      });
    });
  }

  function openYearSummaryModal() {
    let year = Number(activeMonth.split("-")[0]);

    const html = `
      <div class="modal-head">
        <span class="modal-title">Resumen anual</span>
        <button type="button" class="icon-btn" data-close>${ICONS.x}</button>
      </div>
      <div class="month-nav" style="width:fit-content;margin:0 auto 14px">
        <button type="button" class="nav-btn" id="year-prev">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#6B6250" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
        </button>
        <span class="month-label" id="year-label" style="min-width:60px">${year}</span>
        <button type="button" class="nav-btn" id="year-next">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#6B6250" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
        </button>
      </div>
      <div id="year-body"><div class="empty-hint">Cargando...</div></div>
    `;
    const overlay = openOverlay(html, async (ov) => {
      async function load() {
        ov.querySelector("#year-label").textContent = year;
        ov.querySelector("#year-body").innerHTML = '<div class="empty-hint">Cargando...</div>';
        const data = await apiGet("year_summary.php?year=" + year);
        const rest = data.totalIncome - data.totalSpent;
        ov.querySelector("#year-body").innerHTML = `
          <div class="ticket-row"><span class="ticket-label">Ingreso del año</span><span class="ticket-amount">${fmtMoney(data.totalIncome)}</span></div>
          <div class="ticket-row"><span class="ticket-label">Gastado en el año</span><span class="ticket-amount" style="color:var(--red)">${fmtMoney(data.totalSpent)}</span></div>
          <div class="dashed-line"></div>
          <div class="ticket-row"><span class="ticket-label" style="font-weight:700">Balance</span><span class="ticket-big" style="font-size:22px;color:${rest < 0 ? "var(--red)" : "var(--green)"}">${rest < 0 ? "-" : ""}${fmtMoney(Math.abs(rest))}</span></div>
          <div class="dashed-line"></div>
          <div class="months-chart-wrap" style="height:150px;margin-bottom:14px"><canvas id="year-canvas"></canvas></div>
          <div class="card-title" style="margin-bottom:4px">Por categoría</div>
          <div id="year-cat-list"></div>
        `;
        const catsWithSpend = data.categories.filter((c) => c.spent > 0);
        const total = catsWithSpend.reduce((s, c) => s + c.spent, 0);
        ov.querySelector("#year-cat-list").innerHTML = catsWithSpend.length
          ? catsWithSpend.map((c) => `
              <div class="legend-row">
                <span class="dot" style="background:${c.color}"></span>
                <span class="legend-name">${esc(c.name)}</span>
                <span class="legend-val">${fmtMoney(c.spent)} · ${total ? Math.round((c.spent / total) * 100) : 0}%</span>
              </div>`).join("")
          : '<div class="empty-hint">Todavía no hay gastos cargados en este año.</div>';

        const ctx = ov.querySelector("#year-canvas").getContext("2d");
        if (yearChart) yearChart.destroy();
        yearChart = new Chart(ctx, {
          type: "bar",
          data: { labels: data.months.map((m) => m.label.slice(0, 3)), datasets: [{ data: data.months.map((m) => m.spent), backgroundColor: "#2F6F4E", borderRadius: 4, maxBarThickness: 20 }] },
          options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => " " + fmtMoney(c.parsed.y) } } },
            scales: { y: { display: false, beginAtZero: true }, x: { grid: { display: false }, ticks: { font: { size: 10, family: "Inter" }, color: "#8B8368" } } },
          },
        });
      }
      ov.querySelector("#year-prev").addEventListener("click", () => { year -= 1; load(); });
      ov.querySelector("#year-next").addEventListener("click", () => { year += 1; load(); });
      await load();
    });
  }

  // ---------------------------------------------------------------------
  // Secciones plegables (se acuerda de cómo las dejaste, por sección)
  // ---------------------------------------------------------------------
  const COLLAPSE_KEY = "libreta:collapsed";
  function loadCollapsedState() {
    try { return JSON.parse(localStorage.getItem(COLLAPSE_KEY) || "{}"); } catch { return {}; }
  }
  function saveCollapsedState(state) {
    try { localStorage.setItem(COLLAPSE_KEY, JSON.stringify(state)); } catch { /* si el navegador bloquea localStorage, simplemente no persiste */ }
  }
  function applyPanelVisibility() {
    const hidden = new Set(settings.hiddenSections || []);
    document.querySelectorAll(".card[data-section]").forEach((card) => {
      card.classList.toggle("panel-hidden", hidden.has(card.dataset.section));
    });
  }

  function applyCollapsedState() {
    const state = loadCollapsedState();
    document.querySelectorAll(".card[data-section]").forEach((card) => {
      card.classList.toggle("is-collapsed", !!state[card.dataset.section]);
    });
  }
  function bindCollapsibles() {
    document.querySelectorAll(".card-header[data-toggle]").forEach((header) => {
      const toggle = (e) => {
        if (e.target.closest("[data-no-toggle]")) return;
        const card = header.closest(".card[data-section]");
        if (!card) return;
        card.classList.toggle("is-collapsed");
        const state = loadCollapsedState();
        state[card.dataset.section] = card.classList.contains("is-collapsed");
        saveCollapsedState(state);
      };
      header.addEventListener("click", toggle);
      header.addEventListener("keydown", (e) => {
        if (e.key === "Enter" || e.key === " ") { e.preventDefault(); toggle(e); }
      });
    });
  }

  // ---------------------------------------------------------------------
  // Configuración: paneles visibles, importar/exportar, backup, notificaciones
  // ---------------------------------------------------------------------
  function openSettingsModal() {
    const hidden = new Set(settings.hiddenSections || []);
    const panelsHTML = TOGGLEABLE_SECTIONS.map((s) => `
      <label class="category-manage-row" style="cursor:pointer">
        <input type="checkbox" data-panel="${s.key}" ${hidden.has(s.key) ? "" : "checked"} style="width:16px;height:16px;accent-color:var(--green)">
        <span class="category-manage-name">${esc(s.label)}</span>
      </label>`).join("");

    const notifSupported = "Notification" in window;
    const notifChecked = settings.notificationsEnabled && notifSupported && Notification.permission === "granted";

    const html = `
      <div class="modal-head"><span class="modal-title">Configuración</span><button type="button" class="icon-btn" data-close>${ICONS.x}</button></div>

      <div class="card-title" style="margin-bottom:4px">Paneles visibles</div>
      <div class="empty-hint" style="padding-top:0">Destildá lo que no quieras ver en la pantalla principal.</div>
      <div id="settings-panels">${panelsHTML}</div>

      <div class="card-title" style="margin:18px 0 4px">Tus datos</div>
      <button type="button" class="secondary-btn" id="settings-import-btn" style="margin-top:6px">Importar movimientos (CSV)</button>
      <button type="button" class="secondary-btn" id="settings-export-month-btn">Exportar este mes (CSV)</button>
      <button type="button" class="secondary-btn" id="settings-export-all-btn">Exportar todo (CSV)</button>
      <button type="button" class="secondary-btn" id="settings-backup-btn">Backup completo (JSON)</button>

      <div class="card-title" style="margin:18px 0 4px">Notificaciones</div>
      <div class="empty-hint" style="padding-top:0">
        Avisa cuando una categoría llega al 90% del presupuesto, mientras tengas la app abierta.
        ${notifSupported ? "" : " (tu navegador no soporta esto)"}
      </div>
      <label class="remember-row" style="margin-top:8px">
        <input type="checkbox" id="settings-notif-toggle" ${notifChecked ? "checked" : ""} ${notifSupported ? "" : "disabled"}>
        <span>Activar notificaciones</span>
      </label>
    `;

    openOverlay(html, (ov) => {
      ov.querySelectorAll("[data-panel]").forEach((cb) => {
        cb.addEventListener("change", async () => {
          const key = cb.dataset.panel;
          if (cb.checked) hidden.delete(key); else hidden.add(key);
          settings.hiddenSections = Array.from(hidden);
          applyPanelVisibility();
          try {
            await apiPost("settings_set.php", { hiddenSections: settings.hiddenSections, csrf: CSRF });
          } catch (e) { /* el toast global ya avisa si esto falla */ }
        });
      });

      ov.querySelector("#settings-import-btn").addEventListener("click", () => {
        ov.closest(".overlay").remove();
        openImportModal();
      });
      ov.querySelector("#settings-export-month-btn").addEventListener("click", () => {
        window.location.href = "/api/export.php?month=" + encodeURIComponent(activeMonth);
      });
      ov.querySelector("#settings-export-all-btn").addEventListener("click", () => {
        window.location.href = "/api/export.php?month=all";
      });
      ov.querySelector("#settings-backup-btn").addEventListener("click", () => {
        window.location.href = "/api/backup_export.php";
      });

      const notifToggle = ov.querySelector("#settings-notif-toggle");
      if (notifToggle) {
        notifToggle.addEventListener("change", async () => {
          if (notifToggle.checked) {
            const perm = await Notification.requestPermission();
            if (perm !== "granted") {
              notifToggle.checked = false;
              showToast("No se activaron: el navegador bloqueó el permiso de notificaciones.");
              return;
            }
          }
          settings.notificationsEnabled = notifToggle.checked;
          await apiPost("settings_set.php", { notificationsEnabled: settings.notificationsEnabled, csrf: CSRF });
        });
      }
    });
  }

  // Aviso local (mientras la app está abierta) cuando una categoría cruza
  // el 90% del presupuesto. No es push real: si cerrás la app, no llega.
  function maybeNotifyNearLimit(nearItems) {
    if (!settings.notificationsEnabled) return;
    if (!("Notification" in window) || Notification.permission !== "granted") return;
    nearItems.forEach((c) => {
      const key = activeMonth + ":" + c.id;
      if (notifiedThisSession.has(key)) return;
      notifiedThisSession.add(key);
      try {
        new Notification("mi libreta", {
          body: `${c.name}: ya usaste el ${Math.round((c.spent / c.budget) * 100)}% del presupuesto.`,
          icon: "/assets/icons/icon-192.png",
        });
      } catch (e) { /* algunos navegadores restringen esto, no pasa nada si falla */ }
    });
  }

  // ---------------------------------------------------------------------
  // Eventos estáticos
  // ---------------------------------------------------------------------
  function bindStaticEvents() {
    document.getElementById("month-prev").addEventListener("click", async () => {
      activeMonth = shiftMonth(activeMonth, -1);
      await loadMonth(); await loadMonthsChart();
    });
    document.getElementById("month-next").addEventListener("click", async () => {
      activeMonth = shiftMonth(activeMonth, 1);
      await loadMonth(); await loadMonthsChart();
    });
    document.getElementById("ticket-income").addEventListener("click", openIncomeListModal);
    document.getElementById("year-summary-btn").addEventListener("click", openYearSummaryModal);
    document.getElementById("settings-btn").addEventListener("click", openSettingsModal);
    document.getElementById("add-recurring-btn").addEventListener("click", openRecurringModal);
    document.getElementById("add-duedate-btn").addEventListener("click", openDueDateModal);
    document.getElementById("manage-categories-link").addEventListener("click", openManageCategoriesModal);
    document.getElementById("add-category-link").addEventListener("click", () => openCategoryModal());
    document.getElementById("fab-add-expense").addEventListener("click", () => openExpenseModal(null));
    document.getElementById("add-installment-btn").addEventListener("click", openInstallmentModal);

    document.getElementById("search-input").addEventListener("input", (e) => {
      searchQuery = e.target.value;
      clearTimeout(searchDebounce);
      searchDebounce = setTimeout(loadMonth, 300);
    });
    document.getElementById("category-filter").addEventListener("change", (e) => {
      categoryFilter = e.target.value;
      loadMonth();
    });

    renderTemplates();
    renderCategoryFilterOptions();
  }

  function renderCategoryFilterOptions() {
    const sel = document.getElementById("category-filter");
    sel.innerHTML = '<option value="">Todas</option>' + categories.map((c) => `<option value="${c.id}">${esc(c.name)}</option>`).join("");
  }

  document.addEventListener("DOMContentLoaded", init);

  // Red de seguridad: si cualquier llamado a la API falla en algún lado
  // (por ejemplo, una tabla que falta en la base), que se vea en pantalla
  // en vez de fallar en silencio como pasaba antes.
  window.addEventListener("unhandledrejection", (event) => {
    console.error("Error sin capturar:", event.reason);
    const msg = (event.reason && event.reason.message) || "Ocurrió un error. Probá de nuevo.";
    showToast(msg);
    showSaveIndicator("idle");
  });
})();
