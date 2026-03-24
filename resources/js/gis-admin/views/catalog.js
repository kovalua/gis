import { pageHeader, card, codeBlock } from '../ui';

export async function renderCatalog(ctx) {
    const res = await ctx.api.get('/catalog');

    ctx.mount(`
        ${pageHeader({
            title: 'Catalog',
            subtitle: 'Runtime catalog для клієнтських застосунків'
        })}
        ${card('Catalog JSON', codeBlock(res))}
    `);
}