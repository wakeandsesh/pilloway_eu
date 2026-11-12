<?php
return ['x-generator'=>'GlotPress/4.1.0','translation-revision-date'=>'2026-09-16 12:45:24+0000','plural-forms'=>'nplurals=3; plural=(n % 10 == 1 && n % 100 != 11) ? 0 : ((n % 10 >= 2 && n % 10 <= 4 && (n % 100 < 12 || n % 100 > 14)) ? 1 : 2);','project-id-version'=>'Plugins - CatCode Order Notifications with Telegram for WooCommerce - Stable (latest release)','language'=>'uk_UA','messages'=>['Instant notifications about site form submissions to a Telegram chat, group or topic.'=>'Миттєві повідомлення про заявки з форм сайту у Telegram-чат, групу або тему.','Telegram Notifications: neither WooCommerce nor a form source (Contact Form 7) was found, so nothing will be sent yet.'=>'Telegram-сповіщення: не знайдено ані WooCommerce, ані джерела заявок (Contact Form 7) — поки що нічого не надсилатиметься.','Instant Telegram notifications for WooCommerce orders and site form leads: new orders, status changes, failed payments, low stock, Contact Form 7. WooCommerce is optional. Flexible message templates, multiple chats and topics, and a delivery log.'=>'Миттєві сповіщення в Телеграм про замовлення WooCommerce і заявки з форм сайту: нові замовлення, зміна статусу, невдалі оплати, закінчення залишків, Contact Form 7. WooCommerce не обов\'язковий. Гнучкі шаблони повідомлень, кілька чатів і тем, журнал доставки.','🛒 <b>New order {order_number}</b>
👤 {customer_name}
📞 {customer_phone}
💳 {payment_method}
🚚 {shipping_method}
📍 {shipping_address}

{items}
💰 Total: <b>{order_total}</b>
📝 {customer_note}
{admin_url}'=>'🛒 <b>Нове замовлення {order_number}</b>
👤 {customer_name}
📞 {customer_phone}
💳 {payment_method}
🚚 {shipping_method}
📍 {shipping_address}

{items}
💰 Разом: <b>{order_total}</b>
📝 {customer_note}
{admin_url}','📩 <b>{type}</b>
👤 {name}
📞 {phone}
✉️ {email}
📦 {product}
🔖 SKU: {product_sku}
📝 {comment}
{extra}
{page_url}'=>'📩 <b>{type}</b>
👤 {name}
📞 {phone}
✉️ {email}
📦 {product}
🔖 Артикул: {product_sku}
📝 {comment}
{extra}
{page_url}','⚠️ <b>Low stock</b>
{product_name}
SKU: {product_sku}
Remaining: <b>{stock_qty}</b>'=>'⚠️ <b>Низький залишок</b>
{product_name}
Артикул: {product_sku}
Залишилось: <b>{stock_qty}</b>','🔄 <b>Order {order_number}</b>
Status: {order_status_old} → <b>{order_status}</b>
Total: {order_total}
{admin_url}'=>'🔄 <b>Замовлення {order_number}</b>
Статус: {order_status_old} → <b>{order_status}</b>
Разом: {order_total}
{admin_url}','Lead page'=>'Сторінка заявки','Page the lead was sent from'=>'Сторінка, з якої відправлено заявку','Other form fields'=>'Інші поля форми','Product (form lead)'=>'Товар (заявка з форми)','Comment (form lead)'=>'Коментар (заявка з форми)','Email (form lead)'=>'Email (заявка з форми)','Phone (form lead)'=>'Телефон (заявка з форми)','Name (form lead)'=>'Ім’я (заявка з форми)','Form title (form lead)'=>'Назва форми (заявка з форми)','Lead type (form lead)'=>'Тип заявки (заявка з форми)','Form lead'=>'Заявка з форми','Works with Contact Form 7 and with any plugin that fires the catcode_lead_captured action (KeyCRM Form Catcher, for example — then the lead also carries its type, product and SKU).'=>'Працює з Contact Form 7, а також з будь-яким модулем, що викликає екшен catcode_lead_captured (наприклад KeyCRM Form Catcher — тоді в заявці є тип, товар і артикул).','Notify about leads from site forms'=>'Повідомляти про заявки з форм сайту','Site form leads'=>'Заявки з форм','Leave a review'=>'Залишити відгук','The plugin is free — if it works for you, please leave a review, it helps keep it going.'=>'Модуль безкоштовний — якщо він вам підходить, залиште відгук, це допомагає його розвивати.','Telegram notifications are working.'=>'Telegram-сповіщення працюють.','Two copies of Telegram Notifications are active. Keep only one of them.'=>'Активні дві копії Telegram-сповіщень. Залиште лише одну з них.','CatCode Order Notifications with Telegram for WooCommerce'=>'CatCode Order Notifications with Telegram for WooCommerce','CatCode'=>'CatCode','https://catcode.com.ua'=>'https://catcode.com.ua','Telegram Notifications'=>'Telegram-сповіщення','Instant WooCommerce order notifications to a Telegram chat, group or topic.'=>'Миттєві повідомлення про замовлення WooCommerce у Telegram-чат, групу або тему.','Settings'=>'Налаштування','Log'=>'Журнал','Connection'=>'Підключення','Bot token'=>'Токен бота','•••••• saved — enter to replace'=>'•••••• збережено — введіть щоб замінити','Create a bot via @BotFather → /newbot and paste the token you receive. It is stored encrypted.'=>'Створіть бота у @BotFather → /newbot і вставте отриманий токен. Зберігається у зашифрованому вигляді.','Token saved.'=>'Токен збережено.','Chats for notifications'=>'Чати для сповіщень','One per line: a chat id, a group id (negative) or a channel @username. For a topic in a forum group append ":topic_id" — e.g. -1001234567890:42.'=>'По одному на рядок: ID чату, ID групи (від’ємний) або @username каналу. Для теми у форум-групі додайте ":ID_теми" — напр. -1001234567890:42.','Silent messages'=>'Тихі повідомлення','Send notifications without sound'=>'Надсилати без звуку сповіщення','Events'=>'Події','New order'=>'Нове замовлення','Notify about new orders'=>'Повідомляти про нові замовлення','An order is announced once — the first time it enters one of the selected statuses.'=>'Замовлення оголошується один раз — коли вперше потрапляє в один із позначених статусів.','Status change'=>'Зміна статусу','Notify about order status changes'=>'Повідомляти про зміну статусу замовлення','Failed payment'=>'Невдала оплата','Notify about orders with the "Failed" status'=>'Повідомляти про замовлення зі статусом «Не вдалося»','Low stock'=>'Низький залишок','Notify when a product reaches the low-stock threshold'=>'Повідомляти, коли товар досягає порогу низького залишку','The threshold is taken from WooCommerce → Settings → Products → Inventory.'=>'Поріг береться з WooCommerce → Налаштування → Товари → Складський облік.','Message templates'=>'Шаблони повідомлень','Available tags:'=>'Доступні теги:','A line whose tags are all empty is removed from the message. Allowed Telegram markup: <b>, <i>, <u>, <s>, <code>, <a href>.'=>'Рядок, усі теги якого порожні, не потрапляє в повідомлення. Дозволена розмітка Telegram: <b>, <i>, <u>, <s>, <code>, <a href>.','Save settings'=>'Зберегти налаштування','Send a test message'=>'Надіслати тестове повідомлення','Checks the token and sends a test to all specified chats.'=>'Перевіряє токен і надсилає тест у всі вказані чати.','Find chat_id'=>'Знайти chat_id','Send the bot any message (or add it to a group) and click — it will show the chat ids.'=>'Напишіть боту будь-яке повідомлення (або додайте його в групу) і натисніть — покаже ID чатів.','Recent deliveries'=>'Останні відправки','No events yet.'=>'Подій ще немає.','Date'=>'Дата','Order'=>'Замовлення','Event'=>'Подія','Chat'=>'Чат','Result'=>'Результат','Message'=>'Повідомлення','Error'=>'Помилка','Insufficient permissions'=>'Немає прав','Settings saved.'=>'Налаштування збережено.','Token error: %s'=>'Помилка токена: %s','No chats specified.'=>'Не вказано жодного чату.','Bot @%1$s — message sent to %2$d chat(s).'=>'Бот @%1$s — повідомлення надіслано в %2$d чат(ів).','Not delivered: %s'=>'Не доставлено: %s','Error: %s'=>'Помилка: %s','No updates. Send the bot a message (or add it to a group) and try again. If the bot has a webhook enabled, getUpdates will not work.'=>'Оновлень немає. Напишіть боту повідомлення (або додайте його в групу) і спробуйте ще раз. Якщо для бота увімкнено webhook — getUpdates не працює.','Chats found: %s'=>'Знайдені чати: %s','Order number'=>'Номер замовлення','Order ID'=>'ID замовлення','Order total'=>'Сума замовлення','Current status'=>'Поточний статус','Previous status'=>'Попередній статус','Order date'=>'Дата замовлення','Items list'=>'Список товарів','Item count'=>'Кількість позицій','Customer name'=>'Ім’я покупця','Phone'=>'Телефон','Email'=>'Email','Customer note'=>'Коментар покупця','Payment method'=>'Спосіб оплати','Shipping method'=>'Спосіб доставки','Shipping total'=>'Вартість доставки','Billing address'=>'Адреса платника','Shipping address'=>'Адреса доставки','Admin link'=>'Посилання в адмінку','Store name'=>'Назва магазину','Product name (low stock)'=>'Назва товару (низький залишок)','SKU (low stock)'=>'Артикул (низький залишок)','Stock quantity (low stock)'=>'Залишок (низький залишок)','Bot token is not set.'=>'Токен бота не задано.','Open product'=>'Відкрити товар','Edit product'=>'Редагувати товар','Open order'=>'Відкрити замовлення','✅ <b>Test message</b>%1$sStore: %2$s%3$sTelegram notifications are configured.'=>'✅ <b>Тестове повідомлення</b>%1$sМагазин: %2$s%3$sTelegram-сповіщення налаштовано.','https://catcode.com.ua/modules/telegram-order-notifications-for-woocommerce/'=>'https://catcode.com.ua/modules/telegram-order-notifications-for-woocommerce/']];