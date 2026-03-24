import { pageHeader, card, statCard } from '../ui';

export async function renderDashboard(ctx) {
    const [layers, services, dataSources, users] = await Promise.all([
        ctx.api.get('/layers'),
        ctx.api.get('/services'),
        ctx.api.get('/data-sources'),
        ctx.api.get('/users').catch(() => ({ data: [] })),
    ]);

    ctx.mount(`
        ${pageHeader({
            title: 'Dashboard',
            subtitle: 'Огляд стану GIS-платформи'
        })}
        <div class="row g-3 mb-4">
            <div class="col-md-6 col-xl-3">${statCard('Layers', layers?.data?.length ?? 0)}</div>
            <div class="col-md-6 col-xl-3">${statCard('Services', services?.data?.length ?? 0)}</div>
            <div class="col-md-6 col-xl-3">${statCard('Data Sources', dataSources?.data?.length ?? 0)}</div>
            <div class="col-md-6 col-xl-3">${statCard('Users', users?.data?.length ?? 0)}</div>
        </div>
        ${card('Welcome', '<p class="mb-0">Адміністративна консоль GIS готова до роботи.</p>')}
    `);
}