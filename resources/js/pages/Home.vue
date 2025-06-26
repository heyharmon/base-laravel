<script setup>
import { ref, computed, onMounted, onUnmounted } from "vue";
import api from "@/services/api";
import ChatInterface from "@/components/ChatInterface.vue";
import { marked } from "marked";

const articles = ref([]);
const currentArticle = ref(null);
const selectedContent = ref(null); // Persistent context content
const currentSelection = ref(null); // Current visual selection
const showAddToChatTooltip = ref(false);
const tooltipPosition = ref({ x: 0, y: 0 });

const loadArticles = async () => {
    try {
        const response = await api.get("/articles");
        articles.value = response.data;

        // Automatically select the latest article if articles exist
        if (articles.value.length > 0) {
            await selectArticle(articles.value[0]);
        }
    } catch (error) {
        console.error("Error loading articles:", error);
    }
};

const selectArticle = async (article) => {
    try {
        const response = await api.get(`/articles/${article.id}`);
        currentArticle.value = response.data;

        // Clear any selected content when switching articles
        clearSelectedContent();
    } catch (error) {
        console.error("Error loading article:", error);
    }
};

const parsedContent = computed(() => {
    return currentArticle.value?.content
        ? marked(currentArticle.value.content)
        : "";
});

const handleResponseReceived = async () => {
    await loadArticles();

    // If there's a current article selected, reload it
    if (currentArticle.value) {
        try {
            const response = await api.get(
                `/articles/${currentArticle.value.id}`
            );
            currentArticle.value = response.data;
        } catch (error) {
            console.error("Error reloading current article:", error);
        }
    }
};

const createNewArticle = async () => {
    try {
        const response = await api.post("/articles", {
            title: "New Article",
            content: "",
        });

        await loadArticles();
        await selectArticle(response.data);
    } catch (error) {
        console.error("Error creating new article:", error);
    }
};

const handleTextSelection = (event) => {
    // Only handle selections within the article content area
    if (!event.target.closest(".article-content")) {
        return;
    }

    setTimeout(() => {
        const selection = window.getSelection();
        const selectedText = selection.toString().trim();

        if (selectedText.length > 0) {
            currentSelection.value = selectedText;

            // Get selection position for tooltip
            try {
                const range = selection.getRangeAt(0);
                const rect = range.getBoundingClientRect();

                tooltipPosition.value = {
                    x: rect.left + rect.width / 2,
                    y: rect.top - 10,
                };

                showAddToChatTooltip.value = true;
            } catch (error) {
                console.error("Error getting selection position:", error);
            }
        } else {
            clearCurrentSelection();
        }
    }, 10); // Small delay to ensure selection is complete
};

const addSelectedToChat = () => {
    if (!currentSelection.value) return;

    // Move current selection to persistent context
    selectedContent.value = currentSelection.value;

    // Clear the visual selection and tooltip
    clearCurrentSelection();
};

const clearCurrentSelection = () => {
    showAddToChatTooltip.value = false;
    currentSelection.value = null;
    window.getSelection().removeAllRanges();
};

const clearSelectedContent = () => {
    // Clear both the persistent context and current selection
    selectedContent.value = null;
    clearCurrentSelection();
};

const handleClickOutside = (event) => {
    // Only clear current selection if clicking outside of:
    // 1. The tooltip
    // 2. The article content area
    // 3. The entire chat interface area
    if (
        !event.target.closest(".add-to-chat-tooltip") &&
        !event.target.closest(".article-content") &&
        !event.target.closest(".chat-panel")
    ) {
        clearCurrentSelection();
    }
};

const handleKeyUp = (event) => {
    // Handle keyboard selection (shift + arrow keys)
    if (event.shiftKey || event.ctrlKey || event.metaKey) {
        handleTextSelection(event);
    }

    // Clear current selection on Escape key
    if (event.key === "Escape") {
        clearCurrentSelection();
    }
};

onMounted(() => {
    loadArticles();

    // Add event listeners for text selection
    document.addEventListener("mouseup", handleTextSelection);
    document.addEventListener("keyup", handleKeyUp);
    document.addEventListener("click", handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener("mouseup", handleTextSelection);
    document.removeEventListener("keyup", handleKeyUp);
    document.removeEventListener("click", handleClickOutside);
});
</script>

<template>
    <div class="flex h-screen bg-gray-100">
        <!-- Chat Panel -->
        <div class="chat-panel w-[28rem] border-r border-gray-200 bg-gray-50">
            <ChatInterface
                :current-article="currentArticle"
                :selected-content="selectedContent"
                @response-received="handleResponseReceived"
                @clear-selected-content="clearSelectedContent"
            />
        </div>

        <!-- Main Content -->
        <div class="flex-1 flex overflow-hidden relative">
            <!-- Article View -->
            <div class="flex-1 overflow-y-auto bg-white">
                <div v-if="currentArticle">
                    <h1 class="text-3xl font-bold text-gray-800 py-6 p-8">
                        {{ currentArticle.title }}
                    </h1>
                    <div
                        class="article-content markdown-content text-gray-600 max-w-none select-text cursor-text px-8"
                        v-html="currentArticle.content"
                    ></div>
                </div>
                <div v-else class="flex items-center justify-center h-full">
                    <p class="text-gray-500 text-lg">
                        Select an article to view
                    </p>
                </div>
            </div>

            <!-- Add to Chat Tooltip -->
            <Teleport to="body">
                <div
                    v-if="showAddToChatTooltip && currentSelection"
                    class="add-to-chat-tooltip fixed z-50 bg-black text-white text-sm px-3 py-2 rounded-md shadow-lg pointer-events-auto"
                    :style="{
                        left: tooltipPosition.x + 'px',
                        top: tooltipPosition.y + 'px',
                        transform: 'translateX(-50%) translateY(-100%)',
                    }"
                >
                    <button
                        @click="addSelectedToChat"
                        class="hover:bg-gray-700 px-2 py-1 rounded transition-colors"
                    >
                        📎 Add to chat
                    </button>
                    <button
                        @click="clearCurrentSelection"
                        class="hover:bg-gray-700 px-2 py-1 rounded ml-2 transition-colors"
                        title="Clear selection"
                    >
                        ✕
                    </button>
                    <!-- Tooltip arrow -->
                    <div
                        class="absolute top-full left-1/2 transform -translate-x-1/2 w-0 h-0 border-l-4 border-r-4 border-t-4 border-transparent border-t-black"
                    ></div>
                </div>
            </Teleport>
        </div>

        <!-- Sidebar -->
        <div class="w-64 bg-white shadow-md">
            <div class="p-4">
                <div class="flex justify-between items-center">
                    <h2 class="text-xl font-bold text-gray-800">Articles</h2>
                    <button
                        @click="createNewArticle"
                        class="px-3 py-1 border border-gray-300 text-gray-700 hover:bg-gray-100 cursor-pointer text-sm font-medium rounded-md transition-colors"
                    >
                        New
                    </button>
                </div>
            </div>
            <div class="overflow-y-auto max-h-[calc(100vh-5rem)]">
                <div class="px-2 pb-4">
                    <button
                        v-for="article in articles"
                        :key="article.id"
                        @click="selectArticle(article)"
                        :class="[
                            'w-full text-left px-3 py-2 rounded-md mb-1 transition-colors',
                            currentArticle?.id === article.id
                                ? 'bg-blue-500 text-white'
                                : 'hover:bg-gray-100 text-gray-700',
                        ]"
                    >
                        {{ article.title }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style>
/* Markdown styling */
.markdown-content h1 {
    font-size: 1.875rem;
    line-height: 2.25rem;
    font-weight: 700;
    margin-top: 1.5rem;
    margin-bottom: 1rem;
    color: rgb(38 38 38);
}

.markdown-content h2 {
    font-size: 1.5rem;
    line-height: 2rem;
    font-weight: 700;
    margin-top: 1.25rem;
    margin-bottom: 0.75rem;
    color: rgb(38 38 38);
}

.markdown-content h3 {
    font-size: 1.25rem;
    line-height: 1.75rem;
    font-weight: 700;
    margin-top: 1rem;
    margin-bottom: 0.5rem;
    color: rgb(64 64 64);
}

.markdown-content h4 {
    font-size: 1.125rem;
    line-height: 1.75rem;
    font-weight: 700;
    margin-top: 0.75rem;
    margin-bottom: 0.5rem;
    color: rgb(64 64 64);
}

.markdown-content h5,
.markdown-content h6 {
    font-weight: 700;
    margin-top: 0.75rem;
    margin-bottom: 0.25rem;
    color: rgb(64 64 64);
}

.markdown-content p {
    margin-top: 0.75rem;
    margin-bottom: 0.75rem;
    color: rgb(71 85 105);
}

.markdown-content ul {
    list-style-type: disc;
    padding-left: 1.5rem;
    margin-top: 0.75rem;
    margin-bottom: 0.75rem;
}

.markdown-content ol {
    list-style-type: decimal;
    padding-left: 1.5rem;
    margin-top: 0.75rem;
    margin-bottom: 0.75rem;
}

.markdown-content li {
    margin-bottom: 0.25rem;
}

.markdown-content a {
    color: rgb(37 99 235);
    text-decoration: underline;
}

.markdown-content a:hover {
    color: rgb(30 64 175);
}

.markdown-content blockquote {
    padding-left: 1rem;
    border-left-width: 4px;
    border-color: rgb(212 212 212);
    font-style: italic;
    margin-top: 0.75rem;
    margin-bottom: 0.75rem;
    color: rgb(113 113 122);
}

.markdown-content code {
    background-color: rgb(243 244 246);
    padding-left: 0.25rem;
    padding-right: 0.25rem;
    padding-top: 0.125rem;
    padding-bottom: 0.125rem;
    border-radius: 0.25rem;
    font-size: 0.875rem;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas,
        "Liberation Mono", "Courier New", monospace;
}

.markdown-content pre {
    background-color: rgb(243 244 246);
    padding: 0.75rem;
    border-radius: 0.25rem;
    margin-top: 0.75rem;
    margin-bottom: 0.75rem;
    overflow-x: auto;
}

.markdown-content pre code {
    background-color: transparent;
    padding: 0;
}

.markdown-content hr {
    margin-top: 1.25rem;
    margin-bottom: 1.25rem;
    border-color: rgb(212 212 212);
}

.markdown-content img {
    max-width: 100%;
    margin-top: 0.75rem;
    margin-bottom: 0.75rem;
    border-radius: 0.25rem;
}

.markdown-content table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 0.75rem;
    margin-bottom: 0.75rem;
}

.markdown-content th,
.markdown-content td {
    border-width: 1px;
    border-color: rgb(212 212 212);
    padding: 0.5rem;
}

.markdown-content th {
    background-color: rgb(243 244 246);
}

/* Selection styling */
.article-content ::selection {
    background-color: rgba(59, 130, 246, 0.3);
    color: inherit;
}

.article-content ::-moz-selection {
    background-color: rgba(59, 130, 246, 0.3);
    color: inherit;
}

/* Ensure text is selectable */
.article-content * {
    user-select: text;
    -webkit-user-select: text;
    -moz-user-select: text;
    -ms-user-select: text;
}
</style>
