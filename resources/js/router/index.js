import { createRouter, createWebHistory } from 'vue-router';

// Import pages
import Home from '@/pages/Home.vue';
import Chat from '@/pages/Chat.vue';

const routes = [
  {
    path: '/',
    name: 'home',
    component: Home,
  },
  {
    path: '/chat',
    name: 'chat',
    component: Chat,
  },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

export default router;
