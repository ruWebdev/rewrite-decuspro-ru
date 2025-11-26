<script setup>
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    site: {
        type: Object,
        required: true,
    },
});
</script>

<template>

    <Head :title="`Сайт: ${props.site.name}`" />

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

        <main class="mx-auto max-w-5xl px-4 py-6 space-y-4">
            <h1 class="text-2xl font-semibold">Сайт: {{ props.site.name }}</h1>

            <div class="flex gap-3">
                <Link :href="route('sites.authors', props.site.id)"
                    class="inline-flex items-center rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                Авторы
                </Link>

                <Link :href="route('sites.categories', props.site.id)"
                    class="inline-flex items-center rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                Категории
                </Link>

                <Link :href="route('sites.rewrite', props.site.id)"
                    class="inline-flex items-center rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                Рерайт
                </Link>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-4">
                <div class="text-sm text-gray-700">
                    <div class="mb-2">
                        <span class="font-medium">Название:</span>
                        <span class="ml-1">{{ props.site.name }}</span>
                    </div>

                    <div>
                        <span class="font-medium">URL:</span>
                        <a class="ml-1 text-indigo-600 hover:underline" :href="props.site.url" target="_blank"
                            rel="noopener noreferrer">
                            {{ props.site.url }}
                        </a>
                    </div>
                </div>
            </div>

            <div>
                <Link :href="route('rewrite')"
                    class="inline-flex items-center rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                ← К списку сайтов
                </Link>
            </div>
        </main>
    </div>
</template>
