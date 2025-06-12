<script setup>
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import api from '@/services/api';
import DefaultLayout from '@/layouts/DefaultLayout.vue';

const route = useRoute();
const feed = ref(null);
const videos = ref([]);
const loading = ref(true);
const error = ref(null);

onMounted(async () => {
  try {
    const data = await api.get(`/feeds/${route.params.id}`);
    feed.value = data.feed;
    videos.value = data.videos;
  } catch (err) {
    console.error(err);
    error.value = 'Failed to load feed';
  } finally {
    loading.value = false;
  }
});
</script>

<template>
  <DefaultLayout>
    <div>
      <h1 class="text-3xl font-bold mb-6">{{ feed?.name }}</h1>
      <div v-if="loading" class="text-neutral-500">Loading videos...</div>
      <div v-else-if="error" class="text-red-500">{{ error }}</div>
      <ul v-else class="space-y-4">
        <li v-for="video in videos" :key="video.videoId" class="flex space-x-4">
          <img :src="video.thumbnail" class="w-48 h-28 object-cover" />
          <div>
            <h3 class="font-semibold">{{ video.title }}</h3>
            <p class="text-sm text-neutral-600">{{ video.publishedAt }}</p>
          </div>
        </li>
      </ul>
    </div>
  </DefaultLayout>
</template>
