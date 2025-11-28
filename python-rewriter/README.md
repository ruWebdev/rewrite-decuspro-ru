# Python Rewriter Microservice

Микросервис для рерайта статей через Deepseek API с поддержкой streaming.

## Быстрый запуск

```bash
cd /var/www/decuspro/rewrite/python-rewriter
chmod +x run.sh
./run.sh
```

Сервис запустится на `http://127.0.0.1:8765`

## Установка как systemd сервис (для продакшена)

```bash
# Создаём виртуальное окружение и устанавливаем зависимости
cd /var/www/decuspro/rewrite/python-rewriter
python3 -m venv venv
source venv/bin/activate
pip install -r requirements.txt

# Копируем systemd unit
sudo cp rewriter.service /etc/systemd/system/

# Включаем и запускаем
sudo systemctl daemon-reload
sudo systemctl enable rewriter
sudo systemctl start rewriter

# Проверяем статус
sudo systemctl status rewriter
```

## API Endpoints

### POST /rewrite
Рерайт статьи. Длинные статьи автоматически разбиваются на части.

```json
{
    "title": "Заголовок статьи",
    "content": "Текст статьи...",
    "prompt": "Системный промпт для AI",
    "api_key": "sk-xxx",
    "temperature": 0.7,
    "max_tokens": 4096
}
```

Ответ:
```json
{
    "success": true,
    "title": "Новый заголовок",
    "description": "Мета-описание",
    "body": "Переписанный текст..."
}
```

### POST /interlink
Добавление ссылки в текст.

```json
{
    "content": "Текст статьи...",
    "url": "https://example.com/page",
    "api_key": "sk-xxx",
    "temperature": 0.5,
    "max_tokens": 4096
}
```

Ответ:
```json
{
    "success": true,
    "content": "Текст со вставленной ссылкой..."
}
```

### GET /health
Проверка работоспособности сервиса.

## Особенности

- **Streaming**: Использует streaming API Deepseek для избежания таймаутов
- **Retry**: До 5 попыток с экспоненциальной задержкой при ошибках
- **Chunking**: Автоматическое разбиение длинных статей на части (~2500 токенов)
- **Timeout**: 5 минут на каждый запрос к Deepseek API
