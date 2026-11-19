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

echo "Ожидание базы данных..."
i=0
until php -r 'require "/var/www/html/app/bootstrap.php"; Db::pdo();' 2>/dev/null; do
  i=$((i+1)); [ $i -gt 60 ] && { echo "База данных недоступна — проверьте переменные DB_* / MYSQL*"; exit 1; }
  sleep 2
done

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
