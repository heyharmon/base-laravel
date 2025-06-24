<script setup>
import { ref, onMounted, nextTick, watch } from "vue";
import api from "@/services/api";

const props = defineProps({
    currentArticle: {
        type: Object,
        default: null,
    },
});

const conversationId = ref(null);
const chats = ref([]);
const newMessage = ref("");
const loading = ref(false);
const messagesContainer = ref(null);

const loadLatestConversation = async () => {
    try {
        const response = await api.get("/conversations");
        if (response.data.length > 0) {
            const latestConversation = response.data[0];
            conversationId.value = latestConversation.id;
            
            // Load the conversation details including chats
            const conversationResponse = await api.get(`/conversations/${latestConversation.id}`);
            chats.value = conversationResponse.data.chats || [];
        } else {
            // No conversations exist, create a new one
            await createConversation();
        }
    } catch (error) {
        console.error("Error loading latest conversation:", error);
        // Fallback to creating a new conversation
        await createConversation();
    }
};

const createConversation = async () => {
    const context = {};
    if (props.currentArticle) {
        context.viewing_article_id = props.currentArticle.id;
        context.viewing_article_title = props.currentArticle.title;
    }

    const response = await api.post("/conversations", {
        title: "New Chat",
        context,
    });
    console.log("Create conversation", response);

    conversationId.value = response.data.id;
    chats.value = [];
};

const updateContext = async () => {
    if (!conversationId.value) return;

    const context = {};
    if (props.currentArticle) {
        context.viewing_article_id = props.currentArticle.id;
        context.viewing_article_title = props.currentArticle.title;
    }

    await api.put(`/conversations/${conversationId.value}/context`, {
        context,
    });
};

const sendMessage = async () => {
    if (!newMessage.value.trim() || loading.value) return;

    loading.value = true;
    const message = newMessage.value;
    newMessage.value = "";

    try {
        const response = await api.post(
            `/conversations/${conversationId.value}/messages`,
            { message }
        );

        chats.value = response.data.chats;
        await nextTick();
        scrollToBottom();
    } catch (error) {
        console.error("Error sending message:", error);
        newMessage.value = message; // Restore message on error
        alert("Failed to send message. Please try again.");
    } finally {
        loading.value = false;
    }
};

const newConversation = () => {
    createConversation();
};

const getRoleLabel = (type) => {
    switch (type) {
        case "user":
            return "You";
        case "assistant":
            return "Assistant";
        case "tool_call":
            return "Tool";
        default:
            return type;
    }
};

const scrollToBottom = () => {
    if (messagesContainer.value) {
        messagesContainer.value.scrollTop =
            messagesContainer.value.scrollHeight;
    }
};

onMounted(() => {
    loadLatestConversation();
});

watch(
    () => props.currentArticle,
    () => {
        updateContext();
    }
);
</script>

<template>
    <div class="h-full flex flex-col">
        <p>Conversation ID: {{ conversationId }}</p>
        <div class="flex-1 flex flex-col bg-gray-50 rounded-lg overflow-hidden">
            <!-- Header -->
            <div
                class="flex justify-between items-center p-4 bg-white border-b border-gray-200"
            >
                <h3 class="text-lg font-semibold text-gray-800">
                    AI Assistant
                </h3>
                <button
                    @click="newConversation"
                    class="px-4 py-2 bg-green-500 hover:bg-green-600 text-white text-sm font-medium rounded-md transition-colors"
                >
                    New Chat
                </button>
            </div>

            <!-- Messages -->
            <div
                ref="messagesContainer"
                class="flex-1 overflow-y-auto p-4 space-y-4"
            >
                <div
                    v-for="chat in chats"
                    :key="chat.id"
                    :class="[
                        'flex',
                        chat.type === 'user' ? 'justify-end' : 'justify-start',
                    ]"
                >
                    <div
                        :class="[
                            'max-w-[70%] rounded-lg px-4 py-2',
                            chat.type === 'user'
                                ? 'bg-blue-500 text-white'
                                : chat.type === 'assistant'
                                ? 'bg-white shadow-sm'
                                : 'bg-gray-100 border-l-4 border-gray-400 italic',
                        ]"
                    >
                        <div
                            :class="[
                                'text-xs font-semibold mb-1',
                                chat.type === 'user'
                                    ? 'text-blue-100'
                                    : 'text-gray-500',
                            ]"
                        >
                            {{ getRoleLabel(chat.type) }}
                        </div>
                        <div class="whitespace-pre-wrap">
                            {{ chat.content }}
                        </div>
                    </div>
                </div>

                <!-- Loading indicator -->
                <div v-if="loading" class="flex justify-start">
                    <div class="bg-white shadow-sm rounded-lg px-4 py-2">
                        <div class="flex items-center space-x-2">
                            <div
                                class="animate-bounce w-2 h-2 bg-gray-400 rounded-full"
                            ></div>
                            <div
                                class="animate-bounce w-2 h-2 bg-gray-400 rounded-full"
                                style="animation-delay: 0.1s"
                            ></div>
                            <div
                                class="animate-bounce w-2 h-2 bg-gray-400 rounded-full"
                                style="animation-delay: 0.2s"
                            ></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Input -->
            <div class="flex p-4 bg-white border-t border-gray-200">
                <textarea
                    v-model="newMessage"
                    @keydown.enter.prevent="sendMessage"
                    placeholder="Type your message..."
                    rows="3"
                    class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"
                ></textarea>
                <button
                    @click="sendMessage"
                    :disabled="!newMessage.trim() || loading"
                    class="ml-2 px-4 py-2 bg-blue-500 hover:bg-blue-600 disabled:bg-blue-300 disabled:cursor-not-allowed text-white font-medium rounded-md transition-colors"
                >
                    <span v-if="loading">Sending...</span>
                    <span v-else>Send</span>
                </button>
            </div>
        </div>
    </div>
</template>
