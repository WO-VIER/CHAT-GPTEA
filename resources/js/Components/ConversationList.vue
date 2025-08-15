<script setup>
import { defineProps, defineEmits } from 'vue'

const props = defineProps({
    conversations: {
        type: Array,
        required: true
    },
    currentConversationId: {
        type: Number,
        default: null
    }
})

const emit = defineEmits(['selectConversation', 'newConversation'])

function selectConversation(conversationId) {
    emit('selectConversation', conversationId)
}

function startNewConversation() {
    emit('newConversation')
}
</script>

<template>
    <div class="p-4 overflow-y-auto border-r-2 bg-slate-800 border-slate-700">
        <pre class="text-xs mb-4 text-center text-rose-500">
╔═══════════════════════════╗
║       CONVERSATIONS       ║
╚═══════════════════════════╝</pre>

        <button @click="startNewConversation"
            class="w-full p-2 mb-4 cursor-pointer text-xs font-mono border hover:opacity-80 transition-opacity bg-slate-700 text-rose-500 border-slate-600">
            ┌──── NOUVELLE CONVERSATION ────┐
        </button>

        <div v-for="conversation in props.conversations" :key="conversation.id"
            @click="selectConversation(conversation.id)"
            class="p-2 mb-2 border cursor-pointer text-xs hover:opacity-80 transition-opacity" :class="props.currentConversationId === conversation.id ?
                'bg-slate-700 border-rose-500' :
                'bg-slate-800 border-slate-700'">
            <div class="font-bold text-gray-100">{{ conversation.title }}</div>
            <div class="text-xs text-gray-400">{{ conversation.updated_at }}</div>
            <div v-if="props.currentConversationId === conversation.id" class="text-xs text-rose-500">■ ACTIVE</div>
        </div>
    </div>
</template>
