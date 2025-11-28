#!/bin/bash
# Скрипт запуска Python микросервиса для рерайта

cd "$(dirname "$0")"

# Создаём виртуальное окружение если его нет
if [ ! -d "venv" ]; then
    echo "Creating virtual environment..."
    python3 -m venv venv
fi

# Активируем виртуальное окружение
source venv/bin/activate

# Устанавливаем зависимости
echo "Installing dependencies..."
pip install -r requirements.txt --quiet

# Запускаем сервис
echo "Starting rewriter service on http://127.0.0.1:8765"
uvicorn main:app --host 127.0.0.1 --port 8765
