<script setup>
import { ref } from 'vue';
import api from '@/services/api';
import DefaultLayout from '@/layouts/DefaultLayout.vue';

const searchQuery = ref('');
const searchResults = ref([]);
const loading = ref(false);
const error = ref('');

const performSearch = async () => {
  if (!searchQuery.value.trim() || loading.value) return;
  
  loading.value = true;
  error.value = '';
  searchResults.value = [];
  
  try {
    const response = await api.post('/web-search', {
      query: searchQuery.value.trim(),
      limit: 10
    });
    
    searchResults.value = response.data || [];
  } catch (err) {
    error.value = err.message || 'An error occurred while searching';
  } finally {
    loading.value = false;
  }
};

const formatUrl = (url) => {
  try {
    return new URL(url).hostname;
  } catch {
    return url;
  }
};
</script>

<template>
  <DefaultLayout>
    <div class="web-search space-y-6">
      <div class="header">
        <h1 class="text-3xl font-bold text-gray-900">Web Search</h1>
        <p class="text-gray-600 mt-2">Search the web using Firecrawl</p>
      </div>

      <div class="search-form">
        <div class="flex gap-3">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Enter your search query..."
            class="flex-1 px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            @keyup.enter="performSearch"
          />
          <button
            @click="performSearch"
            :disabled="loading || !searchQuery.trim()"
            class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:bg-gray-400 disabled:cursor-not-allowed transition-colors"
          >
            <span v-if="loading">Searching...</span>
            <span v-else>Search</span>
          </button>
        </div>
      </div>

      <div v-if="error" class="error bg-red-50 border border-red-200 rounded-lg p-4">
        <p class="text-red-800">{{ error }}</p>
      </div>

      <div v-if="loading" class="loading text-center py-8">
        <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
        <p class="mt-2 text-gray-600">Searching the web...</p>
      </div>

      <div v-if="searchResults.length > 0" class="results">
        <h2 class="text-xl font-semibold mb-4">Search Results ({{ searchResults.length }})</h2>
        <div class="space-y-4">
          <div
            v-for="(result, index) in searchResults"
            :key="index"
            class="result-item bg-white border border-gray-200 rounded-lg p-6 hover:shadow-md transition-shadow"
          >
            <div class="flex items-start justify-between">
              <div class="flex-1">
                <h3 class="text-lg font-semibold text-blue-600 hover:text-blue-800">
                  <a :href="result.url" target="_blank" rel="noopener noreferrer">
                    {{ result.title || result.url }}
                  </a>
                </h3>
                <p class="text-sm text-green-600 mt-1">{{ formatUrl(result.url) }}</p>
                <p v-if="result.description" class="text-gray-700 mt-2 leading-relaxed">
                  {{ result.description }}
                </p>
                <div v-if="result.content" class="mt-3">
                  <p class="text-gray-600 text-sm line-clamp-3">
                    {{ result.content.substring(0, 200) + (result.content.length > 200 ? '...' : '') }}
                  </p>
                </div>
              </div>
            </div>
            <div v-if="result.metadata" class="metadata mt-4 pt-3 border-t border-gray-100">
              <div class="flex flex-wrap gap-2 text-xs text-gray-500">
                <span v-if="result.metadata.author">Author: {{ result.metadata.author }}</span>
                <span v-if="result.metadata.publishedTime">Published: {{ result.metadata.publishedTime }}</span>
                <span v-if="result.metadata.language">Language: {{ result.metadata.language }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div v-else-if="!loading && searchQuery && searchResults.length === 0" class="no-results text-center py-8">
        <p class="text-gray-600">No results found for "{{ searchQuery }}"</p>
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