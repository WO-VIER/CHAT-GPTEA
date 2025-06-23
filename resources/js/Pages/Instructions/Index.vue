<!-- resources/js/Pages/Instructions/Index.vue -->
<script setup>
import { useForm } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps({
    user_context: String,
    ai_behavior: String,
    custom_commands: Array
})

const form = useForm({
    user_context: props.user_context || '',
    ai_behavior: props.ai_behavior || '',
    custom_commands: props.custom_commands || []
})

function addCommand() {
    form.custom_commands.push({
        token: '',
        description: ''
    })
}

function removeCommand(index) {
    form.custom_commands.splice(index, 1)
}

function saveInstructions() {
    form.post('/instructions', {
        onSuccess: () => {
            console.log('Instructions sauvegardées')
        }
    })
}

function goBack() {
    window.history.back()
}
</script>

<template>
    <div class="min-h-screen bg-slate-900 text-gray-200 font-mono">
        <!-- Header -->
        <div class="bg-slate-800 border-b-2 border-slate-700 p-6">
            <div class="max-w-4xl mx-auto">
                <pre class="text-rose-500 text-center text-xs">
 ▄████▄   ██░ ██  ▄▄▄     ▄▄▄█████▓  ▄████  ██▓███  ▄▄▄█████▓▓█████ ▄▄▄
▒██▀ ▀█  ▓██░ ██▒▒████▄   ▓  ██▒ ▓▒ ██▒ ▀█▒▓██░  ██▒▓  ██▒ ▓▒▓█   ▀▒████▄
▒▓█    ▄ ▒██▀▀██░▒██  ▀█▄ ▒ ▓██░ ▒░▒██░▄▄▄░▓██░ ██▓▒▒ ▓██░ ▒░▒███  ▒██  ▀█▄
▒▓▓▄ ▄██▒░▓█ ░██ ░██▄▄▄▄██░ ▓██▓ ░ ░▓█  ██▓▒██▄█▓▒ ▒░ ▓██▓ ░ ▒▓█  ▄░██▄▄▄▄██
▒ ▓███▀ ░░▓█▒░██▓ ▓█   ▓██▒ ▒██▒ ░ ░▒▓███▀▒▒██▒ ░  ░  ▒██▒ ░ ░▒████▒▓█   ▓██▒
░ ░▒ ▒  ░ ▒ ░░▒░▒ ▒▒   ▓▒█░ ▒ ░░    ░▒   ▒ ▒▓▒░ ░  ░  ▒ ░░   ░░ ▒░ ░▒▒   ▓▒█░
  ░  ▒    ▒ ░▒░ ░  ▒   ▒▒ ░   ░      ░   ░ ░▒ ░         ░     ░ ░  ░ ▒   ▒▒ ░
░         ░  ░░ ░  ░   ▒    ░      ░ ░   ░ ░░         ░         ░    ░   ▒
░ ░       ░  ░  ░      ░  ░              ░                      ░  ░     ░  ░
░
                </pre>
                <div class="flex justify-center mt-2">
                    <button
                        @click="$inertia.visit('/ask')"
                        class="px-3 py-1 text-xs border bg-slate-700 text-rose-500 border-slate-600 hover:opacity-80 cursor-pointer transition-opacity"
                    >
                        Retour au Chat
                    </button>
            </div>
            </div>
        </div>

        <!-- Contenu principal -->
        <div class="max-w-4xl mx-auto p-6">
            <form @submit.prevent="saveInstructions" class="space-y-8">

                <!-- À propos de vous -->
                <div class="bg-slate-800 p-6 rounded border border-slate-700">
                    <h2 class="text-rose-500 font-bold text-lg mb-4">
                        <pre class="text-xs text-rose-500">
┌─────────────────────────────────┐
│          À PROPOS DE VOUS       │
└─────────────────────────────────┘
                        </pre>
                    </h2>

                    <p class="text-gray-400 text-sm mb-4">
                        Présentez-vous brievement pour personnaliser l'interaction avec votre assistant.
                    </p>

                    <textarea
                        v-model="form.user_context"
                        class="w-full p-4 bg-slate-700 border border-slate-600 rounded text-gray-100 placeholder-gray-400 font-mono text-sm"
                        placeholder="Ex: Je suis développeur C et une quiche en dev web. Je désire augmenter mes skills en développement full-stack."
                    ></textarea>
                </div>

                <!-- Comportement de l'assistant -->
                <div class="bg-slate-800 p-6 rounded border border-slate-700">
                    <h2 class="text-rose-500 font-bold text-lg mb-4">
                        <pre class="text-xs text-rose-500">
┌─────────────────────────────────┐
│        COMPORTEMENT IA          |
└─────────────────────────────────┘
                        </pre>
                    </h2>

                    <p class="text-gray-400 text-sm mb-4">
                        Définissez comment vous souhaitez que l'assistant interagisse avec vous.
                    </p>

                    <textarea
                        v-model="form.ai_behavior"
                        class="w-full p-4 bg-slate-700 border border-slate-600 rounded text-gray-100 placeholder-gray-400 font-mono text-sm"
                        placeholder="Ex: Ton familier, amical pour préserver une ambiance détendue . "
                    ></textarea>
                </div>

                <!-- Commandes personnalisées -->
                <div class="bg-slate-800 p-6 rounded border border-slate-700">
                    <h2 class="text-rose-500 font-bold text-lg mb-4">
                        <pre class="text-xs text-rose-500">
┌─────────────────────────────────┐
│     COMMANDES PERSONNALISÉES    │
└─────────────────────────────────┘
                        </pre>
                    </h2>

                    <p class="text-gray-400 text-sm mb-4">
                       Ici, vous pouvez définir vos porpres commandes.
                    </p>

                    <!-- Liste des commandes -->
                    <div v-if="form.custom_commands.length > 0" class="space-y-4 mb-6">
                        <div
                            v-for="(command, index) in form.custom_commands"
                            :key="index"
                            class="bg-slate-700 p-4 rounded border border-slate-600"
                        >
                            <div class="flex gap-3 mb-3">
                                <input
                                    v-model="command.token"
                                    placeholder="/command"
                                    class="flex-1 p-2 bg-slate-600 border border-slate-500 rounded text-gray-100 font-mono text-sm placeholder-gray-400"
                                />
                                <button
                                    type="button"
                                    @click="removeCommand(index)"
                                    class="px-3 py-2  text-white rounded text-sm hover:bg-red-700 transition-colors"
                                >
                                    X
                                </button>
                            </div>
                            <textarea
                                v-model="command.description"
                                placeholder="Description de ce que fait cette commande..."
                                class="w-full p-2 bg-slate-600 border border-slate-500 rounded text-gray-100 font-mono text-sm placeholder-gray-400"
                                rows="2"
                            ></textarea>
                        </div>
                    </div>

                    <!-- Bouton ajouter commande -->
                    <button
                        type="button"
                        @click="addCommand"
                        class="w-full px-4 py-3 bg-slate-600 border border-slate-500 text-rose-500 rounded font-mono text-sm hover:bg-slate-500 transition-colors"
                    >
                        AJOUTER UNE COMMANDE
                    </button>
                </div>

                <!-- Boutons d'action -->
                <div class="flex gap-4">
                    <button
                        type="submit"
                        class="flex-1 px-6 py-3 bg-rose-500 text-white rounded hover:bg-rose-600 font-mono font-bold transition-colors"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'SAUVEGARDE...' : ' SAUVEGARDER' }}
                    </button>

                    <button
                        type="button"
                        @click="goBack"
                        class="px-6 py-3 bg-slate-600 border border-slate-500 text-gray-300 rounded hover:bg-slate-500 font-mono transition-colors"
                    >
                        ANNULER
                    </button>
                </div>
            </form>

            <!-- Messages flash -->
            <div v-if="$page.props.flash?.success" class="mt-6 p-4 bg-green-900 border border-green-500 rounded text-green-300 font-mono text-sm">
                {{ $page.props.flash.success }}
            </div>

            <div v-if="$page.props.flash?.error" class="mt-6 p-4 bg-red-900 border border-red-500 rounded text-red-300 font-mono text-sm">
                {{ $page.props.flash.error }}
            </div>
        </div>
    </div>
</template>
