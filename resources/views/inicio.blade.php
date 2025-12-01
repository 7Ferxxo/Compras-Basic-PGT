<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Facturador PGT</title>
    <link rel="icon" type="image/png" href="/imagenes/logo.png">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <main class="invoice-container">
        <form id="facturaForm">
            <header class="form-header">
                <img src="/imagenes/logo.png" alt="Logo de la Empresa" class="logo">
                <h1>Nuevo Recibo de Compra</h1>
            </header>

            <section class="customer-data">
                <h2>Datos del Cliente</h2>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="casillero">Número de Casillero:</label>
                        <input type="text" id="casillero" name="casillero" required>
                    </div>
                    <div class="form-group">
                        <label for="cliente">Recibimos de:</label>
                        <input type="text" id="cliente" name="cliente" required>
                    </div>
                    <div class="form-group">
                        <label for="email_cliente">Email del Cliente:</label>
                        <input type="email" id="email_cliente" name="email_cliente" required>
                    </div>
                </div>
            </section>

            <section class="invoice-details">
                <h2>Detalles del Recibo</h2>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="sucursal">Sucursal:</label>
                        <select id="sucursal" name="sucursal" required>
                            <option value="">-- Seleccione una sucursal --</option>
                            <option value="DAV">DAV</option>
                            <option value="WEST">WEST</option>
                            <option value="BGA">BGA</option>
                            <option value="TA">TA</option>
                            <option value="SF">SF</option>
                            <option value="CP">CP</option>
                            <option value="EAST">EAST</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="fecha">Fecha:</label>
                        <input type="date" id="fecha" name="fecha" required>
                    </div>
                     <div class="form-group">
                        <label for="metodo_pago">Método de Pago:</label>
                        <select id="metodo_pago" name="metodo_pago" required>
                            <option value="Efectivo">Efectivo</option>                        
                            <option value="Tarjeta">Tarjeta</option>
                            <option value="Transferencia">Transferencia</option>
                            <option value="Yappy">Yappy</option>
                        </select>
                    </div>
                </div>
            </section>

            <section class="invoice-items">
                <h2>Detalles de la Compra</h2>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="item-descripcion">Descripción</label>
                        <input type="text" id="item-descripcion" placeholder="Ej: Compra en Amazon">
                    </div>
                    <div class="form-group">
                        <label for="item-precio">Precio</label>
                        <input type="number" id="item-precio" step="0.01" placeholder="0.00">
                    </div>
                </div>

                <div class="totals-summary">
                    <div class="total-row">
                        <span>Comisión:</span>
                        <span id="itbms">B/.0.00</span>
                    </div>
                    <div class="total-row">
                        <span class="total-label">Total:</span>
                        <span id="total" class="total-value">B/.0.00</span>
                    </div>
                </div>
            </section>

            <button type="submit" class="btn-submit">Generar Recibo</button>
        </form>
        <div class="dashboard-link">
            <a href="dashboard.html">Ver Historial de Recibos →</a>
        </div>
    </main>
    <script src="main.js"></script>
</body>
</html>