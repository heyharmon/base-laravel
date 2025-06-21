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
        <div class="conversation space-y-4">
            <h1 class="text-2xl font-bold">Research Agent Chat</h1>

            <div v-if="conversationId" class="stats text-sm space-x-4">
                <span>Tokens: {{ stats.total_tokens }}</span>
                <span>Cost: ${{ stats.total_cost }}</span>
                <span>Active Jobs: {{ stats.active_jobs }}</span>
            </div>

            <div
                v-if="stats.plan"
                class="agent-plan bg-neutral-100 p-2 rounded"
            >
                <h3 class="font-semibold mb-1">Current Plan</h3>
                <pre class="whitespace-pre-wrap">{{
                    JSON.stringify(stats.plan, null, 2)
                }}</pre>
            </div>

            <div class="chat-history space-y-2">
                <div v-for="chat in chats" :key="chat.id" class="flex">
                    <div
                        :class="[
                            'p-2 rounded',
                            chat.role === 'user'
                                ? 'ml-auto bg-blue-100'
                                : 'mr-auto bg-green-100',
                        ]"
                        class="max-w-xl"
                    >
                        <p class="whitespace-pre-wrap">{{ chat.content }}</p>
                        <div
                            v-if="chat.function_name"
                            class="text-xs text-neutral-500 mt-1"
                        >
                            Running {{ chat.function_name }} ({{
                                chat.job_status || "pending"
                            }})
                        </div>
                    </div>
                </div>
            </div>

            <div class="message-input flex items-end space-x-2">
                <textarea
                    v-model="newMessage"
                    rows="2"
                    class="flex-grow border rounded p-2"
                    placeholder="Type your message"
                    @keyup.enter.ctrl="sendMessage"
                ></textarea>
                <button
                    @click="sendMessage"
                    :disabled="sending"
                    class="bg-blue-500 text-white px-4 py-2 rounded"
                >
                    Send
                </button>
            </div>
        </div>
    </DefaultLayout>
</template>
