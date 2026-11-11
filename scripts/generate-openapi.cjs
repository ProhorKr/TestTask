// Rebuild the checked-in OpenAPI contract: node scripts/generate-openapi.cjs
const fs = require('node:fs');
const ref = name => ({ $ref: `#/components/schemas/${name}` });
const json = schema => ({ 'application/json': { schema } });
const response = (description, schema) => ({ description, ...(schema ? { content: json(schema) } : {}) });
const errors = { 422: response('Validation failed', ref('ValidationError')) };
const itemErrors = { ...errors, 404: response('Note not found', ref('Error')) };
const input = {
    type: 'object',
    properties: {
        title: { type: 'string', minLength: 1, maxLength: 255, example: 'Планы на неделю' },
        content: { type: 'string', minLength: 1, maxLength: 10000, example: 'Подготовить тестовое задание.' },
    },
};
const resource = { type: 'object', required: ['data'], properties: { data: ref('Note') } };
const body = schema => ({ required: true, content: json(schema) });
const spec = {
    openapi: '3.0.3',
    info: { title: 'Notes API', version: '1.0.0', description: 'Общий список заметок без авторизации. Даты UTC (ISO 8601). Входные строки обрезаются по краям; пустые строки запрещены. Неизвестные поля игнорируются. Ошибки API всегда JSON.' },
    servers: [{ url: '/', description: 'Current origin' }],
    tags: [{ name: 'Notes', description: 'CRUD заметок' }],
    paths: {
        '/api/notes': {
            get: { tags: ['Notes'], operationId: 'listNotes', summary: 'Получить список заметок',
                description: 'Постранично, новые заметки первыми (id DESC). Все записи доступны через страницы.',
                parameters: [
                    { name: 'page', in: 'query', schema: { type: 'integer', minimum: 1, maximum: 1000000, default: 1 } },
                    { name: 'per_page', in: 'query', schema: { type: 'integer', minimum: 1, maximum: 100, default: 10 } },
                ], responses: { 200: response('Paginated notes', ref('NoteCollection')), ...errors } },
            post: { tags: ['Notes'], operationId: 'createNote', summary: 'Создать заметку', requestBody: body(ref('NoteInput')),
                responses: { 201: { ...response('Note created', resource), headers: { Location: { description: 'URL созданной заметки', schema: { type: 'string', format: 'uri' } } } }, ...errors } },
        },
        '/api/notes/{id}': {
            parameters: [{ name: 'id', in: 'path', required: true, description: 'Положительный целочисленный ID. Неверный формат: 422, отсутствующая заметка: 404.', schema: { type: 'integer', minimum: 1, maximum: 2147483647 } }],
            get: { tags: ['Notes'], operationId: 'showNote', summary: 'Просмотреть заметку', responses: { 200: response('Note', resource), ...itemErrors } },
            put: { tags: ['Notes'], operationId: 'replaceNote', summary: 'Обновить заголовок и текст', requestBody: body(ref('NoteInput')), responses: { 200: response('Updated note', resource), ...itemErrors } },
            patch: { tags: ['Notes'], operationId: 'patchNote', summary: 'Частично обновить заметку', requestBody: body(ref('NotePatch')), responses: { 200: response('Updated note', resource), ...itemErrors } },
            delete: { tags: ['Notes'], operationId: 'deleteNote', summary: 'Удалить заметку', responses: { 204: response('Deleted; empty body'), ...itemErrors } },
        },
    },
    components: { schemas: {
        NoteInput: { ...input, required: ['title', 'content'] },
        NotePatch: { ...input, anyOf: [{ required: ['title'] }, { required: ['content'] }] },
        Note: { type: 'object', required: ['id', 'title', 'content', 'created_at', 'updated_at'], properties: {
            id: { type: 'integer', example: 1 }, ...input.properties,
            created_at: { type: 'string', format: 'date-time', readOnly: true },
            updated_at: { type: 'string', format: 'date-time', readOnly: true },
        } },
        NoteCollection: { type: 'object', required: ['data', 'links', 'meta'], properties: {
            data: { type: 'array', items: ref('Note') },
            links: { type: 'object', properties: Object.fromEntries(['first', 'last', 'prev', 'next'].map(k => [k, { type: 'string', nullable: true }])) },
            meta: { type: 'object', properties: {
                current_page: { type: 'integer' }, last_page: { type: 'integer' }, per_page: { type: 'integer' }, total: { type: 'integer' },
                from: { type: 'integer', nullable: true }, to: { type: 'integer', nullable: true }, path: { type: 'string' },
                links: { type: 'array', items: { type: 'object', properties: { url: { type: 'string', nullable: true }, label: { type: 'string' }, active: { type: 'boolean' } } } },
            } },
        } },
        Error: { type: 'object', required: ['message'], properties: { message: { type: 'string' } } },
        ValidationError: { type: 'object', required: ['message', 'errors'], properties: {
            message: { type: 'string', example: 'The title field is required.' },
            errors: { type: 'object', additionalProperties: { type: 'array', items: { type: 'string' } } },
        } },
    } },
};
fs.writeFileSync('public/openapi.json', JSON.stringify(spec, null, 2) + '\n');
