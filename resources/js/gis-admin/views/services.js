import { pageHeader, card, dataTable } from '../ui';

export async function renderServices(ctx) {
    const res = await ctx.api.get('/services');
    const rows = res?.data || [];

    ctx.mount(`
        ${pageHeader({
            title: 'Services',
            subtitle: 'Публікація та статус GIS сервісів'
        })}
        ${card('', dataTable(
            [
                { key: 'id', label: 'ID' },
                { key: 'name', label: 'Name' },
                { key: 'code', label: 'Code' },
                { key: 'type', label: 'Type' },
                { key: 'status', label: 'Status' },
            ],
            rows,
            row => `<button class="btn btn-sm btn-outline-secondary inspect-btn" data-id="${row.id}">JSON</button>`
        ))}
    `);

    document.querySelectorAll('.inspect-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const row = rows.find(x => String(x.id) === btn.dataset.id);
            ctx.showJsonModal(`Service #${btn.dataset.id}`, row);
        });
    });
}