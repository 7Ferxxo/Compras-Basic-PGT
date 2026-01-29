(function () {
  function isWeb() {
    return location.protocol === "http:" || location.protocol === "https:";
  }

  function basePrefix() {
    return isWeb() ? "/compras/" : "../../";
  }

  function getActivePage() {
    const fromAttr = document.body?.getAttribute("data-page");
    if (fromAttr) return fromAttr;
    const p = location.pathname.replaceAll("\\", "/");
    if (p.includes("/pages/panel-compras/")) return "panel-compras";
    if (p.includes("/pages/solicitudes/")) return "solicitudes";
    if (p.includes("/pages/compras/")) return "compras";
    if (p.includes("/pages/nueva-solicitud/")) return "nueva-solicitud";
    if (p.includes("/pages/detalle-solicitud/")) return "detalle-solicitud";
    return "";
  }

  function sidebarHtml({ active }) {
    const base = basePrefix();
    const assetLogo = `${base}assets/logo-pgt.png`;

    const panelHref = `${base}pages/panel-compras/panel-compras.html`;
    const solicitudesHref = `${base}pages/solicitudes/solicitudes.html`;
    const comprasHref = `${base}pages/compras/compras.html`;
    const nuevaHref = `${base}pages/nueva-solicitud/nueva-solicitud.html`;

    return `
      <div class="brand">
        <img src="${assetLogo}" alt="PGT" />
        <div class="name">
          <span>Solicitud de compras</span>
        </div>
      </div>

      <nav class="nav">
        <a class="${active === "panel-compras" ? "active" : ""}" href="${panelHref}">
          <span class="ico" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none">
              <path
                d="M4 11.5 12 5l8 6.5V20a1 1 0 0 1-1 1h-5v-6H10v6H5a1 1 0 0 1-1-1v-8.5Z"
                stroke="currentColor"
                stroke-width="2"
                stroke-linejoin="round"
              />
            </svg>
          </span>
          Panel de compras
        </a>

        <a class="${active === "nueva-solicitud" ? "active" : ""}" href="${nuevaHref}">
          <span class="ico" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none">
              <path
                d="M12 5v14M5 12h14"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
            </svg>
          </span>
          Nueva solicitud
        </a>

        <a class="${active === "solicitudes" ? "active" : ""}" href="${solicitudesHref}">
          <span class="ico" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none">
              <path
                d="M8 6h13M8 12h13M8 18h13M3 6h1M3 12h1M3 18h1"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
            </svg>
          </span>
          Solicitudes
        </a>

        <a class="${active === "compras" ? "active" : ""}" href="${comprasHref}">
          <span class="ico" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none">
              <path
                d="M6 7h15l-1.5 10a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 4H3"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
              <path d="M9 22a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" fill="currentColor" />
              <path d="M18 22a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" fill="currentColor" />
            </svg>
          </span>
          Compras
        </a>
      </nav>
    `;
  }

  function mountSidebar() {
    const sidebar = document.querySelector(".sidebar");
    if (!sidebar) return;
    const active = getActivePage();
    sidebar.innerHTML = sidebarHtml({ active });
  }

  document.addEventListener("DOMContentLoaded", mountSidebar);
})();
