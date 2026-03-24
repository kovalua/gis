import { ApiClient } from './api';
import { Router } from './router';
import { codeBlock, toast } from './ui';
import { renderDashboard } from './views/dashboard';
import { renderDataSources } from './views/data-sources';
import { renderLayers } from './views/layers';
import { renderServices } from './views/services';
import { renderCatalog } from './views/catalog';
import { renderQueryConsole } from './views/query-console';
import { renderSavedQueries } from './views/saved-queries';
import { renderAnalytics } from './views/analytics';
import { renderExports } from './views/exports';
import { renderRoles } from './views/roles';
import { renderPermissions } from './views/permissions';
import { renderUsers } from './views/users';

const root = document.getElementById('gis-admin-app');

if (root) {
    const apiBase = root.dataset.apiBase || '/api/v1';
    const appName = root.dataset.appName || 'GIS Admin';
    const api = new ApiClient(apiBase);

    const navItems = [
        { path: '#/dashboard', label: 'Dashboard' },
        { path: '#/catalog', label: 'Catalog' },
        { path: '#/data-sources', label: 'Data Sources' },
        { path: '#/layers', label: 'Layers' },
        { path: '#/services', label: 'Services' },
        { path: '#/query-console', label: 'Feature Query Console' },
        { path: '#/saved-queries', label: 'Saved Queries' },
        { path: '#/analytics', label: 'Analytics' },
        { path: '#/exports', label: 'Exports' },
        { path: '#/roles', label: 'Roles' },
        { path: '#/permissions', label: 'Permissions' },
        { path: '#/users', label: 'Users' },
    ];

    const ctx = {
        api,
        root,
        mount(content) {
            root.querySelector('#gis-page-content').innerHTML = content;
        },
        showJsonModal(title, data) {
            root.querySelector('#gisJsonModalLabel').textContent = title;
            root.querySelector('#gis-json-modal-body').innerHTML = codeBlock(data);
            bootstrap.Modal.getOrCreateInstance(root.querySelector('#gisJsonModal')).show();
        },
    };

    function shell(activeHash = '#/dashboard') {
        const user = api.user;

        root.innerHTML = `
            <div class="gis-shell">
                <aside class="gis-sidebar p-3" id="gis-sidebar">
                    <div class="mb-4">
                        <div class="fw-bold fs-5 text-white">${appName}</div>
                        <div class="small text-secondary">Enterprise GIS Console</div>
                    </div>

                    <nav class="nav flex-column gap-1">
                        ${navItems.map(item => `
                            <a href="${item.path}" class="nav-link ${activeHash.startsWith(item.path) ? 'active' : ''}">
                                ${item.label}
                            </a>
                        `).join('')}
                    </nav>
                </aside>

                <main class="gis-main">
                    <div class="gis-topbar bg-white border-bottom px-3 px-lg-4 d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2">
                            <button class="btn btn-outline-secondary d-lg-none" id="sidebarToggle">Menu</button>
                            <div>
                                <div class="fw-semibold">${appName}</div>
                                <div class="small text-secondary">Bootstrap 5 + Vanilla JS</div>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="text-secondary small">${user?.name || 'Unknown user'}</span>
                            <button class="btn btn-sm btn-outline-secondary" id="refreshViewBtn">Refresh</button>
                            <button class="btn btn-sm btn-outline-danger" id="logoutBtn">Logout</button>
                        </div>
                    </div>

                    <div class="container-fluid px-3 px-lg-4 py-4" id="gis-page-content"></div>
                </main>

                <div class="toast-container position-fixed top-0 end-0 p-3" id="gis-toast-host"></div>

                <div class="modal fade" id="gisJsonModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-xl modal-dialog-scrollable">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="gisJsonModalLabel">JSON</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body" id="gis-json-modal-body"></div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        root.querySelector('#sidebarToggle')?.addEventListener('click', () => {
            root.querySelector('#gis-sidebar')?.classList.toggle('show');
        });

        root.querySelector('#refreshViewBtn')?.addEventListener('click', () => router.resolve());

        root.querySelector('#logoutBtn')?.addEventListener('click', async () => {
            try {
                await api.logout();
            } catch (_) {}

            api.clearAuth();
            renderLogin();
        });
    }

    function renderLogin(errorMessage = '') {
        root.innerHTML = `
            <div class="gis-login-screen bg-body-tertiary">
                <div class="card gis-login-card">
                    <div class="card-body p-4 p-lg-5">
                        <div class="mb-4 text-center">
                            <h1 class="h3 mb-1">${appName}</h1>
                            <div class="text-secondary">Sign in to GIS API Console</div>
                        </div>

                        ${errorMessage ? `<div class="alert alert-danger">${errorMessage}</div>` : ''}

                        <form id="gis-login-form" class="d-grid gap-3">
                            <div>
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" required>
                            </div>
                            <div>
                                <label class="form-label">Password</label>
                                <input type="password" class="form-control" name="password" required>
                            </div>
                            <button class="btn btn-primary" type="submit">Login</button>
                        </form>
                    </div>
                </div>
            </div>
        `;

        root.querySelector('#gis-login-form')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const form = new FormData(e.currentTarget);

            try {
                const result = await api.login(form.get('email'), form.get('password'));
                api.token = result?.data?.token;
                api.user = result?.data?.user;
                window.location.hash = '#/dashboard';
                startApp();
            } catch (e) {
                renderLogin(e.message);
            }
        });
    }

    const routes = [
        { path: '#/dashboard', render: renderDashboard },
        { path: '#/catalog', render: renderCatalog },
        { path: '#/data-sources', render: renderDataSources },
        { path: '#/layers', render: renderLayers },
        { path: '#/services', render: renderServices },
        { path: '#/query-console', render: renderQueryConsole },
        { path: '#/saved-queries', render: renderSavedQueries },
        { path: '#/analytics', render: renderAnalytics },
        { path: '#/exports', render: renderExports },
        { path: '#/roles', render: renderRoles },
        { path: '#/permissions', render: renderPermissions },
        { path: '#/users', render: renderUsers },
    ];

    const router = new Router(routes, async (route, hash) => {
        shell(hash);

        try {
            await route.render(ctx, hash);
        } catch (e) {
            if (e.status === 401) {
                api.clearAuth();
                renderLogin('Session expired.');
                return;
            }

            ctx.mount(`<div class="alert alert-danger">${e.message}</div>`);
            toast(e.message, 'danger');
        }
    });

    async function startApp() {
        if (!api.token) {
            renderLogin();
            return;
        }

        try {
            const me = await api.me();
            api.user = me?.data?.user || api.user;
            router.start();
        } catch (_) {
            api.clearAuth();
            renderLogin();
        }
    }

    startApp();
}