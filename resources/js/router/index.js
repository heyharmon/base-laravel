import { createRouter, createWebHistory } from 'vue-router';

// Import pages
import Home from '@/pages/Home.vue';
import Feed from '@/pages/Feed.vue';
import ManageFeeds from '@/pages/ManageFeeds.vue';

const routes = [
  {
    path: '/',
    name: 'home',
    component: Home,
  },
  {
    path: '/feed/:id',
    name: 'feed.show',
    component: Feed,
  },
  {
    path: '/feeds',
    name: 'feeds.index',
    component: ManageFeeds,
  },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

export default router;
