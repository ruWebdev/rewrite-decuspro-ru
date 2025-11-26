<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    settings: {
        type: Object,
        default: () => ({
            skip_external_links: true,
            allowed_tags: 'p,h2,h3,h4,h5,img,br,li,ul,ol,i,em,table,tr,td,u,th,thead,tbody',
            allowed_attributes: 'src',
        }),
    },
});

const form = useForm({
    skip_external_links: props.settings?.skip_external_links ?? true,
    allowed_tags: props.settings?.allowed_tags ?? '',
    allowed_attributes: props.settings?.allowed_attributes ?? '',
});

const submit = () => {
    form.post(route('default-settings.save'));
};
</script>

<template>
    <Head title="Настройки по умолчанию" />

    <div class="min-h-screen bg-white text-gray-900">
        <header class="border-b">
            <nav class="mx-auto flex max-w-5xl items-center gap-4 px-4 py-3 text-sm font-medium">
                <Link
                    :href="route('rewrite')"
                    class="rounded px-3 py-1 hover:bg-gray-100"
                    :class="{ 'bg-gray-900 text-white hover:bg-gray-900': $page.url === '/' }"
                >
                    Сайты
                </Link>

                <Link
                    :href="route('default-settings')"
                    class="rounded px-3 py-1 hover:bg-gray-100"
                    :class="{ 'bg-gray-900 text-white hover:bg-gray-900': $page.url.startsWith('/default-settings') }"
                >
                    Настройки по умолчанию
                </Link>

                <Link
                    :href="route('ai-settings')"
                    class="rounded px-3 py-1 hover:bg-gray-100"
                    :class="{ 'bg-gray-900 text-white hover:bg-gray-900': $page.url.startsWith('/ai-settings') }"
                >
                    Настройки ИИ
                </Link>
            </nav>
        </header>

        <main class="mx-auto max-w-5xl px-4 py-6">
            <h1 class="mb-6 text-2xl font-semibold">Настройки по умолчанию</h1>

            <p class="mb-6 text-sm text-gray-600">
                Эти настройки будут применяться ко всем новым сайтам при их создании.
                Для каждого сайта настройки можно изменить индивидуально.
            </p>

            <form @submit.prevent="submit" class="max-w-2xl space-y-6">
                <div class="flex items-center gap-3">
                    <input
                        id="skip_external_links"
                        type="checkbox"
                        v-model="form.skip_external_links"
                        class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                    />
                    <label for="skip_external_links" class="text-sm font-medium text-gray-700">
                        Пропускать сайты с внешними ссылками
                    </label>
                </div>

                <div class="space-y-1">
                    <label for="allowed_tags" class="block text-sm font-medium text-gray-700">
                        Разрешённые теги
                    </label>

                    <input
                        id="allowed_tags"
                        type="text"
                        v-model="form.allowed_tags"
                        placeholder="p,h2,h3,h4,h5,img,br,li,ul,ol,i,em,table,tr,td,u,th,thead,tbody"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    />

                    <p class="text-xs text-gray-500">
                        Теги через запятую без пробелов. Остальные теги будут удалены при рерайте.
                    </p>

                    <p v-if="form.errors.allowed_tags" class="text-sm text-red-600">
                        {{ form.errors.allowed_tags }}
                    </p>
                </div>

                <div class="space-y-1">
                    <label for="allowed_attributes" class="block text-sm font-medium text-gray-700">
                        Разрешённые атрибуты
                    </label>

                    <input
                        id="allowed_attributes"
                        type="text"
                        v-model="form.allowed_attributes"
                        placeholder="src,alt,href"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    />

                    <p class="text-xs text-gray-500">
                        Атрибуты через запятую без пробелов. Остальные атрибуты будут удалены при рерайте.
                    </p>

                    <p v-if="form.errors.allowed_attributes" class="text-sm text-red-600">
                        {{ form.errors.allowed_attributes }}
                    </p>
                </div>

                <div>
                    <button
                        type="submit"
                        class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 disabled:opacity-75"
                        :disabled="form.processing"
                    >
                        Сохранить
                    </button>
                </div>
            </form>
        </main>
    </div>
</template>
