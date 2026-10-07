# ONMI Backend — verification service

## Содержание

- [Структура проекта](#struktura)
- [Пояснения по слоям](#poyasneniya)
- [Последовательность сборки](#posledovatelnost)


## <a id="struktura"></a>Структура проекта
```
src/
 ├── Controller/
 │     └── Api/
 │           ├── Registration/                    # Контроллеры регистрации
 │           │     └── RegistrationController.php
 │           │
 │           └── Dictionaries/                    # Контроллеры справочников
 │                 └── DictionariesController.php
 │
 ├── Domain/
 │     └── Dictionary/                            # DDD Domain слой для справочников
 │           └── DictionaryProviderInterface.php  # интерфейс провайдера справочника
 │
 ├── Application/
 │     └── Dictionaries/                          # Слой Application (сервисы, DTO, исключения)
 │           ├── DictionaryService.php            # сервис работы со справочниками
 │           ├── DTO/
 │           │     └── RoleDto.php                # DTO для элемента справочника roles
 │           ├── DictionaryException.php          # базовое исключение справочников
 │           └── DictionaryNotFoundException.php  # кастомное исключение 404
 │
 ├── Infrastructure/
 │     └── Dictionaries/                          # Провайдеры справочников (работа с БД)
 │           ├── RolesDictionary.php
 │           ├── CountriesDictionary.php
 │           └── RolesCountriesDictionary.php
 │
 └── EventSubscriber/                              # Глобальные подписчики событий
       └── ApiExceptionSubscriber.php              # перехватчик всех ошибок API
```

## <a id="poyasneniya"></a>Пояснения по слоям

### Domain
- Не содержит зависимостей от Symfony или БД.

### Application
- Сервисы (`DictionaryService`) управляют справочниками и используют провайдеры.
- DTO (`RoleDto`) описывает структуры данных для API.
- Исключения (`DictionaryNotFoundException`) задают бизнес-ошибки и коды для API.

### Infrastructure
- Конкретная реализация провайдеров (`RolesDictionary`), которые извлекают данные из БД.
- Реализует интерфейсы Domain.

### Controller
- Обрабатывает HTTP-запросы и формирует ответы в формате API.
- Контроллеры регистрационных операций (`RegistrationController`) отделены от контроллеров справочников (`DictionariesController`).

### EventSubscriber
- Перехватывает все исключения и возвращает стандартизированный JSON с кодами ошибок и timestamp.
- Работает для кастомных исключений (404, 500) и технических ошибок (PDO, DBAL).


## <a id="posledovatelnost"></a>Последовательность сборки
```bash
# Клонируем основной репозиторий в директорию /services/auth-service/app
git clone -b main https://git.tknovosib.ru/omni/mp-backend.git .
```
```bash
# После клонирования репозитория собираем оркестрацию согласно командам из ONMI-infra
make up  make migrate  make bootstrap   make rebuild-db
```
```bash
# как только все контейнеры собраны запускаем миграцию базы данных внутри контейнера с проектом
php bin/console doctrine:migrations:migrate   
```
```bash
# Подключение к DB через любой консольный клиент поддерживающий Postgresql
host: localhost
port: $POSTGRES_USER
username: $POSTGRES_USER   
password: $POSTGRES_PASSWORD
```
```bash
# роутинг идет по префиксам в URL - префикс данного проекта
/api/auth/
```


php -r "echo password_hash('pass1234', PASSWORD_BCRYPT) . PHP_EOL;"
