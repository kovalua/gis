import { pageHeader, card, dataTable, badge } from '../ui';

export async function renderLayers(ctx) {
    const res = await ctx.api.get('/layers');
    const rows = res?.data || [];

    ctx.mount(`
        ${pageHeader({
            title: 'Layers',
            subtitle: 'Логічні GIS-шари'
        })}
        ${card('', dataTable(
            [
                { key: 'id', label: 'ID' },
                { key: 'name', label: 'Name' },
                { key: 'code', label: 'Code' },
                { key: 'table_name', label: 'Table' },
                { key: 'geometry_column', label: 'Geometry Column' },
                { key: 'is_active', label: 'Active', render: row => row.is_active ? badge('Yes', 'success') : badge('No', 'secondary') },
            ],
            rows,
            row => `<button class="btn btn-sm btn-outline-secondary inspect-btn" data-id="${row.id}">JSON</button>`
        ))}
    `);

    document.querySelectorAll('.inspect-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const row = rows.find(x => String(x.id) === btn.dataset.id);
            ctx.showJsonModal(`Layer #${btn.dataset.id}`, row);
        });
    });
}