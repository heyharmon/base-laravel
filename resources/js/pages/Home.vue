<script setup>
import { ref, onMounted } from 'vue';
import api from '@/services/api';
import DefaultLayout from '@/layouts/DefaultLayout.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';

const colors = ref([]);
const loading = ref(true);
const error = ref(null);
const search = ref('');

onMounted(async () => {
  try {
    loading.value = true;
    colors.value = await api.get('/colors');
  } catch (err) {
    error.value = 'Failed to load colors';
    console.error(err);
  } finally {
    loading.value = false;
  }
});
</script>

<template>
  <DefaultLayout>
    <div>
      <h1 class="text-3xl font-bold mb-6">Welcome</h1>
      <p class="mb-4">Your Laravel 12 API with Vue 3, Vue router, Vite and Tailwind 4 is ready.</p>
      
      <div class="mt-8">
        <h2 class="text-2xl font-semibold mb-4">Colors from API</h2>
        <div v-if="loading" class="text-neutral-500">Loading colors...</div>
        <div v-else-if="error" class="text-red-500">{{ error }}</div>
        <div v-else-if="colors.length === 0" class="text-neutral-500">No colors found</div>
        <ul v-else class="space-y-2">
          <li v-for="(color, index) in colors" :key="index" class="flex items-center">
            <span class="w-6 h-6 rounded mr-2" :style="{ backgroundColor: color }"></span>
            <span>{{ color }}</span>
          </li>
        </ul>

        <div class="mt-8">
            <Button>Button</Button>
        </div>

        <div class="mt-8">
            <p>Searching: {{ search }}</p>
            <Input v-model="search" placeholder="Search" />
        </div>
      </div>
    </div>
  </DefaultLayout>
</template>
