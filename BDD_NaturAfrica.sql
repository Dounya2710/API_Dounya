DROP TABLE IF EXISTS
  Action_Activity,
  Activity,
  ActivitySector,
  Pillar,
  Action_Implementer,
  Institution,
  Action_MS_Funding,
  MemberState,
  Action_ProtectedArea,
  ProtectedArea,
  Action_Location,
  Location,
  Action_KLCD,
  KLCD_Location,
  ProtectedArea_KLCD,
  KLCD,
  EU_Programme,
  Action
CASCADE;


CREATE TABLE IF NOT EXISTS KLCD(
    KLCD_ID INTEGER PRIMARY KEY NOT NULL,
    KLCD_code TEXT NOT NULL UNIQUE,
    KLCD_name TEXT NOT NULL,
    area_km2 NUMERIC(14, 2) NULL,
    pop_density NUMERIC(10, 2) NULL,
    pop_total NUMERIC(14, 0) NULL,
    notes TEXT NULL
);

CREATE TABLE IF NOT EXISTS Location(
    location_id INTEGER PRIMARY KEY NOT NULL,
    iso3 CHAR(3) NOT NULL,
    admin_level INTEGER NOT NULL,
    admin_code TEXT NULL,
    admin_name TEXT NOT NULL,
    sub_loc TEXT NULL
);

CREATE TABLE IF NOT EXISTS Institution(
    institution_id INTEGER PRIMARY KEY NOT NULL,
    name TEXT NOT NULL,
    short_name TEXT NULL,
    type TEXT NULL,
    iso3 CHAR(3) NULL
);

CREATE TABLE IF NOT EXISTS Pillar(
    pillar_code CHAR(1) PRIMARY KEY NOT NULL,
    pillar_name TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS MemberState(
    ms_id INTEGER PRIMARY KEY NOT NULL,
    ms_iso2 CHAR(2) NOT NULL UNIQUE,
    ms_name TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS EU_Programme(
    programme_id INTEGER PRIMARY KEY NOT NULL,
    opsys_id TEXT NULL,
    title TEXT NOT NULL,
    start_date DATE NULL,
    end_date DATE NULL,
    tei_flag BOOLEAN NOT NULL DEFAULT FALSE,
    instrument TEXT NULL,
    owner_type TEXT NOT NULL,
    ms_id INTEGER REFERENCES MemberState(ms_id) NULL
);

CREATE TABLE IF NOT EXISTS Action(
    action_id SERIAL PRIMARY KEY,
    opsys_contract_id TEXT NULL,
    title TEXT NOT NULL,
    programme_id INTEGER REFERENCES EU_Programme(programme_id) NULL,
    start_date DATE NULL,
    end_date DATE NULL,
    total_budget_EUR NUMERIC(14, 2) NULL,
    biodiversity_flag BOOLEAN NOT NULL DEFAULT FALSE,
    green_economy_flag BOOLEAN NOT NULL DEFAULT FALSE,
    governance_flag BOOLEAN NOT NULL DEFAULT FALSE
);

CREATE TABLE IF NOT EXISTS ProtectedArea(
    pa_id INTEGER PRIMARY KEY NOT NULL,
    wpda_id TEXT NULL,
    name TEXT NOT NULL,
    desig_eng TEXT NULL,
    iucn_cat TEXT NULL,
    marine BOOLEAN NOT NULL DEFAULT FALSE,
    rep_m_area NUMERIC(14, 2) NULL,
    gis_m_area NUMERIC(14, 2) NULL,
    rep_area NUMERIC(14, 2) NULL,
    gis_area NUMERIC(14, 2) NULL,
    status TEXT NULL,
    status_YR INTEGER NULL,
    gov_type TEXT NULL,
    own_type TEXT NULL,
    mang_auth TEXT NULL,
    mang_plan TEXT NULL,
    location_id INTEGER REFERENCES Location(location_id)
);

CREATE TABLE IF NOT EXISTS KLCD_Location(
    KLCD_ID INTEGER NOT NULL REFERENCES KLCD(KLCD_ID),
    location_id INTEGER NOT NULL REFERENCES Location(location_id),
    share_pct NUMERIC(5, 2) NULL,
    PRIMARY KEY(KLCD_ID, location_id)
);

CREATE TABLE IF NOT EXISTS ProtectedArea_KLCD(
    KLCD_ID INTEGER NOT NULL REFERENCES KLCD(KLCD_ID),
    pa_id INTEGER NOT NULL REFERENCES ProtectedArea(pa_id),
    pct_in_klcd NUMERIC(5, 2) NULL,
    PRIMARY KEY (KLCD_ID, pa_id)
);

CREATE TABLE IF NOT EXISTS Action_KLCD(
    action_id INTEGER NOT NULL REFERENCES Action(action_id),
    KLCD_ID INTEGER NOT NULL REFERENCES KLCD(KLCD_ID),
    PRIMARY KEY (action_id, KLCD_ID)
);

CREATE TABLE IF NOT EXISTS Action_ProtectedArea(
    action_id INTEGER NOT NULL REFERENCES Action(action_id),
    pa_id INTEGER NOT NULL REFERENCES ProtectedArea(pa_id),
    note TEXT NULL,
    PRIMARY KEY (action_id, pa_id)
);

CREATE TABLE IF NOT EXISTS Action_Location(
    action_id INTEGER NOT NULL REFERENCES Action(action_id),
    location_id INTEGER NOT NULL REFERENCES Location(location_id),
    PRIMARY KEY (action_id, location_id)
);

CREATE TABLE IF NOT EXISTS Action_Implementer(
    action_id INTEGER NOT NULL REFERENCES Action(action_id),
    institution_id INTEGER NOT NULL REFERENCES Institution(institution_id),
    role TEXT NOT NULL,
    PRIMARY KEY (action_id, institution_id)
);

CREATE TABLE IF NOT EXISTS Action_MS_Funding(
    action_id INTEGER NOT NULL REFERENCES Action(action_id),
    ms_id INTEGER NOT NULL REFERENCES MemberState(ms_id),
    role TEXT NOT NULL,
    amount_eur NUMERIC(14, 2) NULL,
    share_pct NUMERIC(5, 2)  NULL,
    PRIMARY KEY (action_id, ms_id)
);

CREATE TABLE IF NOT EXISTS ActivitySector(
    sector_id TEXT PRIMARY KEY NOT NULL,
    pillar_code CHAR(1) NOT NULL REFERENCES Pillar(pillar_code),
    sector_label TEXT NOT NULL,
    sector_description TEXT NULL
);

CREATE TABLE IF NOT EXISTS Activity(
    activity_id TEXT PRIMARY KEY NOT NULL,
    sector_id TEXT NOT NULL REFERENCES ActivitySector(sector_id),
    activity_label TEXT NOT NULL,
    activity_description TEXT NULL
);

CREATE TABLE IF NOT EXISTS Action_Activity(
    action_id INTEGER NOT NULL REFERENCES Action(action_id),
    activity_id TEXT NOT NULL REFERENCES Activity(activity_id),
    is_primary BOOLEAN NOT NULL DEFAULT FALSE,
    PRIMARY KEY (action_id, activity_id)
);
