(function () {
  const TOKEN_KEY = "PGT_COMPRAS_TOKEN";

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

  function money(n) {
    const v = Number(n || 0);
    return `B/. ${v.toFixed(2)}`;
  }

  function formatDate(iso) {
    if (!iso) return "";
    const raw = String(iso);
    const normalized = /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/.test(raw) ? raw.replace(" ", "T") : raw;
    const d = new Date(normalized);
    if (Number.isNaN(d.getTime())) return raw;
    return d.toLocaleString();
  }

  function statusPill(status) {
    const cls =
      status === "Enviada al Supervisor"
        ? "sent"
        : status === "Pendiente comprobante" || status === "Borrador"
          ? "pending"
          : status === "Compra realizada" || status === "Completada"
            ? "done"
            : "draft";
    return `<span class="pill ${cls}">${escapeHtml(status)}</span>`;
  }

  function statusClass(status) {
    if (status === "Enviada al Supervisor") return "sent";
    if (status === "Pendiente comprobante" || status === "Borrador") return "pending";
    if (status === "Compra realizada" || status === "Completada") return "done";
    return "";
  }

  function getComprasToken() {
    return (localStorage.getItem(TOKEN_KEY) || "").trim();
  }

  function setComprasToken(token) {
    const t = (token || "").trim();
    if (!t) {
      localStorage.removeItem(TOKEN_KEY);
      return;
    }
    localStorage.setItem(TOKEN_KEY, t);
  }

  async function requestJson(path, { method = "GET", body, headers } = {}) {
    const res = await fetch((window.PGT?.api?.API_BASE || "") + path, {
      method,
      headers: {
        ...(body && !(body instanceof FormData) ? { "Content-Type": "application/json" } : {}),
        ...(headers || {}),
      },
      body: body
        ? body instanceof FormData
          ? body
          : JSON.stringify(body)
        : undefined,
    });

    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
      const msg = data?.error || `HTTP ${res.status}`;
      const err = new Error(msg);
      err.status = res.status;
      throw err;
    }
    return data;
  }

  function renderRow(r, { canComplete }) {
    const total =
      Number(r.quoted_total || 0) + Number(r.residential_charge || 0) + Number(r.american_card_charge || 0);

    const store = r.store_name ? ` · ${escapeHtml(r.store_name)}` : "";
    const cls = statusClass(r.status);
    return `
      <div class="req-row" data-id="${r.id}">
        <div class="req-main">
          <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
            <div class="req-code">${escapeHtml(r.code)}</div>
            <div>${statusPill(r.status)}</div>
          </div>
          <div class="req-meta">
            ${escapeHtml(r.client_name)} <span class="muted">(${escapeHtml(r.client_code)})</span>${store}
            · <b>Total:</b> ${money(total)}
            · <span class="muted">${escapeHtml(formatDate(r.updated_at))}</span>
          </div>
        </div>
        <div class="req-actions">
          <a class="btn small" href="../detalle-solicitud/detalle-solicitud.html?id=${encodeURIComponent(r.id)}">Ver</a>
          ${
            canComplete
              ? `<button class="btn small primary markDoneBtn" type="button" data-id="${r.id}">Compra realizada</button>`
              : ""
          }
        </div>
        <div class="status-end ${cls}"></div>
      </div>
    `;
  }

  function setCount(id, n) {
    const el = $(id);
    if (el) el.textContent = String(n ?? 0);
  }

  async function loadList() {
    const all = await window.PGT.api.listRequests({ page: 1, pageSize: 100 });
    const items = all.items || [];

    const countPending = items.filter((r) => r.status === "Borrador" || r.status === "Pendiente comprobante").length;
    const countSent = items.filter((r) => r.status === "Enviada al Supervisor").length;
    const countDone = items.filter((r) => r.status === "Compra realizada" || r.status === "Completada").length;

    setCount("#countPending", countPending);
    setCount("#countSent", countSent);
    setCount("#countDone", countDone);

    const list = $("#comprasList");
    if (list) {
      list.innerHTML = items.length
        ? items
            .map((r) =>
              renderRow(r, {
                canComplete: r.status === "Enviada al Supervisor",
              }),
            )
            .join("")
        : '<div class="hint">No hay solicitudes.</div>';
    }

    wireActions();
  }

  function wireActions() {
    document.querySelectorAll(".markDoneBtn").forEach((btn) => {
      btn.addEventListener("click", async () => {
        const id = btn.getAttribute("data-id");
        const note = prompt("Nota (opcional):", "") || "";

        const token = getComprasToken();
        if (!token) {
          alert("Activa Modo compras (token) para marcar como Compra realizada.");
          return;
        }

        try {
          btn.disabled = true;
          await requestJson(`/api/purchase-requests/${encodeURIComponent(id)}/status`, {
            method: "PATCH",
            body: { status: "Compra realizada", note },
            headers: { "X-Compras-Token": token },
          });
          await loadColumns();
        } catch (e) {
          alert(e.message);
        } finally {
          btn.disabled = false;
        }
      });
    });
  }

  function wireTokenButton() {
    const btn = $("#setComprasTokenBtn");
    if (!btn) return;

    const render = () => {
      const t = getComprasToken();
      btn.textContent = t ? "Modo compras: activo" : "Modo compras";
      btn.classList.toggle("primary", Boolean(t));
    };

    render();

    btn.addEventListener("click", () => {
      const current = getComprasToken();
      const next = prompt("Token del depto de Compras (vacío para desactivar):", current) ?? current;
      setComprasToken(next);
      render();
    });
  }

  async function init() {
    wireTokenButton();
    await loadList();
  }

  document.addEventListener("DOMContentLoaded", () => {
    init().catch((e) => {
      const root = $("#comprasList");
      if (root) root.innerHTML = `<div class="hint">Error: ${escapeHtml(e.message)}</div>`;
    });
  });
})();
