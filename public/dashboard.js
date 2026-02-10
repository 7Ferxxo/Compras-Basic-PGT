(() => {
  function $(sel) {
    return document.querySelector(sel);
  }

  function escapeHtml(s) {
    return String(s ?? "")
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");
  }

  function lower(v) {
    return String(v ?? "").toLowerCase();
  }

  function money(n) {
    const v = Number(n);
    if (!Number.isFinite(v)) return "B/. 0.00";
    return `B/. ${v.toFixed(2)}`;
  }

  function formatDate(value) {
    if (!value) return "-";
    const raw = String(value);
    
    const normalized = /^\d{4}-\d{2}-\d{2}$/.test(raw) ? `${raw}T00:00:00` : raw;
    const d = new Date(normalized);
    if (Number.isNaN(d.getTime())) return raw;
    return d.toLocaleDateString("es-PA", { year: "numeric", month: "2-digit", day: "2-digit" });
  }

  function uniqSorted(values) {
    return Array.from(new Set(values.filter((v) => String(v ?? "").trim() !== ""))).sort((a, b) =>
      String(a).localeCompare(String(b)),
    );
  }

  const state = {
    all: [],
    filtered: [],
    page: 1,
    pageSize: 25,
    sortKey: "id",
    sortDir: "desc",
    q: "",
    sucursal: "",
    metodo: "",
  };

  function setLoading(on) {
    $("#loadingState").style.display = on ? "flex" : "none";
    $("#emptyState").style.display = "none";
    $("#recibos-table").style.opacity = on ? "0.35" : "1";
    $("#refreshBtn").disabled = on;
  }

  function setEmpty(on) {
    $("#emptyState").style.display = on ? "block" : "none";
  }

  function compare(a, b) {
    if (a === b) return 0;
    if (a === null || a === undefined || a === "") return 1;
    if (b === null || b === undefined || b === "") return -1;

    const an = Number(a);
    const bn = Number(b);
    if (Number.isFinite(an) && Number.isFinite(bn)) return an < bn ? -1 : 1;

    const as = String(a);
    const bs = String(b);
    return as.localeCompare(bs);
  }

  function applySort(list) {
    const dir = state.sortDir === "asc" ? 1 : -1;
    const key = state.sortKey;

    const sorted = list.slice().sort((ra, rb) => {
      const a = ra?.[key];
      const b = rb?.[key];

      
      if (key === "fecha") {
        const ad = a ? new Date(String(a)) : null;
        const bd = b ? new Date(String(b)) : null;
        const av = ad && !Number.isNaN(ad.getTime()) ? ad.getTime() : null;
        const bv = bd && !Number.isNaN(bd.getTime()) ? bd.getTime() : null;
        return compare(av, bv) * dir;
      }

      return compare(a, b) * dir;
    });

    return sorted;
  }

  function applyFilters() {
    const q = lower(state.q).trim();
    const suc = String(state.sucursal || "").trim();
    const met = String(state.metodo || "").trim();

    const out = state.all.filter((r) => {
      if (suc && String(r.sucursal || "") !== suc) return false;
      if (met && String(r.metodo_pago || "") !== met) return false;
      if (!q) return true;

      return (
        lower(r.cliente).includes(q) ||
        lower(r.casillero).includes(q) ||
        lower(r.email_cliente).includes(q) ||
        lower(r.sucursal).includes(q) ||
        lower(r.metodo_pago).includes(q) ||
        lower(r.monto).includes(q) ||
        String(r.id ?? "").includes(q)
      );
    });

    state.filtered = applySort(out);
    state.page = 1;
  }

  function renderKpis() {
    const items = state.all || [];
    $("#kpiCount").textContent = String(items.length);

    const total = items.reduce((acc, r) => acc + (Number(r?.monto) || 0), 0);
    $("#kpiAmount").textContent = money(total);

    const last = items[0];
    $("#kpiLast").textContent = last ? `#${last.id} - ${formatDate(last.fecha)}` : "-";
  }

  function renderFilterOptions() {
    const sucSel = $("#filter-sucursal");
    const metSel = $("#filter-metodo");

    const sucursales = uniqSorted(state.all.map((r) => r.sucursal));
    const metodos = uniqSorted(state.all.map((r) => r.metodo_pago));

    const prevSuc = sucSel.value;
    const prevMet = metSel.value;

    sucSel.innerHTML =
      `<option value="">Todas</option>` +
      sucursales.map((s) => `<option value="${escapeHtml(s)}">${escapeHtml(s)}</option>`).join("");
    metSel.innerHTML =
      `<option value="">Todos</option>` +
      metodos.map((m) => `<option value="${escapeHtml(m)}">${escapeHtml(m)}</option>`).join("");

    sucSel.value = prevSuc;
    metSel.value = prevMet;
  }

  function sortHint() {
    const name =
      state.sortKey === "id"
        ? "#"
        : state.sortKey === "cliente"
          ? "Cliente"
          : state.sortKey === "casillero"
            ? "Casillero"
            : state.sortKey === "email_cliente"
              ? "Email"
              : state.sortKey === "sucursal"
                ? "Sucursal"
                : state.sortKey === "monto"
                  ? "Monto"
                  : state.sortKey === "fecha"
                    ? "Fecha"
                    : state.sortKey === "metodo_pago"
                      ? "Metodo"
                      : state.sortKey;
    const dir = state.sortDir === "asc" ? "asc" : "desc";
    $("#metaHint").textContent = `Ordenado por ${name} (${dir})`;
  }

  function pagerHtml(page, totalPages) {
    return `
      <div class="pager">
        <button class="btn ghost" type="button" data-page="prev" ${page <= 1 ? "disabled" : ""}>Anterior</button>
        <div class="info">Pagina ${page} / ${totalPages || 1}</div>
        <button class="btn ghost" type="button" data-page="next" ${page >= totalPages ? "disabled" : ""}>Siguiente</button>
      </div>
    `;
  }

  function renderPager(targetId, page, totalPages) {
    const el = document.getElementById(targetId);
    if (!el) return;
    if (totalPages <= 1) {
      el.innerHTML = "";
      return;
    }
    el.innerHTML = pagerHtml(page, totalPages);
    el.querySelectorAll("button[data-page]").forEach((btn) => {
      btn.addEventListener("click", () => {
        const dir = btn.getAttribute("data-page");
        if (dir === "prev") state.page = Math.max(1, state.page - 1);
        if (dir === "next") state.page = Math.min(totalPages, state.page + 1);
        render();
      });
    });
  }

  function renderTableRows(rows) {
    const tbody = $("#recibos-table tbody");
    tbody.innerHTML = "";
    for (const r of rows) {
      const tr = document.createElement("tr");
      const pdf = r?.pdf_url || (r?.id ? `/recibos/${encodeURIComponent(r.id)}/pdf` : null);

      tr.innerHTML = `
        <td data-label="#">${escapeHtml(r.id)}</td>
        <td data-label="Cliente"><div style="font-weight:900">${escapeHtml(r.cliente)}</div></td>
        <td data-label="Casillero"><span class="muted">${escapeHtml(r.casillero)}</span></td>
        <td data-label="Email"><span class="muted">${escapeHtml(r.email_cliente || "")}</span></td>
        <td data-label="Sucursal">${escapeHtml(r.sucursal || "-")}</td>
        <td data-label="Monto"><b>${money(r.monto)}</b></td>
        <td data-label="Fecha"><span class="muted">${escapeHtml(formatDate(r.fecha))}</span></td>
        <td data-label="Metodo">${escapeHtml(r.metodo_pago || "-")}</td>
        <td data-label="PDF">
          ${
            pdf
              ? `<a class="pdf-link" href="${pdf}" target="_blank" rel="noreferrer">Abrir</a>`
              : `<span class="muted">-</span>`
          }
        </td>
      `;
      tbody.appendChild(tr);
    }
  }

  function render() {
    const total = state.filtered.length;
    $("#metaCount").textContent = `${total} recibo(s)`;

    const pageSize = state.pageSize;
    const totalPages = pageSize ? Math.max(1, Math.ceil(total / pageSize)) : 1;
    state.page = Math.min(state.page, totalPages);

    const start = (state.page - 1) * pageSize;
    const end = Math.min(start + pageSize, total);
    const pageRows = state.filtered.slice(start, end);

    renderTableRows(pageRows);
    setEmpty(total === 0);

    renderPager("pagerTop", state.page, totalPages);
    renderPager("pagerBottom", state.page, totalPages);

    $("#footerSummary").textContent =
      total === 0 ? "Sin resultados." : `Mostrando ${start + 1}-${end} de ${total}.`;

    sortHint();
  }

  function wireSort() {
    document.querySelectorAll("#recibos-table thead th[data-sort]").forEach((th) => {
      th.addEventListener("click", () => {
        const key = th.getAttribute("data-sort");
        if (!key) return;
        if (state.sortKey === key) {
          state.sortDir = state.sortDir === "asc" ? "desc" : "asc";
        } else {
          state.sortKey = key;
          state.sortDir = key === "id" ? "desc" : "asc";
        }
        state.filtered = applySort(state.filtered);
        render();
      });
    });
  }

  async function load() {
    setLoading(true);
    try {
      const res = await fetch("/get-recibos");
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();
      
      state.all = Array.isArray(data) ? data.slice().sort((a, b) => (b.id || 0) - (a.id || 0)) : [];
      renderKpis();
      renderFilterOptions();
      applyFilters();
      render();
    } catch (e) {
      const tbody = $("#recibos-table tbody");
      tbody.innerHTML = `<tr><td colspan="9" class="muted">No se pudieron cargar los recibos: ${escapeHtml(
        e.message,
      )}</td></tr>`;
    } finally {
      setLoading(false);
    }
  }

  function debounce(fn, ms) {
    let t = null;
    return (...args) => {
      if (t) clearTimeout(t);
      t = setTimeout(() => fn(...args), ms);
    };
  }

  function wire() {
    wireSort();

    const onSearch = debounce(() => {
      state.q = $("#search-input").value || "";
      applyFilters();
      render();
    }, 120);

    $("#search-input").addEventListener("input", onSearch);
    $("#filter-sucursal").addEventListener("change", () => {
      state.sucursal = $("#filter-sucursal").value || "";
      applyFilters();
      render();
    });
    $("#filter-metodo").addEventListener("change", () => {
      state.metodo = $("#filter-metodo").value || "";
      applyFilters();
      render();
    });
    $("#page-size").addEventListener("change", () => {
      state.pageSize = Number($("#page-size").value) || 25;
      state.page = 1;
      render();
    });
    $("#clearBtn").addEventListener("click", () => {
      $("#search-input").value = "";
      $("#filter-sucursal").value = "";
      $("#filter-metodo").value = "";
      $("#page-size").value = "25";
      state.q = "";
      state.sucursal = "";
      state.metodo = "";
      state.pageSize = 25;
      applyFilters();
      render();
    });
    $("#refreshBtn").addEventListener("click", () => load());
  }

  document.addEventListener("DOMContentLoaded", () => {
    wire();
    load();
  });
})();
