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
<body>
    <div class="container">
        <div class="header-row">
            <h1>Historial de recibos</h1>
            <div class="header-subtitle">Últimos recibos registrados.</div>
        </div>
        <a href="/" class="btn-volver">← Volver a crear recibo</a>
        
        <div class="search-container">
            <input type="text" id="search-input" placeholder="Buscar por cliente, casillero, etc...">
        </div>

        <div class="table-wrapper">
        <table id="recibos-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Cliente</th>
                    <th>Casillero</th>
                    <th>Email</th>
                    <th>Sucursal</th>
                    <th>Monto</th>
                    <th>Fecha</th>
                    <th>Método</th>
                    <th>PDF</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
        </div>
    </div>
    <script src="dashboard.js"></script>
</body>
</html>
