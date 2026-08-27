#!/usr/bin/env python3
"""Extract the San Carlos eligibility catalog from the official UTN manual.

This is a development-time tool. The generated JSON is committed and imported by
Laravel, so neither the web interface nor exports parse the 448-page PDF at runtime.
"""

from __future__ import annotations

import argparse
import hashlib
import json
import re
import unicodedata
from dataclasses import dataclass
from pathlib import Path

from pypdf import PdfReader


@dataclass(frozen=True)
class CareerSection:
    code: str
    name: str
    source_heading: str
    first_page: int
    last_page: int
    agreement: str
    gazette: str
    valid_from: str


SECTIONS = (
    CareerSection("AA", "ADMINISTRACIÓN ADUANERA", "Administración aduanera", 8, 19, "Acuerdo 8-28-2018", "No indicada en la sección del Manual", "2018-12-19"),
    CareerSection("AGRH", "ADMINISTRACIÓN Y GESTIÓN DE RECURSOS HUMANOS", "Administración y gestión de recursos humanos", 107, 116, "Acuerdo 20 - Sesión Ordinaria 12-2025", "La Gaceta 102 del 05/06/2025", "2025-06-05"),
    CareerSection("ASA", "ASISTENCIA ADMINISTRATIVA", "Asistencia administrativa", 117, 121, "Acuerdo 8-28-2018", "No indicada en la sección del Manual", "2018-12-19"),
    CareerSection("CE", "ADMINISTRACIÓN DEL COMERCIO EXTERIOR", "Comercio exterior", 122, 133, "Acuerdo 20 - Sesión Ordinaria 12-2025", "La Gaceta 102 del 05/06/2025", "2025-06-05"),
    CareerSection("CF", "CONTABILIDAD Y FINANZAS - CONTADURÍA PÚBLICA", "Contabilidad y finanzas /Contaduría Pública", 134, 179, "Acuerdo 20 - Sesión Ordinaria 12-2025", "La Gaceta 102 del 05/06/2025", "2025-06-05"),
    CareerSection("ILE", "INGLÉS COMO LENGUA EXTRANJERA", "Inglés como lengua extranjera", 213, 216, "Acuerdo 8-28-2018", "No indicada en la sección del Manual", "2018-12-19"),
    CareerSection("ISW", "INGENIERÍA DEL SOFTWARE - TECNOLOGÍAS INFORMÁTICAS", "Ingeniería del software", 282, 296, "Acuerdo 14 - Sesión Ordinaria 3-2026", "La Gaceta 55 del 20/03/2026", "2026-03-20"),
    CareerSection("ITI", "INGENIERÍA EN TECNOLOGÍAS DE INFORMACIÓN - TECNOLOGÍAS DE INFORMACIÓN", "Ingeniería en tecnologías de información", 297, 310, "Acuerdo 10 - Sesión Ordinaria 22-2025", "La Gaceta 186 del 06/10/2025", "2025-10-06"),
    CareerSection("IGA", "INGENIERÍA EN GESTIÓN AMBIENTAL", "Ingeniería en gestión ambiental", 311, 328, "Acuerdo 8-28-2018", "No indicada en la sección del Manual", "2018-12-19"),
    CareerSection("ITA", "INGENIERÍA EN TECNOLOGÍA DE ALIMENTOS - TECNOLOGÍA DE ALIMENTOS", "Ingeniería en tecnología de alimentos", 329, 346, "Acuerdo 8-28-2018", "No indicada en la sección del Manual", "2018-12-19"),
    CareerSection("ISOA", "INGENIERÍA EN SALUD OCUPACIONAL Y AMBIENTE - SALUD OCUPACIONAL", "Ingeniería en salud ocupacional y ambiente", 356, 366, "Acuerdo 10 - Sesión Ordinaria 22-2025", "La Gaceta 186 del 06/10/2025", "2025-10-06"),
    CareerSection("AAI", "ADMINISTRACIÓN AGROINDUSTRIAL", "Administración Agroindustrial (Diplomado)", 420, 429, "Acuerdo 20 - Sesión Ordinaria 12-2025", "La Gaceta 102 del 05/06/2025", "2025-06-05"),
)


HEADING_PATTERN = re.compile(
    r"^(?:Atinencias?|Especialidades\s+atinentes)\s+(?:para|de)\s+",
    re.IGNORECASE,
)
NUMBERED_ITEM_PATTERN = re.compile(r"^\s*(\d{1,3})\s*[.)]\s*(.+?)\s*$")
STOP_PATTERN = re.compile(
    r"^\(?(?:Nota\s+[Tt][ée]cnica|Aprobado por el Consejo|Reformado|Propósito|Cursos que conforman)",
    re.IGNORECASE,
)
EXPLANATION_PATTERN = re.compile(
    r"^(?:Son atinentes|Se consideran atinentes|Por ser un curso|Por ser cursos)",
    re.IGNORECASE,
)
LOWERCASE_COURSE_WORDS = {
    "a",
    "al",
    "con",
    "de",
    "del",
    "e",
    "el",
    "en",
    "la",
    "las",
    "los",
    "o",
    "para",
    "por",
    "y",
}
UPPERCASE_COURSE_WORDS = {"TI", "TIC", "TICS"}
OCR_COURSE_REPLACEMENTS = {
    "avanzad os": "avanzados",
    "ambient e": "ambiente",
    "anál isis": "análisis",
    "block chain": "blockchain",
    "dise ño": "diseño",
    "mat emática": "matemática",
    "mercadeo internacional y principios del comercio internacional y negociaciones comerciales": "mercadeo internacional; principios del comercio internacional; negociaciones comerciales",
    "metodologías agiles": "metodologías ágiles",
    "p ara": "para",
    "real idad": "realidad",
    "se guridad": "seguridad",
    "sis temas": "sistemas",
    "tensor flow": "tensorflow",
}


def clean_line(value: str) -> str:
    value = unicodedata.normalize("NFC", value).replace("\u00ad", "")
    return re.sub(r"\s+", " ", value).strip()


def is_page_noise(line: str, page: int) -> bool:
    return line in {"Universidad Técnica Nacional", str(page)}


def normalize_key(value: str) -> str:
    decomposed = unicodedata.normalize("NFD", value.casefold())
    ascii_value = "".join(char for char in decomposed if unicodedata.category(char) != "Mn")
    return re.sub(r"[^a-z0-9]+", " ", ascii_value).strip()


def clean_course_heading(value: str) -> str:
    value = re.sub(r"^Atinencias?\s+", "", value, flags=re.IGNORECASE)
    value = re.sub(r"^(?:para|de)\s+", "", value, flags=re.IGNORECASE)
    value = re.sub(r"^(?:los|las|el|la)\s+", "", value, flags=re.IGNORECASE)
    value = re.sub(r"^cursos?\s*[:;,.-]*\s*(?:de\s+)?", "", value, flags=re.IGNORECASE)
    value = re.sub(r"^(?:para|de)\s*[:;,.-]*\s*", "", value, flags=re.IGNORECASE)
    value = re.split(r"\s+\d{1,3}\.\s+(?=[A-ZÁÉÍÓÚÑ])", value, maxsplit=1)[0]
    value = value.translate(str.maketrans({char: "" for char in "\"'“”‘’"}))
    value = value.replace("_", " ")
    for source, replacement in OCR_COURSE_REPLACEMENTS.items():
        value = re.sub(re.escape(source), replacement, value, flags=re.IGNORECASE)
    value = re.sub(r"^[\s:;,.-]+", "", value)
    value = re.sub(r"\s*[:.;,-]+\s*$", "", value)
    value = re.sub(r"\s+([,;:])", r"\1", value)
    value = re.sub(r"([,;:])(?=\S)", r"\1 ", value)
    value = re.sub(r"\s+", " ", value).strip()
    return value


def title_case_course(value: str) -> str:
    words = value.casefold().split()
    formatted: list[str] = []

    for index, word in enumerate(words):
        clean_word = word.strip(".,;:()")
        prefix = word[: len(word) - len(word.lstrip("("))]
        suffix = word[len(word.rstrip(".,;:)")) :]
        upper_word = clean_word.upper()

        if clean_word == "iot":
            replacement = "IoT"
        elif clean_word == "tensorflow":
            replacement = "TensorFlow"
        elif re.fullmatch(r"[IVXLCDM]+", upper_word) or upper_word in UPPERCASE_COURSE_WORDS:
            replacement = upper_word
        elif index > 0 and clean_word in LOWERCASE_COURSE_WORDS:
            replacement = clean_word
        else:
            replacement = clean_word[:1].upper() + clean_word[1:]

        formatted.append(prefix + replacement + suffix)

    return " ".join(formatted).strip(" .;,:-")


def expand_roman_series(value: str) -> list[str]:
    value = re.sub(r"\s+según\s+área\s*$", "", value, flags=re.IGNORECASE)
    value = re.sub(r"\b([IVXLCDM]+)\.\s+(?=[IVXLCDM]+\b)", r"\1, ", value)
    match = re.fullmatch(
        r"(?P<base>.+?\S)\s+(?P<first>[IVXLCDM]+)"
        r"(?P<rest>(?:\s*,\s*[IVXLCDM]+)*(?:\s*,?\s+y\s+[IVXLCDM]+)?)",
        value,
        flags=re.IGNORECASE,
    )

    if match is None:
        return [value]

    numerals = re.findall(r"\b[IVXLCDM]+\b", match.group("first") + match.group("rest"), re.IGNORECASE)
    if len(numerals) < 2:
        return [value]

    base = match.group("base").strip()
    return [f"{base} {numeral.upper()}" for numeral in numerals]


def split_course_heading(value: str) -> list[str]:
    value = clean_course_heading(value)
    value = re.sub(
        r"^Optativa\s+I(?:\s*,\s*II)?(?:\s+y\s+III)?\s*:\s*",
        "",
        value,
        flags=re.IGNORECASE,
    )
    value = re.sub(
        r"\b([IVXLCDM]+)\s*,\s*y\s+(?=[IVXLCDM]+\b)",
        r"\1§ y ",
        value,
        flags=re.IGNORECASE,
    )
    value = re.sub(
        r"\b([IVXLCDM]+)\s*,\s*(?=[IVXLCDM]+\b)",
        r"\1§ ",
        value,
        flags=re.IGNORECASE,
    )
    parts = re.split(r"\s*[;,]\s*", value)
    courses: list[str] = []

    for part in parts:
        part = part.replace("§", ",").strip(" .;,:-")
        if not part:
            continue

        for expanded in expand_roman_series(part):
            expanded = re.sub(r"\s+(?:y|o)\s*$", "", expanded, flags=re.IGNORECASE)
            normalized = title_case_course(expanded)
            if normalized:
                courses.append(normalized)

    return courses


def clean_specialization(value: str) -> str:
    value = re.split(
        r"\s+(?:\*+\s*)?(?:Nota:|Nota\s+[Tt][ée]cnica|Que ambas atinencias|Preferiblemente con)",
        value,
        maxsplit=1,
        flags=re.IGNORECASE,
    )[0]
    value = re.sub(r"\s+\(?Aprobado por el Consejo.*$", "", value, flags=re.IGNORECASE)
    value = re.sub(r"\s+diciembre de 2018 mediante acuerdo.*$", "", value, flags=re.IGNORECASE)
    value = re.sub(r"\s+", " ", value).strip(" .;:-")
    return value[:220]


def section_lines(reader: PdfReader, section: CareerSection) -> list[tuple[int, str]]:
    lines: list[tuple[int, str]] = []

    for page_number in range(section.first_page, section.last_page + 1):
        text = reader.pages[page_number - 1].extract_text() or ""
        for raw_line in text.splitlines():
            line = clean_line(raw_line)
            numbered_heading = NUMBERED_ITEM_PATTERN.match(line)
            if numbered_heading and HEADING_PATTERN.match(numbered_heading.group(2)):
                line = numbered_heading.group(2)
            if line and not is_page_noise(line, page_number):
                lines.append((page_number, line))

    return lines


def extract_blocks(reader: PdfReader, section: CareerSection) -> list[dict[str, object]]:
    lines = section_lines(reader, section)
    blocks: list[dict[str, object]] = []
    index = 0

    while index < len(lines):
        page, line = lines[index]
        if not HEADING_PATTERN.match(line):
            index += 1
            continue

        heading_parts = [line]
        cursor = index + 1
        inherit_previous = False

        while cursor < len(lines):
            _, candidate = lines[cursor]
            if NUMBERED_ITEM_PATTERN.match(candidate):
                break
            if HEADING_PATTERN.match(candidate) or STOP_PATTERN.match(candidate):
                break
            if EXPLANATION_PATTERN.match(candidate):
                explanation = candidate.casefold()
                inherit_previous = "ítem anterior" in explanation or "item anterior" in explanation
                cursor += 1
                while cursor < len(lines):
                    _, continuation = lines[cursor]
                    if (
                        NUMBERED_ITEM_PATTERN.match(continuation)
                        or HEADING_PATTERN.match(continuation)
                        or STOP_PATTERN.match(continuation)
                    ):
                        break
                    cursor += 1
                continue
            if len(" ".join(heading_parts)) > 1800:
                break
            heading_parts.append(candidate)
            cursor += 1

        heading = clean_course_heading(" ".join(heading_parts))
        specialties: list[str] = []
        current_item: str | None = None
        cursor_started = cursor

        while cursor < len(lines):
            _, candidate = lines[cursor]
            if HEADING_PATTERN.match(candidate) or STOP_PATTERN.match(candidate):
                break

            numbered = NUMBERED_ITEM_PATTERN.match(candidate)
            if numbered:
                if current_item:
                    specialties.append(clean_specialization(current_item))
                current_item = numbered.group(2)
            elif current_item and not candidate.startswith("("):
                current_item = f"{current_item} {candidate}"

            cursor += 1

        if current_item:
            specialties.append(clean_specialization(current_item))

        unique_specialties: list[str] = []
        seen: set[str] = set()
        for specialty in specialties:
            key = normalize_key(specialty)
            if specialty and key and key not in seen:
                seen.add(key)
                unique_specialties.append(specialty)

        if inherit_previous and blocks:
            inherited = list(blocks[-1]["specializations"])
            inherited_keys = {normalize_key(str(item)) for item in inherited}
            inherited.extend(
                specialty
                for specialty in unique_specialties
                if normalize_key(specialty) not in inherited_keys
            )
            unique_specialties = inherited

        if heading and unique_specialties:
            blocks.append(
                {
                    "name": heading,
                    "source_page": page,
                    "specializations": unique_specialties,
                }
            )

        index = max(cursor, cursor_started, index + 1)

    deduplicated: list[dict[str, object]] = []
    by_heading: dict[str, int] = {}
    for block in blocks:
        key = normalize_key(str(block["name"]))
        if key not in by_heading:
            by_heading[key] = len(deduplicated)
            deduplicated.append(block)
            continue

        existing = deduplicated[by_heading[key]]
        combined = list(existing["specializations"])
        present = {normalize_key(str(item)) for item in combined}
        for specialty in block["specializations"]:
            if normalize_key(str(specialty)) not in present:
                combined.append(specialty)
                present.add(normalize_key(str(specialty)))
        existing["specializations"] = combined

    expanded: list[dict[str, object]] = []
    expanded_by_name: dict[str, int] = {}

    for rule_sequence, block in enumerate(deduplicated, start=1):
        source_rule_code = f"MAN-{section.code}-RULE-{rule_sequence:03d}"

        for course_name in split_course_heading(str(block["name"])):
            key = normalize_key(course_name)
            if key not in expanded_by_name:
                expanded_by_name[key] = len(expanded)
                expanded.append(
                    {
                        "name": course_name,
                        "source_page": block["source_page"],
                        "source_rule_codes": [source_rule_code],
                        "specializations": list(block["specializations"]),
                    }
                )
                continue

            existing = expanded[expanded_by_name[key]]
            existing_rules = existing["source_rule_codes"]
            if source_rule_code not in existing_rules:
                existing_rules.append(source_rule_code)

            existing_specializations = existing["specializations"]
            present = {normalize_key(str(item)) for item in existing_specializations}
            for specialization in block["specializations"]:
                if normalize_key(str(specialization)) not in present:
                    existing_specializations.append(specialization)
                    present.add(normalize_key(str(specialization)))

    for sequence, course in enumerate(expanded, start=1):
        course["code"] = f"MAN-{section.code}-{sequence:03d}"

    return expanded


def build_catalog(pdf_path: Path) -> dict[str, object]:
    reader = PdfReader(str(pdf_path))
    careers: list[dict[str, object]] = []

    for section in SECTIONS:
        courses = extract_blocks(reader, section)
        if not courses:
            raise RuntimeError(f"No course blocks extracted for {section.name}")

        careers.append(
            {
                "code": section.code,
                "name": section.name,
                "source_heading": section.source_heading,
                "source_pages": [section.first_page, section.last_page],
                "agreement": section.agreement,
                "gazette": section.gazette,
                "valid_from": section.valid_from,
                "valid_until": None,
                "courses": courses,
            }
        )

    return {
        "source": {
            "document": pdf_path.name,
            "sha256": hashlib.sha256(pdf_path.read_bytes()).hexdigest(),
            "pages": len(reader.pages),
            "extraction_strategy": "offline_once",
        },
        "careers": careers,
    }


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("pdf", type=Path)
    parser.add_argument("output", type=Path)
    arguments = parser.parse_args()

    payload = build_catalog(arguments.pdf)
    arguments.output.parent.mkdir(parents=True, exist_ok=True)
    arguments.output.write_text(
        json.dumps(payload, ensure_ascii=False, indent=2) + "\n",
        encoding="utf-8",
    )

    for career in payload["careers"]:
        specialty_count = sum(len(course["specializations"]) for course in career["courses"])
        print(f"{career['code']}: {len(career['courses'])} course groups, {specialty_count} specialty rows")


if __name__ == "__main__":
    main()
