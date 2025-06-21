<script setup>
import { ref, onMounted } from 'vue';
import api from '@/services/api';
import DefaultLayout from '@/layouts/DefaultLayout.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';

const conversation = ref(null);
const chats = ref([]);
const message = ref('');
const loading = ref(false);
const error = ref(null);

async function startConversation() {
  try {
    const data = await api.post('/conversations', { title: 'Research Session' });
    conversation.value = data;
    await loadChats();
  } catch (err) {
    error.value = 'Failed to start conversation';
    console.error(err);
  }
}

async function loadChats() {
  if (!conversation.value) return;
  try {
    const data = await api.get(`/conversations/${conversation.value.id}/chats`);
    chats.value = data;
  } catch (err) {
    console.error(err);
  }
}

async function sendMessage() {
  if (!conversation.value || !message.value.trim()) return;
  const userText = message.value;
  chats.value.push({ role: 'user', content: userText });
  message.value = '';
  loading.value = true;
  try {
    const resp = await api.post(
      `/conversations/${conversation.value.id}/messages`,
      { message: userText }
    );
    chats.value.push(resp.assistant_chat);
  } catch (err) {
    console.error(err);
    error.value = 'Failed to send message';
  } finally {
    loading.value = false;
  }
}

onMounted(() => {
  startConversation();
});
</script>

<template>
  <DefaultLayout>
    <div class="max-w-2xl mx-auto py-8">
      <h1 class="text-2xl font-bold mb-4">Research Agent Chat</h1>
      <div class="border rounded p-4 h-[60vh] overflow-y-auto mb-4">
        <div v-for="(chat, index) in chats" :key="index" class="mb-2">
          <div :class="chat.role === 'user' ? 'text-right' : 'text-left'">
            <p class="whitespace-pre-line"><strong>{{ chat.role }}</strong>: {{ chat.content }}</p>
          </div>
        </div>
      </div>
      <form @submit.prevent="sendMessage" class="flex space-x-2">
        <Input v-model="message" placeholder="Type your message..." class="flex-1" />
        <Button type="submit" :disabled="loading">Send</Button>
      </form>
      <div v-if="error" class="text-red-500 mt-2">{{ error }}</div>
    </div>
  </DefaultLayout>
</template>
