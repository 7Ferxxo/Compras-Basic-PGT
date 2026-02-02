(function () {
  const API_BASE =
    location.protocol === "http:" || location.protocol === "https:" ? "" : "http://localhost:8000";

  async function requestJson(path, { method = "GET", body, headers } = {}) {
    const res = await fetch(API_BASE + path, {
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
      const msg =
        data?.errors?.message?.[0] ||
        data?.message ||
        data?.error ||
        `HTTP ${res.status}`;
      const err = new Error(msg);
      err.status = res.status;
      throw err;
    }

    if (data && typeof data === "object" && "success" in data) {
      if (!data.success) {
        const msg = data?.errors?.message?.[0] || data?.message || "Error";
        const err = new Error(msg);
        err.status = res.status;
        throw err;
      }
      return data.data ?? {};
    }

    return data;
  }

  function qs(params) {
    const u = new URLSearchParams();
    Object.entries(params || {}).forEach(([k, v]) => {
      if (v === undefined || v === null || v === "") return;
      u.set(k, String(v));
    });
    const s = u.toString();
    return s ? `?${s}` : "";
  }

  window.PGT = window.PGT || {};
  window.PGT.api = {
    API_BASE,
    checkHealth: async () => {
      const res = await fetch(API_BASE + "/api/health");
      const data = await res.json().catch(() => ({}));
      return { ok: res.ok, status: res.status, data };
    },
    getStats: () => requestJson("/api/stats"),
    listStores: () => requestJson("/api/stores"),
    listRequests: (params) => requestJson("/api/purchase-requests" + qs(params)),
    getRequest: (id) => requestJson(`/api/purchase-requests/${encodeURIComponent(id)}`),
    createRequest: (formData) =>
      requestJson("/api/purchase-requests", { method: "POST", body: formData }),
    patchStatus: (id, payload) =>
      requestJson(`/api/purchase-requests/${encodeURIComponent(id)}/status`, {
        method: "PATCH",
        body: payload,
      }),
    sendToSupervisor: (id, payload) =>
      requestJson(`/api/purchase-requests/${encodeURIComponent(id)}/send`, {
        method: "POST",
        body: payload || {},
      }),
    uploadAttachment: (id, formData) =>
      requestJson(`/api/purchase-requests/${encodeURIComponent(id)}/attachments`, {
        method: "POST",
        body: formData,
      }),
  };
})();
