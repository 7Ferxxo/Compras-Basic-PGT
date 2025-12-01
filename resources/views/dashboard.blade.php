<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Recibos </title>
    <link rel="icon" type="image/png" href="/imagenes/logo.png">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="dashboard-style.css">
</head>
<body>
    <div class="container">
        <h1>Recibos Generados</h1>
        <a href="/" class="btn-volver">← Volver a crear recibo</a>
        
        <div class="search-container">
            <input type="text" id="search-input" placeholder="Buscar por cliente, casillero, etc...">
        </div>

        <table id="recibos-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Cliente</th>
                    <th>Casillero</th>
                    <th>Monto</th>
                    <th>Fecha</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
    </div>
    <script src="dashboard.js"></script>
</body>
</html>