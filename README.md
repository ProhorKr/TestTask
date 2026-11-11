# Мини-сервис заметок

Тестовое задание Fullstack Team Lead: REST API на **Laravel 13 / PHP 8.4**, минимальный интерфейс на **Vue 3**, SQLite, Nginx + PHP-FPM. Без авторизации: заметки общие для всех пользователей.

## Быстрый запуск

```sh
git clone https://github.com/ProhorKr/TestTask.git
cd TestTask
docker compose up -d
```

| Адрес | Назначение |
| --- | --- |
| http://localhost:8080 | Интерфейс заметок |
| http://localhost:8080/docs | Swagger UI с выполнением запросов |
| http://localhost:8080/openapi.json | Спецификация OpenAPI 3.0.3 |
| http://localhost:8080/api/notes | API |
| http://localhost:8080/up | Проверка работоспособности Laravel |

## Автоматические проверки

### API — PHPUnit

```sh
docker compose --profile test run --build --rm tests
```

Проверяются все CRUD-операции, коды и структура ответов, пагинация и сортировка, отсутствие записей, неверные ID, обязательность и типы полей, пустые строки, длины Unicode-текста, частичное обновление, неизменяемость служебных полей и наличие операций OpenAPI.

### Frontend — Vitest и сборка

С локальным Node.js 22.12+:

```sh
npm ci
npm test
npm run build
```

Либо без локального Node.js, из PowerShell:

```powershell
docker build --target frontend -t notes-frontend-check .
docker run --rm notes-frontend-check npm test
```

Vitest проверяет API-клиент: JSON-запросы, пустой ответ удаления, ошибки валидации, сети и сервера. Это не браузерные end-to-end тесты. GitHub Actions запускает PHPUnit, Vitest и frontend-сборку после публикации репозитория.


## Архитектура и решения

- Один репозиторий, один origin: Laravel отдаёт страницу Vue и обслуживает API, Nginx раздаёт собранную статику. CORS и отдельный frontend-сервер не требуются.
- SQLite хранится в именованном Docker volume `notes_data`. Это упрощает проверку и подходит для небольшого сервиса; при высокой конкурентной нагрузке стоит перейти на PostgreSQL/MySQL.
- `Dockerfile` содержит этапы зависимостей, frontend, тестов, приложения и Nginx. PHP-образ приложения не содержит dev-пакетов Composer; Vue и Swagger UI собираются на Node и отдаются статически.