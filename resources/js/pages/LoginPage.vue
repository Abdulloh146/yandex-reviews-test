<template>
    <div class="min-h-screen flex items-center justify-center bg-slate-100 px-4">
        <div class="w-full max-w-md bg-white rounded-2xl shadow p-8">
            <h1 class="text-2xl font-bold text-slate-900 mb-2">
                Вход
            </h1>

            <p class="text-slate-500 mb-6">
                Войдите в аккаунт для управления организацией.
            </p>

            <form @submit.prevent="login" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">
                        Email
                    </label>
                    <input
                        v-model="form.email"
                        type="email"
                        class="w-full border rounded-xl px-4 py-3 outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="admin@example.com"
                    >
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">
                        Пароль
                    </label>
                    <input
                        v-model="form.password"
                        type="password"
                        class="w-full border rounded-xl px-4 py-3 outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="password"
                    >
                </div>

                <div v-if="error" class="bg-red-50 text-red-700 px-4 py-3 rounded-xl text-sm">
                    {{ error }}
                </div>

                <button
                    type="submit"
                    :disabled="loading"
                    class="w-full bg-blue-600 text-white rounded-xl py-3 font-semibold hover:bg-blue-700 disabled:opacity-60"
                >
                    {{ loading ? 'Входим...' : 'Войти' }}
                </button>
            </form>
        </div>
    </div>
</template>

<script setup>
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import api from '../axios';

const router = useRouter();

const loading = ref(false);
const error = ref('');

const form = reactive({
    email: 'admin@example.com',
    password: 'password',
});

async function login() {
    loading.value = true;
    error.value = '';

    try {
        const { data } = await api.post('/login', form);

        localStorage.setItem('auth_token', data.token);

        await router.push('/');
    } catch (e) {
        error.value = e.response?.data?.message || 'Ошибка входа';
    } finally {
        loading.value = false;
    }
}
</script>
