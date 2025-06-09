<script setup>
import { ref } from 'vue';
import api from '@/services/api';
import DefaultLayout from '@/layouts/DefaultLayout.vue';

const messages = ref([
  { role: 'system', content: 'You are a helpful assistant that writes articles.' }
]);
const userInput = ref('');
const loading = ref(false);

const sendMessage = async () => {
  if (!userInput.value) return;
  messages.value.push({ role: 'user', content: userInput.value });
  loading.value = true;
  try {
    const response = await api.post('/chat', { messages: messages.value });
    const reply = response.choices[0].message;
    messages.value.push(reply);
  } catch (e) {
    console.error(e);
  } finally {
    userInput.value = '';
    loading.value = false;
  }
};
</script>

<template>
  <DefaultLayout>
    <div>
      <h1 class="text-3xl font-bold mb-4">AI Chat</h1>
      <div class="space-y-2 mb-4">
        <div v-for="(m, i) in messages" :key="i" class="p-2 rounded" :class="m.role === 'user' ? 'bg-blue-100' : 'bg-neutral-200'">
          <pre>{{ m.content }}</pre>
        </div>
      </div>
      <div class="flex gap-2">
        <input v-model="userInput" @keyup.enter="sendMessage" class="flex-1 border rounded p-2" placeholder="Say something" />
        <button @click="sendMessage" class="px-4 py-2 bg-black text-white rounded" :disabled="loading">Send</button>
      </div>
    </div>
  </DefaultLayout>
</template>
