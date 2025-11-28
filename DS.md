Установите таймаут больше 60 секунд, но с запасом для повторных попыток.

// Для JavaScript fetch с AbortController
const controller = new AbortController();
const timeoutId = setTimeout(() => controller.abort(), 120000); // 120 секунд

try {
  const response = await fetch('https://api.deepseek.com/v1/chat/completions', {
    method: 'POST',
    headers: { 'Authorization': 'Bearer YOUR_API_KEY' },
    body: JSON.stringify({
      model: "deepseek-chat",
      messages: [{ role: "user", content: "Your prompt" }],
      max_tokens: 2000
    }),
    signal: controller.signal
  });
  clearTimeout(timeoutId);
} catch (error) {
  if (error.name === 'AbortError') {
    console.log('Request timed out');
  }
}

Реализуйте механизм повторных попыток (Retry Logic) python

import time
import requests

def make_api_request_with_retry(prompt, max_retries=3):
    for attempt in range(max_retries):
        try:
            response = requests.post(
                'https://api.deepseek.com/v1/chat/completions',
                headers={'Authorization': 'Bearer YOUR_API_KEY'},
                json={
                    "model": "deepseek-chat",
                    "messages": [{"role": "user", "content": prompt}],
                    "max_tokens": 2000
                },
                timeout=120
            )
            return response
        except requests.exceptions.Timeout:
            if attempt == max_retries - 1:
                raise
            wait_time = (2 ** attempt) + 1  # Экспоненциальная задержка: 2, 4, 8 сек
            print(f"Timeout occurred. Retrying in {wait_time} seconds...")
            time.sleep(wait_time)