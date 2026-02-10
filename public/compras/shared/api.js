(function () {
  function isWeb() {
    return location.protocol === "http:" || location.protocol === "https:";
  }

  function normalizeBase(base) {
    const value = String(base || "").trim();
    if (!value) return "";
    return value.endsWith("/") ? value.slice(0, -1) : value;
  }

  function readMetaBase() {
    const meta = document.querySelector('meta[name="pgt-api-base"]');
    return meta ? meta.getAttribute("content") || "" : "";
  }

  function resolveApiBase() {
    const configured =
      window.PGT?.config?.apiBase ||
      window.PGT_API_BASE ||
      readMetaBase();

    const normalized = normalizeBase(configured);
    if (normalized) return normalized;

    return isWeb() ? "" : "http://localhost:8000";
  }

  const API_BASE = resolveApiBase();

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
      let msg =
        data?.errors?.message?.[0] ||
        data?.message ||
        data?.error ||
        "";
      if (!msg && data?.errors && typeof data.errors === "object") {
        const firstKey = Object.keys(data.errors)[0];
        const firstVal = data.errors[firstKey];
        if (Array.isArray(firstVal) && firstVal.length) msg = firstVal[0];
      }
      if (!msg) msg = `HTTP ${res.status}`;
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

  function getComprasToken() {
    try {
      return (localStorage.getItem("PGT_COMPRAS_TOKEN") || "").trim();
    } catch {
      return "";
    }
  }

  function getActorToken() {
    try {
      const fromSession = (sessionStorage.getItem("PGT_ACTOR_CONTEXT") || "").trim();
      if (fromSession) return fromSession;
    } catch {}

    try {
      const fromLocal = (localStorage.getItem("PGT_ACTOR_CONTEXT") || "").trim();
      if (fromLocal) return fromLocal;
    } catch {}

    const fromConfig = String(window.PGT?.config?.actorContext || "").trim();
    if (fromConfig) return fromConfig;

    return "";
  }

  function withComprasToken(headers) {
    const token = getComprasToken();
    if (!token) return headers || {};
    return { ...(headers || {}), "X-Compras-Token": token };
  }

  function withActorContext(headers) {
    const token = getActorToken();
    if (!token) return headers || {};
    return { ...(headers || {}), "X-Actor-Context": token };
  }

  function getInjectedActor() {
    return window.PGT?.config?.currentUser || window.PGT_CURRENT_USER || null;
  }

  function normalizeActorPayload(actor) {
    if (!actor || typeof actor !== "object") return null;
    const user_id = String(actor.user_id || actor.userId || actor.id || "").trim();
    const name = String(actor.name || actor.full_name || actor.fullName || "").trim();
    const email = String(actor.email || actor.mail || "").trim();
    if (!user_id && !name && !email) return null;
    return { user_id, name, email };
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
        headers: withActorContext(withComprasToken()),
      }),
    sendToSupervisor: (id, payload) =>
      requestJson(`/api/purchase-requests/${encodeURIComponent(id)}/send`, {
        method: "POST",
        body: payload || {},
        headers: withActorContext(withComprasToken()),
      }),
    uploadAttachment: (id, formData) =>
      requestJson(`/api/purchase-requests/${encodeURIComponent(id)}/attachments`, {
        method: "POST",
        body: formData,
        headers: withActorContext(withComprasToken()),
      }),
    issueActorContext: (actor) =>
      requestJson("/api/auth/actor-context", {
        method: "POST",
        body: actor || {},
        headers: withComprasToken(),
      }),
    getCurrentActor: () =>
      requestJson("/api/auth/me", {
        headers: withActorContext(withComprasToken()),
      }),
    setActorContextToken: (token, { persist = false } = {}) => {
      const clean = String(token || "").trim();
      try {
        if (clean) {
          sessionStorage.setItem("PGT_ACTOR_CONTEXT", clean);
          if (persist) localStorage.setItem("PGT_ACTOR_CONTEXT", clean);
        } else {
          sessionStorage.removeItem("PGT_ACTOR_CONTEXT");
          localStorage.removeItem("PGT_ACTOR_CONTEXT");
        }
      } catch {}
      return clean;
    },
    bootstrapActorContext: async (actor, { persist = false } = {}) => {
      const data = await requestJson("/api/auth/actor-context", {
        method: "POST",
        body: actor || {},
        headers: withComprasToken(),
      });
      const token = String(data?.actor_token || "").trim();
      if (token) {
        window.PGT.api.setActorContextToken(token, { persist });
      }
      return data;
    },
  };

  (async () => {
    const already = getActorToken();
    if (already) return;
    const actor = normalizeActorPayload(getInjectedActor());
    if (!actor) return;
    if (!getComprasToken()) return;
    try {
      await window.PGT.api.bootstrapActorContext(actor, { persist: true });
    } catch {}
  })();
})();
