<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    sites: {
        type: Array,
        default: () => [],
    },
});

const showModal = ref(false);

const form = useForm({
    name: '',
    url: '',
});

const openModal = () => {
    form.reset();
    form.clearErrors();
    showModal.value = true;
};

const closeModal = () => {
    if (!form.processing) {
        showModal.value = false;
    }
};

const submit = () => {
    form.post(route('sites.store'), {
        onSuccess: () => {
            showModal.value = false;
        },
    });
};
</script>

<template>

    <Head title="Сайты" />

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

        <main class="mx-auto max-w-5xl px-4 py-6">
            <div class="mb-4 flex items-center justify-between">
                <h1 class="text-xl font-semibold">Сайты</h1>

                <button type="button"
                    class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2"
                    @click="openModal">
                    Добавить
                </button>
            </div>

            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col"
                                class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                                Название
                            </th>
                            <th scope="col"
                                class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                                URL
                            </th>
                            <th scope="col"
                                class="px-4 py-2 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">
                                Действия
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <tr v-for="site in props.sites" :key="site.id">
                            <td class="px-4 py-2 text-sm text-indigo-600">
                                <Link :href="route('sites.show', site.id)" class="hover:underline">
                                {{ site.name }}
                                </Link>
                            </td>
                            <td class="px-4 py-2 text-sm text-gray-700">
                                {{ site.url }}
                            </td>
                            <td class="px-4 py-2 text-right text-sm">
                                <Link :href="route('sites.show', site.id)"
                                    class="inline-flex items-center rounded-md border border-gray-300 px-3 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50">
                                Перейти
                                </Link>

                                <Link :href="route('sites.destroy', site.id)" method="delete" as="button"
                                    class="ml-2 inline-flex items-center rounded-md border border-red-300 px-3 py-1 text-xs font-medium text-red-700 hover:bg-red-50">
                                Удалить
                                </Link>
                            </td>
                        </tr>
                        <tr v-if="!props.sites.length">
                            <td colspan="3" class="px-4 py-6 text-center text-sm text-gray-500">
                                Сайтов пока нет. Нажмите «Добавить», чтобы создать первый сайт.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
                <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-lg">
                    <h2 class="mb-4 text-lg font-semibold">Добавить сайт</h2>

                    <form @submit.prevent="submit" class="space-y-4">
                        <div>
                            <label for="site-name" class="block text-sm font-medium text-gray-700">
                                Название
                            </label>
                            <input id="site-name" type="text" v-model="form.name"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                            <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">
                                {{ form.errors.name }}
                            </p>
                        </div>

                        <div>
                            <label for="site-url" class="block text-sm font-medium text-gray-700">
                                URL
                            </label>
                            <input id="site-url" type="text" v-model="form.url"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                            <p v-if="form.errors.url" class="mt-1 text-sm text-red-600">
                                {{ form.errors.url }}
                            </p>
                        </div>

                        <div class="mt-6 flex justify-end gap-3">
                            <button type="button"
                                class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                                @click="closeModal" :disabled="form.processing">
                                Отмена
                            </button>

                            <button type="submit"
                                class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 disabled:opacity-75"
                                :disabled="form.processing">
                                Добавить
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</template>
