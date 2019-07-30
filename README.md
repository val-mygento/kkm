[![Build Status](https://travis-ci.com/mygento/kkm.svg?branch=v2.3)](https://travis-ci.com/mygento/kkm)
[![Latest Stable Version](https://poser.pugx.org/mygento/module-kkm/v/stable)](https://packagist.org/packages/mygento/module-kkm)
[![Total Downloads](https://poser.pugx.org/mygento/module-kkm/downloads)](https://packagist.org/packages/mygento/module-kkm)

# Модуль интеграции АТОЛ онлайн для Magento 1/2

Модуль разрабатывается для полной поддержки требований 54 ФЗ интернет-магазинами на Magento 1 и 2 для сервиса АТОЛ онлайн.
Модуль поддерживает версию сервиса АТОЛ v4 (ФФД 1.05).

## Функционал модуля

### Передача данных в АТОЛ
* отправляет данные о счете/возврате в АТОЛ:
  * автоматически при создании счета (настраивается в конфигурации)
  * автоматически при создании возврата (настраивается в конфигурации)
  * вручную одной из консольных команд (см. ниже)
  * вручную из админки кнопкой на странице Счета или Возврата

### Получение данных из АТОЛ
* получает из АТОЛ данные о статусе регистрации счета/возврата
  * автоматически (настраивается в конфигурации). После обработки данных АТОЛ отправляет реультат обратно (колбек). По умолчанию URL: http://shop.ru/kkm/frontend/callback
  * крон задачей для проверки статусов
  * вручную из админки кнопкой на странице Счета или Возврата
  * консольной командой `mygento:atol:update {$uuid}`

### Процесс отправки данных в АТОЛ
1. На основании сущности Invoice или Creditmemo формируется объект `Mygento\Kkm\Api\Data\RequestInterface`.
    1.1. При асинхронной передаче - объект помещается в очередь (см. Magento Queue Framework)
    1.2. При синхронной передаче - передается классу `Vendor` для отправки

2. Регистрируется попытка отправки данных. Создается сущность `Api\Data\TransactionInterface\TransactionAttemptInterface` со статусом `NEW` (1)

3. Осуществляется передача данных в виде JSON в АТОЛ.
    3.1. В случае **УСПЕШНОЙ** передачи (один из HTTP статусов `[200, 400, 401]`)
    * создается транзакция - сущность `Magento\Sales\Api\Data\TransactionInterface` в который записываются UUID и все данные о передаче. В админке это грида Sales -> Transactions.
    * Сущность попытки отправки `TransactionAttemptInterface` получает статус `Sent` (2)
    * Создается комментарий к заказу
    * Транзакция получает в ККМ-статус (kkm_status) `wait`

    3.2. В случае **НЕУСПЕШНОЙ** передачи (статусы отличные от `[200, 400, 401]`, отсутствие ответа от сервера, некорректные данные в инвойсе или возврате)
    * Сущность попытки отправки `TransactionAttemptInterface` получает статус `Error` (3)
    * Создается комментарий к заказу с описанием причины ошибки
    * Заказ получает статус "KKM Failed"
    * Если выброшено исключение `VendorBadServerAnswerException` (сервер АТОЛ не отвечает и еще в некоторых случаях) и   включена асинхронная передача - то отправка будет снова помещена в очередь.
    * Если выброшено исключение `VendorNonFatalErrorException` и включена асинхронная передача - то выполняется генерация нового external_id и отправка будет снова помещена в очередь.

4. Модуль автоматически запрашивает у АТОЛа статус по всем странзакциям с ККМ-статусом `wait`
    4.1 Попытки обновления статуса прекращаются, когда транзакция получает статус `done` 
    4.2 Максимальное количество попыток настройкой модуля ККМ.
    
5. В случае **НЕУСПЕШНОЙ** передачи в АТОЛ выполняется несколько попыток отправки с увеличивающимися интервалами (например через 1 минуту, 5 минут, 15 минут, 30 минут, 1 час). 
    5.1 Настройка интервалов доступна в настройках модуля ККМ.
    5.2 Максимальное количество попыток отправки в АТОЛ тажке ограничего настройкой модуля ККМ. 
    5.3 В случае, когда достигается максимальное количество попыток отправки, счетчик попыток обнуляется и отправка возобновляется через сутки.

### Отчеты
Модуль отправляет отчеты об отправленных данных в АТОЛ на емейл (в конфиге). Неуспешные отправки отображаются в этом же письме с доп.деталями. Также этот отчет можно посмотреть в консоли.

* Еженедельный (за прошлую неделю), Ежедневный (за текущий день), Ежедневный (за вчерашний день)
* Верстка письма. Файл `view/adminhtml/templates/email/kkm_report-mjml.mjml` содержит верстку письма. Редактируется с помощью сервиса https://mjml.io/


### Поддержка новых версий сервиса АТОЛ Онлайн
Модуль поддерживал версии сервиса v3 и v4. Если выйдет новая версия, необходимо сделать след.шаги:
1.  создать class RequestForVersionX наследник абстрактного класса Request
2.  релилизовать его JSON представление - метод  jsonSerialize()
3.  добавить создание объекта реквеста в  Mygento\Kkm\Model\Atol\RequestFactory
4.  добавить инфу о новой версии сервиса в сурс модель Mygento\Kkm\Model\Source\ApiVersion

### Использование очередей
* отправка сообщений в АТОЛ может осущетвляться в двух режимах:
  * синхронный (сразу после сохранения сущности или ручной отправки);
  * асинхронно (через нативный механизм очередей сообщений Magento).
* режим работы настраивается в конфигурации

### Ручная отправка данных в АТОЛ
* Отправка данных на странице сущности
* Отправка данных консольной командой с указанием IncrementId сущности

### Логирование сообщений
* Модуль логирует (при включенном режиме Debug в Stores -> Configuration -> Mygento Extensions -> Extensions and Support) все запросы (и ответы) АТОЛ.
* Лог запросов доступен на странице конфигурации модуля

## Список Rewrite
нет

## Список событий и плагинов, Описание действий и причины

### События
* **sales_order_invoice_save_commit_after**:
  * отправляет данные по инвойсу после его сохранения.
* **sales_order_creditmemo_save_commit_after**:
  * отправляет данные по возврату после сохранения.

### Плагины
* before плагин `ExtraSalesViewToolbarButtons` на метод `Magento\Backend\Block\Widget\Button\Toolbar::pushButtons` добавляет кнопки Отправки в АТОЛ и кнопку проверки статуса на страницу сущности в админке

## Список доступных реализованных API
нет

## Список встроенных тестов, что и как они тестируют
нет

## Cron-процессы
* **kkm_statuses** 
  * Обновление статуса: job обновляет статусы транзакций, у которых статус `wait`. По умолчанию каждую минуту
* **kkm_proceed_scheduled_attempt**
  * выполняет повторные попытки отправки запросов в АТОЛ по заданному расписанию (scheduled_at).
* **kkm_report**
  * Отчет: job отправки отчета. Частота конфигурируется в админке на стр. настроек модуля. По умолчанию ежедневно в 00:07 

## Консольные команды
* `mygento:atol:report` - Отображает отчет. Аргументы: today, yesterday, week
* `mygento:atol:refund` - Отправляет возврат. Аргументы: IncrementId сущности
* `mygento:atol:sell` - Отправляет счет. Аргументы: IncrementId сущности
* `mygento:atol:update` - Запрашивает данные о статусе. Аргументы: UUID или "all". Если указать 'all' - обновит все зависшие (`wait`) отправки
