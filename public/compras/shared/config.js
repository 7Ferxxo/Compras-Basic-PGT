window.PGT = window.PGT || {};
window.PGT.config = window.PGT.config || {};

window.PGT.config.apiBase = "";

(function () {
  function parseJson(raw) {
    try {
      const value = JSON.parse(raw);
      return value && typeof value === "object" ? value : null;
    } catch {
      return null;
    }
  }

  const fromWindow = window.PGT_CURRENT_USER && typeof window.PGT_CURRENT_USER === "object"
    ? window.PGT_CURRENT_USER
    : null;

  let fromSession = null;
  try {
    fromSession = parseJson(sessionStorage.getItem("PGT_CURRENT_USER") || "");
  } catch {}

  let fromLocal = null;
  try {
    fromLocal = parseJson(localStorage.getItem("PGT_CURRENT_USER") || "");
  } catch {}

  window.PGT.config.currentUser =
    window.PGT.config.currentUser ||
    fromWindow ||
    fromSession ||
    fromLocal ||
    null;
})();
