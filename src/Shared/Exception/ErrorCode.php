<?php

namespace App\Shared\Exception;

/**
 * Единый список всех уникальных кодов ошибок в приложении.
 * Используется в exception классах, например UnprocessableEntityException.
 */
enum ErrorCode: string
{
    // Общие
    case S_INTERNAL_SERVER_ERROR = 'S_INTERNAL_SERVER_ERROR';
    case S_BAD_REQUEST = 'S_BAD_REQUEST';
    case S_DEFAULT_UNPROCESSABLE_ENTITY = 'S_DEFAULT_UNPROCESSABLE_ENTITY';
    case S_NOT_FOUND = 'S_NOT_FOUND';
    case S_ACCESS_DENIED = 'S_ACCESS_DENIED';
    case S_SEND_FAILED = 'S_SEND_FAILED'; // Ошибка при отправке кода
    case S_ROUTE_NOT_FOUND = 'S_ROUTE_NOT_FOUND'; // Маршрут API не найден


    // Быстрая регистрация
    case B_USER_NOT_FOUND = 'USER_NOT_FOUND';
    case B_USER_EMAIL_EXISTS = 'USER_EMAIL_EXISTS';
    case B_EMAIL_VERIFICATION_ATTEMPTS_EXCEEDED = 'B_EMAIL_VERIFICATION_ATTEMPTS_EXCEEDED'; // Попытки проверки кода закончились или истёк TTL
    case B_ROLE_UNAVAILABLE_IN_COUNTRY = 'B_ROLE_UNAVAILABLE_IN_COUNTRY'; // Роль поставщика недоступна в стране. Обратитесь в поддержку
    case B_RESEND_INTERVAL_NOT_EXPIRED = 'B_RESEND_INTERVAL_NOT_EXPIRED'; // Интервал повторной отправки кода ещё не истёк
    case B_MAX_RESEND_ATTEMPTS_EXCEEDED = 'B_MAX_RESEND_ATTEMPTS_EXCEEDED'; // Достигнуто максимальное количество повторных генераций кода
    case B_TOO_MANY_REQUESTS = 'Verification_B_TOO_MANY_REQUESTS'; // Слишком много попыток регистрации с эти адресом почты. Прошу ободжать одну минуту.
    case B_MAIL_REGISTRATION_ALREADY_PENDING = 'B_MAIL_REGISTRATION_ALREADY_PENDING'; // Вы уже начали регистрацию  с этим адресом эл почты. Прошу вас ввести код проверки.
    case B_PERSONAL_DATA_CONSENT_REQUIRED ='B_PERSONAL_DATA_CONSENT_REQUIRED'; // Согласие на использование личных данных должно быть получено
    case B_MARKETING_CONSENT_REQUIRED ='B_MARKETING_CONSENT_REQUIRED'; // Подтверждение согласия на маркетинг обязательно
    case B_MARKETING_VERSION_MISSING = 'B_MARKETING_VERSION_MISSING'; // Наличие версии согласия обязательно
    case B_FIELD_REQUIRED = 'B_FIELD_REQUIRED'; // Отсутствует поле в переданных данных
    case B_FIELD_MUST_BE_STRING = 'B_FIELD_MUST_BE_STRING'; // Поле должно быть строкой
    case B_FIELD_MUST_BE_STRING_OR_NULL = 'B_FIELD_MUST_BE_STRING_OR_NULL'; // Поле должно быть строкой или null
    case B_FIELD_MUST_BE_BOOL = 'B_FIELD_MUST_BE_BOOL'; // Поле должно быть логическим
    case B_EMAIL_INVALID = 'B_EMAIL_INVALID'; // Поле email не валидно

    // Проверка ввода кода почты
    case B_OTP_REQUEST_ID_REQUIRED = 'B_OTP_REQUEST_ID_REQUIRED';
    case B_OTP_CODE_REQUIRED = 'B_OTP_CODE_REQUIRED';
    case B_OTP_REQUEST_ID_INVALID = 'B_OTP_REQUEST_ID_INVALID';
    case B_OTP_CODE_INVALID = 'B_OTP_CODE_INVALID';
    case B_REG_NOT_FOUND = 'B_REG_NOT_FOUND';
    case B_REG_EXPIRED = 'B_REG_EXPIRED';
    case B_OTP_NOT_FOUND = 'B_OTP_NOT_FOUND';
    case B_OTP_ATTEMPTS_EXCEEDED = 'B_OTP_ATTEMPTS_EXCEEDED';

    // Справочники
    case D_NOT_FOUND_DICTIONARY_COUNTRIES = 'D_NOT_FOUND_DICTIONARY_COUNTRIES' ; // Справочник стран не найден
    case D_ROLE_NOT_FOUND = 'D_ROLE_NOT_FOUND'; // Роль не найдена в справочнике ролей
    case D_COUNTRY_NOT_FOUND = 'D_COUNTRY_NOT_FOUND'; // Страна не найдена в справочнике стран
    case D_INTEGRATION_ERROR = 'D_INTEGRATION_ERROR';

    // Валидация
    case B_VALIDATION_FAILED = 'Verification_D_VALIDATION_FAILED';
    case B_COUNTRY_NOT_FOUND = 'Verification_D_COUNTRY_NOT_FOUND';
    case B_ROLE_NOT_FOUND = 'Verification_D_ROLE_NOT_FOUND';
    case B_CHANNEL_NOT_FOUND = 'Verification_CHANNEL_NOT_FOUND';

    case B_PHONE_INVALID = 'Verification_PHONE_INVALID';
    case B_COUNTRY_INVALID_FORMAT = 'Verification_COUNTRY_INVALID_FORMAT';
    case B_CHANNEL_INVALID_FORMAT = 'Verification_CHANNEL_INVALID_FORMAT';
    case B_EMAIL_MISMATCH = 'Verification_CHANNEL_EMAIL_MISMATCH';
    case VERIFICATION_EMAIL_PROCESS_ALREADY_STARTED = 'VERIFICATION_EMAIL_PROCESS_ALREADY_STARTED';
    case B_SMS_VERIFICATION_CODE_MISMATCH = 'B_SMS_VERIFICATION_CODE_MISMATCH';

    // AUTH
    case AUTH_BAD_REQUEST = 'AUTH_BAD_REQUEST';
    case AUTH_FIELD_REQUIRED = 'AUTH_FIELD_REQUIRED';
    case AUTH_FIELD_EMPTY = 'AUTH_FIELD_EMPTY';
    case AUTH_FIELD_MISSING = 'AUTH_FIELD_MISSING';
    case AUTH_UNAUTHORIZED = 'AUTH_UNAUTHORIZED';
    case AUTH_TOO_MANY_REQUESTS = 'AUTH_TOO_MANY_REQUESTS';
    case AUTH_USER_NOT_FOUND = 'AUTH_USER_NOT_FOUND';
    case AUTH_INVALID_REFRESH_TOKEN = 'AUTH_INVALID_REFRESH_TOKEN';
    case AUTH_TOKEN_EXPIRED = 'AUTH_TOKEN_EXPIRED';
    case AUTH_TOKEN_REVOKED = 'AUTH_TOKEN_REVOKED';
    case AUTH_SMS_INTEGRATION_ERROR = 'AUTH_SMS_INTEGRATION_ERROR';

    // IMPORT / UPLOAD FILES
    case IMPORT_FILE_MISSING = 'IMPORT_FILE_MISSING';
    case IMPORT_FILE_INVALID = 'IMPORT_FILE_INVALID';
    case IMPORT_FILE_TOO_LARGE = 'IMPORT_FILE_TOO_LARGE';
    case IMPORT_FILE_UPLOAD_FAILED = 'IMPORT_FILE_UPLOAD_FAILED';
    case IMPORT_FILE_STORAGE_ERROR = 'IMPORT_FILE_STORAGE_ERROR';
    case IMPORT_FILE_NOT_SAVED = 'IMPORT_FILE_NOT_SAVED';
    case IMPORT_FILE_UNSUPPORTED_TYPE = 'IMPORT_FILE_UNSUPPORTED_TYPE';
    case IMPORT_FILE_EMPTY = 'IMPORT_FILE_EMPTY';
    case IMPORT_FILE_CORRUPTED = 'IMPORT_FILE_CORRUPTED';
    case IMPORT_FILE_READ_ERROR = 'IMPORT_FILE_READ_ERROR';
    case IMPORT_FILE_PARSE_FAILED = 'IMPORT_FILE_PARSE_FAILED';
    case CATALOG_AUTH_INVALID_TOKEN = 'CATALOG_AUTH_INVALID_TOKEN';
    case CATALOG_AUTH_TOKEN_EXPIRED = 'CATALOG_AUTH_TOKEN_EXPIRED';
    case CATALOG_AUTH_AUTH_REQUIRED = 'CATALOG_AUTH_AUTH_REQUIRED';
    case CATALOG_FORBIDDEN = 'CATALOG_FORBIDDEN';
    case CATALOG_USER_NOT_FOUND = 'CATALOG_USER_NOT_FOUND';
}
