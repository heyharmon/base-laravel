<script setup>
import { ref, onMounted } from 'vue'
import api from '@/services/api'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import DefaultLayout from '@/layouts/DefaultLayout.vue'

const feeds = ref([])
const loading = ref(true)
const error = ref(null)
const subscriptions = ref([])

const newFeedName = ref('')
const newChannels = ref([{ name: '', youtube_channel_id: '' }])

const loadFeeds = async () => {
  try {
    loading.value = true
    feeds.value = await api.get('/feeds')
  } catch (err) {
    console.error(err)
    error.value = 'Failed to load feeds'
  } finally {
    loading.value = false
  }
}

const loadSubscriptions = async () => {
  try {
    subscriptions.value = await api.get('/youtube/subscriptions')
  } catch (err) {
    console.error(err)
  }
}

const addChannelField = () => {
  newChannels.value.push({ name: '', youtube_channel_id: '' })
}

const removeChannelField = (index) => {
  newChannels.value.splice(index, 1)
}

const addSubscriptionChannel = (sub) => {
  if (!newChannels.value.some(c => c.youtube_channel_id === sub.youtube_channel_id)) {
    newChannels.value.push({ name: sub.name, youtube_channel_id: sub.youtube_channel_id })
  }
}

const createFeed = async () => {
  try {
    await api.post('/feeds', {
      name: newFeedName.value,
      channels: newChannels.value,
    })
    newFeedName.value = ''
    newChannels.value = [{ name: '', youtube_channel_id: '' }]
    await loadFeeds()
  } catch (err) {
    console.error(err)
    error.value = 'Failed to create feed'
  }
}

const deleteFeed = async (id) => {
  try {
    await api.delete(`/feeds/${id}`)
    await loadFeeds()
  } catch (err) {
    console.error(err)
    error.value = 'Failed to delete feed'
  }
}

onMounted(() => {
  loadFeeds()
  loadSubscriptions()
})
</script>

<template>
  <DefaultLayout>
    <div>
      <h1 class="text-3xl font-bold mb-6">Manage Feeds</h1>

      <form @submit.prevent="createFeed" class="space-y-4 mb-8">
        <Input v-model="newFeedName" placeholder="Feed name" />
        <div class="space-y-2">
          <div
            v-for="(channel, index) in newChannels"
            :key="index"
            class="flex space-x-2"
          >
            <Input
              v-model="channel.name"
              placeholder="Channel name"
              class="flex-1"
            />
            <Input
              v-model="channel.youtube_channel_id"
              placeholder="YouTube ID"
              class="flex-1"
            />
            <Button
              type="button"
              variant="destructive"
              size="sm"
              @click="removeChannelField(index)"
            >
              X
            </Button>
          </div>
          <Button type="button" variant="secondary" size="sm" @click="addChannelField">
            Add Channel
          </Button>
          <div v-if="subscriptions.length" class="mt-4 space-y-1">
            <h3 class="font-medium">Your YouTube Channels</h3>
            <ul class="space-y-1">
              <li v-for="sub in subscriptions" :key="sub.youtube_channel_id" class="flex justify-between items-center">
                <span>{{ sub.name }}</span>
                <Button type="button" size="sm" variant="secondary" @click="addSubscriptionChannel(sub)">Add</Button>
              </li>
            </ul>
          </div>
        </div>
        <Button type="submit">Create Feed</Button>
      </form>

      <div v-if="loading" class="text-neutral-500">Loading...</div>
      <div v-else-if="error" class="text-red-500">{{ error }}</div>
      <ul v-else class="space-y-4">
        <li
          v-for="feed in feeds"
          :key="feed.id"
          class="border rounded-md p-4 bg-neutral-50"
        >
          <div class="flex justify-between items-center">
            <h2 class="font-semibold">{{ feed.name }}</h2>
            <Button variant="destructive" size="sm" @click="deleteFeed(feed.id)">
              Delete
            </Button>
          </div>
          <ul class="ml-4 mt-2 list-disc">
            <li
              v-for="channel in feed.channels"
              :key="channel.id"
            >
              {{ channel.name }} ({{ channel.youtube_channel_id }})
            </li>
          </ul>
        </li>
      </ul>
    </div>
  </DefaultLayout>
</template>
