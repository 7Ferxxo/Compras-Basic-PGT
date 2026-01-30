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

  function showReceiptError(message) {
    const el = $("#receiptError");
    if (!el) return;
    if (!message) {
      el.style.display = "none";
      el.textContent = "";
      return;
    }
    el.style.display = "block";
    el.innerHTML = escapeHtml(message);
  }

  function getValue(id) {
    return (document.getElementById(id)?.value || "").trim();
  }

  function setText(id, value) {
    const el = document.getElementById(id);
    if (el) el.textContent = value || "-";
  }

  function formatBalboa(value) {
    const n = Number(value);
    if (!Number.isFinite(n)) return "B/. 0.00";
    return `B/. ${n.toFixed(2)}`;
  }

  function filesFromClipboard(e) {
    const dt = e.clipboardData;
    if (!dt) return [];

    const out = [];
    const items = Array.from(dt.items || []);
    for (const it of items) {
      if (it.kind !== "file") continue;
      const f = it.getAsFile?.();
      if (!f) continue;
      out.push(f);
    }
    return out;
  }

  function normalizePastedFiles(files, { allowMultiple }) {
    const now = new Date();
    const ts = `${now.getFullYear()}${String(now.getMonth() + 1).padStart(2, "0")}${String(now.getDate()).padStart(2, "0")}-${String(now.getHours()).padStart(2, "0")}${String(now.getMinutes()).padStart(2, "0")}${String(now.getSeconds()).padStart(2, "0")}`;
    const renamed = files.map((f, idx) => {
      if (!(f instanceof File)) return f;
      const ext = f.type === "image/png" ? "png" : f.type === "image/jpeg" ? "jpg" : "";
      const name = ext ? `pasted-${ts}${idx ? `-${idx + 1}` : ""}.${ext}` : f.name || `pasted-${ts}${idx ? `-${idx + 1}` : ""}`;
      return new File([f], name, { type: f.type || "application/octet-stream" });
    });
    return allowMultiple ? renamed : renamed.slice(0, 1);
  }

  function setFilesOnInput(input, files, { append }) {
    const incoming = Array.from(files || []);
    if (!incoming.length) return;

    const dt = new DataTransfer();
    const current = Array.from(input.files || []);

    if (append && input.multiple) {
      current.forEach((f) => dt.items.add(f));
      incoming.forEach((f) => dt.items.add(f));
    } else {
      incoming.forEach((f) => dt.items.add(f));
    }

    input.files = dt.files;
  }

  function getReceiptMethod() {
    return ($("#rMetodoPago")?.value || "").trim();
  }

  function setReceiptPaymentMethod(method) {
    const input = $("#rMetodoPago");
    if (input) input.value = method;

    document.querySelectorAll("#rPayGrid .pay-option").forEach((el) => {
      el.classList.toggle("active", el.getAttribute("data-method") === method);
    });

    updateReceiptTotals();
  }

  function updateReceiptTotals() {
    const precio = Number(($("#rPrecio")?.value || "").trim());
    const metodo = getReceiptMethod();

    const base = Number.isFinite(precio) ? precio : 0;
    const comision = metodo === "Yappy" ? base * 0.02 : metodo === "Tarjeta" ? base * 0.03 : 0;
    const total = base + comision;

    const comEl = $("#rComision");
    const totEl = $("#rTotal");
    if (comEl) comEl.textContent = formatBalboa(comision);
    if (totEl) totEl.textContent = formatBalboa(total);

    updateSummary();
  }

  function wireEvidenceDropzones() {
    const pasteCatcher = $("#pasteCatcher");
    const inputScreens = $("#quoteScreenshots");
    const inputProof = $("#paymentProof");
    if (!inputScreens || !inputProof) return;

    let activeInputId = "quoteScreenshots";

    function setActiveZone(zone) {
      document.querySelectorAll(".dropzone").forEach((z) => z.classList.remove("active"));
      if (zone) zone.classList.add("active");
      const inputId = zone?.getAttribute?.("data-input");
      if (inputId) activeInputId = inputId;
      if (pasteCatcher) {
        pasteCatcher.value = "";
        pasteCatcher.focus();
      }
    }

    function updateMeta() {
      const screenshots = inputScreens.files || [];
      const proof = inputProof.files?.[0];

      const metaScreens = $("#metaScreenshots");
      if (metaScreens) {
        metaScreens.textContent = screenshots.length
          ? `${screenshots.length} archivo(s) seleccionado(s)`
          : "Pega (Ctrl+V) o doble click para seleccionar";
      }

      const metaProof = $("#metaPaymentProof");
      if (metaProof) {
        metaProof.textContent = proof ? proof.name : "Pega (Ctrl+V) o doble click para seleccionar";
      }
    }

    function openPickerFor(inputId) {
      const input = inputId === "paymentProof" ? inputProof : inputScreens;
      input.click();
    }

    function onPaste(e) {
      const files = filesFromClipboard(e);
      if (!files.length) return;

      const isScreens = activeInputId === "quoteScreenshots";
      const target = isScreens ? inputScreens : inputProof;
      const normalized = normalizePastedFiles(files, { allowMultiple: isScreens });
      setFilesOnInput(target, normalized, { append: isScreens });
      updateMeta();
      e.preventDefault();
    }

    document.addEventListener("paste", onPaste);

    inputScreens.addEventListener("change", updateMeta);
    inputProof.addEventListener("change", updateMeta);

    document.querySelectorAll(".dropzone").forEach((zone) => {
      zone.addEventListener("click", () => setActiveZone(zone));
      zone.addEventListener("dblclick", () => openPickerFor(zone.getAttribute("data-input")));
      zone.addEventListener("keydown", (e) => {
        if (e.key === "Enter" || e.key === " ") {
          e.preventDefault();
          openPickerFor(zone.getAttribute("data-input"));
        }
      });

      zone.addEventListener("dragover", (e) => {
        e.preventDefault();
        zone.classList.add("dragover");
        setActiveZone(zone);
      });
      zone.addEventListener("dragleave", () => zone.classList.remove("dragover"));
      zone.addEventListener("drop", (e) => {
        e.preventDefault();
        zone.classList.remove("dragover");

        const dt = e.dataTransfer;
        const dropped = Array.from(dt?.files || []).filter(Boolean);
        if (!dropped.length) return;

        const inputId = zone.getAttribute("data-input");
        const isScreens = inputId === "quoteScreenshots";
        const target = isScreens ? inputScreens : inputProof;
        setFilesOnInput(target, dropped, { append: isScreens });
        updateMeta();
      });
    });

    setActiveZone(document.querySelector('.dropzone[data-input="quoteScreenshots"]'));
    updateMeta();
  }

  function setCasStatus(text) {
    const el = $("#rCasilleroStatus");
    if (!el) return;
    el.textContent = text || "";
  }

  function todayIso() {
    const hoy = new Date();
    return hoy.toISOString().split("T")[0];
  }

  let currentStep = 1;
  const totalSteps = 4;

  function updateSummary() {
    if (currentStep !== 4) return;
    setText("sumCliente", getValue("rCliente"));
    setText("sumCasillero", getValue("rCasillero"));
    setText("sumEmail", getValue("rEmail"));
    setText("sumSucursal", getValue("rSucursal"));
    setText("sumFecha", getValue("rFecha"));
    setText("sumMetodo", getReceiptMethod() || "-");
    setText("sumPrecio", getValue("rPrecio") ? `B/. ${Number(getValue("rPrecio") || 0).toFixed(2)}` : "-");
    setText("sumComision", $("#rComision")?.textContent || "-");
    setText("sumTotal", $("#rTotal")?.textContent || "-");
    setText("sumLink", getValue("rLinkProducto") || "-");
    setText("sumDesc", getValue("rDescripcion") || "-");

    const screenshots = $("#quoteScreenshots")?.files || [];
    const proof = $("#paymentProof")?.files?.[0];
    const evidenceText = `${screenshots.length} captura(s)${proof ? " + comprobante" : ""}`;
    setText("sumEvidencias", screenshots.length || proof ? evidenceText : "Sin evidencias");
  }

  function setStep(step) {
    currentStep = Math.min(Math.max(1, step), totalSteps);
    document.querySelectorAll(".wizard-section").forEach((el) => {
      const s = Number(el.getAttribute("data-step") || 0);
      el.classList.toggle("active", s === currentStep);
    });
    document.querySelectorAll(".step-chip[data-step]").forEach((chip) => {
      const s = Number(chip.getAttribute("data-step") || 0);
      chip.classList.toggle("active", s === currentStep);
    });

    $("#wizardBack")?.classList.toggle("is-hidden", currentStep === 1);
    $("#wizardNext")?.classList.toggle("is-hidden", currentStep === totalSteps);
    $("#rSubmitBtn")?.classList.toggle("is-hidden", currentStep !== totalSteps);

    updateSummary();
    showReceiptError(null);
    window.scrollTo({ top: 0, behavior: "smooth" });
  }

  function showAndFail(message) {
    showReceiptError(message);
    return false;
  }

  function validateStep(step) {
    if (step == 1) {
      if (!getValue("rCasillero")) return showAndFail("Casillero es obligatorio.");
      if (!getValue("rCliente")) return showAndFail("Nombre del cliente es obligatorio.");
      if (!getValue("rEmail")) return showAndFail("Email del cliente es obligatorio.");
      if (!getValue("rSucursal")) return showAndFail("Sucursal es obligatoria.");
      if (!getValue("rFecha")) return showAndFail("Fecha es obligatoria.");
      return true;
    }
    if (step == 2) {
      if (!getReceiptMethod()) return showAndFail("Metodo de pago es obligatorio.");
      const precio = Number(getValue("rPrecio"));
      if (!Number.isFinite(precio) || precio <= 0) return showAndFail("Precio invalido.");
      return true;
    }
    return true;
  }

  async function init() {
    document.body.classList.add("wizard-ready");
    const rFecha = $("#rFecha");
    if (rFecha && !rFecha.value) rFecha.value = todayIso();

    updateReceiptTotals();
    wireEvidenceDropzones();

    document.querySelectorAll("#rPayGrid .pay-option").forEach((el) => {
      const method = el.getAttribute("data-method");
      const onSelect = () => setReceiptPaymentMethod(method);
      el.addEventListener("click", onSelect);
      el.addEventListener("keydown", (e) => {
        if (e.key === "Enter" || e.key === " ") {
          e.preventDefault();
          onSelect();
        }
      });
    });

    $("#rPrecio")?.addEventListener("input", updateReceiptTotals);
    $("#rPrecio")?.addEventListener("change", updateReceiptTotals);

    let lookupTimer = null;
    let lastLookupCasillero = "";
    let abortController = null;

    async function lookupCliente() {
      const cas = ($("#rCasillero")?.value || "").trim();
      if (!cas) {
        lastLookupCasillero = "";
        setCasStatus("");
        return;
      }
      if (cas === lastLookupCasillero) return;
      lastLookupCasillero = cas;

      if (abortController) abortController.abort();
      abortController = new AbortController();

      const snapshotCliente = $("#rCliente")?.value ?? "";
      const snapshotEmail = $("#rEmail")?.value ?? "";

      setCasStatus("Buscando cliente…");
      try {
        const res = await fetch(`/api/cliente/${encodeURIComponent(cas)}`, { signal: abortController.signal });
        if (res.ok) {
          const data = await res.json();
          if ($("#rCliente") && $("#rCliente").value === snapshotCliente) $("#rCliente").value = data.cliente ?? "";
          if ($("#rEmail") && $("#rEmail").value === snapshotEmail) $("#rEmail").value = data.email_cliente ?? "";
          setCasStatus("Cliente encontrado.");
          return;
        }
        if (res.status === 404) {
          setCasStatus("Casillero no encontrado: completa nombre y email.");
          return;
        }
        setCasStatus("No se pudo consultar el cliente.");
      } catch (e) {
        if (e?.name === "AbortError") return;
        setCasStatus("Error consultando el cliente.");
      }
    }

    $("#rCasillero")?.addEventListener("input", () => {
      if (lookupTimer) clearTimeout(lookupTimer);
      lookupTimer = setTimeout(lookupCliente, 400);
    });
    $("#rCasillero")?.addEventListener("blur", lookupCliente);

    $("#wizardBack")?.addEventListener("click", () => setStep(currentStep - 1));
    $("#wizardNext")?.addEventListener("click", () => {
      if (!validateStep(currentStep)) return;
      setStep(currentStep + 1);
    });
    document.querySelectorAll(".step-chip[data-step]").forEach((chip) => {
      chip.addEventListener("click", () => {
        const target = Number(chip.getAttribute("data-step") || 1);
        if (target > currentStep) {
          for (let s = currentStep; s < target; s += 1) {
            if (!validateStep(s)) return;
          }
        }
        setStep(target);
      });
    });

    $("#receiptForm")?.addEventListener("submit", async (e) => {
      e.preventDefault();
      showReceiptError(null);

      const cliente = ($("#rCliente")?.value || "").trim();
      const casillero = ($("#rCasillero")?.value || "").trim();
      const email = ($("#rEmail")?.value || "").trim();
      const sucursal = ($("#rSucursal")?.value || "").trim();
      const fecha = ($("#rFecha")?.value || "").trim();
      const metodo_pago = getReceiptMethod();
      const tipo_servicio = "BASIC";
      const link_producto = ($("#rLinkProducto")?.value || "").trim();
      const descripcion = ($("#rDescripcion")?.value || "").trim();
      const precio = Number(($("#rPrecio")?.value || "").trim());

      if (!casillero) return showReceiptError("Casillero es obligatorio.");
      if (!cliente) return showReceiptError("Nombre del cliente es obligatorio.");
      if (!email) return showReceiptError("Email del cliente es obligatorio.");
      if (!sucursal) return showReceiptError("Sucursal es obligatoria.");
      if (!fecha) return showReceiptError("Fecha es obligatoria.");
      if (!metodo_pago) return showReceiptError("Método de pago es obligatorio.");
      if (!Number.isFinite(precio) || precio <= 0) return showReceiptError("Precio inválido.");

      $("#rSubmitBtn").disabled = true;
      try {
        const payload = {
          cliente,
          casillero,
          email_cliente: email,
          sucursal,
          fecha,
          metodo_pago,
          tipo_servicio,
          link_producto: link_producto || null,
          items: [{ descripcion: descripcion || null, precio }],
        };

        const res = await fetch("/crear-factura", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(payload),
        });

        const data = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(data?.message || `HTTP ${res.status}`);

        const screenshots = $("#quoteScreenshots")?.files || [];
        const proof = $("#paymentProof")?.files?.[0];
        const reciboId = data?.id_recibo;

        if (reciboId) {
          try {
            const fd = new FormData();
            Array.from(screenshots).forEach((f) => fd.append("quoteScreenshots", f));
            if (proof) fd.append("paymentProof", proof);

            fd.set("cliente", cliente);
            fd.set("casillero", casillero);
            fd.set("metodo_pago", metodo_pago);
            fd.set("descripcion_compra", descripcion);
            fd.set("monto_pagado", String(precio));
            fd.set("link_producto", link_producto || "");

            await fetch(`/api/purchase-requests/receipt/${encodeURIComponent(reciboId)}/attachments`, {
              method: "POST",
              body: fd,
            });
          } catch {
            // No bloquea el flujo si el recibo ya se generó.
          }
        }

        showReceiptError(data?.message || "Recibo generado.");
        if (data.pdf_url) window.open(data.pdf_url, "_blank", "noopener");
      } catch (err) {
        showReceiptError(err?.message || "Error generando recibo.");
      } finally {
        $("#rSubmitBtn").disabled = false;
      }
    });

    const summaryFields = [
      "#rCliente",
      "#rCasillero",
      "#rEmail",
      "#rSucursal",
      "#rFecha",
      "#rLinkProducto",
      "#rDescripcion",
      "#rPrecio",
    ];
    summaryFields.forEach((sel) => {
      document.querySelector(sel)?.addEventListener("input", updateSummary);
      document.querySelector(sel)?.addEventListener("change", updateSummary);
    });

    setStep(1);
  }

  document.addEventListener("DOMContentLoaded", () => {
    init().catch((e) => showReceiptError(`Error inicializando: ${e.message}`));
  });
})();
