"""
Advanced Address Extractor & Geocoder for Russian and Ukrainian.
Hybrid: Free AI model (Groq / Gemini) + offline NLP fallback.

Features:
  - AI-powered extraction via free LLM APIs (Groq Llama 3.3, Google Gemini)
  - Handles inflected street names, no-prefix addresses, implicit locations
  - Offline fallback: regex + natasha + pymorphy2
  - Free geocoding via Nominatim
  - Structured JSON output from LLM for reliable parsing
  - Batch processing with rate limiting

Install:
    pip install geopy natasha pymorphy2 pymorphy2-dicts-uk transliterate requests

Optional (for specific AI backends):
    pip install groq              # for Groq / Llama
    pip install google-genai      # for Google Gemini

Environment variables (set ONE):
    GROQ_API_KEY=gsk_...          # https://console.groq.com  (free)
    GEMINI_API_KEY=...            # https://aistudio.google.com (free)

Usage:
    from address_extractor import extract_and_geocode

    result = extract_and_geocode("Зустрінемось на Хрещатику, 22 о третій")
    print(result.to_dict())
"""

from __future__ import annotations

import json
import logging
import os
import re
import time
from abc import ABC, abstractmethod
from dataclasses import dataclass, asdict, field
from typing import Optional

import requests
from geopy.geocoders import Nominatim
from geopy.exc import GeocoderTimedOut, GeocoderServiceError

# ── Natasha (Russian NLP) ────────────────────────────────────────
from natasha import (
    Segmenter, MorphVocab, NewsEmbedding,
    NewsMorphTagger, NewsNERTagger, Doc, AddrExtractor,
)
import pymorphy2

logger = logging.getLogger(__name__)

# ═══════════════════════════════════════════════════════════════════
# Init heavy NLP resources (loaded once at import)
# ═══════════════════════════════════════════════════════════════════

_segmenter = Segmenter()
_morph_vocab = MorphVocab()
_emb = NewsEmbedding()
_morph_tagger = NewsMorphTagger(_emb)
_ner_tagger = NewsNERTagger(_emb)
_addr_extractor = AddrExtractor(_morph_vocab)
_morph_ru = pymorphy2.MorphAnalyzer(lang="ru")

try:
    _morph_uk = pymorphy2.MorphAnalyzer(lang="uk")
except Exception:
    _morph_uk = None


# ═══════════════════════════════════════════════════════════════════
# Data models
# ═══════════════════════════════════════════════════════════════════

@dataclass
class ParsedAddress:
    """Structured address extracted by any method."""
    street_type: str = ""       # "вулиця", "проспект", etc. (nominative)
    street_name: str = ""       # "Хрещатик", "Тверская" (nominative)
    building: str = ""          # "22", "7А"
    apartment: str = ""         # "5", "12"
    city: str = ""              # "Київ", "Москва" (nominative)
    postal_code: str = ""       # "01001"
    raw_text: str = ""          # original extracted fragment
    confidence: float = 0.0     # 0.0 – 1.0

    def to_geocode_string(self) -> str:
        """Build a string optimized for Nominatim geocoding."""
        parts = []
        if self.street_type and self.street_name:
            parts.append(f"{self.street_type} {self.street_name}")
        elif self.street_name:
            parts.append(self.street_name)
        if self.building:
            parts.append(self.building)
        if self.city:
            parts.append(self.city)
        return ", ".join(parts)

    def to_display_string(self) -> str:
        """Human-readable normalized address."""
        parts = []
        st = f"{self.street_type} {self.street_name}" if self.street_type else self.street_name
        if st.strip():
            parts.append(st.strip())
        if self.building:
            parts.append(self.building)
        if self.apartment:
            parts.append(f"кв. {self.apartment}")
        if self.city:
            parts.append(self.city)
        if self.postal_code:
            parts.append(self.postal_code)
        return ", ".join(parts)


@dataclass
class GeoResult:
    """Final result of extraction + geocoding."""
    original_text: str
    parsed: Optional[ParsedAddress] = None
    latitude: Optional[float] = None
    longitude: Optional[float] = None
    display_name: Optional[str] = None
    language: str = ""
    method: str = ""            # "groq", "gemini", "regex", "natasha", "heuristic"
    geocoded: bool = False
    error: Optional[str] = None

    def to_dict(self) -> dict:
        d = asdict(self)
        return d


# ═══════════════════════════════════════════════════════════════════
# Language detection
# ═══════════════════════════════════════════════════════════════════

_UK_UNIQUE = set("іїєґІЇЄҐ")
_RU_UNIQUE = set("ёъыэЁЪЫЭ")


def detect_language(text: str) -> str:
    chars = set(text)
    has_uk = bool(chars & _UK_UNIQUE)
    has_ru = bool(chars & _RU_UNIQUE)
    if has_uk and not has_ru:
        return "uk"
    if has_ru and not has_uk:
        return "ru"
    if has_uk and has_ru:
        return "uk"
    if re.search(r"\b(вул|просп|пров|буд|м\.)\b", text, re.IGNORECASE):
        return "uk"
    if re.search(r"\b(ул|пр-т|пер|д\.)\b", text, re.IGNORECASE):
        return "ru"
    if re.search(r"[а-яА-Я]", text):
        return "ru"
    return "unknown"


# ═══════════════════════════════════════════════════════════════════
# AI Backends (abstract + implementations)
# ═══════════════════════════════════════════════════════════════════

# ── Shared prompt ─────────────────────────────────────────────────

_SYSTEM_PROMPT = """\
You are an expert address extraction system for Russian and Ukrainian texts.

TASK: Extract the physical address from the user's message.

CRITICAL RULES:
1. Convert ALL words to NOMINATIVE case (називний відмінок / именительный падеж):
   - "на Хрещатику" → street_name: "Хрещатик"
   - "по Тверской"  → street_name: "Тверская"
   - "Шевченка"     → street_name: "Шевченко" (if it's a person's name used as street)
   - "на Арбате"    → street_name: "Арбат"
   - "Большую Садовую" → street_name: "Большая Садовая"
   - "на Невском"   → street_name: "Невский"
   - "Грушевського"  → street_name: "Грушевський"
   - "Тараса Шевченка" → street_name: "Тарас Шевченко"

2. Detect the street type even if abbreviated or absent:
   - "вул." / "вулиця" / "вулиці" → street_type: "вулиця"
   - "ул." / "улица" / "улице"    → street_type: "улица"
   - "просп." / "проспект"        → street_type: "проспект"
   - "пров." / "провулок"         → street_type: "провулок"
   - "пер." / "переулок"          → street_type: "переулок"
   - "пл." / "площа" / "площадь"  → street_type: "площа" (uk) or "площадь" (ru)
   - "бульв." / "бульвар"         → street_type: "бульвар"
   - "наб." / "набережна/ая"      → street_type: "набережна" (uk) or "набережная" (ru)
   - "узвіз"                      → street_type: "узвіз"
   - "шосе" / "шоссе"             → keep as is
   - If no type given, infer "вулиця" (uk) or "улица" (ru) as default.

1. Detect the city from context (it may always be Dnipro if not mentioned).:
   - "у Дніпрі" / "в Дніпрі" → city: "Дніпро"

4. Extract building number, apartment, postal code if present.

5. If NO address is found, return all fields as empty strings.

Respond with ONLY a JSON object, no markdown, no backticks, no explanation:
{
  "street_type": "...",
  "street_name": "...",
  "building": "...",
  "apartment": "...",
  "city": "...",
  "postal_code": "...",
  "raw_text": "...",
  "confidence": 0.0
}

"raw_text" = the substring of the original message that contains the address.
"confidence" = 0.0 to 1.0, how confident you are that an address was found.
"""


def _parse_llm_response(text: str) -> Optional[ParsedAddress]:
    """Safely parse LLM JSON response into ParsedAddress."""
    text = text.strip()
    # Strip markdown fences if present
    text = re.sub(r"^```(?:json)?\s*", "", text)
    text = re.sub(r"\s*```$", "", text)
    text = text.strip()

    try:
        data = json.loads(text)
    except json.JSONDecodeError:
        # Try to extract JSON object from the response
        m = re.search(r"\{[^{}]+\}", text, re.DOTALL)
        if m:
            try:
                data = json.loads(m.group(0))
            except json.JSONDecodeError:
                logger.warning("Failed to parse LLM response as JSON: %s", text[:200])
                return None
        else:
            return None

    # Validate: must have at least street_name or raw_text
    if not data.get("street_name") and not data.get("raw_text"):
        return None

    confidence = float(data.get("confidence", 0.0))
    if confidence < 0.3:
        return None

    return ParsedAddress(
        street_type=data.get("street_type", "").strip(),
        street_name=data.get("street_name", "").strip(),
        building=str(data.get("building", "")).strip(),
        apartment=str(data.get("apartment", "")).strip(),
        city=data.get("city", "").strip(),
        postal_code=str(data.get("postal_code", "")).strip(),
        raw_text=data.get("raw_text", "").strip(),
        confidence=confidence,
    )


# ── Backend: Groq (Llama 3.3 70B — free) ─────────────────────────

class GroqBackend:
    """
    Free AI backend using Groq's API with Llama 3.3 70B.
    Get a free key at: https://console.groq.com
    Free tier: ~6,000 requests/day, 30 RPM.
    """

    API_URL = "https://api.groq.com/openai/v1/chat/completions"
    MODEL = "llama-3.3-70b-versatile"

    def __init__(self, api_key: Optional[str] = None):
        self.api_key = api_key or os.environ.get("GROQ_API_KEY", "")
        self.name = "groq"

    @property
    def available(self) -> bool:
        return bool(self.api_key)

    def extract(self, text: str) -> Optional[ParsedAddress]:
        if not self.available:
            return None

        headers = {
            "Authorization": f"Bearer {self.api_key}",
            "Content-Type": "application/json",
        }
        payload = {
            "model": self.MODEL,
            "messages": [
                {"role": "system", "content": _SYSTEM_PROMPT},
                {"role": "user", "content": text},
            ],
            "temperature": 0.0,
            "max_tokens": 300,
            "response_format": {"type": "json_object"},
        }

        try:
            resp = requests.post(self.API_URL, json=payload, headers=headers, timeout=15)
            resp.raise_for_status()
            content = resp.json()["choices"][0]["message"]["content"]
            return _parse_llm_response(content)
        except Exception as e:
            logger.warning("Groq API error: %s", e)
            return None


# ── Backend: Google Gemini (free tier) ────────────────────────────

class GeminiBackend:
    """
    Free AI backend using Google Gemini API.
    Get a free key at: https://aistudio.google.com/apikey
    Free tier: 15 RPM, 1M tokens/min, 1500 req/day.
    """

    API_URL = "https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent"
    MODEL = "gemini-2.0-flash"

    def __init__(self, api_key: Optional[str] = None):
        self.api_key = api_key or os.environ.get("GEMINI_API_KEY", "")
        self.name = "gemini"

    @property
    def available(self) -> bool:
        return bool(self.api_key)

    def extract(self, text: str) -> Optional[ParsedAddress]:
        if not self.available:
            return None

        url = self.API_URL.format(model=self.MODEL) + f"?key={self.api_key}"
        payload = {
            "contents": [
                {
                    "parts": [
                        {"text": _SYSTEM_PROMPT + "\n\nUser message:\n" + text}
                    ]
                }
            ],
            "generationConfig": {
                "temperature": 0.0,
                "maxOutputTokens": 300,
                "responseMimeType": "application/json",
            },
        }

        try:
            resp = requests.post(url, json=payload, timeout=15)
            resp.raise_for_status()
            content = resp.json()["candidates"][0]["content"]["parts"][0]["text"]
            return _parse_llm_response(content)
        except Exception as e:
            logger.warning("Gemini API error: %s", e)
            return None


# ── Backend: Generic OpenAI-compatible (Ollama, LM Studio, etc.) ─

class OpenAICompatibleBackend:
    """
    For any local or free OpenAI-compatible API:
      - Ollama:    base_url="http://localhost:11434/v1", model="llama3"
      - LM Studio: base_url="http://localhost:1234/v1", model="local-model"
      - Together:  base_url="https://api.together.xyz/v1", model="..."
    """

    def __init__(
        self,
        base_url: str = "http://localhost:11434/v1",
        model: str = "llama3",
        api_key: str = "",
        name: str = "openai_compat",
    ):
        self.base_url = base_url.rstrip("/")
        self.model = model
        self.api_key = api_key or os.environ.get("OPENAI_COMPAT_API_KEY", "")
        self.name = name

    @property
    def available(self) -> bool:
        # Try a quick health check
        try:
            resp = requests.get(f"{self.base_url}/models", timeout=3,
                                headers={"Authorization": f"Bearer {self.api_key}"} if self.api_key else {})
            return resp.status_code == 200
        except Exception:
            return False

    def extract(self, text: str) -> Optional[ParsedAddress]:
        headers = {"Content-Type": "application/json"}
        if self.api_key:
            headers["Authorization"] = f"Bearer {self.api_key}"

        payload = {
            "model": self.model,
            "messages": [
                {"role": "system", "content": _SYSTEM_PROMPT},
                {"role": "user", "content": text},
            ],
            "temperature": 0.0,
            "max_tokens": 300,
        }

        try:
            resp = requests.post(
                f"{self.base_url}/chat/completions",
                json=payload, headers=headers, timeout=30,
            )
            resp.raise_for_status()
            content = resp.json()["choices"][0]["message"]["content"]
            return _parse_llm_response(content)
        except Exception as e:
            logger.warning("%s API error: %s", self.name, e)
            return None


# ═══════════════════════════════════════════════════════════════════
# Offline extraction (regex + natasha + pymorphy2)
# ═══════════════════════════════════════════════════════════════════

# ── Street types (all case forms) ────────────────────────────────

_STREET_TYPES_ALL_UK = {
    "вулиця", "вулиці", "вулицю", "вулицею", "вулиць",
    "проспект", "проспекту", "проспектом", "проспекті",
    "провулок", "провулку", "провулком", "провулкі",
    "бульвар", "бульвару", "бульваром", "бульварі",
    "площа", "площі", "площу", "площею",
    "набережна", "набережної", "набережну", "набережній",
    "шосе", "алея", "алеї", "алеєю", "алею",
    "узвіз", "узвозу", "узвозі", "узвозом",
}
_STREET_TYPES_ALL_RU = {
    "улица", "улицы", "улице", "улицу", "улицей",
    "проспект", "проспекта", "проспекте", "проспекту", "проспектом",
    "переулок", "переулка", "переулке", "переулку", "переулком",
    "бульвар", "бульвара", "бульваре", "бульвару", "бульваром",
    "площадь", "площади", "площадью",
    "набережная", "набережной", "набережную", "набережных",
    "шоссе", "аллея", "аллеи", "аллее", "аллею", "аллеей",
    "проезд", "проезда", "проезде", "проезду", "проездом",
}
_STREET_TYPES_NOM_UK = {
    "вулиця", "вул", "проспект", "просп", "провулок", "пров",
    "бульвар", "бульв", "площа", "пл", "набережна", "наб",
    "шосе", "алея", "узвіз",
}
_STREET_TYPES_NOM_RU = {
    "улица", "ул", "проспект", "пр", "переулок", "пер",
    "бульвар", "площадь", "пл", "набережная", "наб",
    "шоссе", "аллея", "проезд",
}
_ALL_STREET_TYPES = (
    _STREET_TYPES_ALL_UK | _STREET_TYPES_ALL_RU
    | _STREET_TYPES_NOM_UK | _STREET_TYPES_NOM_RU
    | {s + "." for s in _STREET_TYPES_NOM_UK | _STREET_TYPES_NOM_RU}
)

_STREET_TYPE_TO_NOM: dict[str, str] = {}
for _form in _STREET_TYPES_ALL_UK:
    if _form.startswith("вулиц"): _STREET_TYPE_TO_NOM[_form] = "вулиця"
    elif _form.startswith("проспект"): _STREET_TYPE_TO_NOM[_form] = "проспект"
    elif _form.startswith("провул"): _STREET_TYPE_TO_NOM[_form] = "провулок"
    elif _form.startswith("бульвар"): _STREET_TYPE_TO_NOM[_form] = "бульвар"
    elif _form.startswith("площ"): _STREET_TYPE_TO_NOM[_form] = "площа"
    elif _form.startswith("набережн"): _STREET_TYPE_TO_NOM[_form] = "набережна"
    elif _form.startswith("але"): _STREET_TYPE_TO_NOM[_form] = "алея"
    elif _form.startswith("узво"): _STREET_TYPE_TO_NOM[_form] = "узвіз"
for _form in _STREET_TYPES_ALL_RU:
    if _form.startswith("улиц"): _STREET_TYPE_TO_NOM[_form] = "улица"
    elif _form.startswith("проспект"): _STREET_TYPE_TO_NOM[_form] = "проспект"
    elif _form.startswith("переул"): _STREET_TYPE_TO_NOM[_form] = "переулок"
    elif _form.startswith("бульвар"): _STREET_TYPE_TO_NOM[_form] = "бульвар"
    elif _form.startswith("площад"): _STREET_TYPE_TO_NOM[_form] = "площадь"
    elif _form.startswith("набережн"): _STREET_TYPE_TO_NOM[_form] = "набережная"
    elif _form.startswith("алле"): _STREET_TYPE_TO_NOM[_form] = "аллея"
    elif _form.startswith("проезд"): _STREET_TYPE_TO_NOM[_form] = "проезд"


# ── Cities ────────────────────────────────────────────────────────

_CITIES_UK = {
    "Київ": ("Київ","Києві","Києва","Києвом"),
    "Львів": ("Львів","Львові","Львова","Львовом"),
    "Одеса": ("Одеса","Одесі","Одеси","Одесу","Одесою"),
    "Харків": ("Харків","Харкові","Харкова","Харковом"),
    "Дніпро": ("Дніпро","Дніпрі","Дніпра","Дніпром"),
    "Запоріжжя": ("Запоріжжя","Запоріжжі"),
    "Вінниця": ("Вінниця","Вінниці","Вінницю","Вінницею"),
    "Полтава": ("Полтава","Полтаві","Полтави","Полтаву"),
    "Чернігів": ("Чернігів","Чернігові","Чернігова"),
    "Черкаси": ("Черкаси","Черкасах","Черкас"),
    "Суми": ("Суми","Сумах","Сум"),
    "Рівне": ("Рівне","Рівному"),
    "Тернопіль": ("Тернопіль","Тернополі","Тернополя"),
    "Луцьк": ("Луцьк","Луцьку","Луцька"),
    "Ужгород": ("Ужгород","Ужгороді","Ужгорода"),
    "Миколаїв": ("Миколаїв","Миколаєві","Миколаєва"),
    "Хмельницький": ("Хмельницький","Хмельницькому","Хмельницького"),
    "Івано-Франківськ": ("Івано-Франківськ","Івано-Франківську"),
    "Кропивницький": ("Кропивницький","Кропивницькому"),
    "Житомир": ("Житомир","Житомирі","Житомира"),
}


_CITY_LOOKUP: dict[str, str] = {}
for _nom, _forms in {**_CITIES_UK}.items():
    for _f in _forms:
        _CITY_LOOKUP[_f.lower()] = _nom


def _find_city(text: str) -> Optional[str]:
    text_lower = text.lower()
    for form in sorted(_CITY_LOOKUP.keys(), key=len, reverse=True):
        pat = r"(?:^|[\s,;.(])" + re.escape(form) + r"(?:[\s,;.!?)]|$)"
        if re.search(pat, text_lower):
            return _CITY_LOOKUP[form]
    return None


# ── Morph helpers ─────────────────────────────────────────────────

def _get_morph(lang: str):
    if lang == "uk" and _morph_uk:
        return _morph_uk
    return _morph_ru


def _inflect_nominative(phrase: str, lang: str) -> str:
    morph = _get_morph(lang)
    result = []
    for word in phrase.split():
        if len(word) <= 2 or word.isdigit():
            result.append(word)
            continue
        parsed = morph.parse(word)
        if not parsed:
            result.append(word)
            continue
        nom = parsed[0].inflect({"nomn"})
        if nom:
            w = nom.word
            if word[0].isupper():
                w = w[0].upper() + w[1:]
            result.append(w)
        else:
            nf = parsed[0].normal_form
            if word[0].isupper():
                nf = nf[0].upper() + nf[1:]
            result.append(nf)
    return " ".join(result)


def _normalize_street_type(word: str) -> Optional[str]:
    w = word.lower().rstrip(".")
    return _STREET_TYPE_TO_NOM.get(w) or (w if w in _STREET_TYPES_NOM_UK | _STREET_TYPES_NOM_RU else None)


# ── Regex extraction ─────────────────────────────────────────────

_CYR = r"[А-ЯІЇЄҐЁа-яіїєґё'ʼ\u2019\-]+"
_NUM = r"\d{1,4}\s*[А-Яа-яA-Za-z]?"
_BLDG = r"(?:\s*,?\s*(?:буд\.?|д\.?)\s*\d+\w?)?"
_APT = r"(?:\s*,?\s*(?:кв\.?|оф\.?|корп\.?)\s*\d+\w?)*"
_POSTAL = r"(?:\s*,?\s*\d{5,6})?"

_PREPS = (
    r"(?:на|по|біля|коло|поблизу|навпроти|"
    r"возле|около|напротив|рядом\s+с|"
    r"за адресою|по адресу)"
)

def _build_patterns() -> list[re.Pattern]:
    types_sorted = sorted(_ALL_STREET_TYPES, key=len, reverse=True)
    types_re = "|".join(re.escape(t) for t in types_sorted)
    city_names = sorted(_CITY_LOOKUP.keys(), key=len, reverse=True)
    city_re = "|".join(re.escape(c) for c in city_names)

    return [
        # P1: [prep] street_type name, number
        re.compile(
            rf"(?:{_PREPS}\s+)?(?:{types_re})\.?\s+"
            rf"({_CYR}(?:\s+{_CYR}){{0,3}})\s*,?\s*({_NUM})"
            rf"{_BLDG}{_APT}{_POSTAL}", re.I | re.U),
        # P2: name street_type, number (postfix)
        re.compile(
            rf"({_CYR}(?:\s+{_CYR}){{0,2}})\s+(?:{types_re})\.?"
            rf"\s*,?\s*({_NUM}){_BLDG}{_APT}{_POSTAL}", re.I | re.U),
        # P3: prep + name, number (no street type)
        re.compile(
            rf"{_PREPS}\s+({_CYR}(?:\s+{_CYR}){{0,3}})\s*,\s*({_NUM})"
            rf"{_BLDG}{_APT}", re.I | re.U),
        # P4: prep + name + дом/буд number
        re.compile(
            rf"{_PREPS}\s+({_CYR}(?:\s+{_CYR}){{0,3}})"
            rf"\s*,?\s*(?:дом|д\.|буд\.|буд)\s*({_NUM})", re.I | re.U),
        # P5: city, [type] name, number
        re.compile(
            rf"(?:м\.?\s*|г\.?\s*)?(?:{city_re})\s*,\s*"
            rf"(?:(?:{types_re})\.?\s+)?({_CYR}(?:\s+{_CYR}){{0,3}})"
            rf"\s*,?\s*({_NUM}){_BLDG}{_APT}{_POSTAL}", re.I | re.U),
        # P6: "address:" + freeform
        re.compile(
            r"(?:за адресою|по адресу|адреса|адрес)\s*[:\-]?\s*"
            r"([А-ЯІЇЄҐЁа-яіїєґё0-9\s,.\-/'ʼ]{8,90})", re.I | re.U),
        # P7: bare Name, number
        re.compile(
            rf"([А-ЯІЇЄҐЁ]{_CYR[1:]}(?:\s+{_CYR}){{0,2}})\s*,\s*({_NUM})"
            rf"{_BLDG}{_APT}", re.U),
    ]

_PATTERNS = _build_patterns()

_STOP_WORDS = {
    "але","або","від","для","при","без","між","або","или","для",
    "при","без","между","это","сьогодні","завтра","вчора","зараз",
    "потім","сегодня","завтра","вчера","сейчас","потом","добрий",
    "добрый","привіт","привет","дякую","спасибо","будь","ласка",
}


def _extract_offline(text: str, lang: str) -> Optional[ParsedAddress]:
    """Regex + natasha + pymorphy2 fallback extraction."""

    city = _find_city(text)

    # ── Try regex patterns ────────────────────────────────────────
    for idx, pat in enumerate(_PATTERNS):
        m = pat.search(text)
        if not m:
            continue

        if idx in (0, 1):
            name = m.group(1).strip()
            number = m.group(2).strip() if m.lastindex >= 2 else ""
            st_type = None
            for w in m.group(0).split():
                n = _normalize_street_type(w)
                if n:
                    st_type = n
                    break
            name_nom = _inflect_nominative(name, lang)
            if not st_type:
                st_type = "вулиця" if lang == "uk" else "улица"
            return ParsedAddress(
                street_type=st_type, street_name=name_nom,
                building=number, city=city or "",
                raw_text=m.group(0).strip(), confidence=0.8,
            )

        elif idx in (2, 3):
            name = m.group(1).strip()
            if name.lower() in _STOP_WORDS:
                continue
            number = m.group(2).strip() if m.lastindex >= 2 else ""
            name_nom = _inflect_nominative(name, lang)
            default_type = "вулиця" if lang == "uk" else "улица"
            return ParsedAddress(
                street_type=default_type, street_name=name_nom,
                building=number, city=city or "",
                raw_text=m.group(0).strip(), confidence=0.7,
            )

        elif idx == 4:
            name = m.group(1).strip()
            number = m.group(2).strip() if m.lastindex >= 2 else ""
            name_nom = _inflect_nominative(name, lang)
            default_type = "вулиця" if lang == "uk" else "улица"
            return ParsedAddress(
                street_type=default_type, street_name=name_nom,
                building=number, city=city or "",
                raw_text=m.group(0).strip(), confidence=0.75,
            )

        elif idx == 5:
            raw = m.group(1).strip().rstrip(",;.!")
            return ParsedAddress(
                raw_text=raw, confidence=0.6,
                city=city or "",
            )

        elif idx == 6:
            name = m.group(1).strip()
            if name.lower() in _STOP_WORDS:
                continue
            if not any(len(w) >= 4 and w[0].isupper() for w in name.split()):
                continue
            number = m.group(2).strip() if m.lastindex >= 2 else ""
            name_nom = _inflect_nominative(name, lang)
            default_type = "вулиця" if lang == "uk" else "улица"
            return ParsedAddress(
                street_type=default_type, street_name=name_nom,
                building=number, city=city or "",
                raw_text=m.group(0).strip(), confidence=0.5,
            )

    # ── Natasha fallback ──────────────────────────────────────────
    matches = list(_addr_extractor(text))
    if matches:
        start = min(m_.start for m_ in matches)
        stop = max(m_.stop for m_ in matches)
        raw = text[start:stop].strip().rstrip(",;.!")
        if len(raw) > 5:
            after = text[stop:stop + 20]
            nm = re.match(r"\s*,?\s*(\d{1,4}\w?)", after)
            number = nm.group(1) if nm else ""
            return ParsedAddress(
                raw_text=raw, building=number,
                city=city or "", confidence=0.6,
            )

    # ── NER fallback ──────────────────────────────────────────────
    doc = Doc(text)
    doc.segment(_segmenter)
    doc.tag_morph(_morph_tagger)
    doc.tag_ner(_ner_tagger)
    locs = [s for s in doc.spans if s.type == "LOC"]
    if locs:
        best = max(locs, key=lambda s: s.stop - s.start)
        raw = text[best.start:best.stop].strip()
        after = text[best.stop:best.stop + 20]
        nm = re.match(r"\s*,?\s*(\d{1,4}\w?)", after)
        number = nm.group(1) if nm else ""
        return ParsedAddress(
            raw_text=raw, building=number,
            city=city or "", confidence=0.5,
        )

    return None


# ═══════════════════════════════════════════════════════════════════
# Geocoding
# ═══════════════════════════════════════════════════════════════════

_geolocator = Nominatim(user_agent="addr_extract_ai_v3/1.0", timeout=10)


def _geocode(address: str, lang: str = "uk", retries: int = 2) -> Optional[dict]:
    """Geocode with multiple fallback query strategies."""
    attempts = [address]

    # Expand abbreviations
    repls = {
        "вул.":"вулиця","просп.":"проспект","пров.":"провулок",
        "бульв.":"бульвар","пл.":"площа","наб.":"набережна",
        "буд.":"","м.":"","ул.":"улица","пр-т":"проспект",
        "пер.":"переулок","д.":"","г.":"",
    }
    cleaned = address
    for s, f in repls.items():
        cleaned = cleaned.replace(s, f)
    cleaned = re.sub(r",?\s*(?:кв\.?|оф\.?|корп\.?)\s*\d+\w?", "", cleaned, flags=re.I)
    cleaned = cleaned.strip().rstrip(",")
    if cleaned != address:
        attempts.append(cleaned)

    try:
        from transliterate import translit
        attempts.append(translit(address, reversed=True))
    except ImportError:
        pass

    simplified = re.sub(r",\s*\d{1,4}\w?\b", "", address).strip().rstrip(",")
    if simplified != address:
        attempts.append(simplified)

    seen = set()
    unique = [a for a in attempts if a.strip() and a.strip() not in seen and not seen.add(a.strip())]

    for attempt in unique:
        for _ in range(retries):
            try:
                loc = _geolocator.geocode(attempt, language=lang, addressdetails=True)
                if loc:
                    return {
                        "latitude": loc.latitude,
                        "longitude": loc.longitude,
                        "display_name": loc.raw.get("display_name", ""),
                        "address_details": loc.raw.get("address", {}),
                        "query_used": attempt,
                    }
                break
            except GeocoderTimedOut:
                time.sleep(1)
            except GeocoderServiceError:
                return None
        time.sleep(1.1)

    return None


# ═══════════════════════════════════════════════════════════════════
# Orchestrator: choose backend, extract, geocode
# ═══════════════════════════════════════════════════════════════════

# Default backends (ordered by preference)
_backends: list = []


def configure(
    groq_api_key: Optional[str] = None,
    gemini_api_key: Optional[str] = None,
    ollama_url: Optional[str] = None,
    ollama_model: str = "llama3",
    custom_backends: Optional[list] = None,
):
    """
    Configure which AI backends to use (in priority order).
    Call once at startup; afterwards extract_and_geocode() uses them.

    Args:
        groq_api_key:    Groq API key (free at console.groq.com)
        gemini_api_key:  Google Gemini key (free at aistudio.google.com)
        ollama_url:      Ollama base URL (e.g. "http://localhost:11434/v1")
        ollama_model:    Ollama model name (default: "llama3")
        custom_backends: List of backend instances (GroqBackend, GeminiBackend, etc.)
    """
    global _backends
    _backends = []

    if custom_backends:
        _backends.extend(custom_backends)
        return

    # Auto-detect from args or env
    groq = GroqBackend(api_key=groq_api_key)
    if groq.available:
        _backends.append(groq)

    gemini = GeminiBackend(api_key=gemini_api_key)
    if gemini.available:
        _backends.append(gemini)

    if ollama_url:
        ollama = OpenAICompatibleBackend(
            base_url=ollama_url, model=ollama_model, name="ollama"
        )
        if ollama.available:
            _backends.append(ollama)

    if not _backends:
        logger.info(
            "No AI backend configured. Using offline extraction only. "
            "Set GROQ_API_KEY or GEMINI_API_KEY for better results."
        )


def _auto_configure():
    """Auto-configure from environment on first use."""
    if not _backends:
        configure()


def extract_and_geocode(
    text: str,
    city_hint: Optional[str] = None,
) -> GeoResult:
    """
    Extract an address from Russian/Ukrainian text and geocode it.

    Pipeline:
      1. Try each configured AI backend (Groq → Gemini → Ollama → ...)
      2. Fall back to offline (regex → natasha → heuristic)
      3. Geocode the best result via Nominatim

    Args:
        text:       Input text containing an address.
        city_hint:  Optional city (nominative) to help geocoding.

    Returns:
        GeoResult with parsed address and coordinates.
    """
    _auto_configure()

    lang = detect_language(text)
    parsed: Optional[ParsedAddress] = None
    method = "none"

    # ── Step 1: Try AI backends ───────────────────────────────────
    for backend in _backends:
        try:
            parsed = backend.extract(text)
            if parsed and parsed.confidence >= 0.3:
                method = backend.name
                # If AI didn't find city, try detecting from text
                if not parsed.city:
                    parsed.city = city_hint or _find_city(text) or ""
                break
            parsed = None
        except Exception as e:
            logger.warning("Backend %s failed: %s", backend.name, e)
            continue

    # ── Step 2: Offline fallback ──────────────────────────────────
    if not parsed:
        parsed = _extract_offline(text, lang)
        if parsed:
            method = "offline"
            if not parsed.city:
                parsed.city = city_hint or _find_city(text) or ""

    if not parsed:
        return GeoResult(
            original_text=text, language=lang,
            method="none", error="No address found",
        )

    # ── Step 3: Geocode ───────────────────────────────────────────
    # Build geocoding query from parsed address
    geocode_query = parsed.to_geocode_string()
    geo = _geocode(geocode_query, lang=lang if lang != "unknown" else "en")

    # Fallback: try raw_text + city
    if not geo and parsed.raw_text:
        fallback = parsed.raw_text
        if parsed.city and parsed.city.lower() not in fallback.lower():
            fallback += f", {parsed.city}"
        geo = _geocode(fallback, lang=lang if lang != "unknown" else "en")

    result = GeoResult(
        original_text=text,
        parsed=parsed,
        language=lang,
        method=method,
    )

    if geo:
        result.latitude = geo["latitude"]
        result.longitude = geo["longitude"]
        result.display_name = geo["display_name"]
        result.geocoded = True
    else:
        result.error = "Address extracted but geocoding failed"

    return result


def extract_and_geocode_batch(
    texts: list[str],
    city_hint: Optional[str] = None,
    delay: float = 1.1,
) -> list[GeoResult]:
    """Process multiple texts with rate limiting."""
    results = []
    for text in texts:
        result = extract_and_geocode(text, city_hint=city_hint)
        results.append(result)
        if result.geocoded:
            time.sleep(delay)
    return results


# ═══════════════════════════════════════════════════════════════════
# Demo
# ═══════════════════════════════════════════════════════════════════

if __name__ == "__main__":
    # Auto-configures from GROQ_API_KEY / GEMINI_API_KEY env vars.
    # Or configure manually:
    #   configure(groq_api_key="gsk_...")
    #   configure(gemini_api_key="AI...")
    #   configure(ollama_url="http://localhost:11434/v1", ollama_model="llama3")

    tests = [
        " Скоріш за все підари на білому дасторі на Міхновського стоять, лівий 3",
        " Новокримська, навпроти Сільпо пірожок 317-95АЕ питали документи",

        # ── Ukrainian: WITH prefix ───────────────────────────────
        "Зустріч за адресою вул. Хрещатик, 22, Київ, 01001.",

        # ── Russian: inflected, NO prefix ────────────────────────
        "  По яворницкого вниз , белый бус рено , за ним белый дастер , 7 минут назад были на дзержинского",
        "  По ходу на пр.Свободы кого то пакуют,крики ор,мужики и баба,район Делви,перекресток как на ДЗмо",
        "  Пидары на универсальная 29 во дворах гнались за парнями.",
        "  Левый 2 белый рено и Мазда по дворам",
        "  Слобожанский 70. Возле Атб на океане На 19:30 Бегали за 1, и 1 поймали",

        # ── Russian: WITH prefix ─────────────────────────────────
        "  Титова недалеко от перекрестка пр. Поля, стоят на аварийках 2 черных машины, похожие на дастер.",
    ]

    active = [b.name for b in _backends] or ["offline only"]
    print("=" * 70)
    print(f"ADDRESS EXTRACTOR v3 — AI-powered")
    print(f"Active backends: {', '.join(active)}")
    print("=" * 70)

    for msg in tests:
        print(f"\n{'─' * 60}")
        print(f"📩  {msg}")
        r = extract_and_geocode(msg)

        if r.geocoded and r.parsed:
            print(f"  ✅ Parsed:     {r.parsed.to_display_string()}")
            print(f"     Geocode →   {r.parsed.to_geocode_string()}")
            print(f"     Method:     {r.method} (conf: {r.parsed.confidence:.1f})")
            print(f"     Coords:     ({r.latitude:.6f}, {r.longitude:.6f})")
        elif r.parsed:
            print(f"  ⚠️  Parsed:     {r.parsed.to_display_string()}")
            print(f"     Error:      {r.error}")
        else:
            print(f"  ❌ {r.error}")

        time.sleep(1.2)
