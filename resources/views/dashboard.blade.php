<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de recibos</title>
    <link rel="icon" type="image/png" href="/imagenes/logo.png">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="dashboard-style.css">
</head>
<body class="pgt-dashboard">
    <div class="shell">
        <header class="top">
            <div class="top-left">
                <div class="kicker">HISTORIAL</div>
                <h1>Recibos</h1>
                <div class="sub">Ultimos recibos registrados. Busca, filtra y abre el PDF en un clic.</div>
            </div>
            <div class="top-actions">
                <a href="/" class="btn ghost" id="backBtn">Volver</a>
                <button class="btn" type="button" id="refreshBtn">Actualizar</button>
            </div>
        </header>

        <section class="kpis" aria-label="Resumen">
            <div class="kpi">
                <div class="kpi-label">Total recibos</div>
                <div class="kpi-value" id="kpiCount">-</div>
            </div>
            <div class="kpi">
                <div class="kpi-label">Ultimo Basic</div>
                <div class="kpi-value" id="kpiBasicLast">-</div>
            </div>
            <div class="kpi">
                <div class="kpi-label">Ultimo</div>
                <div class="kpi-value" id="kpiLast">-</div>
            </div>
        </section>

        <section class="controls" aria-label="Filtros">
            <div class="field">
                <label for="search-input">Buscar</label>
                <input type="text" id="search-input" placeholder="Cliente, casillero, email, sucursal, metodo, monto o #..." autocomplete="off">
            </div>
            <div class="field">
                <label for="filter-sucursal">Sucursal</label>
                <select id="filter-sucursal">
                    <option value="">Todas</option>
                </select>
            </div>
            <div class="field">
                <label for="filter-metodo">Metodo</label>
                <select id="filter-metodo">
                    <option value="">Todos</option>
                </select>
            </div>
            <div class="field">
                <label for="page-size">Pagina</label>
                <select id="page-size">
                    <option value="10">10</option>
                    <option value="25" selected>25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>
            <div class="field actions">
                <label>&nbsp;</label>
                <button class="btn danger" type="button" id="clearBtn">Limpiar</button>
            </div>
        </section>

        <section class="table-card" aria-label="Tabla de recibos">
            <div class="table-meta">
                <div class="meta-left">
                    <span class="meta-pill" id="metaCount">-</span>
                    <span class="meta-pill subtle" id="metaHint">Ordenado por -</span>
                </div>
                <div class="meta-right" id="pagerTop"></div>
            </div>

            <div class="table-wrap">
                <table id="recibos-table">
                    <thead>
                        <tr>
                            <th data-sort="id">#</th>
                            <th data-sort="cliente">Cliente</th>
                            <th data-sort="casillero">Casillero</th>
                            <th data-sort="email_cliente">Email</th>
                            <th data-sort="sucursal">Sucursal</th>
                            <th data-sort="monto">Monto</th>
                            <th data-sort="fecha">Fecha</th>
                            <th data-sort="metodo_pago">Metodo</th>
                            <th>PDF</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>

                <div class="empty" id="emptyState" style="display:none;">
                    <div class="empty-title">Sin resultados</div>
                    <div class="empty-sub">Ajusta la busqueda o limpia los filtros.</div>
                </div>

                <div class="loading" id="loadingState" style="display:none;">
                    <div class="spinner" aria-hidden="true"></div>
                    Cargando recibos...
                </div>
            </div>

            <div class="footer">
                <div class="footer-left muted" id="footerSummary">-</div>
                <div class="footer-right" id="pagerBottom"></div>
            </div>
        </section>
    </div>

    <script src="dashboard.js"></script>
</body>
</html>
