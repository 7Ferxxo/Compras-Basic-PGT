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

  function linkifyText(s) {
    const raw = String(s ?? "");
    if (!raw) return "";
    const parts = raw.split(/(https?:\/\/[^\s<>"']+)/g);
    return parts
      .map((p) => {
        if (/^https?:\/\//i.test(p)) {
          const safe = escapeHtml(p);
          return `<a href="${safe}" target="_blank" rel="noreferrer">${safe}</a>`;
        }
        return escapeHtml(p);
      })
      .join("");
  }

  function money(n) {
    const v = Number(n || 0);
    return `B/. ${v.toFixed(2)}`;
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

  function actionLabel(action) {
    return (
      {
        created: "Creada",
        created_from_invoice: "Creada desde recibo",
        status_change: "Cambio de estado",
        send_to_supervisor: "En proceso",
        attachment_added: "Adjunto agregado",
        payment_proof_attached: "Comprobante adjunto",
      }[action] || action || "-"
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

  function formatDate(iso) {
    if (!iso) return "";
    const raw = String(iso);
    const normalized = /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/.test(raw) ? raw.replace(" ", "T") : raw;
    const d = new Date(normalized);
    if (Number.isNaN(d.getTime())) return String(iso);
    return d.toLocaleString();
  }

  function getId() {
    const u = new URL(location.href);
    return u.searchParams.get("id");
  }

  function groupAttachments(attachments) {
    const groups = { QUOTE_SCREENSHOT: [], PAYMENT_PROOF: [] };
    (attachments || []).forEach((a) => {
      if (!groups[a.type]) groups[a.type] = [];
      groups[a.type].push(a);
    });
    return groups;
  }

  function renderDetail(r) {
    $("#code").textContent = r.code;
    $("#status").innerHTML = statusPill(r.status, r.status_label);
    $("#client").textContent = `${r.client_name} (${r.client_code})`;
    if ($("#paymentMethod")) $("#paymentMethod").textContent = r.payment_method || "-";
    $("#store").textContent = r.store_name;
    if ($("#accountEmail")) $("#accountEmail").textContent = r.account_email || "-";
    const itemLink = String(r.item_link || "").trim();
    $("#link").innerHTML = itemLink
      ? `<a href="${escapeHtml(itemLink)}" target="_blank" rel="noreferrer">${escapeHtml(itemLink)}</a>`
      : "-";
    const optionsEl = $("#options");
    if (optionsEl) optionsEl.textContent = r.item_options || "-";
    $("#qty").textContent = String(r.item_quantity || 1);
    $("#quotedTotal").textContent = money(r.quoted_total);
    const chargeResidential = $("#chargeResidential");
    if (chargeResidential) chargeResidential.textContent = money(r.residential_charge);
    $("#chargeCard").textContent = money(r.american_card_charge);
    $("#grandTotal").textContent = money(
      Number(r.quoted_total || 0) +
        Number(r.residential_charge || 0) +
        Number(r.american_card_charge || 0),
    );
    $("#notes").innerHTML = r.notes ? linkifyText(r.notes) : "-";

    const groups = groupAttachments(r.attachments);

    $("#attachmentsQuote").innerHTML = groups.QUOTE_SCREENSHOT.length
      ? groups.QUOTE_SCREENSHOT.map(renderAttachment).join("")
      : `<div class="muted">Sin capturas.</div>`;

    const paymentEl = $("#attachmentsPayment");
    if (paymentEl) {
      paymentEl.innerHTML = groups.PAYMENT_PROOF.length
        ? groups.PAYMENT_PROOF.map(renderAttachment).join("")
        : `<div class="muted">Sin comprobante.</div>`;
    }

    $("#logs").innerHTML = r.logs?.length
      ? `<table class="table">
          <thead><tr><th>Fecha</th><th>Accion</th><th>De</th><th>A</th><th>Nota</th></tr></thead>
          <tbody>
            ${r.logs
              .map(
                (l) => `
                  <tr>
                    <td class="muted">${formatDate(l.created_at)}</td>
                    <td>${escapeHtml(actionLabel(l.action))}</td>
                    <td>${escapeHtml(l.from_status ? statusLabel(l.from_status) : "-")}</td>
                    <td>${escapeHtml(l.to_status ? statusLabel(l.to_status) : "-")}</td>
                    <td>${escapeHtml(l.note || "-")}</td>
                  </tr>
                `,
              )
              .join("")}
          </tbody>
        </table>`
      : `<div class="muted">Sin eventos.</div>`;

    const sendBtn = $("#sendBtn");
    if (sendBtn) {
      sendBtn.disabled = ["sent_to_supervisor", "completed"].includes(r.status);
    }
  }

  function renderAttachment(a) {
    return `<div style="display:flex;gap:10px;align-items:center;justify-content:space-between;padding:10px 0;border-bottom:1px solid rgba(15,23,42,.08);">
      <div>
        <div style="font-weight:800">${escapeHtml(a.original_name)}</div>
        <div class="muted">${escapeHtml(a.type)} - ${escapeHtml(a.mime_type)} - ${Math.round(
      (a.size_bytes || 0) / 1024,
    )} KB</div>
      </div>
      <a class="btn" href="${a.url}" target="_blank" rel="noreferrer">Descargar</a>
    </div>`;
  }

  async function init() {
    const id = getId();
    if (!id) {
      $("#detailRoot").innerHTML = `<div class="hint">Falta el parametro <b>id</b>.</div>`;
      return;
    }
    try {
      const r = await window.PGT.api.getRequest(id);
      renderDetail(r);

      $("#sendBtn")?.addEventListener("click", async () => {
        const note = $("#sendNote")?.value || "";
        try {
          $("#sendBtn").disabled = true;
          const file = $("#sendPaymentProof")?.files?.[0];
          if (file) {
            const fd = new FormData();
            fd.set("note", note);
            fd.append("paymentProof", file);
            await window.PGT.api.sendToSupervisor(id, fd);
          } else {
            await window.PGT.api.sendToSupervisor(id, { note });
          }
          const updated = await window.PGT.api.getRequest(id);
          renderDetail(updated);
        } catch (e) {
          $("#sendBtn").disabled = false;
          alert(e.message);
        }
      });

    } catch (e) {
      let message = e.message;
      try {
        const health = await window.PGT.api.checkHealth();
        if (!health.ok && health.data?.code === "db_unreachable") {
          message = "No hay conexion a la base de datos. Verifica que el servidor este encendido.";
        }
      } catch {
      }
      $("#detailRoot").innerHTML = `<div class="hint">Error cargando detalle: ${escapeHtml(message)}</div>`;
    }
  }

  document.addEventListener("DOMContentLoaded", init);
})();
