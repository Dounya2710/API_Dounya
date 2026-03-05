import pandas as pd
import warnings

warnings.filterwarnings(
    "ignore",
    message="Data Validation extension is not supported and will be removed",
    category=UserWarning
)

EXCEL_FILE = "Fiches_NA_DB_24_v5b.xlsm"
CSV_FILE   = "Fiches_NA_DB_24_v5b.csv"

# The data starts on line 31, we skip the first 30 lines
SKIP_ROWS = 30

def main():
    # Reading the sheet (by default the first one)
    df = pd.read_excel(
        EXCEL_FILE,
        sheet_name=0,
        engine="openpyxl",
        skiprows=SKIP_ROWS
    )

    # 1) We remove column A (the first column) since the actual data starts in column B
    df = df.iloc[:, 1:]

    # 2) We remove completely empty columns, just in case
    df = df.dropna(axis=1, how="all")

    # 3) We clean up any Excel markers of type _x000D_
    df = df.map(
        lambda v: v.replace("_x000D_", "\n") if isinstance(v, str) else v
    )

    # 4) Saved as CSV UTF-8, separator ';'
    df.to_csv(
        CSV_FILE,
        index=False,
        sep=";",
        encoding="utf-8"
    )

    print(f"CSV file on UTF-8 generated: {CSV_FILE}")

if __name__ == "__main__":
    main()
