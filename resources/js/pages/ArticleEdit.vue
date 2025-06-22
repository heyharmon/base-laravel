<script setup>
import { ref, onMounted } from "vue";
import { useRoute, useRouter } from "vue-router";
import api from "@/services/api";
import DefaultLayout from "@/layouts/DefaultLayout.vue";

const route = useRoute();
const router = useRouter();

const article = ref({
    title: '',
    content: '',
    outline: [],
    status: 'planning'
});
const loading = ref(false);
const saving = ref(false);
const error = ref(null);
const successMessage = ref('');

const statusOptions = [
    { value: 'planning', label: 'Planning' },
    { value: 'researching', label: 'Researching' },
    { value: 'writing', label: 'Writing' },
    { value: 'reviewing', label: 'Reviewing' },
    { value: 'completed', label: 'Completed' }
];

const loadArticle = async () => {
    loading.value = true;
    error.value = null;
    
    try {
        const articleData = await api.get(`/articles/${route.params.id}`);
        article.value = {
            ...articleData,
            outline: articleData.outline || []
        };
    } catch (err) {
        error.value = 'Failed to load article';
        console.error('Error loading article:', err);
    } finally {
        loading.value = false;
    }
};

const saveArticle = async () => {
    saving.value = true;
    error.value = null;
    successMessage.value = '';
    
    try {
        const response = await api.put(`/articles/${route.params.id}`, {
            title: article.value.title,
            content: article.value.content,
            outline: article.value.outline.length ? article.value.outline : null,
            status: article.value.status
        });
        
        successMessage.value = 'Article saved successfully!';
        
        // Clear success message after 3 seconds
        setTimeout(() => {
            successMessage.value = '';
        }, 3000);
        
    } catch (err) {
        error.value = 'Failed to save article';
        console.error('Error saving article:', err);
    } finally {
        saving.value = false;
    }
};

const addOutlineItem = () => {
    article.value.outline.push('');
};

const removeOutlineItem = (index) => {
    article.value.outline.splice(index, 1);
};

const goBack = () => {
    router.push('/articles');
};

onMounted(() => {
    if (route.params.id) {
        loadArticle();
    }
});
</script>

<template>
    <DefaultLayout>
        <div class="article-edit space-y-6">
            <div class="flex justify-between items-center">
                <h1 class="text-3xl font-bold">Edit Article</h1>
                <button 
                    @click="goBack"
                    class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded"
                >
                    Back to Articles
                </button>
            </div>

            <div v-if="error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                {{ error }}
            </div>

            <div v-if="successMessage" class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                {{ successMessage }}
            </div>

            <div v-if="loading" class="text-center py-8">
                <div class="text-gray-500">Loading article...</div>
            </div>

            <form v-else @submit.prevent="saveArticle" class="space-y-6">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="space-y-4">
                        <!-- Title -->
                        <div>
                            <label for="title" class="block text-sm font-medium text-gray-700 mb-2">
                                Title
                            </label>
                            <input
                                id="title"
                                v-model="article.title"
                                type="text"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                placeholder="Enter article title"
                                required
                            />
                        </div>

                        <!-- Status -->
                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700 mb-2">
                                Status
                            </label>
                            <select
                                id="status"
                                v-model="article.status"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            >
                                <option v-for="option in statusOptions" :key="option.value" :value="option.value">
                                    {{ option.label }}
                                </option>
                            </select>
                        </div>

                        <!-- Outline -->
                        <div>
                            <div class="flex justify-between items-center mb-2">
                                <label class="block text-sm font-medium text-gray-700">
                                    Outline
                                </label>
                                <button
                                    type="button"
                                    @click="addOutlineItem"
                                    class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-sm"
                                >
                                    Add Item
                                </button>
                            </div>
                            <div v-if="article.outline.length" class="space-y-2">
                                <div
                                    v-for="(item, index) in article.outline"
                                    :key="index"
                                    class="flex items-center space-x-2"
                                >
                                    <input
                                        v-model="article.outline[index]"
                                        type="text"
                                        class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                        :placeholder="`Outline item ${index + 1}`"
                                    />
                                    <button
                                        type="button"
                                        @click="removeOutlineItem(index)"
                                        class="bg-red-500 hover:bg-red-600 text-white px-2 py-2 rounded text-sm"
                                    >
                                        Remove
                                    </button>
                                </div>
                            </div>
                            <div v-else class="text-gray-500 text-sm">
                                No outline items. Click "Add Item" to create an outline.
                            </div>
                        </div>

                        <!-- Content -->
                        <div>
                            <label for="content" class="block text-sm font-medium text-gray-700 mb-2">
                                Content
                            </label>
                            <textarea
                                id="content"
                                v-model="article.content"
                                rows="15"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                placeholder="Write your article content here..."
                            ></textarea>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end space-x-4">
                    <button
                        type="button"
                        @click="goBack"
                        class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="saving"
                        class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded disabled:opacity-50"
                    >
                        {{ saving ? 'Saving...' : 'Save Article' }}
                    </button>
                </div>
            </form>
        </div>
    </DefaultLayout>
</template>