<!-- filepath: c:\laragon\www\chatgpt-like\resources\js\Pages\Ask\Index.vue -->
<script setup>
import { useForm, router } from '@inertiajs/vue3'
import { ref, computed, onMounted, nextTick, watch } from 'vue'
import ChatArea from './AskComponents/ChatAreaStream.vue'

const props = defineProps({
    modelsfromdb: Array,
    selectedModel: String,
    conversations: Array, // Toutes les conv de l'user
    currentConversationId: Number,
})

// État local
const lastMessage = ref('')
const showModelSelector = ref(false)
const chatAreaRf = ref(null) // Référence vers le composant ChatArea

// Form avec correction pour model
const form = useForm({
    message: '',
    model: props.selectedModel,
    conversation_id: props.currentConversationId
})


onMounted(() => {
    console.log('- modelsfromdb:', props.modelsfromdb)
    console.log('- selectedModel:', props.selectedModel)
    console.log('- conversations:', props.conversations)
    console.log('- currentConversationId:', props.currentConversationId)
    console.log('- form.model', form.model)
})

// Fonctions existantes...
function getSelectedModelName() {
    if (!props.modelsfromdb || props.modelsfromdb.length === 0) {
        return 'Aucun modèle disponible'
    }
    const model = props.modelsfromdb.find(m => m.id === form.model)
    return model?.name || 'Sélectionner un modèle'
}

function selectModel(modelId) {
    form.model = modelId
    showModelSelector.value = false
}

function selectConversation(conversationId) {
    lastMessage.value = ''
    useForm({ conversation_id: conversationId }).post('/ask/select-conversation', {
        preserveState: false,
        replace: true
    })
}

function startNewConversation() {
    lastMessage.value = ''
    form.reset('message')
    useForm({ conversation_id: null }).post('/ask/new-conversation', {
        preserveState: false,
        replace: true,
        onSuccess: () => {
            console.log('Nouvelle conversation initiée');
        }
    })
}

function submitForm() {
    if (!form.message.trim() || form.processing) return

    // Vérifier si le composant ChatArea est en cours de streaming
    if (chatAreaRf.value?.isStreaming.value) {
        return
    }

    lastMessage.value = form.message
    const messageToSend = form.message

    // Envoie du message au composant ChatArea pour traitement streaming
    chatAreaRf.value?.sendMessage(
        messageToSend,
        form.model,
        props.currentConversationId
    )

    form.reset('message')
}

//Index on écoute l'événement de l'enfant
function onMessageProcessed() {
    lastMessage.value = ''
    console.log('Message traité avec succès')

    router.reload({
        only: ['conversations', 'currentConversationId'],
        preserveScroll: true
    })
}

</script>

<template>
    <div class="h-screen grid grid-cols-[280px_1fr] font-mono text-gray-200 bg-slate-900">
        <!-- CONVERSATIONS -->
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

        <!-- MAIN -->
        <div class="flex flex-col h-screen">

            <!-- HEADER INSTRUCTIONS / GETMODEL -->
            <div class="flex-none p-4 text-center border-b-2 bg-slate-800 border-slate-700">
                <pre class="text-xs text-rose-500">
 ▄████▄   ██░ ██  ▄▄▄     ▄▄▄█████▓  ▄████  ██▓███  ▄▄▄█████▓▓█████ ▄▄▄
▒██▀ ▀█  ▓██░ ██▒▒████▄   ▓  ██▒ ▓▒ ██▒ ▀█▒▓██░  ██▒▓  ██▒ ▓▒▓█   ▀▒████▄
▒▓█    ▄ ▒██▀▀██░▒██  ▀█▄ ▒ ▓██░ ▒░▒██░▄▄▄░▓██░ ██▓▒▒ ▓██░ ▒░▒███  ▒██  ▀█▄
▒▓▓▄ ▄██▒░▓█ ░██ ░██▄▄▄▄██░ ▓██▓ ░ ░▓█  ██▓▒██▄█▓▒ ▒░ ▓██▓ ░ ▒▓█  ▄░██▄▄▄▄██
▒ ▓███▀ ░░▓█▒░██▓ ▓█   ▓██▒ ▒██▒ ░ ░▒▓███▀▒▒██▒ ░  ░  ▒██▒ ░ ░▒████▒▓█   ▓██▒
░ ░▒ ▒  ░ ▒ ░░▒░▒ ▒▒   ▓▒█░ ▒ ░░    ░▒   ▒ ▒▓▒░ ░  ░  ▒ ░░   ░░ ▒░ ░▒▒   ▓▒█░
  ░  ▒    ▒ ░▒░ ░  ▒   ▒▒ ░   ░      ░   ░ ░▒ ░         ░     ░ ░  ░ ▒   ▒▒ ░
░         ░  ░░ ░  ░   ▒    ░      ░ ░   ░ ░░         ░         ░    ░   ▒
░ ░       ░  ░  ░      ░  ░              ░                      ░  ░     ░  ░
░                                                                            </pre>

                <div class="flex gap-2 justify-center mt-2">
                    <button @click="$inertia.visit('/instructions')"
                        class="px-3 py-1 text-xs border bg-slate-700 text-rose-500 border-slate-600 hover:opacity-80 cursor-pointer transition-opacity">
                        Instructions
                    </button>
                    <button @click="showModelSelector = true"
                        class="px-3 py-1 text-xs border bg-slate-700 text-rose-500 border-slate-600 hover:opacity-80 cursor-pointer transition-opacity">
                        {{ getSelectedModelName() }}
                    </button>
                </div>
            </div>

            <!--CHAT AREA -->
            <ChatArea ref="chatAreaRf" :current-conversation-id="props.currentConversationId"
                :conversations="props.conversations" :last-message="lastMessage" :is-processing="form.processing"
                @message-processed="onMessageProcessed" />

            <!-- INPUT Form to -->
            <div class="flex-none p-4 border-t-2 bg-slate-800 border-slate-700">
                <form @submit.prevent="submitForm" class="flex gap-3 items-center">
                    <span class="text-rose-500 text-lg">❯</span>
                    <input v-model="form.message" type="text"
                        placeholder="Tapez votre message... (ex: {/context} Bonjour {/command} !)"
                        class="flex-1 p-3 bg-slate-700 border border-slate-600 rounded text-gray-100 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-rose-500"
                        :disabled="form.processing || chatAreaRf?.isStreaming" />
                    <button type="submit" :disabled="form.processing || !form.message.trim() || chatAreaRf?.isStreaming"
                        class="px-4 py-3 bg-slate-700 border border-rose-500 text-rose-500 rounded font-bold hover:opacity-80 disabled:opacity-50 transition-opacity">
                        {{ (form.processing || chatAreaRf?.isStreaming) ? '(-.-)Zzz...' : '➤' }}
                    </button>
                </form>
            </div>
        </div>

        <!-- MODELS -->
        <div v-if="showModelSelector"
            class="fixed inset-0 bg-black bg-opacity-80 flex items-center justify-center z-50">
            <div class="bg-slate-800 border border-rose-500 rounded p-6 max-w-lg w-full mx-4 max-h-96 overflow-y-auto">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-rose-500 font-bold">SÉLECTION MODÈLE</h3>
                    <button @click="showModelSelector = false"
                        class="text-rose-500 hover:text-red-400 text-xl">X</button>
                </div>

                <!-- COUNT MODELS -->
                <div class="text-rose-500 p-2 mb-4 rounded text-xs">
                    <div>Modèles disponibles: {{ props.modelsfromdb?.length || 0 }}</div>
                    <div>Modèle actuel: {{ form.model }}</div>
                </div>

                <!-- Liste des modèles AVEC IMAGES -->
                <div v-if="props.modelsfromdb && props.modelsfromdb.length > 0">
                    <div v-for="model in props.modelsfromdb" :key="model.id" @click="selectModel(model.id)"
                        class="p-3 mb-2 border rounded cursor-pointer hover:opacity-80 transition-all flex items-center gap-3"
                        :class="form.model == model.id ? 'border-rose-500 bg-slate-700' : 'border-slate-600 bg-slate-800'">
                        <!-- IMAGE DU PROVIDER -->
                        <img :src="model.provider_icon" :alt="model.provider_name"
                            class="w-6 h-6 object-contain flex-shrink-0"/>

                        <!-- INFOS DU MODÈLE -->
                        <div class="flex-1">
                            <div class="text-rose-500 font-bold text-sm">{{ model.name }}</div>
                            <div class="text-gray-400 text-xs" v-if="model.provider_name">
                                {{ model.provider_name }}
                            </div>
                            <div class="text-gray-400 text-xs" v-if="model.context_length">
                                {{ model.context_length?.toLocaleString() }} tokens
                            </div>
                        </div>

                        <!-- INDICATEUR DE SÉLECTION -->
                        <div v-if="form.model == model.id" class="text-rose-400 font-bold flex-shrink-0">
                            V
                        </div>
                    </div>
                </div>

                <!-- Si PAS DE MODELS -->
                <div v-else class="text-center text-gray-400 py-8">
                    <div>Aucun modèle disponible</div>
                </div>
            </div>
        </div>
    </div>
</template>
