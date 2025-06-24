<script setup>
import { ref, reactive } from "vue";
import api from "@/services/api";

const props = defineProps({
    article: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(["close", "saved"]);

const saving = ref(false);
const form = reactive({
    title: props.article?.title || "",
    content: props.article?.content || "",
});

const save = async () => {
    saving.value = true;

    try {
        if (props.article) {
            await api.put(`/articles/${props.article.id}`, form);
        } else {
            await api.post("/articles", form);
        }

        emit("saved");
        emit("close");
    } catch (error) {
        console.error("Error saving article:", error);
        alert("Failed to save article. Please try again.");
    } finally {
        saving.value = false;
    }
};
</script>

<template>
    <div
        class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
    >
        <div
            class="bg-white rounded-lg shadow-xl w-full max-w-2xl max-h-[90vh] overflow-hidden"
        >
            <!-- Header -->
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-xl font-semibold text-gray-800">
                    {{ article ? "Edit Article" : "Create New Article" }}
                </h3>
            </div>

            <!-- Form -->
            <form @submit.prevent="save" class="p-6">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Title
                    </label>
                    <input
                        v-model="form.title"
                        type="text"
                        required
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        placeholder="Enter article title"
                    />
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Content
                    </label>
                    <textarea
                        v-model="form.content"
                        required
                        rows="10"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"
                        placeholder="Enter article content"
                    ></textarea>
                </div>

                <!-- Actions -->
                <div class="flex justify-end space-x-3">
                    <button
                        type="button"
                        @click="$emit('close')"
                        class="px-4 py-2 text-gray-700 bg-gray-200 hover:bg-gray-300 rounded-md transition-colors"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="saving"
                        class="px-4 py-2 bg-blue-500 hover:bg-blue-600 disabled:bg-blue-300 text-white rounded-md transition-colors"
                    >
                        {{ saving ? "Saving..." : "Save" }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
