# Pilloway EU: эксплуатация и публикация

Локальная среда: EU http://127.0.0.1:8098, UA http://127.0.0.1:8099.
EU обслуживается из named volume `pilloway_eu_wordpress_data`; uploads и БД — отдельные существующие тома. Изменения исходников переносить командой `sudo python3 wordpress/ops/sync-local.py` из родительской папки проекта в WSL. Скрипт сохраняет uploads. Не удалять тома БД/uploads.

Снимок compose: `compose.local.yaml`. БД и runtime-конфигурация расположены вне Git. `wp-config.template.php` содержит конфигурацию домена, локального доступа и памяти 512M без ключей аутентификации. При использовании шаблона передать уникальные WORDPRESS_AUTH_KEY/SECURE_AUTH_KEY/LOGGED_IN_KEY/NONCE_KEY/AUTH_SALT/SECURE_AUTH_SALT/LOGGED_IN_SALT/NONCE_SALT через окружение; их нельзя копировать с другого сайта.

Для домена применить значения из `production.env.example`: PILLOWAY_HOME=https://pilloway.eu, WP_ENVIRONMENT_TYPE=production, WORDPRESS_DEBUG=0. Передать реальные пароли БД. Настроить DNS на целевой сервер, TLS на reverse proxy и доступ к WordPress только через этот proxy. PILLOWAY_HOME принимает только настроенный Host и задаёт HTTPS-ссылки. После импорта БД выполнить сериализованно-безопасный `wp search-replace 'http://127.0.0.1:8098' 'https://pilloway.eu' --all-tables-with-prefix --skip-columns=guid`, обновить home/siteurl и сбросить rewrite/cache. Пересоздать контейнер после изменения окружения.

Платежи: локально включён sandbox WayForPay для EUR/USD и LiqPay для UA/UAH. В production тестовые шлюзы автоматически исключаются. Для приёма реальных платежей нужны рабочий WayForPay merchant и secret, testmode=no, HTTPS webhook. Банковский перевод с прежним UA IBAN за пределами UA скрыт; EU-IBAN не выдуман. bacs/cheque остаются выключены.

Аналитика: Meta ID задаётся WordPress option `pilloway_meta_pixel_id`; до предоставления EU ID скопированный UA ID не используется. CAPI token — только PILLOWAY_META_CAPI_TOKEN вне Git. GA4 — штатная настройка WooCommerce Google Analytics; EU GA4 ID ещё не предоставлен. TikTok ID не задан. GLA target audience содержит 27 стран ЕС.

Cookies: display-only по согласованному требованию, выбор хранится год, трекеры им не блокируются. Это информационная настройка, а не механизм блокирования трекеров до согласия. Реальное поведение описано в privacy policy на языках сайта.

Языки: все шесть существующих языков сохранены, включая немецкий; каталог темы twentytwentyfive сохранён ради привязок меню Polylang. Поддержка доставки определяется существующими зонами Nova Post; наличие страны в списке оплаты не создаёт доставку в неподдерживаемую страну. Бесплатная доставка UA от 5000 UAH не переносится автоматически на зарубежные направления — они остаются по тарифам перевозчика.

Бэкапы перед изменениями: ../backups/database-before-handoff-completion.sql (EU), ../../pilloway-ua/backups/database-before-handoff-cookie-completion.sql (UA). Восстановление БД выполнять отдельно от кода; не применять старые формулы P2 — новые правила в PRICING.md.

EU Google Shopping Feed формирует английские карточки в EUR. Feed URL берётся в WooCommerce → Google Shopping Feed, scope «только акции» возвращает 101 товар, весь каталог — 384. Целевые ссылки задают страну DE для совпадения валюты. До загрузки в Merchant Center требуется подтвердить GTIN/MPN или их отсутствие у товаров.

Первый запуск на новом Docker-хосте: клонировать репозиторий в каталог wordpress, из wordpress/ops скопировать compose.local.yaml как ../compose.yaml, Dockerfile.wsl как ../Dockerfile.wsl и каталог apache в ../apache. Подготовить ../.env с production-настройками и скопировать wp-config.template.php в wordpress/wp-config.php. Запустить compose с override wordpress/ops/compose.production.yaml, затем выполнить sync-local.py. На НОВОМ пустом uploads-томе отдельно перенести содержимое wordpress/wp-content/uploads в /var/www/html/wp-content/uploads (обычная синхронизация кода uploads намеренно не заменяет). Импортировать финальный дамп БД, выполнить замену локальных URL и проверить HTTPS. Существующие тома не удалять. Эти шаги публикации ещё не выполнялись на сервере: доступ не предоставлен.
