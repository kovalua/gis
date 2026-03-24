import { pageHeader, card, dataTable } from '../ui';

export async function renderDataSources(ctx) {
    const res = await ctx.api.get('/data-sources');
    const rows = res?.data || [];

    ctx.mount(`
        ${pageHeader({
            title: 'Data Sources',
            subtitle: 'Джерела даних PostGIS та інші підключення'
        })}
        ${card('', dataTable(
            [
                { key: 'id', label: 'ID' },
                { key: 'name', label: 'Name' },
                { key: 'driver', label: 'Driver' },
                { key: 'host', label: 'Host' },
                { key: 'database', label: 'Database' },
            ],
            rows,
            row => `<button class="btn btn-sm btn-outline-secondary inspect-btn" data-id="${row.id}">JSON</button>`
        ))}
    `);

    document.querySelectorAll('.inspect-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const row = rows.find(x => String(x.id) === btn.dataset.id);
            ctx.showJsonModal(`Data Source #${btn.dataset.id}`, row);
        });
    });
}