#!/bin/sh
# Запуск контейнера: порт, ожидание БД, установка/обновление таблиц и демо-данных.
set -e
export PORT="${PORT:-80}"
sed -i "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf

# Секрет форм: если не задан — генерируем (формы, открытые до перезапуска, попросят обновить страницу)
if [ -z "$APP_SECRET" ]; then
  export APP_SECRET="$(php -r 'echo bin2hex(random_bytes(32));')"
  echo "APP_SECRET не задан — сгенерирован временный. Задайте постоянный в переменных окружения."
fi
# Передаём переменные окружения в Apache/PHP
for v in APP_ENV APP_URL APP_FORCE_HTTPS APP_DEBUG APP_SECRET DB_HOST DB_PORT DB_NAME DB_USER DB_PASS \
         MYSQLHOST MYSQLPORT MYSQLDATABASE MYSQLUSER MYSQLPASSWORD TELEGRAM_BOT_TOKEN TELEGRAM_CHAT_ID TELEGRAM_API_BASE; do
  eval "val=\${$v:-}"
  [ -n "$val" ] && echo "PassEnv $v" >> /etc/apache2/conf-enabled/app-env.conf
done
sort -u -o /etc/apache2/conf-enabled/app-env.conf /etc/apache2/conf-enabled/app-env.conf 2>/dev/null || true

# Диагностика: какие переменные БД видны контейнеру (только названия и хост, без паролей)
for v in DB_HOST DB_PORT DB_NAME DB_USER DB_PASS MYSQLHOST; do
  eval "val=\${$v:-}"
  if [ -n "$val" ]; then echo "  $v: задана"; else echo "  $v: пусто"; fi
done
if [ -z "${DB_HOST:-}${MYSQLHOST:-}" ]; then
  echo "ОШИБКА: не заданы переменные базы данных (DB_HOST, DB_NAME, DB_USER, DB_PASS)."
  echo "Railway: добавьте сервис MySQL (+ Create -> Database -> MySQL), затем в сервисе сайта"
  echo "Variables -> Raw Editor вставьте переменные из docs/DEPLOY-RAILWAY.md (DB_HOST=\${{MySQL.MYSQLHOST}} и т.д.)."
  echo "Если переменные уже добавлены — нажмите Redeploy: Railway подставляет их только при новом запуске."
  sleep 20
  exit 1
fi
echo "Ожидание базы данных (${DB_HOST:-$MYSQLHOST}:${DB_PORT:-${MYSQLPORT:-3306}})..."
i=0
until err=$(php -r 'require "/var/www/html/app/bootstrap.php"; try { Db::pdo(); } catch (Throwable $e) { echo $e->getMessage(); exit(1); }' 2>&1); do
  i=$((i+1))
  [ $((i % 5)) -eq 1 ] && echo "  база пока недоступна: $err"
  if [ $i -gt 45 ]; then
    echo "ОШИБКА: база данных недоступна 90 секунд. Проверьте, что сервис MySQL запущен и переменные DB_* указывают на него."
    exit 1
  fi
  sleep 2
done
echo "База данных подключена."

# Идемпотентно: создаёт недостающие таблицы, демо-данные — только если каталог пуст,
# администратора — только если его нет (пароль из ADMIN_PASSWORD или сгенерированный, см. логи)
cd /var/www/html
if [ -n "$ADMIN_PASSWORD" ]; then
  php bin/install.php --admin="${ADMIN_LOGIN:-admin}" --password="$ADMIN_PASSWORD"
else
  php bin/install.php --admin="${ADMIN_LOGIN:-admin}"
fi
# Если фото потерялись (нет постоянного диска) — восстанавливаем демо-картинки
php bin/repair_images.php
chown -R www-data:www-data public/uploads storage

exec "$@"
