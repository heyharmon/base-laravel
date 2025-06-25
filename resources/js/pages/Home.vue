<script setup>
import { ref, computed, onMounted } from "vue";
import api from "@/services/api";
import ChatInterface from "@/components/ChatInterface.vue";
import { marked } from "marked";

const articles = ref([]);
const currentArticle = ref(null);

const loadArticles = async () => {
    try {
        const response = await api.get("/articles");
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

const parsedContent = computed(() => {
    return currentArticle.value?.content
        ? marked(currentArticle.value.content)
        : "";
});

const handleResponseReceived = async () => {
    await loadArticles();

    // If there's a current article selected, reload it
    if (currentArticle.value) {
        try {
            const response = await api.get(
                `/articles/${currentArticle.value.id}`
            );
            currentArticle.value = response.data;
        } catch (error) {
            console.error("Error reloading current article:", error);
        }
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
                    <div
                        class="markdown-content text-gray-600 max-w-none"
                        v-html="parsedContent"
                    ></div>
                </div>
                <div v-else class="flex items-center justify-center h-full">
                    <p class="text-gray-500 text-lg">
                        Select an article to view
                    </p>
                </div>
            </div>

            <!-- Chat Panel -->
            <div class="w-96 border-l border-gray-200 bg-gray-50">
                <ChatInterface
                    :current-article="currentArticle"
                    @response-received="handleResponseReceived"
                />
            </div>
        </div>
    </div>
</template>

<style>
/* Markdown styling */
.markdown-content h1 {
    font-size: 1.875rem;
    line-height: 2.25rem;
    font-weight: 700;
    margin-top: 1.5rem;
    margin-bottom: 1rem;
    color: rgb(38 38 38);
}

.markdown-content h2 {
    font-size: 1.5rem;
    line-height: 2rem;
    font-weight: 700;
    margin-top: 1.25rem;
    margin-bottom: 0.75rem;
    color: rgb(38 38 38);
}

.markdown-content h3 {
    font-size: 1.25rem;
    line-height: 1.75rem;
    font-weight: 700;
    margin-top: 1rem;
    margin-bottom: 0.5rem;
    color: rgb(64 64 64);
}

.markdown-content h4 {
    font-size: 1.125rem;
    line-height: 1.75rem;
    font-weight: 700;
    margin-top: 0.75rem;
    margin-bottom: 0.5rem;
    color: rgb(64 64 64);
}

.markdown-content h5,
.markdown-content h6 {
    font-weight: 700;
    margin-top: 0.75rem;
    margin-bottom: 0.25rem;
    color: rgb(64 64 64);
}

.markdown-content p {
    margin-top: 0.75rem;
    margin-bottom: 0.75rem;
    color: rgb(71 85 105);
}

.markdown-content ul {
    list-style-type: disc;
    padding-left: 1.5rem;
    margin-top: 0.75rem;
    margin-bottom: 0.75rem;
}

.markdown-content ol {
    list-style-type: decimal;
    padding-left: 1.5rem;
    margin-top: 0.75rem;
    margin-bottom: 0.75rem;
}

.markdown-content li {
    margin-bottom: 0.25rem;
}

.markdown-content a {
    color: rgb(37 99 235);
    text-decoration: underline;
}

.markdown-content a:hover {
    color: rgb(30 64 175);
}

.markdown-content blockquote {
    padding-left: 1rem;
    border-left-width: 4px;
    border-color: rgb(212 212 212);
    font-style: italic;
    margin-top: 0.75rem;
    margin-bottom: 0.75rem;
    color: rgb(113 113 122);
}

.markdown-content code {
    background-color: rgb(243 244 246);
    padding-left: 0.25rem;
    padding-right: 0.25rem;
    padding-top: 0.125rem;
    padding-bottom: 0.125rem;
    border-radius: 0.25rem;
    font-size: 0.875rem;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas,
        "Liberation Mono", "Courier New", monospace;
}

.markdown-content pre {
    background-color: rgb(243 244 246);
    padding: 0.75rem;
    border-radius: 0.25rem;
    margin-top: 0.75rem;
    margin-bottom: 0.75rem;
    overflow-x: auto;
}

.markdown-content pre code {
    background-color: transparent;
    padding: 0;
}

.markdown-content hr {
    margin-top: 1.25rem;
    margin-bottom: 1.25rem;
    border-color: rgb(212 212 212);
}

.markdown-content img {
    max-width: 100%;
    margin-top: 0.75rem;
    margin-bottom: 0.75rem;
    border-radius: 0.25rem;
}

.markdown-content table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 0.75rem;
    margin-bottom: 0.75rem;
}

.markdown-content th,
.markdown-content td {
    border-width: 1px;
    border-color: rgb(212 212 212);
    padding: 0.5rem;
}

.markdown-content th {
    background-color: rgb(243 244 246);
}
</style>
