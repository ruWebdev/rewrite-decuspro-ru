<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import axios from 'axios';

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

// Site settings form
const settingsForm = useForm({
    skip_external_links: props.site.skip_external_links ?? true,
    allowed_tags: props.site.allowed_tags ?? '',
    allowed_attributes: props.site.allowed_attributes ?? '',
});

const saveSettings = () => {
    settingsForm.post(route('sites.rewrite.settings', props.site.id));
};

// Rewrite form
const rewriteForm = useForm({
    author_id: null,
    category_id: null,
    limit: null,
});

const isRewriting = ref(false);

const runRewrite = () => {
    isRewriting.value = true;
    rewriteForm.post(route('sites.rewrite.run', props.site.id), {
        onFinish: () => {
            isRewriting.value = false;
        },
    });
};

const stopRewrite = () => {
    if (confirm('Вы уверены, что хотите остановить рерайт?')) {
        window.location.reload();
    }
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

// Content modal for logs
const showContentModal = ref(false);
const contentModalTitle = ref('');
const contentModalContent = ref('');

const loadLogContent = async (log, type, titlePrefix) => {
    contentModalTitle.value = titlePrefix + (log.article_title || 'ID: ' + log.article_joomla_id);
    contentModalContent.value = 'Загрузка...';
    showContentModal.value = true;

    try {
        const response = await axios.get(route('rewrite-logs.show', log.id), {
            params: { type },
        });

        contentModalContent.value = response.data.content || 'Контент не сохранён';
    } catch (e) {
        contentModalContent.value = 'Ошибка загрузки контента';
    }
};

const openOriginalModal = (log) => {
    loadLogContent(log, 'original', 'Оригинал: ');
};

const openResultModal = (log) => {
    loadLogContent(log, 'rewritten', 'Результат: ');
};

const openCleanedModal = (log) => {
    loadLogContent(log, 'cleaned', 'Очищенный контент: ');
};

const closeContentModal = () => {
    showContentModal.value = false;
};

// Подсчёт успешно переписанных статей
const successCount = () => {
    return props.logs.filter(log => log.status === 'success').length;
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
                <!-- Site Settings Card -->
                <div class="rounded-lg border border-gray-200 bg-white p-6">
                    <h2 class="mb-4 text-lg font-medium">Настройки сайта</h2>

                    <form @submit.prevent="saveSettings" class="space-y-4">
                        <div class="flex items-center gap-3">
                            <input id="skip_external_links" type="checkbox" v-model="settingsForm.skip_external_links"
                                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                            <label for="skip_external_links" class="text-sm font-medium text-gray-700">
                                Пропускать статьи с внешними ссылками
                            </label>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label for="allowed_tags" class="block text-sm font-medium text-gray-700">
                                    Разрешённые теги
                                </label>
                                <input id="allowed_tags" type="text" v-model="settingsForm.allowed_tags"
                                    placeholder="p,h2,h3,h4,h5,img,br,li,ul,ol"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                                <p class="mt-1 text-xs text-gray-500">Теги через запятую</p>
                            </div>

                            <div>
                                <label for="allowed_attributes" class="block text-sm font-medium text-gray-700">
                                    Разрешённые атрибуты
                                </label>
                                <input id="allowed_attributes" type="text" v-model="settingsForm.allowed_attributes"
                                    placeholder="src,alt"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                                <p class="mt-1 text-xs text-gray-500">Атрибуты через запятую</p>
                            </div>
                        </div>

                        <div>
                            <button type="submit" :disabled="settingsForm.processing"
                                class="inline-flex items-center rounded-md bg-gray-600 px-3 py-1.5 text-sm font-medium text-white shadow-sm hover:bg-gray-500 disabled:opacity-75">
                                Сохранить настройки
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Rewrite Parameters Card -->
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

                    <div class="mt-6 flex items-center gap-3">
                        <button type="button" @click="runRewrite" :disabled="isRewriting"
                            class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 disabled:opacity-75">
                            <svg v-if="isRewriting" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white"
                                xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                    stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                </path>
                            </svg>
                            <span v-if="isRewriting">Рерайт выполняется...</span>
                            <span v-else>Запустить рерайт</span>
                        </button>
                        <button v-if="isRewriting" type="button" @click="stopRewrite"
                            class="inline-flex items-center rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2">
                            <svg class="-ml-1 mr-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12" />
                            </svg>
                            Остановить рерайт
                        </button>
                    </div>
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
                    <div>
                        <p class="text-sm text-gray-600">
                            Последние 50 записей лога рерайта.
                        </p>
                        <p class="text-sm font-medium text-green-600 mt-1">
                            Успешно переписано статей: {{ successCount() }}
                        </p>
                    </div>
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
                                <th
                                    class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                                    Контент
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <tr v-for="log in props.logs" :key="log.id">
                                <td class="px-4 py-2 text-sm text-gray-500 whitespace-nowrap">
                                    {{ new Date(log.created_at).toLocaleString('ru-RU') }}
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-700">
                                    <div>
                                        <span v-if="log.article_title">{{ log.article_title }}</span>
                                        <span v-else class="text-gray-400">—</span>
                                    </div>
                                    <div v-if="log.article_joomla_id" class="text-xs text-gray-500">
                                        ID: {{ log.article_joomla_id }}
                                    </div>
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
                                <td class="px-4 py-2 text-sm">
                                    <div v-if="log.has_original_content || log.has_cleaned_content || log.has_rewritten_content"
                                        class="flex flex-wrap gap-1">
                                        <button v-if="log.has_original_content" @click="openOriginalModal(log)"
                                            class="text-xs px-2 py-1 rounded bg-gray-100 text-gray-700 hover:bg-gray-200">
                                            Оригинал
                                        </button>
                                        <button v-if="log.has_cleaned_content" @click="openCleanedModal(log)"
                                            class="text-xs px-2 py-1 rounded bg-yellow-100 text-yellow-700 hover:bg-yellow-200">
                                            Очистка
                                        </button>
                                        <button v-if="log.has_rewritten_content" @click="openResultModal(log)"
                                            class="text-xs px-2 py-1 rounded bg-indigo-100 text-indigo-700 hover:bg-indigo-200">
                                            Результат
                                        </button>
                                    </div>
                                    <span v-else class="text-gray-400">—</span>
                                </td>
                            </tr>
                            <tr v-if="!props.logs.length">
                                <td colspan="5" class="px-4 py-6 text-center text-sm text-gray-500">
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

        <!-- Content View Modal -->
        <div v-if="showContentModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
            <div class="w-full max-w-4xl max-h-[90vh] rounded-lg bg-white shadow-lg flex flex-col">
                <div class="flex items-center justify-between border-b px-6 py-4">
                    <h2 class="text-lg font-semibold">{{ contentModalTitle }}</h2>
                    <button @click="closeContentModal" class="text-gray-400 hover:text-gray-600">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="flex-1 overflow-auto p-6">
                    <pre
                        class="whitespace-pre-wrap text-sm text-gray-700 bg-gray-50 p-4 rounded-lg border font-mono overflow-x-auto">{{ contentModalContent }}</pre>
                </div>
                <div class="border-t px-6 py-4 flex justify-end">
                    <button @click="closeContentModal"
                        class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Закрыть
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
