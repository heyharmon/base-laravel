<script setup>
import { ref, onMounted, onUnmounted } from "vue";

const props = defineProps({
    selectedContent: {
        type: String,
        default: null,
    },
    targetSelector: {
        type: String,
        default: ".selectable-content",
    },
});

const emit = defineEmits(["update:selectedContent", "selectionAdded"]);

// Selection state
const currentSelection = ref(null);
const showAddToChatTooltip = ref(false);
const tooltipPosition = ref({ x: 0, y: 0 });

const handleTextSelection = (event) => {
    // Only handle selections within the target selector
    if (!event.target.closest(props.targetSelector)) {
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

    // Emit the selected content to parent
    emit("update:selectedContent", currentSelection.value);
    emit("selectionAdded", currentSelection.value);

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
    emit("update:selectedContent", null);
    clearCurrentSelection();
};

const handleClickOutside = (event) => {
    // Only clear current selection if clicking outside of:
    // 1. The tooltip
    // 2. The target content area
    // 3. The chat interface area (to prevent clearing when interacting with chat)
    if (
        !event.target.closest(".add-to-chat-tooltip") &&
        !event.target.closest(props.targetSelector) &&
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

// Expose methods to parent if needed
defineExpose({
    clearSelectedContent,
    clearCurrentSelection,
});

onMounted(() => {
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
    <div class="text-selection-handler">
        <!-- Content slot with selection styling -->
        <slot :clearSelectedContent="clearSelectedContent" />

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
</template>

<style scoped>
/* Selection styling - these will be applied globally */
:deep(.selectable-content ::selection) {
    background-color: rgba(59, 130, 246, 0.3);
    color: inherit;
}

:deep(.selectable-content ::-moz-selection) {
    background-color: rgba(59, 130, 246, 0.3);
    color: inherit;
}

/* Ensure text is selectable */
:deep(.selectable-content *) {
    user-select: text;
    -webkit-user-select: text;
    -moz-user-select: text;
    -ms-user-select: text;
}

:deep(.selectable-content) {
    cursor: text;
}
</style>
