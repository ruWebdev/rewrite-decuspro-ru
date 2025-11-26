<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    settings: {
        type: Object,
        default: () => ({
            deepseek_api: '',
            prompt: '',
            domain_usage_limit: 1,
        }),
    },
});

const form = useForm({
    deepseek_api: props.settings?.deepseek_api ?? '',
    prompt: props.settings?.prompt ?? '',
    domain_usage_limit: props.settings?.domain_usage_limit ?? 1,
});

const submit = () => {
    form.post(route('ai-settings.save'));
};
</script>

<template>

    <Head title="Настройки ИИ" />

    <div class="min-h-screen bg-white text-gray-900">
        <header class="border-b">
            <nav class="mx-auto flex max-w-5xl items-center gap-4 px-4 py-3 text-sm font-medium">
                <Link :href="route('rewrite')" class="rounded px-3 py-1 hover:bg-gray-100"
                    :class="{ 'bg-gray-900 text-white hover:bg-gray-900': $page.url === '/' }">
                Сайты
                </Link>

                <Link :href="route('default-settings')" class="rounded px-3 py-1 hover:bg-gray-100"
                    :class="{ 'bg-gray-900 text-white hover:bg-gray-900': $page.url.startsWith('/default-settings') }">
                Настройки по умолчанию
                </Link>

                <Link :href="route('ai-settings')" class="rounded px-3 py-1 hover:bg-gray-100"
                    :class="{ 'bg-gray-900 text-white hover:bg-gray-900': $page.url.startsWith('/ai-settings') }">
                Настройки ИИ
                </Link>
            </nav>
        </header>

        <main class="mx-auto max-w-5xl px-4 py-6">
            <form @submit.prevent="submit" class="max-w-2xl space-y-6">
                <div class="space-y-1">
                    <label for="deepseek_api" class="block text-sm font-medium text-gray-700">
                        Deepseek API
                    </label>

                    <input id="deepseek_api" type="text" v-model="form.deepseek_api"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />

                    <p v-if="form.errors.deepseek_api" class="text-sm text-red-600">
                        {{ form.errors.deepseek_api }}
                    </p>
                </div>

                <div class="space-y-1">
                    <label for="prompt" class="block text-sm font-medium text-gray-700">
                        Промпт
                    </label>

                    <textarea id="prompt" rows="8" v-model="form.prompt"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>

                    <p v-if="form.errors.prompt" class="text-sm text-red-600">
                        {{ form.errors.prompt }}
                    </p>
                </div>

                <div class="space-y-1">
                    <label for="domain_usage_limit" class="block text-sm font-medium text-gray-700">
                        Макс. использований домена на сайте
                    </label>

                    <input id="domain_usage_limit" type="number" min="1" v-model="form.domain_usage_limit"
                        class="mt-1 block w-32 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />

                    <p class="text-xs text-gray-500">
                        Сколько раз один домен может быть использован для перелинковки на одном сайте
                    </p>

                    <p v-if="form.errors.domain_usage_limit" class="text-sm text-red-600">
                        {{ form.errors.domain_usage_limit }}
                    </p>
                </div>

                <div>
                    <button type="submit"
                        class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 disabled:opacity-75"
                        :disabled="form.processing">
                        Сохранить
                    </button>
                </div>

                <!-- Здесь позже добавим функционал настроек ИИ -->
            </form>
        </main>
    </div>
</template>
