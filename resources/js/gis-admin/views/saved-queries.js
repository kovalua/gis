import { pageHeader, card, dataTable } from '../ui';

export async function renderSavedQueries(ctx) {
    const res = await ctx.api.get('/saved-queries');
    const rows = res?.data || [];

    ctx.mount(`
        ${pageHeader({
            title: 'Saved Queries',
            subtitle: 'Збережені запити та пресети'
        })}
        ${card('', dataTable(
            [
                { key: 'id', label: 'ID' },
                { key: 'name', label: 'Name' },
                { key: 'layer_id', label: 'Layer ID' },
                { key: 'is_public', label: 'Public' },
            ],
            rows,
            row => `<button class="btn btn-sm btn-outline-secondary inspect-btn" data-id="${row.id}">JSON</button>`
        ))}
    `);

    document.querySelectorAll('.inspect-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const row = rows.find(x => String(x.id) === btn.dataset.id);
            ctx.showJsonModal(`Saved Query #${btn.dataset.id}`, row);
        });
    });
}