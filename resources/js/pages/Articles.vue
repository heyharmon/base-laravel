<script setup>
import { ref, onMounted } from "vue";
import { useRouter } from "vue-router";
import api from "@/services/api";
import DefaultLayout from "@/layouts/DefaultLayout.vue";

const router = useRouter();
const articles = ref([]);
const loading = ref(false);
const creating = ref(false);
const error = ref(null);

const loadArticles = async () => {
    loading.value = true;
    error.value = null;
    try {
        articles.value = await api.get("/articles");
    } catch (err) {
        error.value = "Failed to load articles";
        console.error("Error loading articles:", err);
    } finally {
        loading.value = false;
    }
};

const createArticle = async () => {
    creating.value = true;
    error.value = null;
    
    try {
        const newArticle = await api.post('/articles', {
            title: 'New Article',
            status: 'planning'
        });
        
        // Redirect to edit page for the new article
        router.push(`/articles/${newArticle.article.id}/edit`);
    } catch (err) {
        error.value = "Failed to create article";
        console.error("Error creating article:", err);
    } finally {
        creating.value = false;
    }
};

const deleteArticle = async (articleId) => {
    if (!confirm("Are you sure you want to delete this article?")) return;

    try {
        await api.delete(`/articles/${articleId}`);
        articles.value = articles.value.filter(
            (article) => article.id !== articleId
        );
    } catch (err) {
        error.value = "Failed to delete article";
        console.error("Error deleting article:", err);
    }
};

const getStatusColor = (status) => {
    const colors = {
        planning: "bg-yellow-100 text-yellow-800",
        researching: "bg-blue-100 text-blue-800",
        writing: "bg-purple-100 text-purple-800",
        reviewing: "bg-orange-100 text-orange-800",
        completed: "bg-green-100 text-green-800",
    };
    return colors[status] || "bg-gray-100 text-gray-800";
};

const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString("en-US", {
        year: "numeric",
        month: "short",
        day: "numeric",
        hour: "2-digit",
        minute: "2-digit",
    });
};

onMounted(() => {
    loadArticles();
});
</script>

<template>
    <DefaultLayout>
        <div class="articles space-y-6">
            <div class="flex justify-between items-center">
                <h1 class="text-3xl font-bold">Articles</h1>
                <div class="flex space-x-2">
                    <button
                        @click="createArticle"
                        :disabled="creating"
                        class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded disabled:opacity-50"
                    >
                        {{ creating ? "Creating..." : "Create Article" }}
                    </button>
                    <button
                        @click="loadArticles"
                        :disabled="loading"
                        class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded disabled:opacity-50"
                    >
                        {{ loading ? "Loading..." : "Refresh" }}
                    </button>
                </div>
            </div>

            <div
                v-if="error"
                class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded"
            >
                {{ error }}
            </div>

            <div
                v-if="loading && articles.length === 0"
                class="text-center py-8"
            >
                <div class="text-gray-500">Loading articles...</div>
            </div>

            <div v-else-if="articles.length === 0" class="text-center py-8">
                <div class="text-gray-500">No articles found.</div>
            </div>

            <div v-else class="grid gap-6">
                <div
                    v-for="article in articles"
                    :key="article.id"
                    class="bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow duration-200 p-6"
                >
                    <div class="flex justify-between items-start mb-4">
                        <h2 class="text-xl font-semibold text-gray-900">
                            {{ article.title || "Untitled Article" }}
                        </h2>
                        <div class="flex items-center space-x-2">
                            <span
                                :class="getStatusColor(article.status)"
                                class="px-2 py-1 rounded-full text-xs font-medium capitalize"
                            >
                                {{ article.status || "draft" }}
                            </span>
                        </div>
                    </div>

                    <div v-if="article.content" class="mb-4">
                        <p class="text-gray-600 line-clamp-3">
                            {{ article.content.substring(0, 200)
                            }}{{ article.content.length > 200 ? "..." : "" }}
                        </p>
                    </div>

                    <div
                        class="flex justify-between items-center text-sm text-gray-500 mb-4"
                    >
                        <div class="space-x-4">
                            <span
                                >Created:
                                {{ formatDate(article.created_at) }}</span
                            >
                            <span
                                v-if="article.updated_at !== article.created_at"
                            >
                                Updated: {{ formatDate(article.updated_at) }}
                            </span>
                        </div>
                    </div>

                    <div class="flex justify-between items-center">
                        <router-link
                            :to="`/articles/${article.id}/edit`"
                            class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm"
                        >
                            Edit
                        </router-link>
                        <button
                            @click="deleteArticle(article.id)"
                            class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-sm"
                        >
                            Delete
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </DefaultLayout>
</template>

<style scoped>
.line-clamp-3 {
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
