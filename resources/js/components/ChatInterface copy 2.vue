<script setup>
import { marked } from "marked";
import { ref, onMounted, nextTick, watch, onUnmounted } from "vue";
import api from "@/services/api";

const emit = defineEmits(["responseReceived"]);

const props = defineProps({
    currentArticle: {
        type: Object,
        default: null,
    },
    selectedContent: {
        type: String,
        default: null,
    },
});

const conversationId = ref(null);
const chats = ref([]);
const newMessage = ref("");
const loading = ref(false);
const messagesContainer = ref(null);
const pollingInterval = ref(null);

// Context management as a reactive ref
const context = ref({
    viewing_article_id: null,
    viewing_article_title: null,
    selected_content: null,
});

const renderMarkdown = (content) => {
    return marked.parse(content || "");
};

const loadLatestConversation = async () => {
    try {
        const response = await api.get("/conversations");
        if (response.data.length > 0) {
            const latestConversation = response.data[0];
            conversationId.value = latestConversation.id;

            // Load the conversation details including chats
            const conversationResponse = await api.get(
                `/conversations/${latestConversation.id}`
            );
            chats.value = conversationResponse.data.chats || [];
        } else {
            // No conversations exist, create a new one
            await createConversation();
        }

        await nextTick();
        scrollToBottom();
    } catch (error) {
        console.error("Error loading latest conversation:", error);
        // Fallback to creating a new conversation
        await createConversation();
    }
};

const createConversation = async () => {
    // Initialize context when creating conversation
    updateContextFromProps();

    const response = await api.post("/conversations", {
        title: "New Chat",
        context: context.value,
    });
    console.log("Create conversation", response);

    conversationId.value = response.data.id;
    chats.value = [];
};

const updateContextFromProps = () => {
    console.log("Updating context from props", props);

    if (props.currentArticle) {
        context.value.viewing_article_id = props.currentArticle.id;
        context.value.viewing_article_title = props.currentArticle.title;
    } else {
        context.value.viewing_article_id = null;
        context.value.viewing_article_title = null;
    }

    context.value.selected_content = props.selectedContent || null;
};

const updateContext = async () => {
    if (!conversationId.value) return;

    updateContextFromProps();

    await api.put(`/conversations/${conversationId.value}/context`, {
        context: context.value,
    });
};

const pollForUpdates = async () => {
    if (!conversationId.value) return;

    try {
        const response = await api.get(
            `/conversations/${conversationId.value}`
        );
        const newChats = response.data.chats || [];

        if (newChats.length !== chats.value.length) {
            chats.value = newChats;
            await nextTick();
            scrollToBottom();

            // Check if assistant has finished responding
            const lastChat = newChats[newChats.length - 1];
            if (lastChat && lastChat.type === "assistant") {
                stopPolling();
                loading.value = false;
                emit("responseReceived");
            }
        }
    } catch (error) {
        console.error("Error polling for updates:", error);
    }
};

const startPolling = () => {
    if (pollingInterval.value) return;
    pollingInterval.value = setInterval(pollForUpdates, 1000);
};

const stopPolling = () => {
    if (pollingInterval.value) {
        clearInterval(pollingInterval.value);
        pollingInterval.value = null;
    }
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

        // Start polling for updates
        startPolling();
    } catch (error) {
        console.error("Error sending message:", error);
        newMessage.value = message; // Restore message on error
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
        case "reasoning":
            return "Reasoning";
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

// Clear selected content
const clearSelectedContent = () => {
    console.log("clearing selected content...");
    context.value.selected_content = null;
    updateContext();
};

onMounted(() => {
    loadLatestConversation();
});

onUnmounted(() => {
    stopPolling();
});

// Watch for changes in current article
watch(
    () => props.currentArticle,
    () => {
        updateContext();
    }
);

// Watch for changes in selected content
watch(
    () => props.selectedContent,
    () => {
        updateContext();
    }
);
</script>

<template>
    <div class="h-full flex flex-col">
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Header -->
            <div
                class="flex justify-between items-start p-4 bg-white border-b border-gray-200"
            >
                <div>
                    <h3 class="text-lg font-semibold text-gray-800">
                        Conversation {{ conversationId }}
                    </h3>
                    <p class="text-sm text-gray-500">
                        Ask agent to create, read, edit, research or outline an
                        article. Agent knows which article you are viewing.
                    </p>
                </div>
                <button
                    @click="newConversation"
                    class="px-3 py-1 bg-green-500 hover:bg-green-600 text-white text-sm font-medium rounded-md transition-colors cursor-pointer"
                >
                    New
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
                            'max-w-[90%] rounded-lg px-4 py-2',
                            chat.type === 'user'
                                ? 'bg-blue-500 text-white'
                                : chat.type === 'assistant'
                                ? 'bg-white shadow-sm'
                                : chat.type === 'reasoning'
                                ? 'bg-purple-50 border border-purple-200 shadow-sm'
                                : 'bg-gray-100 border-l-4 border-gray-400 italic',
                        ]"
                    >
                        <div
                            :class="[
                                'text-xs font-semibold mb-1 flex items-center gap-1',
                                chat.type === 'user'
                                    ? 'text-blue-100'
                                    : chat.type === 'reasoning'
                                    ? 'text-purple-600'
                                    : 'text-gray-500',
                            ]"
                        >
                            <!-- Thinking icon for reasoning -->
                            <svg
                                v-if="chat.type === 'reasoning'"
                                class="w-3 h-3"
                                fill="currentColor"
                                viewBox="0 0 20 20"
                            >
                                <path
                                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                                />
                            </svg>
                            {{ getRoleLabel(chat.type) }}
                        </div>
                        <div
                            :class="[
                                chat.type === 'assistant'
                                    ? 'markdown-content'
                                    : '',
                                chat.type === 'reasoning'
                                    ? 'text-purple-800 text-sm'
                                    : '',
                            ]"
                            v-html="renderMarkdown(chat.content)"
                        ></div>

                        <!-- Annotations section -->
                        <div
                            v-if="
                                chat.annotations && chat.annotations.length > 0
                            "
                            class="mt-3 pt-3 border-t border-gray-200"
                        >
                            <div
                                class="text-xs font-semibold text-gray-500 mb-2"
                            >
                                Sources:
                            </div>
                            <div class="space-y-1">
                                <a
                                    v-for="(
                                        annotation, index
                                    ) in chat.annotations"
                                    :key="index"
                                    :href="annotation.url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="block text-xs text-blue-600 hover:text-blue-800 hover:underline"
                                >
                                    {{ annotation.title }}
                                </a>
                            </div>
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

            <!-- Context Indicators -->
            <div
                v-if="context.viewing_article_id || context.selected_content"
                class="px-4 py-2 bg-blue-50 border-b border-blue-200"
            >
                <div class="text-xs text-blue-600 font-medium mb-1">
                    Context:
                </div>
                <div
                    v-if="context.viewing_article_id"
                    class="text-xs text-blue-600 mb-1"
                >
                    Viewing: {{ context.viewing_article_title }}
                </div>
                <div
                    v-if="context.selected_content"
                    class="flex items-start gap-2"
                >
                    <div class="text-xs text-blue-600 flex-1">
                        Selected: "{{
                            context.selected_content.length > 100
                                ? context.selected_content.substring(0, 100) +
                                  "..."
                                : context.selected_content
                        }}"
                    </div>
                    <button
                        @click="clearSelectedContent"
                        class="size-5 rounded-md bg-blue-100 text-blue-400 hover:text-blue-600 text-sm cursor-pointer"
                        title="Clear selected content"
                    >
                        ✕
                    </button>
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
