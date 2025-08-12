<!-- filepath: c:\laragon\www\chatgpt-like\resources\js\Pages\Ask\Index.vue -->
<script setup>
import MarkdownIt from 'markdown-it'
import { useForm } from '@inertiajs/vue3'
import { ref, computed, onMounted, nextTick, watch } from 'vue'

const props = defineProps({
    modelsfromdb: Array,
    selectedModel: String,
    conversations: Array,
    currentConversationId: Number,
    modelsApi: { type: Array, default: () => [] }, // NEW
});

// État local
const lastMessage = ref('')
const showModelSelector = ref(false)
const chatContainer = ref(null) // Référence pour le conteneur de chat

// Form avec correction pour model
const form = useForm({
    message: '',
    model: props.selectedModel,
    conversation_id: props.currentConversationId
})

// Debug initial des données
onMounted(() => {
    console.log('🔍 DEBUG PROPS AU MONTAGE:')
    console.log('- modelsfromdb:', props.modelsfromdb)
    console.log('- selectedModel:', props.selectedModel)
    console.log('- conversations:', props.conversations)
    console.log('- currentConversationId:', props.currentConversationId)
    console.log('- form.model initial:', form.model)

    // Scroll initial au montage
    scrollToBottom()
})

const md = new MarkdownIt({
    html: true,
    linkify: true,
    typographer: true,
    breaks: true
})

const renderMarkdown = computed(() => {
    return (text) => {
        if (!text) return ''
        return md.render(text)
    }
})

// Messages de la conversation actuelle
const currentMessages = computed(() => {
    if (!props.currentConversationId || !props.conversations) {
        return []
    }

    const currentConv = props.conversations.find(c => c.id === props.currentConversationId)
    return currentConv?.messages || []
})

// NOUVELLE FONCTION: Auto-scroll vers le bas
function scrollToBottom() {
    nextTick(() => {
        if (chatContainer.value) {
            chatContainer.value.scrollTop = chatContainer.value.scrollHeight
            console.log('📜 Auto-scroll vers le bas')
        }
    })
}

// WATCH: Scroll automatique quand les messages changent
watch(currentMessages, () => {
    scrollToBottom()
}, { deep: true })

// WATCH: Scroll quand un nouveau message flash arrive
watch(() => props.flash?.message, (newMessage) => {
    if (newMessage) {
        scrollToBottom()
    }
})

// WATCH: Scroll quand lastMessage change
watch(lastMessage, () => {
    scrollToBottom()
})

// CORRIGÉ: Fonction pour récupérer le nom du modèle sélectionné
function getSelectedModelName() {
    console.log('🤖 DEBUG getSelectedModelName:')
    console.log('- form.model:', form.model)
    console.log('- props.modelsfromdb:', props.modelsfromdb)

    if (!props.modelsfromdb || props.modelsfromdb.length === 0) {
        console.log('❌ Aucun modèle disponible')
        return 'Aucun modèle disponible'
    }

    const model = props.modelsfromdb.find(m => m.id === form.model)
    console.log('- modèle trouvé:', model)

    return model?.name || 'Sélectionner un modèle'
}

function handleImageError(event) {
    event.target.style.display = 'none'
}

// CORRIGÉ: Fonction de sélection de modèle
function selectModel(modelId) {
    console.log('🔧 SÉLECTION MODÈLE:')
    console.log('- modelId sélectionné:', modelId)
    console.log('- form.model avant:', form.model)

    form.model = modelId
    showModelSelector.value = false

    console.log('- form.model après:', form.model)
    console.log('- popup fermé')
}

function selectConversation(conversationId) {
    console.log('🔄 Sélection conversation:', conversationId)
    lastMessage.value = ''

    useForm({ conversation_id: conversationId }).post('/ask/select-conversation', {
        preserveState: false,
        replace: true
    })
}

// CORRIGÉ: Pour nouvelle conversation, utilise la route existante
function startNewConversation() {
    console.log('🆕 Nouvelle conversation')
    lastMessage.value = ''
    form.reset('message')

    // Utilise la route existante avec conversation_id = null
    useForm({ conversation_id: null }).post('/ask/select-conversation', {
        preserveState: false,
        replace: true
    })
}

function submitForm() {
    if (!form.message.trim() || form.processing) return

    console.log('📤 Envoi message:', form.message)
    console.log('📤 Modèle utilisé:', form.model)

    lastMessage.value = form.message
    form.conversation_id = props.currentConversationId

    form.post('/ask', {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            form.reset('message')
            console.log('✅ Message envoyé avec succès')
            // Auto-scroll après envoi réussi
            scrollToBottom()
        },
        onError: (errors) => {
            console.error('❌ Erreur envoi:', errors)
        }
    })
}

const dbCount = computed(() => props.modelsfromdb?.length || 0);
const apiCount = computed(() => props.modelsApi?.length || 0);
const missingIcons = computed(() => (props.modelsfromdb || []).filter(m => !m.provider_icon));
</script>

<template>
    <div class="h-screen grid grid-cols-[280px_1fr] font-mono text-gray-200 bg-slate-900">

        <!-- SIDEBAR -->
        <div class="p-4 overflow-y-auto border-r-2 bg-slate-800 border-slate-700">
            <pre class="text-xs mb-4 text-center text-rose-500">
╔═══════════════════════════╗
║        CONVERSATIONS      ║
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

        <!-- MAIN AREA -->
        <div class="flex flex-col h-screen">

            <!-- HEADER -->
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
                    <!-- Bouton instructions -->
                    <button @click="$inertia.visit('/instructions')"
                        class="px-3 py-1 text-xs border bg-slate-700 text-rose-500 border-slate-600 hover:opacity-80 cursor-pointer transition-opacity">
                        Instructions
                    </button>

                    <!-- Ton bouton modèle existant -->
                    <button @click="showModelSelector = true"
                        class="px-3 py-1 text-xs border bg-slate-700 text-rose-500 border-slate-600 hover:opacity-80 cursor-pointer transition-opacity">
                        ⚙ {{ getSelectedModelName() }}
                    </button>
                </div>
            </div>

            <!-- CHAT AREA AVEC AUTO-SCROLL -->
            <div ref="chatContainer" class="flex-1 overflow-y-auto p-5 bg-slate-900 scroll-smooth">
                <!-- DEBUG AMÉLIORÉ -->
                <div class="bg-red-900 p-3 text-xs mb-4 rounded border border-red-500">
                    <div class="font-bold text-red-300 mb-2">🔍 DEBUG INFO:</div>
                    <div>🆔 Conv ID: {{ props.currentConversationId }}</div>
                    <div>📊 Messages: {{ currentMessages.length }}</div>
                    <div>💬 Last: {{ lastMessage }}</div>
                    <div>🤖 Selected Model: {{ form.model }} ({{ getSelectedModelName() }})</div>
                    <div>📚 Models Count: {{ props.modelsfromdb?.length || 0 }}</div>
                    <div>📚 DB Models Count: {{ dbCount }}</div>
                    <div>🌐 API Models Count: {{ apiCount }}</div>
                    <div>🖼️ DB Models missing provider_icon: {{ missingIcons.length }}</div>
                    <div>⚡ Flash: {{ $page.props.flash?.message ? 'OUI' : 'NON' }}</div>
                    <div>❌ Error: {{ $page.props.flash?.error ? 'OUI' : 'NON' }}</div>
                </div>

                <!-- Messages existants -->
                <div v-if="currentMessages.length > 0">
                    <div v-for="message in currentMessages" :key="message.id" class="mb-6">
                        <div class="mb-3">
                            <span class="text-rose-500 font-bold">USER&gt;</span>
                            <span class="ml-3 text-gray-100">{{ message.user_message }}</span>
                        </div>
                        <div class="p-4 border-l-4 ml-5 bg-slate-800 border-rose-500 rounded-r">
                            <span class="text-rose-500 font-bold">AI&gt;</span>
                            <div class="mt-2 text-gray-300" v-html="renderMarkdown(message.ai_message)"></div>
                        </div>
                    </div>
                </div>

                <!-- Message en cours -->
                <div v-if="lastMessage" class="mb-4">
                    <span class="text-rose-500 font-bold">USER&gt;</span>
                    <span class="ml-3 text-gray-100">{{ lastMessage }}</span>
                </div>

                <!-- Flash message -->
                <div v-if="$page.props.flash?.message"
                    class="p-4 border-l-4 ml-5 bg-slate-800 border-rose-500 rounded-r">
                    <span class="text-rose-500 font-bold">AI&gt;</span>
                    <div class="mt-2 text-gray-300" v-html="renderMarkdown($page.props.flash.message)"></div>
                </div>

                <!-- Erreurs -->
                <div v-if="$page.props.flash?.error" class="p-4 bg-red-900 border border-red-500 rounded">
                    <span class="text-red-400 font-bold">ERROR&gt;</span>
                    <span class="ml-2 text-red-300">{{ $page.props.flash.error }}</span>
                </div>

                <!-- Messages d'accueil -->
                <div v-if="!props.currentConversationId && !lastMessage && !$page.props.flash?.message"
                    class="text-center mt-24">
                    <pre class="text-xs text-rose-500">
┌─────────────────────────────────────┐
│      BIENVENUE SUR ChatGPTEA        │
│                                     │
│  Créez une nouvelle conversation    │
│                                     |
└─────────────────────────────────────┘</pre>
                </div>

                <div v-else-if="props.currentConversationId && currentMessages.length === 0 && !lastMessage && !$page.props.flash?.message"
                    class="text-center mt-24">
                    <pre class="text-xs text-rose-500">
┌─────────────────────────────────────┐
│        CONVERSATION VIDE            │
│                                     │
│   Tapez un message pour commencer   │
└─────────────────────────────────────┘</pre>
                </div>
            </div>

            <!-- INPUT -->
            <div class="flex-none p-4 border-t-2 bg-slate-800 border-slate-700">
                <form @submit.prevent="submitForm" class="flex gap-3 items-center">
                    <span class="text-rose-500 text-lg">❯</span>
                    <input v-model="form.message" type="text" placeholder="Tapez votre message..."
                        class="flex-1 p-3 bg-slate-700 border border-slate-600 rounded text-gray-100 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-rose-500"
                        :disabled="form.processing" />
                    <button type="submit" :disabled="form.processing || !form.message.trim()"
                        class="px-4 py-3 bg-slate-700 border border-rose-500 text-rose-500 rounded font-bold hover:opacity-80 disabled:opacity-50 transition-opacity">
                        {{ form.processing ? '(-.-)Zzz...' : '➤' }}
                    </button>
                </form>
            </div>
        </div>

        <!-- POPUP MODÈLES -->
        <div v-if="showModelSelector"
            class="fixed inset-0 bg-black bg-opacity-80 flex items-center justify-center z-50">
            <div class="bg-slate-800 border border-rose-500 rounded p-6 max-w-lg w-full mx-4 max-h-96 overflow-y-auto">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-rose-500 font-bold">SÉLECTION MODÈLE</h3>
                    <button @click="showModelSelector = false"
                        class="text-rose-500 hover:text-red-400 text-xl">X</button>
                </div>

                <!-- DEBUG MODÈLES -->
                <div class="bg-blue-900 p-2 mb-4 rounded text-xs">
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
                            class="w-6 h-6 object-contain flex-shrink-0" @error="handleImageError" />

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
                            ✓
                        </div>
                    </div>
                </div>

                <!-- Si aucun modèle -->
                <div v-else class="text-center text-gray-400 py-8">
                    <div>Aucun modèle disponible</div>
                </div>
            </div>
        </div>
    </div>
</template>
