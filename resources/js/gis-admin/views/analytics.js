import { pageHeader, card, dataTable } from '../ui';

export async function renderAnalytics(ctx) {
    const res = await ctx.api.get('/analytics-executions');
    const rows = res?.data || [];

    ctx.mount(`
        ${pageHeader({
            title: 'Analytics',
            subtitle: 'Виконання аналітичних сценаріїв'
        })}
        ${card('', dataTable(
            [
                { key: 'id', label: 'ID' },
                { key: 'type', label: 'Type' },
                { key: 'status', label: 'Status' },
                { key: 'created_at', label: 'Created At' },
            ],
            rows,
            row => `<button class="btn btn-sm btn-outline-secondary inspect-btn" data-id="${row.id}">JSON</button>`
        ))}
    `);

    document.querySelectorAll('.inspect-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const row = rows.find(x => String(x.id) === btn.dataset.id);
            ctx.showJsonModal(`Analytics #${btn.dataset.id}`, row);
        });
    });
}