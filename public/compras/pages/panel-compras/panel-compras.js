(function () {
  function $(sel) {
    return document.querySelector(sel);
  }

  function setText(sel, value) {
    const el = $(sel);
    if (el) el.textContent = String(value ?? "");
  }

  function renderRecent(items) {
    const el = $("#recentActivity");
    if (!el) return;
    if (!items?.length) {
      el.innerHTML =
        '<div class="hint">Aún no hay solicitudes recientes. Comienza creando una nueva.</div>';
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
            <th>Actualizado</th>
          </tr>
        </thead>
        <tbody>
          ${items
            .map(
              (r) => `
                <tr>
                  <td><a href="../detalle-solicitud/detalle-solicitud.html?id=${encodeURIComponent(r.id)}">${r.code}</a></td>
                  <td>${escapeHtml(r.client_name)}<div class="muted">${escapeHtml(
                r.client_code,
              )}</div></td>
                  <td>${escapeHtml(r.store_name)}</td>
                  <td>${statusPill(r.status)}</td>
                  <td class="muted">${formatDate(r.updated_at)}</td>
                </tr>
              `,
            )
            .join("")}
        </tbody>
      </table>
    `;
  }

  function escapeHtml(s) {
    return String(s ?? "")
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");
  }

  function formatDate(iso) {
    if (!iso) return "";
    const raw = String(iso);
    const normalized = /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/.test(raw) ? raw.replace(" ", "T") : raw;
    const d = new Date(normalized);
    if (Number.isNaN(d.getTime())) return String(iso);
    return d.toLocaleString();
  }

  function statusPill(status) {
    const cls =
      status === "Enviada al Supervisor"
        ? "sent"
        : status === "Pendiente comprobante"
          ? "pending"
          : status === "Compra realizada" || status === "Completada"
            ? "done"
            : status === "Cancelada"
              ? "cancelled"
              : "draft";
    return `<span class="pill ${cls}">${escapeHtml(status)}</span>`;
  }

  async function init() {
    try {
      const data = await window.PGT.api.getStats();
      setText("#kpiTotal", data.kpis.total);
      setText("#kpiPending", data.kpis.pending);
      setText("#kpiSent", data.kpis.sentToSupervisor);
      setText("#kpiCompleted", data.kpis.completed);
      renderRecent(data.recentActivity);
    } catch (e) {
      $("#recentActivity").innerHTML = `<div class="hint">Error cargando dashboard: ${escapeHtml(
        e.message,
      )}</div>`;
    }
  }

  document.addEventListener("DOMContentLoaded", init);
})();
