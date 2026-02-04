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

  function statusLabel(status) {
    return (
      {
        pending: "Pendiente",
        sent_to_supervisor: "En proceso",
        completed: "Completada",
      }[status] || status || "Pendiente"
    );
  }

  function statusPill(status, label) {
    const cls =
      status === "sent_to_supervisor"
        ? "sent"
        : status === "pending"
          ? "pending"
          : status === "completed"
            ? "done"
            : "draft";
    const text = label || statusLabel(status);
    return `<span class="pill ${cls}">${escapeHtml(text)}</span>`;
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
      el.innerHTML = `<div class=\"hint\">No hay solicitudes con los filtros seleccionados.</div>`;
      return;
    }
    el.innerHTML = `
      <table class=\"table\">
        <thead>
          <tr>
            <th>Codigo</th>
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
                  <td data-label=\"Codigo\"><a href=\"../detalle-solicitud/detalle-solicitud.html?id=${encodeURIComponent(r.id)}\">${escapeHtml(
                r.code,
              )}</a></td>
                  <td data-label=\"Cliente\">${escapeHtml(r.client_name)}<div class=\"muted\">${escapeHtml(
                r.client_code,
              )}</div></td>
                  <td data-label=\"Tienda\">${escapeHtml(r.store_name)}</td>
                  <td data-label=\"Estado\">${statusPill(r.status, r.status_label)}</td>
                  <td data-label=\"Total\">${money(
                    Number(r.quoted_total || 0) +
                      Number(r.residential_charge || 0) +
                      Number(r.american_card_charge || 0),
                  )}</td>
                  <td data-label=\"Acciones\">
                    <div class=\"actions-row\">
                      <a class=\"btn\" href=\"../detalle-solicitud/detalle-solicitud.html?id=${encodeURIComponent(r.id)}\">Ver</a>
                      <select class=\"statusSelect\" data-id=\"${r.id}\" data-current=\"${r.status}\" style=\"padding:10px 10px;border-radius:999px;\">
                        ${[
                          { value: "pending", label: "Pendiente" },
                          { value: "sent_to_supervisor", label: "En proceso" },
                        ]
                          .map(
                            (s) =>
                              `<option value=\"${s.value}\" ${s.value === r.status ? "selected" : ""}>${escapeHtml(s.label)}</option>`,
                          )
                          .join("")}
                      </select>
                    </div>
                  </td>
                </tr>
              `,
            )
            .join("")}
        </tbody>
      </table>
    `;

    document.querySelectorAll(".statusSelect").forEach((sel) => {
      sel.addEventListener("change", async () => {
        const id = sel.getAttribute("data-id");
        const prev = sel.dataset.current || "pending";
        const next = sel.value;
        if (next === prev) return;

        const note = prompt("Nota (opcional) para registro:", "") || "";
        try {
          sel.disabled = true;
          await window.PGT.api.patchStatus(id, { status: next, note });
          sel.dataset.current = next;
          await loadList(1);
        } catch (e) {
          sel.value = prev;
          alert(e.message);
        } finally {
          sel.disabled = false;
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
    const meta = $("#resultsMeta");
    if (meta && data.pagination) {
      meta.textContent = `${data.pagination.total || 0} solicitudes`;
    }
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
        <div class="muted">Pagina ${p.page} / ${p.totalPages}</div>
        <button class="btn" id="nextPage" ${p.page >= p.totalPages ? "disabled" : ""}>Siguiente</button>
      </div>
    `;
    $("#prevPage")?.addEventListener("click", () => loadList(p.page - 1));
    $("#nextPage")?.addEventListener("click", () => loadList(p.page + 1));
  }

  async function init() {
    try {
      const u = new URL(location.href);
      const presetQ = u.searchParams.get("q");
      if (presetQ && $("#filterQ")) $("#filterQ").value = presetQ;

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


