(function () {
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

  function statusPill(status) {
    const cls =
      status === "Enviada al Supervisor"
        ? "sent"
        : status === "Pendiente comprobante"
          ? "pending"
          : status === "Compra realizada" || status === "Completada"
            ? "done"
            : "draft";
    return `<span class="pill ${cls}">${escapeHtml(status)}</span>`;
  }

  function money(n) {
    const v = Number(n || 0);
    return `B/. ${v.toFixed(2)}`;
  }

  async function loadStores() {
    const data = await window.PGT.api.listStores();
    const storeSel = $("#filterStore");
    if (!storeSel) return;
    storeSel.innerHTML = `<option value="">Todas</option>${data.items
      .map((s) => `<option value="${s.id}">${escapeHtml(s.name)}</option>`)
      .join("")}`;
  }

  function renderTable(items) {
    const el = $("#requestsTable");
    if (!el) return;
    if (!items?.length) {
      el.innerHTML = `<div class="hint">No hay solicitudes con los filtros seleccionados.</div>`;
      return;
    }
    el.innerHTML = `
      <table class="table">
        <thead>
          <tr>
            <th>Código</th>
            <th>Cliente</th>
            <th>Tienda</th>
            <th>Estado</th>
            <th>Total</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          ${items
            .map(
              (r) => `
                <tr>
                  <td><a href="../detalle-solicitud/detalle-solicitud.html?id=${encodeURIComponent(r.id)}">${escapeHtml(
                r.code,
              )}</a></td>
                  <td>${escapeHtml(r.client_name)}<div class="muted">${escapeHtml(
                r.client_code,
              )}</div></td>
                  <td>${escapeHtml(r.store_name)}</td>
                  <td>${statusPill(r.status)}</td>
                  <td>${money(
                    Number(r.quoted_total || 0) +
                      Number(r.residential_charge || 0) +
                      Number(r.american_card_charge || 0),
                  )}</td>
                  <td>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                      <a class="btn" href="../detalle-solicitud/detalle-solicitud.html?id=${encodeURIComponent(r.id)}">Ver</a>
                      <select class="statusSelect" data-id="${r.id}" style="padding:10px 10px;border-radius:999px;">
                        ${["Borrador", "Pendiente comprobante", "Enviada al Supervisor", "Compra realizada", "Completada"]
                          .map(
                            (s) =>
                              `<option ${s === r.status ? "selected" : ""}>${escapeHtml(s)}</option>`,
                          )
                          .join("")}
                      </select>
                      <button class="btn statusSave" data-id="${r.id}" type="button">Actualizar</button>
                      <a class="btn ${r.payment_proof_file ? "" : "disabled"}"
                         ${r.payment_proof_file ? `href="${window.PGT.api.API_BASE}/uploads/${encodeURIComponent(r.payment_proof_file)}"` : ""}
                         ${r.payment_proof_file ? "target=\"_blank\" rel=\"noreferrer\"" : ""}
                         style="${r.payment_proof_file ? "" : "pointer-events:none;opacity:.55;"}">
                        Comprobante
                      </a>
                    </div>
                  </td>
                </tr>
              `,
            )
            .join("")}
        </tbody>
      </table>
    `;

    document.querySelectorAll(".statusSave").forEach((btn) => {
      btn.addEventListener("click", async () => {
        const id = btn.getAttribute("data-id");
        const sel = document.querySelector(`.statusSelect[data-id="${id}"]`);
        const status = sel?.value;
        const note = prompt("Nota (opcional) para bitácora:", "") || "";
        try {
          btn.disabled = true;
          await window.PGT.api.patchStatus(id, { status, note });
          await loadList(1);
        } catch (e) {
          alert(e.message);
        } finally {
          btn.disabled = false;
        }
      });
    });
  }

  async function loadList(page = 1) {
    const params = {
      page,
      pageSize: 10,
      q: $("#filterQ")?.value || "",
      storeId: $("#filterStore")?.value || "",
      status: $("#filterStatus")?.value || "",
    };
    const data = await window.PGT.api.listRequests(params);
    renderTable(data.items);
    renderPager(data.pagination);
  }

  function renderPager(p) {
    const el = $("#pager");
    if (!el) return;
    if (!p || p.totalPages <= 1) {
      el.innerHTML = "";
      return;
    }
    el.innerHTML = `
      <div style="display:flex;gap:10px;align-items:center;justify-content:flex-end;margin-top:12px;">
        <button class="btn" id="prevPage" ${p.page <= 1 ? "disabled" : ""}>Anterior</button>
        <div class="muted">Página ${p.page} / ${p.totalPages}</div>
        <button class="btn" id="nextPage" ${p.page >= p.totalPages ? "disabled" : ""}>Siguiente</button>
      </div>
    `;
    $("#prevPage")?.addEventListener("click", () => loadList(p.page - 1));
    $("#nextPage")?.addEventListener("click", () => loadList(p.page + 1));
  }

  async function init() {
    try {
      await loadStores();
      $("#filtersForm")?.addEventListener("submit", (e) => {
        e.preventDefault();
        loadList(1);
      });
      await loadList(1);
    } catch (e) {
      $("#requestsTable").innerHTML = `<div class="hint">Error cargando lista: ${escapeHtml(
        e.message,
      )}</div>`;
    }
  }

  document.addEventListener("DOMContentLoaded", init);
})();
