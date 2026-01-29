document.addEventListener('DOMContentLoaded', () => {
    const tbody = document.querySelector('#recibos-table tbody');
    const searchInput = document.getElementById('search-input');
    let recibos = [];

    const lower = (value) => String(value ?? '').toLowerCase();

    const renderTable = (data) => {
        tbody.innerHTML = '';
        data.forEach(recibo => {
            const fecha = new Date(recibo.fecha).toLocaleDateString('es-PA');
            const monto = Number(recibo.monto);
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${recibo.id}</td>
                <td>${recibo.cliente}</td>
                <td>${recibo.casillero}</td>
                <td>${recibo.email_cliente ?? ''}</td>
                <td>${recibo.sucursal ?? ''}</td>
                <td>B/.${Number.isFinite(monto) ? monto.toFixed(2) : ''}</td>
                <td>${fecha}</td>
                <td>${recibo.metodo_pago ?? ''}</td>
                <td>${recibo.id ? `<a href="/recibos/${recibo.id}/pdf" target="_blank" class="btn-pdf">Ver PDF</a>` : '-'}</td>
            `;
            tbody.appendChild(row);
        });
    };

    fetch('/get-recibos')
        .then(res => res.json())
        .then(data => {
            recibos = data;
            renderTable(recibos);
        })
        .catch(error => {
            console.error('Error al cargar los recibos:', error);
            tbody.innerHTML = '<tr><td colspan="9">No se pudieron cargar los recibos.</td></tr>';
        });

    searchInput.addEventListener('input', (e) => {
        const searchTerm = e.target.value.toLowerCase();
        const filteredRecibos = recibos.filter(recibo => {
            return (
                lower(recibo.cliente).includes(searchTerm) ||
                lower(recibo.casillero).includes(searchTerm) ||
                lower(recibo.email_cliente).includes(searchTerm) ||
                lower(recibo.sucursal).includes(searchTerm) ||
                lower(recibo.metodo_pago).includes(searchTerm) ||
                lower(recibo.monto).includes(searchTerm) ||
                String(recibo.id ?? '').includes(searchTerm)
            );
        });
        renderTable(filteredRecibos);
    });
});
