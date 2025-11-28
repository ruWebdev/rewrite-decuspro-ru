"""
Python микросервис для рерайта статей через Deepseek API.
Использует streaming для избежания таймаутов.
"""

import json
import time
import re
from typing import Optional
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
from openai import OpenAI

app = FastAPI(title="Rewriter Service")


class RewriteRequest(BaseModel):
    """Запрос на рерайт статьи."""
    title: str
    content: str
    prompt: str
    api_key: str
    temperature: float = 0.7
    max_tokens: int = 4096


class RewriteResponse(BaseModel):
    """Ответ с результатом рерайта."""
    success: bool
    title: Optional[str] = None
    description: Optional[str] = None
    body: Optional[str] = None
    error: Optional[str] = None


class InterlinkRequest(BaseModel):
    """Запрос на добавление ссылки в текст."""
    content: str
    url: str
    api_key: str
    temperature: float = 0.5
    max_tokens: int = 4096


class InterlinkResponse(BaseModel):
    """Ответ с текстом со ссылкой."""
    success: bool
    content: Optional[str] = None
    error: Optional[str] = None


def estimate_tokens(text: str) -> int:
    """Оценка количества токенов (примерно 3 символа = 1 токен для русского)."""
    return len(text) // 3


def split_content_into_chunks(content: str, max_tokens: int = 2500) -> list[str]:
    """Разбивает контент на части по параграфам."""
    # Разбиваем по параграфам
    paragraphs = re.split(r'(</p>|<br\s*/?>|\n\n)', content, flags=re.IGNORECASE)
    
    chunks = []
    current_chunk = ''
    current_tokens = 0
    
    for paragraph in paragraphs:
        paragraph_tokens = estimate_tokens(paragraph)
        
        # Если параграф слишком большой, разбиваем по предложениям
        if paragraph_tokens > max_tokens:
            if current_chunk:
                chunks.append(current_chunk)
                current_chunk = ''
                current_tokens = 0
            
            # Разбиваем по предложениям
            sentences = re.split(r'(?<=[.!?])\s+', paragraph)
            for sentence in sentences:
                sentence_tokens = estimate_tokens(sentence)
                if current_tokens + sentence_tokens > max_tokens and current_chunk:
                    chunks.append(current_chunk)
                    current_chunk = sentence
                    current_tokens = sentence_tokens
                else:
                    current_chunk += (' ' if current_chunk else '') + sentence
                    current_tokens += sentence_tokens
            continue
        
        if current_tokens + paragraph_tokens > max_tokens and current_chunk:
            chunks.append(current_chunk)
            current_chunk = paragraph
            current_tokens = paragraph_tokens
        else:
            current_chunk += paragraph
            current_tokens += paragraph_tokens
    
    if current_chunk:
        chunks.append(current_chunk)
    
    return chunks if chunks else [content]


def call_deepseek_with_retry(
    client: OpenAI,
    system_prompt: str,
    user_message: str,
    temperature: float,
    max_tokens: int,
    max_retries: int = 5
) -> str:
    """Вызов Deepseek API со streaming и retry."""
    
    last_error = None
    
    for attempt in range(max_retries):
        try:
            # Используем streaming
            stream = client.chat.completions.create(
                model="deepseek-chat",
                messages=[
                    {"role": "system", "content": system_prompt},
                    {"role": "user", "content": user_message}
                ],
                temperature=temperature,
                max_tokens=max_tokens,
                stream=True,
                timeout=300  # 5 минут таймаут
            )
            
            # Собираем ответ из стрима
            full_content = ""
            for chunk in stream:
                if chunk.choices and chunk.choices[0].delta.content:
                    full_content += chunk.choices[0].delta.content
            
            return full_content
            
        except Exception as e:
            last_error = e
            
            if attempt < max_retries - 1:
                # Экспоненциальная задержка: 2, 4, 8, 16, 32 сек (макс 30)
                wait_time = min(2 ** (attempt + 1), 30)
                print(f"Attempt {attempt + 1} failed: {e}. Retrying in {wait_time}s...")
                time.sleep(wait_time)
            else:
                raise Exception(f"Failed after {max_retries} attempts: {last_error}")
    
    raise Exception(f"Failed after {max_retries} attempts: {last_error}")


def parse_json_response(ai_content: str) -> dict:
    """Парсинг JSON из ответа AI."""
    # Удаляем markdown блоки кода
    json_content = ai_content
    match = re.search(r'```(?:json)?\s*([\s\S]*?)```', ai_content)
    if match:
        json_content = match.group(1).strip()
    
    try:
        parsed = json.loads(json_content)
    except json.JSONDecodeError as e:
        raise Exception(f"Failed to parse JSON: {e}")
    
    if not parsed.get('title') or not parsed.get('body'):
        raise Exception("AI returned incomplete JSON (missing title or body)")
    
    return parsed


@app.get("/health")
async def health_check():
    """Проверка работоспособности сервиса."""
    return {"status": "ok"}


@app.post("/rewrite", response_model=RewriteResponse)
async def rewrite_article(request: RewriteRequest):
    """
    Рерайт статьи через Deepseek API.
    Длинные статьи автоматически разбиваются на части.
    """
    try:
        # Создаём клиент OpenAI с Deepseek endpoint
        client = OpenAI(
            api_key=request.api_key,
            base_url="https://api.deepseek.com"
        )
        
        content_tokens = estimate_tokens(request.content)
        max_chunk_tokens = 2500
        
        # Если статья короткая, обрабатываем целиком
        if content_tokens <= max_chunk_tokens:
            user_message = f"Заголовок: {request.title}\n\nТекст статьи:\n{request.content}"
            
            ai_content = call_deepseek_with_retry(
                client=client,
                system_prompt=request.prompt,
                user_message=user_message,
                temperature=request.temperature,
                max_tokens=request.max_tokens
            )
            
            parsed = parse_json_response(ai_content)
            
            return RewriteResponse(
                success=True,
                title=parsed['title'],
                description=parsed.get('description', ''),
                body=parsed['body']
            )
        
        # Разбиваем длинную статью на части
        chunks = split_content_into_chunks(request.content, max_chunk_tokens)
        
        # Первый чанк — получаем title + description + body
        first_message = f"Заголовок: {request.title}\n\nТекст статьи:\n{chunks[0]}"
        first_content = call_deepseek_with_retry(
            client=client,
            system_prompt=request.prompt,
            user_message=first_message,
            temperature=request.temperature,
            max_tokens=request.max_tokens
        )
        
        first_parsed = parse_json_response(first_content)
        new_title = first_parsed['title']
        new_description = first_parsed.get('description', '')
        rewritten_parts = [first_parsed['body']]
        
        # Остальные чанки — только body
        chunk_prompt = "Перепиши следующий текст в том же стиле, сохраняя смысл. Верни только переписанный текст без JSON:"
        
        for i in range(1, len(chunks)):
            try:
                chunk_content = call_deepseek_with_retry(
                    client=client,
                    system_prompt=chunk_prompt,
                    user_message=chunks[i],
                    temperature=request.temperature,
                    max_tokens=3000
                )
                rewritten_parts.append(chunk_content.strip())
            except Exception as e:
                # Если не удалось переписать часть, используем оригинал
                print(f"Failed to rewrite chunk {i}: {e}")
                rewritten_parts.append(chunks[i])
        
        return RewriteResponse(
            success=True,
            title=new_title,
            description=new_description,
            body="\n\n".join(rewritten_parts)
        )
        
    except Exception as e:
        return RewriteResponse(
            success=False,
            error=str(e)
        )


@app.post("/interlink", response_model=InterlinkResponse)
async def add_interlink(request: InterlinkRequest):
    """Добавление ссылки в текст через Deepseek API."""
    try:
        client = OpenAI(
            api_key=request.api_key,
            base_url="https://api.deepseek.com"
        )
        
        system_prompt = f'Впиши в основной текст органично этот URL "{request.url}" в виде гиперссылки. Верни только обновлённый текст без пояснений.'
        
        new_content = call_deepseek_with_retry(
            client=client,
            system_prompt=system_prompt,
            user_message=request.content,
            temperature=request.temperature,
            max_tokens=request.max_tokens
        )
        
        return InterlinkResponse(
            success=True,
            content=new_content.strip()
        )
        
    except Exception as e:
        return InterlinkResponse(
            success=False,
            error=str(e)
        )


if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="127.0.0.1", port=8765)
