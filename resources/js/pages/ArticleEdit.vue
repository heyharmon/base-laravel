<script setup>
import { ref, onMounted, onBeforeUnmount, computed } from "vue";
import { useRoute, useRouter } from "vue-router";
import api from "@/services/api";
import DefaultLayout from "@/layouts/DefaultLayout.vue";

const route = useRoute();
const router = useRouter();

// Article state
const article = ref({
    title: "",
    content: "",
    outline: [],
    status: "planning",
});
const loading = ref(false);
const saving = ref(false);
const error = ref(null);
const successMessage = ref("");

// Chat state
const conversationId = ref(null);
const conversation = ref({});
const chats = ref([]);
const stats = ref({});
const newMessage = ref("");
const sending = ref(false);
const showChat = ref(true);
let pollInterval = null;

const statusOptions = [
    { value: "planning", label: "Planning" },
    { value: "researching", label: "Researching" },
    { value: "writing", label: "Writing" },
    { value: "reviewing", label: "Reviewing" },
    { value: "completed", label: "Completed" },
];

// Computed property for article ID
const articleId = computed(() => route.params.id);

// Article methods
const loadArticle = async () => {
    loading.value = true;
    error.value = null;

    try {
        const articleData = await api.get(`/articles/${articleId.value}`);
        article.value = {
            ...articleData,
            outline: articleData.outline || [],
        };

        // Check if there's an existing conversation for this article
        await checkExistingConversation();
    } catch (err) {
        error.value = "Failed to load article";
        console.error("Error loading article:", err);
    } finally {
        loading.value = false;
    }
};

const saveArticle = async () => {
    saving.value = true;
    error.value = null;
    successMessage.value = "";

    try {
        const response = await api.put(`/articles/${articleId.value}`, {
            title: article.value.title,
            content: article.value.content,
            outline: article.value.outline.length
                ? article.value.outline
                : null,
            status: article.value.status,
        });

        successMessage.value = "Article saved successfully!";

        setTimeout(() => {
            successMessage.value = "";
        }, 3000);
    } catch (err) {
        error.value = "Failed to save article";
        console.error("Error saving article:", err);
    } finally {
        saving.value = false;
    }
};

const addOutlineItem = () => {
    article.value.outline.push("");
};

const removeOutlineItem = (index) => {
    article.value.outline.splice(index, 1);
};

const goBack = () => {
    router.push("/articles");
};

// Chat methods
const checkExistingConversation = async () => {
    // In a real implementation, you might want to store the conversation ID
    // associated with each article in the database
    // For now, we'll create a new conversation for each article edit session
};

const loadStats = async () => {
    if (!conversationId.value) return;
    try {
        stats.value = await api.get(
            `/conversations/${conversationId.value}/stats`
        );
    } catch (err) {
        console.error("Error loading stats:", err);
    }
};

const loadConversation = async () => {
    if (!conversationId.value) return;
    try {
        const data = await api.get(`/conversations/${conversationId.value}`);
        conversation.value = data.conversation;
        chats.value = data.conversation.chats;
        await loadStats();

        // Auto-refresh article content if it was updated by the agent
        await loadArticle();
    } catch (err) {
        console.error("Error loading conversation:", err);
    }
};

const sendMessage = async () => {
    if (!newMessage.value.trim() || sending.value) return;
    sending.value = true;

    try {
        if (!conversationId.value) {
            // Create a new conversation with article context
            const resp = await api.post("/conversations", {
                title: `Article Assistant: ${article.value.title}`,
                initial_message: newMessage.value,
                context: {
                    article_id: parseInt(articleId.value),
                    article_title: article.value.title,
                    article_status: article.value.status,
                },
            });
            conversationId.value = resp.conversation.id;
        } else {
            // Send message with article context
            await api.post(`/conversations/${conversationId.value}/message`, {
                message: newMessage.value,
                context: {
                    article_id: parseInt(articleId.value),
                    article_title: article.value.title,
                    article_status: article.value.status,
                },
            });
        }
        newMessage.value = "";
        await loadConversation();
    } catch (err) {
        console.error("Error sending message:", err);
        error.value = "Failed to send message";
    } finally {
        sending.value = false;
    }
};

const toggleChat = () => {
    showChat.value = !showChat.value;
};

const startPolling = () => {
    pollInterval = setInterval(loadConversation, 5000);
};

const stopPolling = () => {
    if (pollInterval) clearInterval(pollInterval);
};

onMounted(() => {
    if (articleId.value) {
        loadArticle();
        startPolling();
    }
});

onBeforeUnmount(() => {
    stopPolling();
});
</script>

<template>
    <DefaultLayout>
        <div class="article-edit-container flex gap-6">
            <!-- AI Assistant Chat Panel -->
            <div
                v-if="showChat"
                class="chat-panel w-96 bg-white rounded-lg shadow-lg flex flex-col h-[calc(100vh-8rem)] sticky top-4"
            >
                <div
                    class="chat-header bg-purple-600 text-white p-4 rounded-t-lg"
                >
                    <h2 class="text-lg font-semibold">AI Writing Assistant</h2>
                    <p class="text-sm text-purple-100 mt-1">
                        Ask me to help write, review, or research for this
                        article
                    </p>
                </div>

                <!-- Stats -->
                <div
                    v-if="conversationId && stats.total_tokens"
                    class="stats bg-gray-50 border-b border-gray-200 p-3"
                >
                    <div class="flex flex-wrap gap-3 text-xs text-gray-600">
                        <span>
                            <span class="font-medium">Tokens:</span>
                            {{ stats.total_tokens || 0 }}
                        </span>
                        <span>
                            <span class="font-medium">Cost:</span> ${{
                                stats.total_cost || "0.00"
                            }}
                        </span>
                        <span
                            v-if="stats.active_jobs > 0"
                            class="text-orange-600"
                        >
                            <span class="font-medium">Active:</span>
                            {{ stats.active_jobs }}
                        </span>
                    </div>
                </div>

                <!-- Chat History -->
                <div class="chat-history flex-1 overflow-y-auto p-4 space-y-3">
                    <div
                        v-if="!conversationId"
                        class="text-center text-gray-500 py-8"
                    >
                        <p class="text-sm">
                            Start a conversation to get AI assistance with your
                            article.
                        </p>
                        <p class="text-xs mt-2">The AI can help you:</p>
                        <ul class="text-xs mt-2 space-y-1">
                            <li>• Research topics</li>
                            <li>• Write sections</li>
                            <li>• Review content</li>
                            <li>• Improve structure</li>
                        </ul>
                    </div>
                    <div v-for="chat in chats" :key="chat.id" class="flex">
                        <div
                            :class="[
                                'p-3 rounded-lg max-w-[85%] text-sm',
                                chat.role === 'user'
                                    ? 'ml-auto bg-purple-50 border border-purple-200'
                                    : 'mr-auto bg-white border border-gray-200',
                            ]"
                        >
                            <p class="whitespace-pre-wrap text-gray-800">
                                {{ chat.content }}
                            </p>
                            <div
                                v-if="chat.function_name"
                                class="text-xs text-gray-500 mt-2 pt-2 border-t border-gray-100"
                            >
                                <span
                                    class="inline-flex items-center px-2 py-1 rounded-full bg-gray-100"
                                >
                                    {{ chat.function_name }} ({{
                                        chat.job_status || "pending"
                                    }})
                                </span>
                            </div>
                        </div>
                    </div>
                    <div v-if="sending" class="flex">
                        <div
                            class="mr-auto bg-white border border-gray-200 p-3 rounded-lg"
                        >
                            <div class="flex items-center space-x-2">
                                <div
                                    class="animate-spin rounded-full h-4 w-4 border-b-2 border-purple-600"
                                ></div>
                                <span class="text-sm text-gray-600"
                                    >AI is thinking...</span
                                >
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Message Input -->
                <div class="message-input border-t border-gray-200 p-4">
                    <div class="flex gap-2">
                        <textarea
                            v-model="newMessage"
                            rows="3"
                            class="flex-1 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent resize-none"
                            placeholder="Ask AI to help with this article..."
                            @keyup.enter.ctrl="sendMessage"
                        ></textarea>
                        <button
                            @click="sendMessage"
                            :disabled="sending || !newMessage.trim()"
                            class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 disabled:bg-gray-400 disabled:cursor-not-allowed transition-colors self-end text-sm"
                        >
                            Send
                        </button>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">Ctrl+Enter to send</p>
                </div>
            </div>

            <!-- Main Article Edit Area -->
            <div class="article-edit flex-1 space-y-6">
                <div class="flex justify-between items-center">
                    <h1 class="text-3xl font-bold">Edit Article</h1>
                    <div class="flex gap-2">
                        <button
                            @click="toggleChat"
                            class="bg-purple-500 hover:bg-purple-600 text-white px-4 py-2 rounded"
                        >
                            {{ showChat ? "Hide" : "Show" }} AI Assistant
                        </button>
                        <button
                            @click="goBack"
                            class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded"
                        >
                            Back to Articles
                        </button>
                    </div>
                </div>

                <div
                    v-if="error"
                    class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded"
                >
                    {{ error }}
                </div>

                <div
                    v-if="successMessage"
                    class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded"
                >
                    {{ successMessage }}
                </div>

                <!-- <div v-if="loading" class="text-center py-8">
                    <div class="text-gray-500">Loading article...</div>
                </div> -->

                <form @submit.prevent="saveArticle" class="space-y-6">
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <div class="space-y-4">
                            <!-- Title -->
                            <div>
                                <label
                                    for="title"
                                    class="block text-sm font-medium text-gray-700 mb-2"
                                >
                                    Title
                                </label>
                                <input
                                    id="title"
                                    v-model="article.title"
                                    type="text"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    placeholder="Enter article title"
                                    required
                                />
                            </div>

                            <!-- Status -->
                            <div>
                                <label
                                    for="status"
                                    class="block text-sm font-medium text-gray-700 mb-2"
                                >
                                    Status
                                </label>
                                <select
                                    id="status"
                                    v-model="article.status"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                >
                                    <option
                                        v-for="option in statusOptions"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </option>
                                </select>
                            </div>

                            <!-- Outline -->
                            <div>
                                <div
                                    class="flex justify-between items-center mb-2"
                                >
                                    <label
                                        class="block text-sm font-medium text-gray-700"
                                    >
                                        Outline
                                    </label>
                                    <button
                                        type="button"
                                        @click="addOutlineItem"
                                        class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-sm"
                                    >
                                        Add Item
                                    </button>
                                </div>
                                <div
                                    v-if="article.outline.length"
                                    class="space-y-2"
                                >
                                    <div
                                        v-for="(item, index) in article.outline"
                                        :key="index"
                                        class="flex items-center space-x-2"
                                    >
                                        <input
                                            v-model="article.outline[index]"
                                            type="text"
                                            class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                            :placeholder="`Outline item ${
                                                index + 1
                                            }`"
                                        />
                                        <button
                                            type="button"
                                            @click="removeOutlineItem(index)"
                                            class="bg-red-500 hover:bg-red-600 text-white px-2 py-2 rounded text-sm"
                                        >
                                            Remove
                                        </button>
                                    </div>
                                </div>
                                <div v-else class="text-gray-500 text-sm">
                                    No outline items. Click "Add Item" to create
                                    an outline.
                                </div>
                            </div>

                            <!-- Content -->
                            <div>
                                <label
                                    for="content"
                                    class="block text-sm font-medium text-gray-700 mb-2"
                                >
                                    Content
                                </label>
                                <textarea
                                    id="content"
                                    v-model="article.content"
                                    rows="15"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    placeholder="Write your article content here..."
                                ></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end space-x-4">
                        <button
                            type="button"
                            @click="goBack"
                            class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="saving"
                            class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded disabled:opacity-50"
                        >
                            {{ saving ? "Saving..." : "Save Article" }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </DefaultLayout>
</template>

<style scoped>
.article-edit-container {
    min-height: calc(100vh - 8rem);
}

.chat-panel {
    max-height: calc(100vh - 8rem);
}

.chat-history {
    scrollbar-width: thin;
    scrollbar-color: #cbd5e0 #f7fafc;
}

.chat-history::-webkit-scrollbar {
    width: 6px;
}

.chat-history::-webkit-scrollbar-track {
    background: #f7fafc;
}

.chat-history::-webkit-scrollbar-thumb {
    background-color: #cbd5e0;
    border-radius: 3px;
}
</style>
