document.addEventListener('DOMContentLoaded', () => {
    const tbody = document.querySelector('#recibos-table tbody');
    const searchInput = document.getElementById('search-input');
    let recibos = [];

    const renderTable = (data) => {
        tbody.innerHTML = '';
        data.forEach(recibo => {
            const fecha = new Date(recibo.fecha).toLocaleDateString('es-PA');
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${recibo.id}</td>
                <td>${recibo.cliente}</td>
                <td>${recibo.casillero}</td>
                <td>B/.${parseFloat(recibo.monto).toFixed(2)}</td>
                <td>${fecha}</td>
                <td>
                    ${recibo.pdf_filename ? `<a href="/facturas_pdf/${recibo.pdf_filename}" target="_blank" class="btn-pdf">Ver PDF</a>` : 'No disponible'}
                </td>
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
            tbody.innerHTML = '<tr><td colspan="6">No se pudieron cargar los recibos.</td></tr>';
        });

    searchInput.addEventListener('input', (e) => {
        const searchTerm = e.target.value.toLowerCase();
        const filteredRecibos = recibos.filter(recibo => {
            return (
                recibo.cliente.toLowerCase().includes(searchTerm) ||
                recibo.casillero.toLowerCase().includes(searchTerm) ||
                recibo.id.toString().includes(searchTerm)
            );
        });
        renderTable(filteredRecibos);
    });
});