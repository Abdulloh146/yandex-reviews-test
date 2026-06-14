<template>
    <div class="min-h-screen bg-slate-100">
        <header class="bg-white border-b">
            <div class="max-w-6xl mx-auto px-4 py-4 flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-bold text-slate-900">
                        Yandex Reviews Parser
                    </h1>
                    <p class="text-sm text-slate-500">
                        Настройки организации и отзывы
                    </p>
                </div>

                <button
                    @click="logout"
                    class="px-4 py-2 bg-slate-900 text-white rounded-xl"
                >
                    Выйти
                </button>
            </div>
        </header>

        <main class="max-w-6xl mx-auto px-4 py-8 space-y-6">
            <section class="bg-white rounded-2xl shadow p-6">
                <h2 class="text-lg font-bold mb-4">
                    Ссылка на карточку организации
                </h2>

                <form @submit.prevent="saveOrganization" class="space-y-4">
                    <input
                        v-model="yandexUrl"
                        type="url"
                        class="w-full border rounded-xl px-4 py-3 outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="https://yandex.ru/maps/org/..."
                    >

                    <div v-if="error" class="bg-red-50 text-red-700 px-4 py-3 rounded-xl text-sm">
                        {{ error }}
                    </div>

                    <div v-if="success" class="bg-green-50 text-green-700 px-4 py-3 rounded-xl text-sm">
                        {{ success }}
                    </div>

                    <div class="flex gap-3">
                        <button
                            type="submit"
                            :disabled="saving"
                            class="px-5 py-3 bg-blue-600 text-white rounded-xl font-semibold disabled:opacity-60"
                        >
                            {{ saving ? 'Сохраняем и парсим...' : 'Сохранить' }}
                        </button>

                        <button
                            type="button"
                            @click="refreshOrganization"
                            :disabled="refreshing || !organization"
                            class="px-5 py-3 bg-slate-900 text-white rounded-xl font-semibold disabled:opacity-60"
                        >
                            {{ refreshing ? 'Парсим...' : 'Обновить данные' }}
                        </button>
                    </div>
                </form>
            </section>

            <section v-if="organization" class="bg-white rounded-2xl shadow p-6">
                <h2 class="text-lg font-bold mb-4">
                    Данные организации
                </h2>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="bg-slate-100 rounded-xl p-4">
                        <div class="text-sm text-slate-500">Название</div>
                        <div class="font-bold">{{ organization.name || '—' }}</div>
                    </div>

                    <div class="bg-slate-100 rounded-xl p-4">
                        <div class="text-sm text-slate-500">Средний рейтинг</div>
                        <div class="font-bold">{{ organization.average_rating || '—' }}</div>
                    </div>

                    <div class="bg-slate-100 rounded-xl p-4">
                        <div class="text-sm text-slate-500">Оценок</div>
                        <div class="font-bold">{{ organization.ratings_count }}</div>
                    </div>

                    <div class="bg-slate-100 rounded-xl p-4">
                        <div class="text-sm text-slate-500">Отзывов</div>
                        <div class="font-bold">{{ organization.reviews_count }}</div>
                    </div>
                </div>

                <div class="mt-4 text-sm text-slate-500">
                    Статус парсинга: <b>{{ organization.parse_status }}</b>
                </div>

                <div v-if="organization.parse_error" class="mt-3 bg-red-50 text-red-700 px-4 py-3 rounded-xl text-sm">
                    {{ organization.parse_error }}
                </div>
            </section>

            <section class="bg-white rounded-2xl shadow p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-bold">
                        Отзывы
                    </h2>

                    <button
                        @click="loadReviews(currentPage)"
                        class="px-4 py-2 bg-slate-200 rounded-xl"
                    >
                        Обновить список
                    </button>
                </div>

                <div v-if="reviewsLoading" class="text-slate-500">
                    Загружаем отзывы...
                </div>

                <div v-else-if="reviews.length === 0" class="text-slate-500">
                    Пока отзывов нет.
                </div>

                <div v-else class="space-y-4">
                    <div
                        v-for="review in reviews"
                        :key="review.id"
                        class="border rounded-xl p-4"
                    >
                        <div class="flex justify-between gap-4 mb-2">
                            <div class="font-bold">
                                {{ review.author_name || 'Аноним' }}
                            </div>
                            <div class="text-sm text-slate-500">
                                {{ review.review_date_text || review.review_date || '—' }}
                            </div>
                        </div>

                        <div class="text-sm text-yellow-600 font-semibold mb-2">
                            Оценка: {{ review.rating || '—' }}
                        </div>

                        <p class="text-slate-700 whitespace-pre-line">
                            {{ review.text || 'Без текста' }}
                        </p>
                    </div>
                </div>

                <div v-if="lastPage > 1" class="flex items-center gap-2 mt-6">
                    <button
                        @click="loadReviews(currentPage - 1)"
                        :disabled="currentPage <= 1"
                        class="px-4 py-2 bg-slate-200 rounded-xl disabled:opacity-50"
                    >
                        Назад
                    </button>

                    <span class="text-sm text-slate-600">
                        Страница {{ currentPage }} из {{ lastPage }}
                    </span>

                    <button
                        @click="loadReviews(currentPage + 1)"
                        :disabled="currentPage >= lastPage"
                        class="px-4 py-2 bg-slate-200 rounded-xl disabled:opacity-50"
                    >
                        Вперёд
                    </button>
                </div>
            </section>
        </main>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import api from '../axios';

const router = useRouter();

const organization = ref(null);
const yandexUrl = ref('');

const reviews = ref([]);
const currentPage = ref(1);
const lastPage = ref(1);

const saving = ref(false);
const refreshing = ref(false);
const reviewsLoading = ref(false);

const error = ref('');
const success = ref('');

async function loadOrganization() {
    try {
        const { data } = await api.get('/organization');
        organization.value = data.organization;

        if (organization.value) {
            yandexUrl.value = organization.value.yandex_url;
        }
    } catch (e) {
        console.error(e);
    }
}

async function saveOrganization() {
    saving.value = true;
    error.value = '';
    success.value = '';

    try {
        const { data } = await api.post('/organization', {
            yandex_url: yandexUrl.value,
        });

        organization.value = data.organization;

        if (data.organization?.parse_status === 'success') {
            success.value = data.message;
        } else {
            error.value = data.organization?.parse_error || data.message || 'Parsing failed.';
        }

        await loadReviews(1);
    } catch (e) {
        error.value = e.response?.data?.message || 'Ошибка сохранения';
    } finally {
        saving.value = false;
    }
}

async function refreshOrganization() {
    refreshing.value = true;
    error.value = '';
    success.value = '';

    try {
        const { data } = await api.post('/organization/refresh');

        organization.value = data.organization;

        if (data.organization?.parse_status === 'success') {
            success.value = data.message;
        } else {
            error.value = data.organization?.parse_error || data.message || 'Parsing failed.';
        }

        await loadReviews(1);
    } catch (e) {
        error.value = e.response?.data?.message || 'Ошибка парсинга';
    } finally {
        refreshing.value = false;
    }
}

async function loadReviews(page = 1) {
    reviewsLoading.value = true;

    try {
        const { data } = await api.get('/organization/reviews', {
            params: {
                page,
                per_page: 50,
            },
        });

        reviews.value = data.data;
        currentPage.value = data.current_page;
        lastPage.value = data.last_page;
    } catch (e) {
        console.error(e);
    } finally {
        reviewsLoading.value = false;
    }
}

async function logout() {
    try {
        await api.post('/logout');
    } catch (e) {
        console.error(e);
    }

    localStorage.removeItem('auth_token');
    await router.push('/login');
}

onMounted(async () => {
    if (! localStorage.getItem('auth_token')) {
        await router.push('/login');
        return;
    }

    await loadOrganization();
    await loadReviews(1);
});
</script>
