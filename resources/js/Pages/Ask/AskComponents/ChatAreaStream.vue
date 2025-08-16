<script setup lang="ts">
import { ref, computed, nextTick, watch, onMounted } from 'vue';
import { useStream } from '@laravel/stream-vue';
import MarkdownIt from 'markdown-it';
import hljs from 'highlight.js';
import 'highlight.js/styles/github-dark.css';

const props = defineProps({
    currentConversationId: Number,
    conversations: Array,
    lastMessage: String,
    isProcessing: Boolean,
})

const chatContainer = ref(null)
const isStreaming = ref(false)
const localMessages = ref([])

const emit = defineEmits(['messageProcessed', 'scroll'])

const md = new MarkdownIt({
    html: true,
    linkify: true,
    typographer: true,
    highlight: function (str, lang) {
        if (lang && hljs.getLanguage(lang)) {
            try {
                return `<pre class="hljs rounded-md p-4 overflow-x-auto bg-slate-800"><code class="hljs language-${lang}">${hljs.highlight(str, { language: lang }).value}</code></pre>`;
            } catch (__) { }
        }
        return `<pre class="hljs rounded-md p-4 overflow-x-auto bg-slate-800"><code class="hljs">${hljs.highlightAuto(str).value}</code></pre>`;
    }
});

const { send: sendStream } = useStream('/stream', {
    onData: (data: string) => {
        isStreaming.value = true;
        // Concaténer chaque chunk au dernier message
        const lastMessage = localMessages.value[localMessages.value.length - 1];
        if (lastMessage && lastMessage.role === "assistant") {
            // Récupérer le contenu actuel et ajouter le nouveau chunk
            const currentContent = lastMessage.content[0]?.data || "";
            lastMessage.content = [
                { type: "text", data: currentContent + data },
            ];
            nextTick(() => scrollToBottom());
        }
    },
    onFinish: () => {
        console.log("Fin du stream");
        isStreaming.value = false;
        emit('messageProcessed'); //Signal au parent

        //Vider localMessages
        localMessages.value = [];
    },
    onError: (error) => {
        console.error("Erreur streaming:", error);
        isStreaming.value = false;
    },
});

const displayMessages = computed(() => {
    let messages = [];

    //1.Recup les messages locaux de la db
    if (props.currentConversationId && props.conversations) {
        const currentConv = props.conversations.find(conv => conv.id === props.currentConversationId);
        const baseMessages = currentConv?.messages || [];

        baseMessages.forEach(message => {
            // Message utilisateur
            if (message.user_message) {
                messages.push({
                    id: `${message.id}-user`,
                    role: "user",
                    content: [{
                        type: "text",
                        data: message.user_message || ''
                    }],
                    created_at: message.created_at,
                    isFromDB: true
                });
            }

            // Message assistant
            if (message.ai_message) {
                messages.push({
                    id: `${message.id}-assistant`,
                    role: "assistant",
                    content: [{
                        type: "text",
                        data: message.ai_message || ''
                    }],
                    created_at: message.created_at,
                    isFromDB: true
                });
            }
        });
    }


    return [...messages, ...localMessages.value];
});

const renderMarkdown = computed(() => {
    return (text) => {
        if (!text) return '';
        try {
            return md.render(text);
        } catch (error) {
            console.log('Erreur rendu markown : ', error);
        }
    }
})

const scrollToBottom = async () => {
    await nextTick();
    if (chatContainer.value) {
        chatContainer.value.scrollTop = chatContainer.value.scrollHeight;
    }
};

//Recois le message de AskForm
function sendMessage(message, model, conversationId) {
    if (isStreaming.value) return;

    console.log('Envoi stream:', { message, model, conversationId });

    //1. Ajouter le message utilisateur
    const userMessage = {
        id: Date.now(),
        role: "user",
        content: [{ type: "text", data: message }],
        created_at: new Date().toISOString(),
    };
    localMessages.value.push(userMessage);

    //2. Ajouter un message vide pour l'assistant - sera forcément le dernier
    const assistantMessage = {
        id: Date.now() + 1,
        role: "assistant",
        content: [{ type: "text", data: "" }],
        created_at: new Date().toISOString(),
    };
    localMessages.value.push(assistantMessage);

    scrollToBottom();

    //Envoyer via le stream
    sendStream({
        text: message,  // Pass text
        model: model,
        conversation_id: conversationId
    });
}


watch(() => displayMessages.value, () => {
    scrollToBottom()
}, { deep: true })

onMounted(() => {
    localMessages.value = [];
    scrollToBottom()
})


defineExpose({
    sendMessage,
    isStreaming
})
</script>

<template>
    <div ref="chatContainer" class="flex-1 overflow-y-auto p-5 bg-slate-900 no-scrollbar">
        <!-- Affichage des messages -->
        <div v-if="displayMessages.length > 0">
            <div v-for="message in displayMessages" :key="message.id" class="mb-6">

                <!-- Message utilisateur -->
                <div v-if="message.role === 'user'" class="mb-3">
                    <span class="text-rose-500 font-bold">USER&gt;</span>
                    <span class="ml-3 text-gray-100">{{ message.content[0]?.data }}</span>
                </div>
                <!-- Message assistant -->
                <div v-if="message.role === 'assistant'" class="rounded-md p-4 ml-5 bg-slate-800 ">
                    <span class="text-rose-500 font-bold">AI&gt;</span>
                    <div class="mt-2 prose prose-slate dark:prose-invert max-w-none"
                        v-html="renderMarkdown(message.content[0]?.data)"></div>
                </div>
            </div>
        </div>

        <!-- Messages d'accueil -->
        <div v-if="displayMessages.length === 0 && !isStreaming" class="text-center mt-24">
            <pre class="text-xs text-rose-500">
┌─────────────────────────────────────┐
│       BIENVENUE SUR ChatGPTEA       │
│                                     │
│   Créez une nouvelle conversation   │
│                                     │
└─────────────────────────────────────┘</pre>
        </div>
    </div>
</template>
