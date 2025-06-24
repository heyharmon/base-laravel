import { createRouter, createWebHistory } from "vue-router";

// Import pages
import Home from "@/pages/Home.vue";
import ArticleEditor from "@/pages/ArticleEditor.vue";

const routes = [
    {
        path: "/",
        name: "home",
        component: Home,
    },
    {
        path: "/articles/:id/edit",
        name: "article.edit",
        component: ArticleEditor,
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

export default router;
