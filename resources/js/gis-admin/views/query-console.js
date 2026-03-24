import { pageHeader, card, codeBlock } from '../ui';

export async function renderQueryConsole(ctx) {
    ctx.mount(`
        ${pageHeader({
            title: 'Feature Query Console',
            subtitle: 'Швидке тестування feature query API'
        })}
        ${card('', `
            <form id="query-console-form" class="d-grid gap-3">
                <div>
                    <label class="form-label">Layer code</label>
                    <input class="form-control" name="layerCode" placeholder="fields" required>
                </div>
                <div>
                    <label class="form-label">JSON body</label>
                    <textarea class="form-control" name="payload" rows="12">{
  "filters": [],
  "limit": 10
}</textarea>
                </div>
                <div>
                    <button class="btn btn-primary">Execute query</button>
                </div>
            </form>
            <div class="mt-4" id="query-console-result"></div>
        `)}
    `);

    document.getElementById('query-console-form')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const form = new FormData(e.currentTarget);
        const layerCode = form.get('layerCode');
        const payload = JSON.parse(form.get('payload'));

        const res = await ctx.api.post(`/features/${layerCode}/query`, payload);
        document.getElementById('query-console-result').innerHTML = codeBlock(res);
    });
}