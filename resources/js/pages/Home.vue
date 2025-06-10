<script setup>
import { ref, onMounted } from 'vue';
import api from '@/services/api';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import DefaultLayout from '@/layouts/DefaultLayout.vue';

const colors = ref([]);
const loading = ref(true);
const error = ref(null);
const search = ref('');
const chats = ref([]);
const chatMessage = ref('');
const sending = ref(false);

  onMounted(async () => {
  try {
    loading.value = true;
    colors.value = await api.get('/colors');
    chats.value = await api.get('/chats');
  } catch (err) {
    error.value = 'Failed to load colors';
    console.error(err);
  } finally {
    loading.value = false;
  }
  });

const sendChat = async () => {
  if (!chatMessage.value.trim()) {
    return;
  }
  try {
    sending.value = true;
    const { reply } = await api.post('/chats', { message: chatMessage.value });
    chats.value.push({ role: 'user', content: chatMessage.value });
    chats.value.push({ role: 'assistant', content: reply });
    chatMessage.value = '';
  } catch (err) {
    console.error(err);
  } finally {
    sending.value = false;
  }
};
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

        <div class="mt-8 space-y-4">
          <h2 class="text-2xl font-semibold">Chat</h2>
          <div class="space-y-2">
            <div v-for="(chat, index) in chats" :key="index" :class="chat.role === 'user' ? 'text-right' : ''">
              <div :class="['inline-block px-3 py-2 rounded', chat.role === 'user' ? 'bg-neutral-200' : 'bg-neutral-300']">
                {{ chat.content }}
              </div>
            </div>
          </div>
          <div class="flex gap-2 items-end">
            <Input v-model="chatMessage" placeholder="Say something" class="flex-1" />
            <Button @click="sendChat" :disabled="sending">Send</Button>
          </div>
        </div>
      </div>
    </div>
  </DefaultLayout>
</template>
