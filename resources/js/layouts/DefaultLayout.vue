<script setup>
// Default layout component
</script>

<template>
    <div class="min-h-screen bg-neutral-100">
        <header class="bg-neutral-200">
            <div
                class="max-w-7xl p-4 mx-auto flex justify-between items-center"
            >
                <h1 class="text-xl font-semibold">Laravel API with Vue 3</h1>
                <nav>
                    <router-link to="/" class="px-4 py-2"> Chat </router-link>
                    <router-link to="/articles" class="px-4 py-2">Articles</router-link>
                    <router-link to="/web-search" class="px-4 py-2"
                        >Web Search</router-link
                    >
                    <router-link to="/fetch-webpage" class="px-4 py-2"
                        >Fetch Webpage</router-link
                    >
                </nav>
            </div>
        </header>

        <main class="max-w-7xl mx-auto p-4">
            <slot />
        </main>
    </div>
</template>
