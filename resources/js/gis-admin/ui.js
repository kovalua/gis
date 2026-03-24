export function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

export function badge(value, type = 'secondary') {
    return `<span class="badge text-bg-${type}">${escapeHtml(value)}</span>`;
}

export function codeBlock(data) {
    return `<pre class="gis-json bg-dark text-light rounded p-3 mb-0">${escapeHtml(JSON.stringify(data, null, 2))}</pre>`;
}

export function pageHeader({ title, subtitle = '', actions = '' }) {
    return `
        <div class="gis-page-header">
            <div>
                <h1 class="h3 mb-1">${escapeHtml(title)}</h1>
                <div class="text-secondary">${escapeHtml(subtitle)}</div>
            </div>
            <div class="d-flex gap-2 flex-wrap">${actions}</div>
        </div>
    `;
}

export function card(title, body, extraClass = '') {
    return `
        <div class="card gis-card ${extraClass}">
            <div class="card-body">
                ${title ? `<h2 class="h5 mb-3">${escapeHtml(title)}</h2>` : ''}
                ${body}
            </div>
        </div>
    `;
}

export function dataTable(columns, rows, actions = null) {
    const head = columns.map(c => `<th>${escapeHtml(c.label)}</th>`).join('');
    const actionHead = actions ? '<th class="text-end">Actions</th>' : '';

    const body = rows.map(row => {
        const cells = columns.map(c => `<td>${c.render ? c.render(row) : escapeHtml(row[c.key])}</td>`).join('');
        const actionCell = actions ? `<td class="text-end text-nowrap">${actions(row)}</td>` : '';
        return `<tr>${cells}${actionCell}</tr>`;
    }).join('');

    return `
        <div class="gis-table-wrap">
            <table class="table table-hover align-middle mb-0">
                <thead><tr>${head}${actionHead}</tr></thead>
                <tbody>${body || '<tr><td colspan="99" class="text-center text-secondary py-4">No data</td></tr>'}</tbody>
            </table>
        </div>
    `;
}

export function statCard(title, value, note = '') {
    return `
        <div class="card gis-stat-card h-100">
            <div class="card-body">
                <div class="text-secondary small text-uppercase mb-2">${escapeHtml(title)}</div>
                <div class="display-6 fw-semibold">${escapeHtml(value)}</div>
                ${note ? `<div class="text-secondary mt-2">${escapeHtml(note)}</div>` : ''}
            </div>
        </div>
    `;
}

export function toast(message, type = 'success') {
    const host = document.getElementById('gis-toast-host');
    if (!host) return;

    const el = document.createElement('div');
    el.className = `toast align-items-center text-bg-${type} border-0 show`;
    el.role = 'alert';
    el.innerHTML = `<div class="d-flex"><div class="toast-body">${escapeHtml(message)}</div><button type="button" class="btn-close btn-close-white me-2 m-auto"></button></div>`;
    host.appendChild(el);

    el.querySelector('.btn-close')?.addEventListener('click', () => el.remove());
    setTimeout(() => el.remove(), 3500);
}