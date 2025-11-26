<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    site: {
        type: Object,
        required: true,
    },
    authors: {
        type: Array,
        default: () => [],
    },
    categories: {
        type: Array,
        default: () => [],
    },
    logs: {
        type: Array,
        default: () => [],
    },
    links: {
        type: Array,
        default: () => [],
    },
});

// Rewrite form
const rewriteForm = useForm({
    author_id: null,
    category_id: null,
    limit: null,
});

const runRewrite = () => {
    rewriteForm.post(route('sites.rewrite.run', props.site.id));
};

// Link form
const showLinkModal = ref(false);
const linkForm = useForm({
    url: '',
    anchor: '',
});

const openLinkModal = () => {
    linkForm.reset();
    linkForm.clearErrors();
    showLinkModal.value = true;
};

const closeLinkModal = () => {
    if (!linkForm.processing) {
        showLinkModal.value = false;
    }
};

const submitLink = () => {
    linkForm.post(route('rewrite-links.store'), {
        onSuccess: () => {
            showLinkModal.value = false;
        },
    });
};

// Import links
const showImportModal = ref(false);
const importForm = useForm({
    urls: '',
});

const openImportModal = () => {
    importForm.reset();
    importForm.clearErrors();
    showImportModal.value = true;
};

const closeImportModal = () => {
    if (!importForm.processing) {
        showImportModal.value = false;
    }
};

const submitImport = () => {
    importForm.post(route('rewrite-links.import'), {
        onSuccess: () => {
            showImportModal.value = false;
        },
    });
};

// Active tab
const activeTab = ref('rewrite');

// Status badge classes
const statusClass = (status) => {
    switch (status) {
        case 'success':
            return 'bg-green-100 text-green-800';
        case 'error':
            return 'bg-red-100 text-red-800';
        case 'skipped':
            return 'bg-yellow-100 text-yellow-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
};

const statusText = (status) => {
    switch (status) {
        case 'success':
            return 'Успех';
        case 'error':
            return 'Ошибка';
        case 'skipped':
            return 'Пропущено';
        default:
            return status;
    }
};
</script>

<template>

    <Head :title="`Рерайт: ${props.site.name}`" />

    <div class="min-h-screen bg-white text-gray-900">
        <header class="border-b">
            <nav class="mx-auto flex max-w-5xl items-center gap-4 px-4 py-3 text-sm font-medium">
                <Link :href="route('rewrite')" class="rounded px-3 py-1 hover:bg-gray-100"
                    :class="{ 'bg-gray-900 text-white hover:bg-gray-900': $page.url === '/' }">
                Сайты
                </Link>

                <Link :href="route('ai-settings')" class="rounded px-3 py-1 hover:bg-gray-100"
                    :class="{ 'bg-gray-900 text-white hover:bg-gray-900': $page.url.startsWith('/ai-settings') }">
                Настройки ИИ
                </Link>
            </nav>
        </header>

        <main class="mx-auto max-w-5xl px-4 py-6 space-y-6">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-semibold">Рерайт для сайта: {{ props.site.name }}</h1>

                <Link :href="route('sites.show', props.site.id)"
                    class="inline-flex items-center rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                ← Назад к сайту
                </Link>
            </div>

            <!-- Tabs -->
            <div class="border-b border-gray-200">
                <nav class="-mb-px flex space-x-8">
                    <button @click="activeTab = 'rewrite'" :class="[
                        activeTab === 'rewrite'
                            ? 'border-indigo-500 text-indigo-600'
                            : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700',
                        'whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium',
                    ]">
                        Запуск рерайта
                    </button>
                    <button @click="activeTab = 'links'" :class="[
                        activeTab === 'links'
                            ? 'border-indigo-500 text-indigo-600'
                            : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700',
                        'whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium',
                    ]">
                        Пул ссылок ({{ props.links.length }})
                    </button>
                    <button @click="activeTab = 'logs'" :class="[
                        activeTab === 'logs'
                            ? 'border-indigo-500 text-indigo-600'
                            : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700',
                        'whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium',
                    ]">
                        Логи ({{ props.logs.length }})
                    </button>
                </nav>
            </div>

            <!-- Rewrite Tab -->
            <div v-if="activeTab === 'rewrite'" class="space-y-6">
                <div class="rounded-lg border border-gray-200 bg-white p-6">
                    <h2 class="mb-4 text-lg font-medium">Параметры рерайта</h2>

                    <div class="grid gap-4 md:grid-cols-3">
                        <div>
                            <label for="author" class="block text-sm font-medium text-gray-700">
                                Автор (логин)
                            </label>
                            <select id="author" v-model="rewriteForm.author_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                <option :value="null">Все авторы</option>
                                <option v-for="author in props.authors" :key="author.id" :value="author.joomla_id">
                                    {{ author.name }} ({{ author.username }})
                                </option>
                            </select>
                        </div>

                        <div>
                            <label for="category" class="block text-sm font-medium text-gray-700">
                                Категория
                            </label>
                            <select id="category" v-model="rewriteForm.category_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                <option :value="null">Все категории</option>
                                <option v-for="category in props.categories" :key="category.id"
                                    :value="category.joomla_id">
                                    {{ category.title }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label for="limit" class="block text-sm font-medium text-gray-700">
                                Количество статей
                            </label>
                            <input id="limit" type="number" min="1" v-model="rewriteForm.limit" placeholder="Все"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                        </div>
                    </div>

                    <div class="mt-6">
                        <button type="button" @click="runRewrite" :disabled="rewriteForm.processing"
                            class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 disabled:opacity-75">
                            <span v-if="rewriteForm.processing">Обработка...</span>
                            <span v-else>Запустить рерайт</span>
                        </button>
                    </div>
                </div>

                <div class="rounded-lg border border-yellow-200 bg-yellow-50 p-4">
                    <h3 class="text-sm font-medium text-yellow-800">Важно</h3>
                    <ul class="mt-2 list-disc pl-5 text-sm text-yellow-700 space-y-1">
                        <li>Статьи с внешними ссылками (на другие домены) будут пропущены</li>
                        <li>Разрешённые теги: p, h2, h3, h4, h5, img, br, li, ul, ol, i, em, table, tr, td, u, th,
                            thead, tbody</li>
                        <li>Разрешённые атрибуты: src</li>
                        <li>Убедитесь, что авторы и категории загружены</li>
                        <li>Настройте API ключ Deepseek и промпт в «Настройках ИИ»</li>
                    </ul>
                </div>
            </div>

            <!-- Links Tab -->
            <div v-if="activeTab === 'links'" class="space-y-4">
                <div class="flex items-center justify-between">
                    <p class="text-sm text-gray-600">
                        Пул ссылок для перелинковки. Ссылки вставляются рандомно в статьи.
                    </p>
                    <div class="flex gap-2">
                        <button type="button" @click="openImportModal"
                            class="inline-flex items-center rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            Импорт
                        </button>
                        <button type="button" @click="openLinkModal"
                            class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                            Добавить ссылку
                        </button>
                    </div>
                </div>

                <div class="overflow-hidden rounded-lg border border-gray-200 bg-white">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th
                                    class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                                    URL
                                </th>
                                <th
                                    class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                                    Домен
                                </th>
                                <th
                                    class="px-4 py-2 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">
                                    Действия
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <tr v-for="link in props.links" :key="link.id">
                                <td class="px-4 py-2 text-sm text-gray-700 max-w-md truncate">
                                    <a :href="link.url" target="_blank" class="text-indigo-600 hover:underline">
                                        {{ link.url }}
                                    </a>
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-500">
                                    {{ link.domain }}
                                </td>
                                <td class="px-4 py-2 text-right text-sm">
                                    <Link :href="route('rewrite-links.destroy', link.id)" method="delete" as="button"
                                        class="text-red-600 hover:text-red-800">
                                    Удалить
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="!props.links.length">
                                <td colspan="3" class="px-4 py-6 text-center text-sm text-gray-500">
                                    Ссылок пока нет. Добавьте ссылки для перелинковки.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Logs Tab -->
            <div v-if="activeTab === 'logs'" class="space-y-4">
                <div class="flex items-center justify-between">
                    <p class="text-sm text-gray-600">
                        Последние 50 записей лога рерайта.
                    </p>
                    <Link v-if="props.logs.length" :href="route('sites.rewrite.clear-logs', props.site.id)"
                        method="post" as="button"
                        class="inline-flex items-center rounded-md border border-red-300 px-3 py-1.5 text-sm font-medium text-red-700 hover:bg-red-50">
                    Очистить логи
                    </Link>
                </div>

                <div class="overflow-hidden rounded-lg border border-gray-200 bg-white">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th
                                    class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                                    Время
                                </th>
                                <th
                                    class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                                    Статья
                                </th>
                                <th
                                    class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                                    Статус
                                </th>
                                <th
                                    class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                                    Сообщение
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <tr v-for="log in props.logs" :key="log.id">
                                <td class="px-4 py-2 text-sm text-gray-500 whitespace-nowrap">
                                    {{ new Date(log.created_at).toLocaleString('ru-RU') }}
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-700">
                                    <span v-if="log.article_title">{{ log.article_title }}</span>
                                    <span v-else-if="log.article_joomla_id">ID: {{ log.article_joomla_id }}</span>
                                    <span v-else class="text-gray-400">—</span>
                                </td>
                                <td class="px-4 py-2 text-sm">
                                    <span :class="statusClass(log.status)"
                                        class="inline-flex rounded-full px-2 text-xs font-semibold leading-5">
                                        {{ statusText(log.status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-700">
                                    {{ log.message }}
                                </td>
                            </tr>
                            <tr v-if="!props.logs.length">
                                <td colspan="4" class="px-4 py-6 text-center text-sm text-gray-500">
                                    Логов пока нет.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>

        <!-- Add Link Modal -->
        <div v-if="showLinkModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-lg">
                <h2 class="mb-4 text-lg font-semibold">Добавить ссылку</h2>

                <form @submit.prevent="submitLink" class="space-y-4">
                    <div>
                        <label for="link-url" class="block text-sm font-medium text-gray-700">
                            URL
                        </label>
                        <input id="link-url" type="url" v-model="linkForm.url" placeholder="https://example.com/page"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                        <p v-if="linkForm.errors.url" class="mt-1 text-sm text-red-600">
                            {{ linkForm.errors.url }}
                        </p>
                    </div>

                    <div>
                        <label for="link-anchor" class="block text-sm font-medium text-gray-700">
                            Анкор (необязательно)
                        </label>
                        <input id="link-anchor" type="text" v-model="linkForm.anchor" placeholder="Текст ссылки"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <button type="button" @click="closeLinkModal" :disabled="linkForm.processing"
                            class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            Отмена
                        </button>
                        <button type="submit" :disabled="linkForm.processing"
                            class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-75">
                            Добавить
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Import Links Modal -->
        <div v-if="showImportModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
            <div class="w-full max-w-lg rounded-lg bg-white p-6 shadow-lg">
                <h2 class="mb-4 text-lg font-semibold">Импорт ссылок</h2>

                <form @submit.prevent="submitImport" class="space-y-4">
                    <div>
                        <label for="import-urls" class="block text-sm font-medium text-gray-700">
                            Ссылки (по одной на строку)
                        </label>
                        <textarea id="import-urls" rows="10" v-model="importForm.urls"
                            placeholder="https://site1.com/page1&#10;https://site2.com/page2&#10;https://site3.com/page3"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm font-mono"></textarea>
                        <p v-if="importForm.errors.urls" class="mt-1 text-sm text-red-600">
                            {{ importForm.errors.urls }}
                        </p>
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <button type="button" @click="closeImportModal" :disabled="importForm.processing"
                            class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            Отмена
                        </button>
                        <button type="submit" :disabled="importForm.processing"
                            class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-75">
                            Импортировать
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
