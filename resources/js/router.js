import { createRouter, createWebHistory } from 'vue-router';

import LoginPage from './pages/LoginPage.vue';
import DashboardPage from './pages/DashboardPage.vue';

const routes = [
    {
        path: '/login',
        name: 'login',
        component: LoginPage,
    },
    {
        path: '/',
        name: 'dashboard',
        component: DashboardPage,
        meta: {
            requiresAuth: true,
        },
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach((to, from, next) => {
    const token = localStorage.getItem('auth_token');

    if (to.meta.requiresAuth && !token) {
        next('/login');
        return;
    }

    if (to.path === '/login' && token) {
        next('/');
        return;
    }

    next();
});

export default router;
