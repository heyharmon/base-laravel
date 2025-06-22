<script setup>
import { ref, onMounted, onBeforeUnmount } from "vue";
import api from "@/services/api";
import DefaultLayout from "@/layouts/DefaultLayout.vue";

const conversationId = ref(null);
const conversation = ref({});
const chats = ref([]);
const articles = ref([]);
const stats = ref({});
const newMessage = ref("");
const sending = ref(false);
let pollInterval = null;

const loadStats = async () => {
    if (!conversationId.value) return;
    stats.value = await api.get(`/conversations/${conversationId.value}/stats`);
};

const loadConversation = async () => {
    if (!conversationId.value) return;
    const data = await api.get(`/conversations/${conversationId.value}`);
    conversation.value = data.conversation;
    chats.value = data.conversation.chats;
    articles.value = data.conversation.articles;
    await loadStats();
};

const sendMessage = async () => {
    if (!newMessage.value.trim() || sending.value) return;
    sending.value = true;
    try {
        if (!conversationId.value) {
            const resp = await api.post("/conversations", {
                title: "Research Agent Chat",
                initial_message: newMessage.value,
            });
            conversationId.value = resp.conversation.id;
        } else {
            await api.post(`/conversations/${conversationId.value}/message`, {
                message: newMessage.value,
            });
        }
        newMessage.value = "";
        await loadConversation();
    } finally {
        sending.value = false;
    }
};

const startPolling = () => {
    pollInterval = setInterval(loadConversation, 5000);
};

const stopPolling = () => {
    if (pollInterval) clearInterval(pollInterval);
};

onMounted(() => {
    startPolling();
});

onBeforeUnmount(() => {
    stopPolling();
});
</script>

<template>
    <DefaultLayout>
        <div class="conversation space-y-6">
            <div class="header">
                <h1 class="text-3xl font-bold text-gray-900">Research Agent Chat</h1>
                <p class="text-gray-600 mt-2">Chat with the AI research agent to get help with your tasks</p>
            </div>

            <div v-if="conversationId" class="stats bg-white border border-gray-200 rounded-lg p-4">
                <div class="flex flex-wrap gap-4 text-sm text-gray-600">
                    <span class="flex items-center">
                        <span class="font-medium text-gray-900">Tokens:</span> 
                        <span class="ml-1">{{ stats.total_tokens || 0 }}</span>
                    </span>
                    <span class="flex items-center">
                        <span class="font-medium text-gray-900">Cost:</span> 
                        <span class="ml-1">${{ stats.total_cost || '0.00' }}</span>
                    </span>
                    <span class="flex items-center">
                        <span class="font-medium text-gray-900">Active Jobs:</span> 
                        <span class="ml-1">{{ stats.active_jobs || 0 }}</span>
                    </span>
                </div>
            </div>

            <div v-if="stats.plan" class="agent-plan bg-white border border-gray-200 rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-3">Current Plan</h3>
                <div class="bg-gray-50 border border-gray-200 rounded p-4 max-h-64 overflow-y-auto">
                    <pre class="whitespace-pre-wrap text-sm font-mono text-gray-700">{{
                        JSON.stringify(stats.plan, null, 2)
                    }}</pre>
                </div>
            </div>

            <div class="chat-history space-y-4">
                <div v-for="chat in chats" :key="chat.id" class="flex">
                    <div
                        :class="[
                            'p-4 rounded-lg max-w-2xl shadow-sm border transition-shadow hover:shadow-md',
                            chat.role === 'user'
                                ? 'ml-auto bg-blue-50 border-blue-200'
                                : 'mr-auto bg-white border-gray-200',
                        ]"
                    >
                        <p class="whitespace-pre-wrap text-gray-800 leading-relaxed">{{ chat.content }}</p>
                        <div
                            v-if="chat.function_name"
                            class="text-xs text-gray-500 mt-2 pt-2 border-t border-gray-100"
                        >
                            <span class="inline-flex items-center px-2 py-1 rounded-full bg-gray-100">
                                Running {{ chat.function_name }} ({{ chat.job_status || "pending" }})
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="sending" class="loading text-center py-4">
                <div class="inline-block animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600"></div>
                <p class="mt-2 text-gray-600 text-sm">Sending message...</p>
            </div>

            <div class="message-input bg-white border border-gray-200 rounded-lg p-4">
                <div class="flex gap-3">
                    <textarea
                        v-model="newMessage"
                        rows="3"
                        class="flex-1 px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"
                        placeholder="Type your message... (Ctrl+Enter to send)"
                        @keyup.enter.ctrl="sendMessage"
                    ></textarea>
                    <button
                        @click="sendMessage"
                        :disabled="sending || !newMessage.trim()"
                        class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:bg-gray-400 disabled:cursor-not-allowed transition-colors self-end"
                    >
                        <span v-if="sending">Sending...</span>
                        <span v-else>Send</span>
                    </button>
                </div>
            </div>
        </div>
    </DefaultLayout>
</template>
