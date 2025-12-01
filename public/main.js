document.addEventListener('DOMContentLoaded', () => {
    const facturaForm = document.querySelector('#facturaForm');
    const itbmsElem = document.getElementById('itbms');
    const totalElem = document.getElementById('total');
    const fechaInput = document.getElementById('fecha');
    const casilleroInput = document.getElementById('casillero');
    const precioInput = document.getElementById('item-precio');
    const metodoPagoInput = document.getElementById('metodo_pago');

    casilleroInput.addEventListener('blur', async () => {
        const casillero = casilleroInput.value.trim();
        if (casillero) {
            try {
                const response = await fetch(`/api/cliente/${casillero}`);
                if (response.ok) {
                    const data = await response.json();
                    document.getElementById('cliente').value = data.cliente;
                    document.getElementById('email_cliente').value = data.email_cliente;
                } else if (response.status === 404) {
                    console.log('Cliente no encontrado para ese casillero, se puede registrar como nuevo.');
                }
            } catch (error) {
                console.error('Error al buscar cliente:', error);
            }
        }
    });

    if (fechaInput && !fechaInput.value) {
        const hoy = new Date();
        fechaInput.value = hoy.toISOString().split('T')[0];
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

        if (!descripcion || isNaN(precio) || precio <= 0) {
            alert('Debe ingresar una descripción y un precio válido.');
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
            itbms: comision,
            total: total,
            items: [{
                descripcion: descripcion,
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
        })
        .catch((error) => {
            console.error('Error:', error);
            alert('Hubo un error al enviar el recibo.');
        })
        .finally(() => {
            botonEnviar.disabled = false;
            botonEnviar.textContent = 'Generar y Enviar Recibo';
        });
    };

    facturaForm.addEventListener('submit', manejarEnvioFactura);
});
