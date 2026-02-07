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

  function showMessage(message) {
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
    const el = document.getElementById(id);
    return (el?.value || "").trim();
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

  function wireSearchSelect({ rootId, inputId, toggleId, panelId, searchId, optionsId, labelId, onSelect } = {}) {
    const root = document.getElementById(rootId);
    if (!root) return null;
    const input = document.getElementById(inputId);
    const toggle = document.getElementById(toggleId);
    const panel = document.getElementById(panelId);
    const search = searchId ? document.getElementById(searchId) : null;
    const options = document.getElementById(optionsId);
    const label = document.getElementById(labelId);

    function setActive(value, text) {
      const val = String(value || "");
      if (input) input.value = val;
      if (label) label.textContent = text || "Selecciona...";
      options?.querySelectorAll(".select-option").forEach((b) => {
        b.classList.toggle("active", (b.getAttribute("data-value") || "") === val);
      });
      if (onSelect) onSelect(val);
    }

    function open() {
      root.classList.add("open");
      toggle?.setAttribute("aria-expanded", "true");
      search?.focus();
    }

    function close() {
      root.classList.remove("open");
      toggle?.setAttribute("aria-expanded", "false");
      if (search) {
        search.value = "";
        filter("");
      }
    }

    function filter(term) {
      const q = String(term || "").toLowerCase();
      options?.querySelectorAll(".select-option").forEach((b) => {
        const text = (b.textContent || "").toLowerCase();
        b.style.display = text.includes(q) ? "" : "none";
      });
    }

    toggle?.addEventListener("click", () => {
      if (root.classList.contains("open")) close();
      else open();
    });

    options?.addEventListener("click", (e) => {
      const btn = e.target.closest(".select-option");
      if (!btn) return;
      const value = btn.getAttribute("data-value") || "";
      const text = btn.textContent || value;
      setActive(value, text);
      close();
    });

    search?.addEventListener("input", (e) => filter(e.target.value));

    document.addEventListener("click", (e) => {
      if (!root.contains(e.target)) close();
    });

    return { setActive, open, close };
  }

  function getReceiptMethod() {
    return (document.getElementById("rMetodoPago")?.value || "").trim();
  }

  function setReceiptPaymentMethod(method) {
    const input = document.getElementById("rMetodoPago");
    if (input) input.value = method;

    document.querySelectorAll("#rPayGrid .pay-option").forEach((el) => {
      el.classList.toggle("active", el.getAttribute("data-method") === method);
    });

    updateReceiptTotals();
  }

  function updateReceiptTotals() {
    const precio = Number(getValue("rPrecio"));
    const metodo = getReceiptMethod();

    const base = Number.isFinite(precio) ? precio : 0;
    const comision = metodo === "Yappy" ? base * 0.02 : metodo === "Tarjeta" ? base * 0.03 : 0;
    const total = base + comision;

    const comEl = document.getElementById("rComision");
    const totEl = document.getElementById("rTotal");
    if (comEl) comEl.textContent = formatBalboa(comision);
    if (totEl) totEl.textContent = formatBalboa(total);

    updateSummary();
  }

  function updateEvidenceMeta() {
    const inputScreens = document.getElementById("quoteScreenshots");
    const inputProof = document.getElementById("paymentProof");
    if (!inputScreens || !inputProof) return;

    const screenshots = inputScreens.files || [];
    const proof = inputProof.files?.[0];

    const metaScreens = document.getElementById("metaScreenshots");
    if (metaScreens) {
      metaScreens.textContent = screenshots.length
        ? `${screenshots.length} archivo(s) seleccionado(s)`
        : "Selecciona archivos";
    }

    const metaProof = document.getElementById("metaPaymentProof");
    if (metaProof) {
      metaProof.textContent = proof ? proof.name : "Selecciona archivo";
    }
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

  function wireEvidenceInputs() {
    const inputScreens = document.getElementById("quoteScreenshots");
    const inputProof = document.getElementById("paymentProof");
    if (!inputScreens || !inputProof) return;

    inputScreens.addEventListener("change", updateEvidenceMeta);
    inputProof.addEventListener("change", updateEvidenceMeta);

    let activeInputId = "quoteScreenshots";

    function setActiveZone(zone) {
      document.querySelectorAll(".dropzone").forEach((z) => z.classList.remove("active"));
      if (zone) zone.classList.add("active");
      const inputId = zone?.getAttribute?.("data-input");
      if (inputId) activeInputId = inputId;
    }

    function onPaste(e) {
      const files = filesFromClipboard(e);
      if (!files.length) return;

      const isScreens = activeInputId === "quoteScreenshots";
      const target = isScreens ? inputScreens : inputProof;
      const normalized = normalizePastedFiles(files, { allowMultiple: isScreens });
      setFilesOnInput(target, normalized, { append: isScreens });
      updateEvidenceMeta();
      e.preventDefault();
    }

    document.addEventListener("paste", onPaste);

    document.querySelectorAll(".dropzone").forEach((zone) => {
      zone.addEventListener("click", () => {
        setActiveZone(zone);
        const inputId = zone.getAttribute("data-input");
        const target = inputId === "paymentProof" ? inputProof : inputScreens;
        target.click();
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
        updateEvidenceMeta();
      });
    });

    updateEvidenceMeta();
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
    setText("sumComision", document.getElementById("rComision")?.textContent || "-");
    setText("sumTotal", document.getElementById("rTotal")?.textContent || "-");
    setText("sumLink", getValue("rLinkProducto") || "-");
    setText("sumDesc", getValue("rDescripcion") || "-");

    const screenshots = document.getElementById("quoteScreenshots")?.files || [];
    const proof = document.getElementById("paymentProof")?.files?.[0];
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

    document.getElementById("wizardBack")?.classList.toggle("is-hidden", currentStep === 1);
    document.getElementById("wizardNext")?.classList.toggle("is-hidden", currentStep === totalSteps);
    document.getElementById("rSubmitBtn")?.classList.toggle("is-hidden", currentStep !== totalSteps);

    updateSummary();
    showMessage(null);
    window.scrollTo({ top: 0, behavior: "smooth" });
  }

  function showAndFail(message) {
    showMessage(message);
    return false;
  }

  function validateStep(step) {
    if (step === 1) {
      if (!getValue("rCasillero")) return showAndFail("Casillero es obligatorio.");
      if (!getValue("rCliente")) return showAndFail("Nombre del cliente es obligatorio.");
      if (!getValue("rEmail")) return showAndFail("Email del cliente es obligatorio.");
      if (!getValue("rSucursal")) return showAndFail("Sucursal es obligatoria.");
      if (!getValue("rFecha")) return showAndFail("Fecha es obligatoria.");
      return true;
    }
    if (step === 2) {
      if (!getValue("rStoreId")) return showAndFail("Tienda es obligatoria.");
      if (getValue("rStoreId") === "7" && !getValue("rStoreCustomName")) {
        return showAndFail("Nombre de la tienda es obligatorio.");
      }
      if (!getReceiptMethod()) return showAndFail("Metodo de pago es obligatorio.");
      const precio = Number(getValue("rPrecio"));
      if (!Number.isFinite(precio) || precio <= 0) return showAndFail("Precio invalido.");
      return true;
    }
    if (step === 3) {
      const screenshots = document.getElementById("quoteScreenshots")?.files || [];
      if (!screenshots.length) return showAndFail("Debes adjuntar al menos una captura.");
      return true;
    }
    return true;
  }

  async function init() {
    document.body.classList.add("wizard-ready");
    const rFecha = document.getElementById("rFecha");
    if (rFecha && !rFecha.value) rFecha.value = todayIso();

    updateReceiptTotals();
    wireEvidenceInputs();

    wireSearchSelect({
      rootId: "rSucursalSelect",
      inputId: "rSucursal",
      toggleId: "rSucursalToggle",
      panelId: "rSucursalPanel",
      optionsId: "rSucursalOptions",
      labelId: "rSucursalLabel",
    });

    const storeCustomWrap = document.getElementById("rStoreCustomWrap");
    const storeCustomInput = document.getElementById("rStoreCustomName");
    const handleStoreSelect = (value) => {
      const isOtros = String(value) === "7";
      if (storeCustomWrap) storeCustomWrap.style.display = isOtros ? "" : "none";
      if (!isOtros && storeCustomInput) storeCustomInput.value = "";
    };
    const storeSelect = wireSearchSelect({
      rootId: "rStoreSelect",
      inputId: "rStoreId",
      toggleId: "rStoreToggle",
      panelId: "rStorePanel",
      optionsId: "rStoreOptions",
      labelId: "rStoreLabel",
      onSelect: handleStoreSelect,
    });

    const fallbackStores = [
      { id: 1, name: "WALMART" },
      { id: 2, name: "AMAZON" },
      { id: 3, name: "TEMU" },
      { id: 4, name: "SHEIN" },
      { id: 5, name: "EBAY" },
      { id: 6, name: "ALIEXPRESS" },
      { id: 7, name: "OTROS" },
    ];

    function renderStores(items) {
      const storeOptions = document.getElementById("rStoreOptions");
      if (storeOptions) {
        storeOptions.innerHTML = items
          .map((s) => {
            const name = String(s.name || "").toUpperCase();
            return `<button type="button" class="select-option" data-value="${s.id}">${escapeHtml(name)}</button>`;
          })
          .join("");
      }

      if (items.length) {
        const first = items[0];
        const firstName = String(first.name || "").toUpperCase();
        if (storeSelect) {
          storeSelect.setActive(String(first.id), firstName);
        } else {
          const input = document.getElementById("rStoreId");
          const label = document.getElementById("rStoreLabel");
          if (input) input.value = String(first.id);
          if (label) label.textContent = firstName || "Selecciona...";
          handleStoreSelect(String(first.id));
        }
      }
    }

    try {
      const data = await window.PGT.api.listStores();
      const items = (data.items || []).filter((s) => s && s.id);
      renderStores(items.length ? items : fallbackStores);
    } catch (e) {
      renderStores(fallbackStores);
    }

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

    document.getElementById("rPrecio")?.addEventListener("input", updateReceiptTotals);
    document.getElementById("rPrecio")?.addEventListener("change", updateReceiptTotals);

    const rCasillero = document.getElementById("rCasillero");
    let lookupTimer = null;
    let lastLookupCasillero = "";
    let abortController = null;

    async function lookupCliente() {
      const cas = (rCasillero?.value || "").trim();
      if (!cas) {
        lastLookupCasillero = "";
        const el = document.getElementById("rCasilleroStatus");
        if (el) el.textContent = "";
        return;
      }
      if (cas === lastLookupCasillero) return;
      lastLookupCasillero = cas;

      if (abortController) abortController.abort();
      abortController = new AbortController();
      const apiBase = window.PGT?.api?.API_BASE || "";

      const snapshotCliente = document.getElementById("rCliente")?.value ?? "";
      const snapshotEmail = document.getElementById("rEmail")?.value ?? "";

      const statusEl = document.getElementById("rCasilleroStatus");
      if (statusEl) statusEl.textContent = "Buscando cliente...";
      try {
        const res = await fetch(`${apiBase}/api/cliente/${encodeURIComponent(cas)}`, {
          signal: abortController.signal,
        });
        if (res.ok) {
          const data = await res.json();
          if (document.getElementById("rCliente")?.value === snapshotCliente) {
            document.getElementById("rCliente").value = data.cliente ?? "";
          }
          if (document.getElementById("rEmail")?.value === snapshotEmail) {
            document.getElementById("rEmail").value = data.email_cliente ?? "";
          }
          if (statusEl) statusEl.textContent = "Cliente encontrado.";
          return;
        }
        if (res.status === 404) {
          if (statusEl) statusEl.textContent = "Casillero no encontrado: completa nombre y email.";
          return;
        }
        if (statusEl) statusEl.textContent = "No se pudo consultar el cliente.";
      } catch (e) {
        if (e?.name === "AbortError") return;
        if (statusEl) statusEl.textContent = "Error consultando el cliente.";
      }
    }

    rCasillero?.addEventListener("input", () => {
      if (lookupTimer) clearTimeout(lookupTimer);
      lookupTimer = setTimeout(lookupCliente, 400);
    });
    rCasillero?.addEventListener("blur", lookupCliente);

    document.getElementById("wizardBack")?.addEventListener("click", () => setStep(currentStep - 1));
    document.getElementById("wizardNext")?.addEventListener("click", () => {
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

    document.getElementById("receiptForm")?.addEventListener("submit", async (e) => {
      e.preventDefault();
      showMessage(null);

      if (!validateStep(1) || !validateStep(2) || !validateStep(3)) return;

      const cliente = getValue("rCliente");
      const casillero = getValue("rCasillero");
      const email = getValue("rEmail");
      const sucursal = getValue("rSucursal");
      const fecha = getValue("rFecha");
      const metodo_pago = getReceiptMethod();
      const link_producto = getValue("rLinkProducto");
      const descripcion = getValue("rDescripcion");
      const precio = Number(getValue("rPrecio"));
      const storeId = getValue("rStoreId");
      const storeCustomName = getValue("rStoreCustomName");

      const screenshots = document.getElementById("quoteScreenshots")?.files || [];
      const proof = document.getElementById("paymentProof")?.files?.[0];

      const submitBtn = document.getElementById("rSubmitBtn");
      if (submitBtn) submitBtn.disabled = true;
      try {
        const noteLines = [];
        if (descripcion) noteLines.push(descripcion);
        if (sucursal) noteLines.push(`Sucursal: ${sucursal}`);
        if (fecha) noteLines.push(`Fecha: ${fecha}`);
        const notes = noteLines.join("\n");

        const fd = new FormData();
        fd.set("clientName", cliente);
        fd.set("clientCode", casillero);
        fd.set("accountEmail", email);
        fd.set("contactChannel", "Formulario");
        fd.set("paymentMethod", metodo_pago);
        fd.set("itemLink", link_producto);
        fd.set("itemOptions", descripcion || "");
        fd.set("quotedTotal", String(precio));
        fd.set("itemQuantity", "1");
        if (storeId) fd.set("storeId", storeId);
        if (storeId === "7" && storeCustomName) fd.set("storeCustomName", storeCustomName);
        if (notes) fd.set("notes", notes);

        Array.from(screenshots).forEach((f) => fd.append("quoteScreenshots", f));
        if (proof) fd.append("paymentProof", proof);

        const data = await window.PGT.api.createRequest(fd);
        showMessage(`Solicitud creada: ${data.code}`);
        if (data?.id) {
          setTimeout(() => {
            window.location.href = "../solicitudes-compra/solicitudes-compra.html";
          }, 700);
        }
      } catch (err) {
        showMessage(err?.message || "Error creando solicitud.");
      } finally {
        if (submitBtn) submitBtn.disabled = false;
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
    init().catch((e) => showMessage(`Error inicializando: ${e.message}`));
  });
})();
