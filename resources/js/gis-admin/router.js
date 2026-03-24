export class Router {
    constructor(routes, onResolve) {
        this.routes = routes;
        this.onResolve = onResolve;
    }

    findRoute(hash) {
        return this.routes.find(route => hash.startsWith(route.path)) || this.routes[0];
    }

    async resolve() {
        const hash = window.location.hash || '#/dashboard';
        const route = this.findRoute(hash);
        await this.onResolve(route, hash);
    }

    start() {
        window.addEventListener('hashchange', () => this.resolve());
        if (!window.location.hash) {
            window.location.hash = '#/dashboard';
            return;
        }
        this.resolve();
    }
}