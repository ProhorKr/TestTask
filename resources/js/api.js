export class ApiError extends Error {
    constructor(message, errors = {}, status = 0) {
        super(message);
        this.errors = errors;
        this.status = status;
    }
}

export async function api(path, options = {}) {
    let response;
    try {
        response = await fetch(`/api${path}`, {
            ...options,
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', ...options.headers },
        });
    } catch {
        throw new ApiError('Нет соединения с сервером. Проверьте подключение и повторите попытку.');
    }
    if (response.status === 204) return null;
    const body = await response.json().catch(() => ({}));
    if (!response.ok) {
        const message = response.status === 422 ? 'Проверьте заполнение полей.'
            : response.status === 404 ? 'Заметка больше не существует. Обновите список.'
            : 'Не удалось выполнить запрос. Попробуйте ещё раз.';
        throw new ApiError(message, body.errors || {}, response.status);
    }
    return body;
}
