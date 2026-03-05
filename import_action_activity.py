import os, sys
import pandas as pd
import psycopg2
from psycopg2.extras import execute_values

XLSX_DEFAULT = "20260204_Jungers__NaturAfrica_DB_core_v2.xlsx"

def env(n, d=None):
    v = os.getenv(n)
    return v if v not in (None,"") else d

def conn():
    return psycopg2.connect(
        host=env("PGHOST","localhost"),
        port=int(env("PGPORT","5432")),
        dbname=env("PGDATABASE","naturafrica"),
        user=env("PGUSER","postgres"),
        password=env("PGPASSWORD","visioterra"),
    )

def main():
    xlsx = sys.argv[1] if len(sys.argv)>1 else XLSX_DEFAULT
    df = pd.read_excel(xlsx, sheet_name="Action_Activity", engine="openpyxl")
    df = df.dropna(how="all").copy()
    df.columns = [str(c).strip().lower() for c in df.columns]

    rows=[]
    for _, r in df.iterrows():
        aid = r.get("action_id")
        act = r.get("activity_id")
        if pd.isna(aid) or pd.isna(act):
            continue
        rows.append((int(aid), str(act).strip(), False))

    if not rows:
        print("0 rows")
        return

    sql = """
    INSERT INTO Action_Activity ("action_id","activity_id","is_primary")
    VALUES %s
    ON CONFLICT ("action_id","activity_id") DO NOTHING;
    """

    c = conn()
    with c.cursor() as cur:
        execute_values(cur, sql, rows, page_size=1000)
    c.commit()
    c.close()
    print(f"✅ action_activity importé: {len(rows)} lignes")

if __name__ == "__main__":
    main()