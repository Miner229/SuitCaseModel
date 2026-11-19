# Демо-ссылка на Railway (≈10 минут)

Railway собирает сайт из этого GitHub-репозитория (по `Dockerfile`) и выдаёт ссылку вида
`https://suitcasemodel-production.up.railway.app`, которую можно открыть с любого устройства.
При каждом `git push` в ветку `main` сайт обновляется автоматически.

> Это **демо-стенд** для показа заказчику. Боевой сайт с заявками реальных клиентов размещайте
> на хостинге в Казахстане: этого требует закон о персональных данных (см. README).
> Тарифы Railway: пробный период с бесплатными $5, дальше от $5 в месяц (на демо обычно хватает).

## 1. Проект и база данных

1. Зайдите на https://railway.com → **Login with GitHub** (аккаунт Miner229).
2. **New Project → Deploy from GitHub repo** → выберите `Miner229/SuitCaseModel`.
   Если репозитория нет в списке — **Configure GitHub App** и дайте Railway доступ к нему.
   Первая сборка может упасть с ошибкой базы данных — это нормально, база появится на следующем шаге.
3. В том же проекте: **+ Create → Database → MySQL**. Подождите, пока статус станет зелёным.

## 2. Переменные окружения сайта

Откройте сервис **SuitCaseModel → Variables → Raw Editor** и вставьте:

```
DB_HOST=${{MySQL.MYSQLHOST}}
DB_PORT=${{MySQL.MYSQLPORT}}
DB_NAME=${{MySQL.MYSQLDATABASE}}
DB_USER=${{MySQL.MYSQLUSER}}
DB_PASS=${{MySQL.MYSQLPASSWORD}}
APP_ENV=development
APP_DEBUG=0
APP_FORCE_HTTPS=1
APP_SECRET=впишите_случайную_строку_из_64_символов
ADMIN_PASSWORD=придумайте_пароль_от_10_символов
TELEGRAM_BOT_TOKEN=
TELEGRAM_CHAT_ID=
```

- `APP_ENV=development` закрывает демо-стенд от индексации поисковиками (robots.txt `Disallow: /`) —
  иначе Яндекс и Google проиндексируют демо-ссылку вместо будущего боевого домена.
- `APP_SECRET` — случайная строка 64 символа: например, сгенерируйте на https://www.random.org/strings/
  или командой `php -r "echo bin2hex(random_bytes(32));"`.
- `ADMIN_PASSWORD` — пароль входа в `/admin/` (логин `admin`). Задаётся только при первом запуске,
  потом меняется в самой админке.
- Telegram-переменные можно заполнить позже (инструкция в README).

Нажмите **Deploy** (или «Apply changes»).

## 3. Постоянный диск для фото (обязательно)

Без него загруженные в админке фото пропадут при следующем обновлении сайта.

Сервис **SuitCaseModel → правый клик → Attach Volume** (или Settings → Volumes) →
**Mount path:** `/var/www/html/public/uploads`.

## 4. Публичная ссылка

**SuitCaseModel → Settings → Networking → Generate Domain**. Railway выдаст адрес вида
`https://suitcasemodel-production.up.railway.app` — это и есть ссылка для вас и заказчика.

- Сайт: `https://…up.railway.app/`
- Админка: `https://…up.railway.app/admin/` — логин `admin`, пароль из `ADMIN_PASSWORD`.

Свой домен: там же **Custom Domain** → добавить CNAME-запись у регистратора → SSL выдаётся автоматически.
После этого добавьте переменную `APP_URL=https://ваш-домен.kz`.

## Если что-то не работает

- **Deployments → View logs**: при старте пишется «Ожидание базы данных…», затем отчёт установщика.
  «База данных недоступна» — проверьте переменные `DB_*` из шага 2 и что MySQL-сервис запущен.
- Фото товаров показываются заглушками после обновления — не подключён диск из шага 3
  (сайт сам восстанавливает демо-фото, но загруженные вами вручную — нет).
- Проверка после запуска: `php tests/postdeploy.php https://…up.railway.app`
  (на демо-стенде ожидаемо будут ошибки про robots.txt и Метрику — сайт закрыт от индексации специально).

## Запуск на своём компьютере (без Railway)

Нужен Docker Desktop. В папке проекта:

```bash
docker compose up --build
```

Откройте http://localhost:8080, админка: http://localhost:8080/admin/ (`admin` / `Admin12345`).
