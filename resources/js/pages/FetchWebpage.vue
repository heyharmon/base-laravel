<script setup>
import { ref } from 'vue';
import api from '@/services/api';
import DefaultLayout from '@/layouts/DefaultLayout.vue';

const url = ref('');
const pageData = ref(null);
const loading = ref(false);
const error = ref('');

const fetchPage = async () => {
  if (!url.value.trim() || loading.value) return;
  
  loading.value = true;
  error.value = '';
  pageData.value = null;
  
  try {
    const response = await api.post('/fetch-webpage', {
      url: url.value.trim()
    });
    
    pageData.value = response.data || null;
  } catch (err) {
    error.value = err.message || 'An error occurred while fetching the webpage';
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
    <div class="fetch-webpage space-y-6">
      <div class="header">
        <h1 class="text-3xl font-bold text-gray-900">Fetch Webpage</h1>
        <p class="text-gray-600 mt-2">Fetch and convert any webpage to markdown using Firecrawl</p>
      </div>

      <div class="fetch-form">
        <div class="flex gap-3">
          <input
            v-model="url"
            type="url"
            placeholder="Enter webpage URL..."
            class="flex-1 px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            @keyup.enter="fetchPage"
          />
          <button
            @click="fetchPage"
            :disabled="loading || !url.trim()"
            class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:bg-gray-400 disabled:cursor-not-allowed transition-colors"
          >
            <span v-if="loading">Fetching...</span>
            <span v-else>Fetch Page</span>
          </button>
        </div>
      </div>

      <div v-if="error" class="error bg-red-50 border border-red-200 rounded-lg p-4">
        <p class="text-red-800">{{ error }}</p>
      </div>

      <div v-if="loading" class="loading text-center py-8">
        <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
        <p class="mt-2 text-gray-600">Fetching webpage...</p>
      </div>

      <div v-if="pageData" class="result">
        <div class="page-header bg-white border border-gray-200 rounded-lg p-6 mb-6">
          <h2 class="text-2xl font-semibold text-blue-600 hover:text-blue-800">
            <a :href="pageData.url" target="_blank" rel="noopener noreferrer">
              {{ pageData.title || pageData.url }}
            </a>
          </h2>
          <p class="text-sm text-green-600 mt-1">{{ formatUrl(pageData.url) }}</p>
          <p v-if="pageData.description" class="text-gray-700 mt-2 leading-relaxed">
            {{ pageData.description }}
          </p>
          <div v-if="pageData.metadata" class="metadata mt-4 pt-3 border-t border-gray-100">
            <div class="flex flex-wrap gap-2 text-xs text-gray-500">
              <span v-if="pageData.metadata.author">Author: {{ pageData.metadata.author }}</span>
              <span v-if="pageData.metadata.publishedTime">Published: {{ pageData.metadata.publishedTime }}</span>
              <span v-if="pageData.metadata.language">Language: {{ pageData.metadata.language }}</span>
            </div>
          </div>
        </div>

        <div v-if="pageData.content" class="content bg-white border border-gray-200 rounded-lg p-6">
          <h3 class="text-lg font-semibold mb-4">Page Content (Markdown)</h3>
          <div class="markdown-content bg-gray-50 border border-gray-200 rounded p-4 max-h-96 overflow-y-auto">
            <pre class="whitespace-pre-wrap text-sm font-mono">{{ pageData.content }}</pre>
          </div>
        </div>
      </div>

      <div v-else-if="!loading && url && !pageData" class="no-result text-center py-8">
        <p class="text-gray-600">Unable to fetch content from "{{ url }}"</p>
      </div>
    </div>
  </DefaultLayout>
</template>

<style scoped>
.markdown-content {
  scrollbar-width: thin;
  scrollbar-color: #cbd5e0 #f7fafc;
}

.markdown-content::-webkit-scrollbar {
  width: 8px;
}

.markdown-content::-webkit-scrollbar-track {
  background: #f7fafc;
}

.markdown-content::-webkit-scrollbar-thumb {
  background-color: #cbd5e0;
  border-radius: 4px;
}
</style>