<script setup>
import { ref, onMounted } from "vue";
import api from "@/services/api";
import ChatInterface from "@/components/ChatInterface.vue";

const articles = ref([]);
const currentArticle = ref(null);

const loadArticles = async () => {
    try {
        const response = await api.get("/articles");
        console.log("Articles loaded:", response);
        articles.value = response.data;

        // Automatically select the latest article if articles exist
        if (articles.value.length > 0) {
            await selectArticle(articles.value[0]);
        }
    } catch (error) {
        console.error("Error loading articles:", error);
    }
};

const selectArticle = async (article) => {
    try {
        const response = await api.get(`/articles/${article.id}`);
        currentArticle.value = response.data;
    } catch (error) {
        console.error("Error loading article:", error);
    }
};

onMounted(() => {
    loadArticles();
});
</script>

<template>
    <div class="flex h-screen bg-gray-100">
        <!-- Sidebar -->
        <div class="w-64 bg-white shadow-md">
            <div class="p-4">
                <h2 class="text-xl font-bold text-gray-800">Articles</h2>
            </div>
            <div class="overflow-y-auto max-h-[calc(100vh-5rem)]">
                <div class="px-2 pb-4">
                    <button
                        v-for="article in articles"
                        :key="article.id"
                        @click="selectArticle(article)"
                        :class="[
                            'w-full text-left px-3 py-2 rounded-md mb-1 transition-colors',
                            currentArticle?.id === article.id
                                ? 'bg-blue-500 text-white'
                                : 'hover:bg-gray-100 text-gray-700',
                        ]"
                    >
                        {{ article.title }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="flex-1 flex overflow-hidden">
            <!-- Article View -->
            <div class="flex-1 overflow-y-auto bg-white">
                <div v-if="currentArticle" class="p-8">
                    <h1 class="text-3xl font-bold text-gray-800 mb-6">
                        {{ currentArticle.title }}
                    </h1>
                    <div class="prose prose-lg max-w-none">
                        <p
                            class="whitespace-pre-wrap text-gray-600 leading-relaxed"
                        >
                            {{ currentArticle.content }}
                        </p>
                    </div>
                </div>
                <div v-else class="flex items-center justify-center h-full">
                    <p class="text-gray-500 text-lg">
                        Select an article to view
                    </p>
                </div>
            </div>

            <!-- Chat Panel -->
            <div class="w-96 border-l border-gray-200 bg-gray-50">
                <ChatInterface :current-article="currentArticle" />
            </div>
        </div>
    </div>
</template>
