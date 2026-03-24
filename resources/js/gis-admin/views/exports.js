import { pageHeader, card, dataTable } from '../ui';

export async function renderExports(ctx) {
    const [jobs, snapshots] = await Promise.all([
        ctx.api.get('/exports/jobs'),
        ctx.api.get('/result-snapshots'),
    ]);

    const jobRows = jobs?.data || [];
    const snapshotRows = snapshots?.data || [];

    ctx.mount(`
        ${pageHeader({
            title: 'Exports',
            subtitle: 'Export jobs та result snapshots'
        })}
        <div class="row g-3">
            <div class="col-12">
                ${card('Export Jobs', dataTable(
                    [
                        { key: 'id', label: 'ID' },
                        { key: 'status', label: 'Status' },
                        { key: 'format', label: 'Format' },
                        { key: 'created_at', label: 'Created At' },
                    ],
                    jobRows,
                    row => `<button class="btn btn-sm btn-outline-secondary inspect-job-btn" data-id="${row.id}">JSON</button>`
                ))}
            </div>
            <div class="col-12">
                ${card('Result Snapshots', dataTable(
                    [
                        { key: 'id', label: 'ID' },
                        { key: 'layer_id', label: 'Layer ID' },
                        { key: 'created_at', label: 'Created At' },
                    ],
                    snapshotRows,
                    row => `<button class="btn btn-sm btn-outline-secondary inspect-snapshot-btn" data-id="${row.id}">JSON</button>`
                ))}
            </div>
        </div>
    `);

    document.querySelectorAll('.inspect-job-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const row = jobRows.find(x => String(x.id) === btn.dataset.id);
            ctx.showJsonModal(`Export Job #${btn.dataset.id}`, row);
        });
    });

    document.querySelectorAll('.inspect-snapshot-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const row = snapshotRows.find(x => String(x.id) === btn.dataset.id);
            ctx.showJsonModal(`Result Snapshot #${btn.dataset.id}`, row);
        });
    });
}