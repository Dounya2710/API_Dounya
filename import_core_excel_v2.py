import os
import sys
import re
from datetime import datetime, date
import pandas as pd

try:
    import psycopg2
    from psycopg2.extras import execute_values
except ImportError:
    print("❌ psycopg2 non installé. Fais: pip install psycopg2-binary pandas openpyxl")
    sys.exit(1)

XLSX_DEFAULT = "20260204_Jungers__NaturAfrica_DB_core_v2.xlsx"
#XLSX_DEFAULT = "20260216_Jungers__NaturAfrica_DB_core_v5_AC_AS_AE_AO.xlsx"


# ------------------------
# Helpers
# ------------------------
def env(name, default=None):
    v = os.getenv(name)
    return v if v not in (None, "") else default


def get_conn():
    return psycopg2.connect(
        host=env("PGHOST", "localhost"),
        port=int(env("PGPORT", "5432")),
        dbname=env("PGDATABASE", "naturafrica"),
        user=env("PGUSER", "postgres"),
        password=env("PGPASSWORD", ""),
    )


def clean_df(df: pd.DataFrame) -> pd.DataFrame:
    df = df.dropna(how="all").copy()
    df.columns = [re.sub(r"\s+", "_", str(c).strip().lower()) for c in df.columns]
    return df


def pick(r, *candidates):
    """Return first non-empty value among candidate columns."""
    for c in candidates:
        if c in r.index:
            v = r.get(c)
            if v is None:
                continue
            if isinstance(v, float) and pd.isna(v):
                continue
            s = str(v).strip()
            if s == "" or s.lower() == "nan":
                continue
            return v
    return None


def to_int_or_none(x):
    try:
        if x is None or (isinstance(x, float) and pd.isna(x)):
            return None
        return int(x)
    except Exception:
        s = str(x).strip()
        if s == "" or s.lower() == "nan":
            return None
        try:
            return int(float(s))
        except Exception:
            return None


def parse_prefixed_id(x, prefix="INST"):
    """
    Parse IDs like 'INST_0001' -> 1, or 'INST-12' -> 12.
    Returns int or None.
    """
    if x is None or (isinstance(x, float) and pd.isna(x)):
        return None
    if isinstance(x, int):
        return x
    s = str(x).strip()
    if s == "" or s.lower() == "nan":
        return None
    # direct int?
    i = to_int_or_none(s)
    if i is not None:
        return i
    # extract digits after prefix
    m = re.search(r"(\d+)", s)
    if m:
        try:
            return int(m.group(1))
        except Exception:
            return None
    return None


def to_float_or_none(x):
    if x is None or (isinstance(x, float) and pd.isna(x)):
        return None
    try:
        return float(x)
    except Exception:
        s = str(x).strip().replace(",", ".")
        if s == "" or s.lower() == "nan":
            return None
        return float(s)


def to_date_or_none(x):
    if x is None or (isinstance(x, float) and pd.isna(x)):
        return None
    if isinstance(x, (datetime, date)):
        return x.date() if isinstance(x, datetime) else x
    s = str(x).strip()
    if s == "" or s.lower() == "nan":
        return None
    for fmt in ("%Y-%m-%d", "%d/%m/%Y", "%d-%m-%Y"):
        try:
            return datetime.strptime(s, fmt).date()
        except Exception:
            pass
    return None


def flag_present(x) -> bool:
    """
    Excel flags are codes like 'C1', 'G2', 'E1' (not booleans).
    We consider a flag True if cell is non-empty.
    """
    if x is None:
        return False
    if isinstance(x, float) and pd.isna(x):
        return False
    s = str(x).strip()
    return s != "" and s.lower() != "nan"


def fix_mojibake(s: str) -> str:
    """
    Try to fix CP1252/Latin-1 mojibake (e.g. 'coopÚration' -> 'coopération').
    If not mojibake, returns original.
    """
    if s is None:
        return s
    s = str(s)
    try:
        return s.encode("latin1").decode("utf-8")
    except Exception:
        return s


def dedupe_rows(rows, key_indexes):
    seen = {}
    for row in rows:
        key = tuple(row[i] for i in key_indexes)
        seen[key] = row
    return list(seen.values())


def bulk_upsert(conn, table, columns, rows, conflict_cols):
    """
    Upsert helper using INSERT ... ON CONFLICT.
    If the target table does NOT have a matching UNIQUE/EXCLUDE constraint for conflict_cols,
    we fallback to a plain INSERT (no ON CONFLICT). This keeps everything "code-side"
    and avoids requiring schema changes.
    """
    if not rows:
        print(f"[SKIP] {table}: 0 rows")
        return

    cols_sql = ", ".join(f'"{c}"' for c in columns)

    def _insert_plain():
        sql_plain = f'INSERT INTO {table} ({cols_sql}) VALUES %s;'
        with conn.cursor() as cur:
            execute_values(cur, sql_plain, rows, page_size=1000)
        conn.commit()
        print(f"[OK] {table}: {len(rows)} rows (insert)")

    # If no conflict columns provided, just insert
    if not conflict_cols:
        return _insert_plain()

    conflict_sql = ", ".join(f'"{c}"' for c in conflict_cols)
    update_cols = [c for c in columns if c not in conflict_cols]

    if update_cols:
        set_sql = ", ".join(f'"{c}"=EXCLUDED."{c}"' for c in update_cols)
        on_conflict = f"ON CONFLICT ({conflict_sql}) DO UPDATE SET {set_sql}"
    else:
        on_conflict = f"ON CONFLICT ({conflict_sql}) DO NOTHING"

    sql = f'INSERT INTO {table} ({cols_sql}) VALUES %s {on_conflict};'

    try:
        with conn.cursor() as cur:
            execute_values(cur, sql, rows, page_size=1000)
        conn.commit()
        print(f"[OK] {table}: {len(rows)} rows (upsert)")
    except psycopg2.errors.InvalidColumnReference:
        # No matching UNIQUE constraint for ON CONFLICT (...) on this table.
        conn.rollback()
        _insert_plain()


def read_sheet(xlsx, sheet_name) -> pd.DataFrame:
    df = pd.read_excel(xlsx, sheet_name=sheet_name, engine="openpyxl")
    return clean_df(df)


def count_rows(conn, table, where_sql=None):
    with conn.cursor() as cur:
        if where_sql:
            cur.execute(f"SELECT COUNT(*) FROM {table} WHERE {where_sql};")
        else:
            cur.execute(f"SELECT COUNT(*) FROM {table};")
        return cur.fetchone()[0]


# ------------------------
# Main
# ------------------------
def main():
    xlsx = sys.argv[1] if len(sys.argv) > 1 else XLSX_DEFAULT
    if not os.path.exists(xlsx):
        raise FileNotFoundError(f"Excel introuvable: {xlsx}")

    sheets = [
        "Pillar",
        "Sector",
        "ActivitySector",
        "KLCD",
        "KLCD_Location",
        "Location",
        "Protected_Area",
        "Institution",
        "Action",
        "Action_KLCD",
        "Action_Implementer",
        "Action_Activity",
        "Action_ProtectedArea",
    ]

    data = {}
    for s in sheets:
        try:
            df = read_sheet(xlsx, s)
            data[s] = df
            print(f"[INFO] {s}: {len(df)} rows | cols={list(df.columns)}")
        except Exception as e:
            print(f"[WARN] Sheet {s} ignorée ({e})")

    conn = get_conn()

    # 1) Pillar
    if "Pillar" in data:
        df = data["Pillar"]
        rows = []
        for _, r in df.iterrows():
            code = pick(r, "pillar_code")
            name = pick(r, "pillar_name")
            if code is None:
                continue
            rows.append((str(code).strip(), None if name is None else str(name).strip()))
        rows = dedupe_rows(rows, [0])
        bulk_upsert(conn, "pillar", ["pillar_code", "pillar_name"], rows, ["pillar_code"])

    # 2) Sector -> activitysector
    if "Sector" in data:
        df = data["Sector"]
        rows = []
        for _, r in df.iterrows():
            sector_id = pick(r, "sector_id")
            pillar = pick(r, "pillar")
            label = pick(r, "sector_label")
            if sector_id is None:
                continue
            rows.append((str(sector_id).strip(),
                         None if pillar is None else str(pillar).strip(),
                         None if label is None else str(label).strip()))
        rows = dedupe_rows(rows, [0])
        bulk_upsert(conn, "activitysector",
                    ["sector_id", "pillar_code", "sector_label"],
                    rows, ["sector_id"])

    # 3) ActivitySector -> activity
    if "ActivitySector" in data:
        df = data["ActivitySector"]
        rows = []
        for _, r in df.iterrows():
            act_id = pick(r, "activity_id")
            sector_id = pick(r, "sector_id")
            label = pick(r, "activity_label")
            if act_id is None:
                continue
            rows.append((str(act_id).strip(),
                         None if sector_id is None else str(sector_id).strip(),
                         None if label is None else str(label).strip()))
        rows = dedupe_rows(rows, [0])
        bulk_upsert(conn, "activity",
                    ["activity_id", "sector_id", "activity_label"],
                    rows, ["activity_id"])

    # 4) KLCD
    if "KLCD" in data:
        df = data["KLCD"]
        rows = []
        for _, r in df.iterrows():
            klcd_id = to_int_or_none(pick(r, "klcd_id"))
            if klcd_id is None:
                continue
            rows.append((
                klcd_id,
                None if pick(r, "klcd_code") is None else str(pick(r, "klcd_code")).strip(),
                None if pick(r, "klcd_name") is None else fix_mojibake(str(pick(r, "klcd_name")).strip()),
                to_float_or_none(pick(r, "area_km2")),
                to_float_or_none(pick(r, "pop_density")),
                to_int_or_none(pick(r, "pop_total")),
            ))
        rows = dedupe_rows(rows, [0])
        bulk_upsert(conn, "klcd",
                    ["klcd_id", "klcd_code", "klcd_name", "area_km2", "pop_density", "pop_total"],
                    rows, ["klcd_id"])

    # 5) Location
    #if "Location" in data:
    #    df = data["Location"]
    #    rows = []
    #    for _, r in df.iterrows():
    #        lid = to_int_or_none(pick(r, "location_id"))
    #        if lid is None:
    #            continue
    #        rows.append((
    #            lid,
    #            None if pick(r, "iso3") is None else str(pick(r, "iso3")).strip(),
    #            to_int_or_none(pick(r, "admin_level")) or 0,
    #            None if pick(r, "admin_code") is None else str(pick(r, "admin_code")).strip(),
    #            None if pick(r, "admin_name") is None else fix_mojibake(str(pick(r, "admin_name")).strip()),
    #            None if pick(r, "sub_loc") is None else fix_mojibake(str(pick(r, "sub_loc")).strip()),
    #        ))
    #    rows = dedupe_rows(rows, [0])
    #    bulk_upsert(conn, "location",
    #                ["location_id", "iso3", "admin_level", "admin_code", "admin_name", "sub_loc"],
    #                rows, ["location_id"])

    # 6) KLCD_Location
    #if "KLCD_Location" in data:
    #    df = data["KLCD_Location"]
    #    rows = []
    #    for _, r in df.iterrows():
    #        kid = to_int_or_none(pick(r, "klcd_id"))
    #        lid = to_int_or_none(pick(r, "location_id"))
    #        if kid is None or lid is None:
    #            continue
            # share_pct missing in core_v2; keep NULL
    #        rows.append((kid, lid, None))
    #    rows = dedupe_rows(rows, [0, 1])
    #    bulk_upsert(conn, "klcd_location",
    #                ["klcd_id", "location_id", "share_pct"],
    #                rows, ["klcd_id", "location_id"])

    # 7) Protected_Area
    if "Protected_Area" in data:
        df = data["Protected_Area"]
        rows = []
        for _, r in df.iterrows():
            pa_id = to_int_or_none(pick(r, "wdpa_id", "pa_id"))
            if pa_id is None:
                continue
            rows.append((
                pa_id,
                str(pa_id),
                None if pick(r, "name_eng") is None else fix_mojibake(str(pick(r, "name_eng")).strip()),
                None if pick(r, "desig_eng") is None else fix_mojibake(str(pick(r, "desig_eng")).strip()),
                None if pick(r, "iucn_cat") is None else str(pick(r, "iucn_cat")).strip(),
                False,
                to_float_or_none(pick(r, "rep_m_area")),
                to_float_or_none(pick(r, "gis_m_area")),
                to_float_or_none(pick(r, "rep_area")),
                to_float_or_none(pick(r, "gis_area")),
                None if pick(r, "status") is None else str(pick(r, "status")).strip(),
                to_int_or_none(pick(r, "status_yr")),
                None if pick(r, "gov_type") is None else fix_mojibake(str(pick(r, "gov_type")).strip()),
                None if pick(r, "own_type") is None else fix_mojibake(str(pick(r, "own_type")).strip()),
                None if pick(r, "mang_auth") is None else fix_mojibake(str(pick(r, "mang_auth")).strip()),
                None,
                None,
            ))
        rows = dedupe_rows(rows, [0])
        bulk_upsert(conn, "protectedarea",
                    ["pa_id", "wpda_id", "name", "desig_eng", "iucn_cat", "marine",
                     "rep_m_area", "gis_m_area", "rep_area", "gis_area", "status", "status_yr",
                     "gov_type", "own_type", "mang_auth", "mang_plan", "location_id"],
                    rows, ["pa_id"])

    # 8) Institution (Excel: institution_id like INST_0001, name, short_name, type, iso3)
    if "Institution" in data:
        df = data["Institution"]
        rows = []
        for _, r in df.iterrows():
            iid = parse_prefixed_id(pick(r, "institution_id"))
            if iid is None:
                continue
            name = pick(r, "name")
            short_name = pick(r, "short_name")
            itype = pick(r, "type")
            iso3 = pick(r, "iso3")

            rows.append((
                iid,
                None if name is None else fix_mojibake(str(name).strip()),
                None if short_name is None else fix_mojibake(str(short_name).strip()),
                None if itype is None else fix_mojibake(str(itype).strip()),
                None if iso3 is None else str(iso3).strip(),
            ))
        rows = dedupe_rows(rows, [0])
        # Keep as many fields as your DB has; these columns exist in the Excel and typically in DB.
        bulk_upsert(conn, "institution",
                    ["institution_id", "name", "short_name", "type", "iso3"],
                    rows, ["institution_id"])

    # 9) Action (Excel: action_id, title, programme_id, biodiversity, green_economy, governance, ...)
    if "Action" in data:
        df = data["Action"]
        rows = []
        for _, r in df.iterrows():
            aid = to_int_or_none(pick(r, "action_id"))
            if aid is None:
                continue
    #        programme_id = to_int_or_none(pick(r, "programme_id"))  # will stay NULL if missing
            title = pick(r, "title")

            # Excel has codes like 'C1', 'G2', 'E1' => treat non-empty as True
            biodiv = flag_present(pick(r, "biodiversity"))
            green = flag_present(pick(r, "green_economy"))
            gov = flag_present(pick(r, "governance"))

            rows.append((
                aid,
    #            programme_id,
                None if title is None else fix_mojibake(str(title).strip()),
                biodiv,
                green,
                gov,
            ))
        rows = dedupe_rows(rows, [0])
        # DB columns used by dashboard are *_flag booleans
        bulk_upsert(conn, "action",
                    ["action_id", "title", #"programme_id", "title",
                     "biodiversity_flag", "green_economy_flag", "governance_flag"],
                    rows, ["action_id"])

    # 10) Action_KLCD
    if "Action_KLCD" in data:
        df = data["Action_KLCD"]
        rows = []
        for _, r in df.iterrows():
            aid = to_int_or_none(pick(r, "action_id"))
            kid = to_int_or_none(pick(r, "klcd_id"))
            if aid is None or kid is None:
                continue
            rows.append((aid, kid))
        rows = dedupe_rows(rows, [0, 1])
        bulk_upsert(conn, "action_klcd", ["action_id", "klcd_id"], rows, ["action_id", "klcd_id"])

    # 11) Action_Implementer (Excel: action_id, institution_id like INST_0001, role_code)
    if "Action_Implementer" in data:
        df = data["Action_Implementer"]
        rows = []
        for _, r in df.iterrows():
            aid = to_int_or_none(pick(r, "action_id"))
            iid = parse_prefixed_id(pick(r, "institution_id"))
            role = pick(r, "role_code")
            role = None if role is None else str(role).strip()  # keep 'Leader'/'Co-implementer' (case)
            if aid is None or iid is None:
                continue
            rows.append((aid, iid, role))
        rows = dedupe_rows(rows, [0, 1, 2])
        
        unique_rows = {}
        for a_id, i_id, role in rows:
            key = (a_id, i_id)
            if key not in unique_rows:
                unique_rows[key] = (a_id, i_id, role)

        rows = list(unique_rows.values())
        
        bulk_upsert(
            conn,
            "action_implementer",
            ["action_id", "institution_id", "role"],
            rows,
            ["action_id", "institution_id"]
        )

    # 12) Action_Activity (pivot needs at least action_id + activity_id)
    if "Action_Activity" in data:
        df = data["Action_Activity"]
        rows = []
        for _, r in df.iterrows():
            aid = to_int_or_none(pick(r, "action_id"))
            act_id = pick(r, "activity_id")
            if aid is None or act_id is None:
                continue
            act_id = str(act_id).strip()
            if act_id == "" or act_id.lower() == "nan":
                continue
            rows.append((aid, act_id))
        rows = dedupe_rows(rows, [0, 1])
        bulk_upsert(conn, "action_activity", ["action_id", "activity_id"], rows, ["action_id", "activity_id"])

    print("\n✅ Import terminé.\n[CHECKS CAMEMBERTS]")
    try:
        print(f"- action: {count_rows(conn, 'action')}")
        print(f"- action_activity (Pillar): {count_rows(conn, 'action_activity')}")
        print(f"- action_implementer (Implementers): {count_rows(conn, 'action_implementer')}")
        print(f"- action with programme_id not null (Programme): {count_rows(conn, 'action', '\"programme_id\" IS NOT NULL')}")
        if count_rows(conn, "action", "\"programme_id\" IS NOT NULL") == 0:
            print("=> Programme restera vide tant que PROGRAMME_ID est vide dans l’Excel.")
    except Exception as e:
        print(f"[WARN] checks skipped: {e}")

    conn.close()


if __name__ == "__main__":
    main()
