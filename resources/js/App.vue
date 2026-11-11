<script setup>
import { ref, reactive, onMounted } from 'vue';
import { api } from './api';

const notes = ref([]);
const meta = ref({ current_page: 1, last_page: 1, total: 0 });
const loading = ref(false);
const busy = ref(false);
const opening = ref(false);
const error = ref('');
const notice = ref('');
const fieldErrors = ref({});
const selected = ref(null);
const mode = ref('empty');
const form = reactive({ title: '', content: '' });
let selectionRequest = 0;

async function load(page = 1) {
    loading.value = true;
    error.value = '';
    try {
        const result = await api(`/notes?page=${page}`);
        notes.value = result.data;
        meta.value = result.meta;
        if (!result.data.length && page > 1) await load(page - 1);
        return true;
    } catch (e) { error.value = e.message; return false; }
    finally { loading.value = false; }
}

function create() {
    selectionRequest++;
    opening.value = false;
    selected.value = null;
    form.title = '';
    form.content = '';
    fieldErrors.value = {};
    notice.value = '';
    error.value = '';
    mode.value = 'create';
}

async function open(note) {
    const requestId = ++selectionRequest;
    opening.value = true;
    error.value = '';
    notice.value = '';
    try {
        const result = await api(`/notes/${note.id}`);
        if (requestId !== selectionRequest) return;
        selected.value = result.data;
        form.title = result.data.title;
        form.content = result.data.content;
        fieldErrors.value = {};
        mode.value = 'view';
    } catch (e) { if (requestId === selectionRequest) error.value = e.message; }
    finally { if (requestId === selectionRequest) opening.value = false; }
}

async function save() {
    busy.value = true;
    error.value = '';
    notice.value = '';
    fieldErrors.value = {};
    const creating = mode.value === 'create';
    try {
        const result = await api(creating ? '/notes' : `/notes/${selected.value.id}`, {
            method: creating ? 'POST' : 'PUT', body: JSON.stringify(form),
        });
        selected.value = result.data;
        mode.value = 'view';
        notice.value = creating ? 'Заметка создана.' : 'Изменения сохранены.';
        await load(creating ? 1 : meta.value.current_page);
    } catch (e) { error.value = e.message; fieldErrors.value = e.errors || {}; }
    finally { busy.value = false; }
}

async function remove() {
    if (!window.confirm(`Удалить заметку «${selected.value.title}»? Это действие нельзя отменить.`)) return;
    busy.value = true;
    error.value = '';
    notice.value = '';
    try {
        await api(`/notes/${selected.value.id}`, { method: 'DELETE' });
        selected.value = null;
        mode.value = 'empty';
        notice.value = 'Заметка удалена.';
        await load(meta.value.current_page);
    } catch (e) { error.value = e.message; }
    finally { busy.value = false; }
}

function cancel() {
    if (!selected.value) mode.value = 'empty';
    else {
        form.title = selected.value.title;
        form.content = selected.value.content;
        mode.value = 'view';
    }
    fieldErrors.value = {};
}

function date(value) {
    return new Intl.DateTimeFormat('ru-RU', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
}

onMounted(() => load());
</script>

<template>
    <main class="shell">
        <header class="header">
            <div><p class="eyebrow">LARAVEL + VUE</p><h1>Заметки<span class="dot">.</span></h1></div>
            <a class="docs-link" href="/docs">Документация API ↗</a>
        </header>
        <p class="intro">Сохраняйте мысли, планы и всё, что важно.</p>
        <div v-if="error" class="alert error" role="alert">{{ error }}</div>
        <div v-if="notice" class="alert success" role="status">{{ notice }}</div>
        <div class="workspace">
            <aside class="panel list-panel" aria-label="Список заметок">
                <div class="panel-heading"><h2>Все заметки <span class="count">{{ meta.total }}</span></h2>
                    <button class="primary" :disabled="busy" @click="create">+ Создать</button></div>
                <div v-if="loading" class="placeholder" role="status">Загружаем заметки…</div>
                <template v-else>
                    <div v-if="!notes.length" class="placeholder"><strong>Пока здесь пусто</strong><p>Создайте свою первую заметку.</p></div>
                    <ul v-else class="note-list"><li v-for="note in notes" :key="note.id">
                        <button class="note-item" :class="{ active: selected?.id === note.id }" :disabled="busy" @click="open(note)">
                            <strong>{{ note.title }}</strong><span class="preview">{{ note.content }}</span>
                            <time :datetime="note.updated_at">{{ date(note.updated_at) }}</time>
                        </button>
                    </li></ul>
                </template>
                <nav class="pagination" aria-label="Страницы заметок">
                    <button :disabled="loading || busy || meta.current_page === 1" aria-label="Предыдущая страница" @click="load(meta.current_page - 1)">←</button>
                    <button class="link-button" :disabled="loading || busy" @click="load(meta.current_page)">Обновить</button>
                    <span>{{ meta.current_page }} / {{ meta.last_page }}</span>
                    <button :disabled="loading || busy || meta.current_page === meta.last_page" aria-label="Следующая страница" @click="load(meta.current_page + 1)">→</button>
                </nav>
            </aside>
            <section class="panel editor" aria-label="Заметка" :aria-busy="opening || busy">
                <div v-if="opening" class="placeholder" role="status">Открываем заметку…</div>
                <div v-else-if="mode === 'empty'" class="welcome">
                    <div class="paper-icon" aria-hidden="true">✎</div>
                    <h2>Место для ваших идей</h2><p>Выберите заметку слева<br>или начните с чистого листа.</p>
                    <button class="primary" @click="create">Создать заметку</button>
                </div>
                <template v-else-if="mode === 'view' && selected">
                    <div class="detail-actions"><span class="eyebrow">ЗАМЕТКА #{{ selected.id }}</span>
                        <div class="actions"><button :disabled="busy" @click="mode = 'edit'">Редактировать</button>
                            <button class="danger" :disabled="busy" @click="remove">Удалить</button></div></div>
                    <h2 class="note-title">{{ selected.title }}</h2><p class="note-content">{{ selected.content }}</p>
                    <dl class="timestamps"><div><dt>Создана</dt><dd>{{ date(selected.created_at) }}</dd></div>
                        <div><dt>Обновлена</dt><dd>{{ date(selected.updated_at) }}</dd></div></dl>
                </template>
                <form v-else @submit.prevent="save">
                    <p class="eyebrow">{{ mode === 'create' ? 'ЧИСТЫЙ ЛИСТ' : 'РЕДАКТИРОВАНИЕ' }}</p>
                    <h2>{{ mode === 'create' ? 'Новая заметка' : 'Изменить заметку' }}</h2>
                    <fieldset :disabled="busy">
                        <label for="title">Заголовок</label>
                        <input id="title" v-model="form.title" required maxlength="255" placeholder="Например, идеи для проекта"
                            :aria-invalid="!!fieldErrors.title" :aria-describedby="fieldErrors.title ? 'title-error' : undefined">
                        <p v-if="fieldErrors.title" id="title-error" class="field-error">{{ fieldErrors.title[0] }}</p>
                        <label for="content">Текст заметки</label>
                        <textarea id="content" v-model="form.content" required maxlength="10000" rows="12" placeholder="О чём хотите не забыть?"
                            :aria-invalid="!!fieldErrors.content" :aria-describedby="fieldErrors.content ? 'content-error' : undefined"></textarea>
                        <p v-if="fieldErrors.content" id="content-error" class="field-error">{{ fieldErrors.content[0] }}</p>
                        <p class="char-count">{{ form.content.length }} / 10 000</p>
                        <div class="actions"><button class="primary" type="submit">{{ busy ? 'Сохраняем…' : 'Сохранить' }}</button>
                            <button type="button" @click="cancel">Отмена</button></div>
                    </fieldset>
                </form>
            </section>
        </div>
        <footer>Мини-сервис заметок <span>REST API · OpenAPI · Docker</span></footer>
    </main>
</template>
