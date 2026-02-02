document.addEventListener('DOMContentLoaded', () => {
    const facturaForm = document.querySelector('#facturaForm');
    const itbmsElem = document.getElementById('itbms');
    const totalElem = document.getElementById('total');
    const fechaInput = document.getElementById('fecha');
    const casilleroInput = document.getElementById('casillero');
    const casilleroStatus = document.getElementById('casillero-status');
    const clienteInput = document.getElementById('cliente');
    const emailInput = document.getElementById('email_cliente');
    const precioInput = document.getElementById('item-precio');
    const metodoPagoInput = document.getElementById('metodo_pago');
    const tipoServicioInput = document.getElementById('tipo_servicio');
    const linkProductoGroup = document.getElementById('link-producto-group');
    const linkProductoInput = document.getElementById('link_producto');

    const actualizarLinkProducto = () => {
        const isBasic = (tipoServicioInput?.value || '').toUpperCase() === 'BASIC';
        if (linkProductoGroup) linkProductoGroup.style.display = isBasic ? '' : 'none';
        if (linkProductoInput) {
            linkProductoInput.required = false;
            if (!isBasic) linkProductoInput.value = '';
        }
    };

    let lookupTimer = null;
    let lastLookupCasillero = '';
    let abortController = null;

    const setCasilleroStatus = (text, kind) => {
        if (!casilleroStatus) return;
        casilleroStatus.textContent = text || '';
        casilleroStatus.className = `field-hint${kind ? ` is-${kind}` : ''}`;
    };

    const lookupClienteByCasillero = async () => {
        const casillero = (casilleroInput?.value || '').trim();
        if (!casillero) {
            lastLookupCasillero = '';
            setCasilleroStatus('', '');
            return;
        }

        if (casillero === lastLookupCasillero) return;
        lastLookupCasillero = casillero;

        if (abortController) abortController.abort();
        abortController = new AbortController();

        const snapshotCliente = clienteInput?.value ?? '';
        const snapshotEmail = emailInput?.value ?? '';

        setCasilleroStatus('Buscando cliente…', '');

        try {
            const response = await fetch(`/api/cliente/${encodeURIComponent(casillero)}`, {
                signal: abortController.signal,
            });

            if (response.ok) {
                const data = await response.json();

                if (clienteInput && clienteInput.value === snapshotCliente) clienteInput.value = data.cliente ?? '';
                if (emailInput && emailInput.value === snapshotEmail) emailInput.value = data.email_cliente ?? '';

                const source = String(data.source || '').toLowerCase();
                const sourceLabel = source === 'crm' ? 'CRM' : 'historial';
                setCasilleroStatus(`Cliente encontrado (${sourceLabel}).`, 'success');
                return;
            }

            if (response.status === 404) {
                setCasilleroStatus('Casillero no encontrado: completa nombre y email.', 'warning');
                return;
            }

            setCasilleroStatus('No se pudo consultar el cliente. Completa manualmente.', 'error');
        } catch (error) {
            if (error?.name === 'AbortError') return;
            console.error('Error al buscar cliente:', error);
            setCasilleroStatus('Error consultando el cliente. Completa manualmente.', 'error');
        }
    };

    casilleroInput?.addEventListener('input', () => {
        if (lookupTimer) clearTimeout(lookupTimer);
        lookupTimer = setTimeout(lookupClienteByCasillero, 400);
    });
    casilleroInput?.addEventListener('blur', lookupClienteByCasillero);

    if (fechaInput && !fechaInput.value) {
        const hoy = new Date();
        fechaInput.value = hoy.toISOString().split('T')[0];
    }

    if (tipoServicioInput) {
        tipoServicioInput.addEventListener('change', actualizarLinkProducto);
        actualizarLinkProducto();
    }

    const formatearMoneda = (valor) => {
        return `B/.${valor.toFixed(2)}`;
    };

    const actualizarTotales = () => {
        const precio = parseFloat(precioInput.value) || 0;
        const metodo_pago = metodoPagoInput.value;
        let comision = 0;

        if (metodo_pago === 'Yappy') {
            comision = precio * 0.02;
        } else if (metodo_pago === 'Tarjeta') {
            comision = precio * 0.03;
        }

        const total = precio + comision;

        itbmsElem.textContent = formatearMoneda(comision);
        totalElem.textContent = formatearMoneda(total);
    };

    precioInput.addEventListener('input', actualizarTotales);
    metodoPagoInput.addEventListener('change', actualizarTotales);

    const manejarEnvioFactura = (evento) => {
        evento.preventDefault();
        const botonEnviar = facturaForm.querySelector('.btn-submit');
        const precio = parseFloat(precioInput.value);
        const descripcion = document.getElementById('item-descripcion').value.trim();
        const tipoServicio = (tipoServicioInput?.value || '').toUpperCase() || 'OTRO';
        const linkProducto = (linkProductoInput?.value || '').trim();

        if (isNaN(precio) || precio <= 0) {
            alert('Debe ingresar un precio válido.');
            return;
        }

        botonEnviar.disabled = true;
        botonEnviar.textContent = 'Enviando...';

        const comision = parseFloat(itbmsElem.textContent.replace('B/.', ''));
        const total = parseFloat(totalElem.textContent.replace('B/.', ''));

        const datosRecibo = {
            cliente: document.querySelector('#cliente').value,
            casillero: document.querySelector('#casillero').value,
            email_cliente: document.querySelector('#email_cliente').value,
            sucursal: document.querySelector('#sucursal').value,
            fecha: fechaInput.value,
            metodo_pago: metodoPagoInput.value,
            tipo_servicio: tipoServicio,
            link_producto: linkProducto || null,
            itbms: comision,
            total: total,
            items: [{
                descripcion: descripcion || null,
                precio: precio,
                total: precio
            }]
        };

        console.log('Enviando al backend:', JSON.stringify(datosRecibo, null, 2));
        
        fetch('/crear-factura', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(datosRecibo)
        })
        .then(response => response.json())
        .then(data => {
            alert('Recibo de compra enviado con éxito!');
            facturaForm.reset();
            actualizarTotales();
            fechaInput.value = new Date().toISOString().split('T')[0];

            // Al terminar el facturador (etapa 1), enviar al modulo de Solicitudes de compra.
            const q = encodeURIComponent(datosRecibo.casillero || '');
            window.location.href = '/compras/pages/solicitudes-compra/solicitudes-compra.html' + (q ? ('?q=' + q) : '');
        })
        .catch((error) => {
            console.error('Error:', error);
            alert('Hubo un error al enviar el recibo.');
        })
        .finally(() => {
            botonEnviar.disabled = false;
            botonEnviar.textContent = 'Generar Recibo';
        });
    };

    facturaForm.addEventListener('submit', manejarEnvioFactura);
});
