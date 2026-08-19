import { createRouter, createWebHistory } from 'vue-router'
import Home from '@/views/Home.vue'
import NotFound from '@/views/NotFound.vue'

const router = createRouter({
    history: createWebHistory(),
    routes: [
        {
            path: '/',
            name: 'home',
            component: Home,
        },
        {
            path: '/404',
            name: 'error_404',
            component: NotFound,
        },
        {
            path: '/:pathMatch(.*)*',
            redirect: '/404',
        },
    ],
})

export default router
