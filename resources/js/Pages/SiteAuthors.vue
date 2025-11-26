<script setup>
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    site: {
        type: Object,
        required: true,
    },
    authors: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>

    <Head :title="`Авторы: ${props.site.name}`" />

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
            <h1 class="text-2xl font-semibold">Авторы сайта: {{ props.site.name }}</h1>

            <div class="flex items-center justify-between">
                <p class="text-sm text-gray-600">
                    Загрузка авторов с Joomla по URL: {{ props.site.url }}
                </p>

                <Link :href="route('sites.authors.sync', props.site.id)" method="post" as="button"
                    class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2">
                Получить / Обновить
                </Link>
            </div>

            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col"
                                class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                                ID Joomla
                            </th>
                            <th scope="col"
                                class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                                Имя
                            </th>
                            <th scope="col"
                                class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                                Логин
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <tr v-for="author in props.authors" :key="author.id">
                            <td class="px-4 py-2 text-sm text-gray-700">
                                {{ author.joomla_id }}
                            </td>
                            <td class="px-4 py-2 text-sm text-gray-700">
                                {{ author.name }}
                            </td>
                            <td class="px-4 py-2 text-sm text-gray-700">
                                {{ author.username }}
                            </td>
                        </tr>
                        <tr v-if="!props.authors.length">
                            <td colspan="3" class="px-4 py-6 text-center text-sm text-gray-500">
                                Авторов пока нет. Нажмите «Получить / Обновить», чтобы загрузить их из Joomla.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div>
                <Link :href="route('sites.show', props.site.id)"
                    class="inline-flex items-center rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                ← Назад к сайту
                </Link>
            </div>
        </main>
    </div>
</template>
