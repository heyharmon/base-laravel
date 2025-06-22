import { createRouter, createWebHistory } from "vue-router";

// Import pages
import Home from "@/pages/Home.vue";
import Articles from "@/pages/Articles.vue";
import ArticleEdit from "@/pages/ArticleEdit.vue";
import WebSearch from "@/pages/WebSearch.vue";
import FetchWebpage from "@/pages/FetchWebpage.vue";

const routes = [
    {
        path: "/",
        name: "home",
        component: Home,
    },
    {
        path: "/articles",
        name: "articles",
        component: Articles,
    },
    {
        path: "/articles/:id/edit",
        name: "article-edit",
        component: ArticleEdit,
    },
    {
        path: "/web-search",
        name: "web-search",
        component: WebSearch,
    },
    {
        path: "/fetch-webpage",
        name: "fetch-webpage",
        component: FetchWebpage,
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

export default router;
