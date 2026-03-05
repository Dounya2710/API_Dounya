import os
import re
import sys
from typing import Optional, Tuple, Dict, List

import pandas as pd
import psycopg2
from psycopg2.extras import execute_values


SHEET_DEFAULT = "Reference"

# Formats attendus (avec tolérance)
DOMAIN_RE = re.compile(r"^\s*([A-Z]\s*\d+)\s*[-–]\s*(.+?)\s*$")      # "C1 - Anti-poaching" / "E4- Renewable energy"
ACT_RE    = re.compile(r"^\s*([A-Z]\s*\d+)\s*[-–]\s*(.+?)\s*$")      # "C10 - Habitat restoration" etc.

def norm_str(x) -> Optional[str]:
    if x is None:
        return None
    if isinstance(x, float) and pd.isna(x):
        return None
    s = str(x).strip()
    if s == "" or s.lower() in ("none", "nan"):
        return None
    return s

def clean_code(code: str) -> str:
    # "C 1" -> "C1"
    return re.sub(r"\s+", "", code.strip())

def parse_code_label(v: str, rx: re.Pattern) -> Optional[Tuple[str, str]]:
    """
    Return (code, label) from something like "C1 - ..."
    """
    v = norm_str(v)
    if not v:
        return None
    m = rx.match(v)
    if not m:
        return None
    code = clean_code(m.group(1))
    label = m.group(2).strip()
    return code, label

def get_conn():
    host = os.getenv("PGHOST", "localhost")
    port = int(os.getenv("PGPORT", "5432"))
    db   = os.getenv("PGDATABASE", "naturafrica")
    user = os.getenv("PGUSER", "postgres")
    pw   = os.getenv("PGPASSWORD", "")
    if not pw:
        raise RuntimeError("PGPASSWORD non défini (PowerShell: $env:PGPASSWORD='...')")
    return psycopg2.connect(host=host, port=port, dbname=db, user=user, password=pw)

def maybe_truncate(cur):
    truncate = os.getenv("TRUNCATE_BEFORE", "0").strip() == "1"
    if not truncate:
        return
    # ordre FK
    cur.execute('TRUNCATE TABLE "Activity" CASCADE;')
    cur.execute('TRUNCATE TABLE "ActivitySector" CASCADE;')
    cur.execute('TRUNCATE TABLE "Pillar" CASCADE;')


def main():
    xlsm_path = os.getenv("XLSM_PATH", r"C:\Users\visioterra\Documents\API_Dounya\Fiches_NA_DB_24_v5b.xlsm")
    sheet = os.getenv("REF_SHEET", SHEET_DEFAULT)
    inspect_only = os.getenv("INSPECT_ONLY", "0").strip() == "1"

    print(f"[INFO] XLSM={xlsm_path}")
    df = pd.read_excel(xlsm_path, sheet_name=sheet, engine="openpyxl")
    print(f"[INFO] Sheet='{sheet}' | rows={len(df)} | cols={len(df.columns)}")
    print("[INFO] Columns detected:")
    for c in df.columns:
        print(f"  - {c}")

    col_pillar = "Pillar"
    col_domain = "NaturAfrica domain"
    col_act    = "NaturAfrica activities"

    missing = [c for c in [col_pillar, col_domain, col_act] if c not in df.columns]
    if missing:
        raise RuntimeError(f"Colonnes manquantes dans la feuille '{sheet}': {missing}")

    # Pillar : propagation vers le bas (dans ton fichier, seules les premières lignes sont remplies)
    df[col_pillar] = df[col_pillar].apply(norm_str).ffill()

    # 1) Pillars (code -> name)
    pillar_name_to_code: Dict[str, str] = {}
    for name in df[col_pillar].dropna().unique().tolist():
        nlow = name.lower()
        if nlow.startswith("conserv"):
            code = "C"
        elif nlow.startswith("green"):
            code = "E"
        elif nlow.startswith("govern"):
            code = "G"
        else:
            code = name.strip()[:1].upper()
        pillar_name_to_code[name] = code

    pillars = sorted([(code, name) for name, code in pillar_name_to_code.items()], key=lambda x: x[0])

    # 2) Domains -> ActivitySector : sector_id = C1..G6
    sectors_dict: Dict[str, Tuple[str, str, str]] = {}
    for v in df[col_domain].apply(norm_str).tolist():
        if not v:
            continue
        parsed = parse_code_label(v, DOMAIN_RE)
        if not parsed:
            continue
        sector_id, label = parsed
        pillar_code = sector_id[0]  # C/E/G
        sectors_dict[sector_id] = (sector_id, pillar_code, label)

    # + secteurs globaux C/E/G (nécessaires pour rattacher les activités)
    for pc, pname in [("C","Conservation"), ("E","Green Economy"), ("G","Governance")]:
        if pc in [p[0] for p in pillars] or True:
            sectors_dict.setdefault(pc, (pc, pc, f"{pname} (all activities)"))

    sectors = sorted(list(sectors_dict.values()), key=lambda x: x[0])

    # 3) Activities -> Activity : activity_id = C1..C20 etc, sector_id = C/E/G
    acts_dict: Dict[str, Tuple[str, str, str]] = {}
    for v in df[col_act].apply(norm_str).tolist():
        if not v:
            continue
        parsed = parse_code_label(v, ACT_RE)
        if not parsed:
            continue
        activity_id, label = parsed
        pillar_code = activity_id[0]  # C/E/G
        sector_id = pillar_code       # IMPORTANT : FK vers ActivitySector(sector_id='C'/'E'/'G')
        acts_dict[activity_id] = (activity_id, sector_id, label)

    activities = sorted(list(acts_dict.values()), key=lambda x: x[0])

    if inspect_only:
        print("\n[INSPECT_ONLY=1] Aucun import effectué.")
        print(df[[col_pillar, col_domain, col_act]].head(25).to_string(index=False))
        print(f"\n[PREVIEW] Pillar rows: {len(pillars)} -> {pillars}")
        print(f"\n[PREVIEW] ActivitySector rows: {len(sectors)} -> first 10: {sectors[:10]}")
        print(f"\n[PREVIEW] Activity rows: {len(activities)} -> first 10: {activities[:10]}")
        return

    conn = get_conn()
    try:
        conn.autocommit = False
        with conn.cursor() as cur:
            maybe_truncate(cur)

            # Pillar
            if pillars:
                execute_values(
                    cur,
                    'INSERT INTO Pillar(pillar_code, pillar_name) VALUES %s '
                    'ON CONFLICT (pillar_code) DO UPDATE SET pillar_name = EXCLUDED.pillar_name',
                    pillars
                )

            # ActivitySector
            if sectors:
                execute_values(
                    cur,
                    'INSERT INTO ActivitySector(sector_id, pillar_code, sector_label, sector_description) VALUES %s '
                    'ON CONFLICT (sector_id) DO UPDATE SET '
                    'pillar_code=EXCLUDED.pillar_code, sector_label=EXCLUDED.sector_label',
                    [(sid, pcode, label, None) for (sid, pcode, label) in sectors]
                )

            # Activity
            if activities:
                execute_values(
                    cur,
                    'INSERT INTO Activity(activity_id, sector_id, activity_label, activity_description) VALUES %s '
                    'ON CONFLICT (activity_id) DO UPDATE SET '
                    'sector_id=EXCLUDED.sector_id, activity_label=EXCLUDED.activity_label',
                    [(aid, sid, label, None) for (aid, sid, label) in activities]
                )

        conn.commit()
        print("[OK] Import Reference terminé.")
        print(f"  - Pillar: {len(pillars)}")
        print(f"  - ActivitySector: {len(sectors)}")
        print(f"  - Activity: {len(activities)}")

    except Exception:
        conn.rollback()
        raise
    finally:
        conn.close()


if __name__ == "__main__":
    try:
        main()
    except Exception as e:
        print(f"[ERROR] {e}", file=sys.stderr)
        raise
    
# $env:XLSM_PATH="C:\Users\visioterra\Documents\API_Dounya\Fiches_NA_DB_24_v5b.xlsm"
# $env:PGDATABASE="naturafrica"
# $env:PGUSER="postgres"
# $env:PGPASSWORD="visioterra"
# $env:INSPECT_ONLY="0"
# $env:TRUNCATE_BEFORE="0"
# python .\import_references.py