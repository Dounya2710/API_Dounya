-- Indexes for frequent joins
CREATE INDEX IF NOT EXISTS idx_eu_programme_ms_id ON EU_Programme(ms_id);
CREATE INDEX IF NOT EXISTS idx_action_programme_id ON Action(programme_id);

-- Junction tables
CREATE INDEX IF NOT EXISTS idx_action_klcd_action_id ON Action_KLCD(action_id);
CREATE INDEX IF NOT EXISTS idx_action_klcd_klcd_id   ON Action_KLCD(KLCD_ID);

CREATE INDEX IF NOT EXISTS idx_action_loc_action_id ON Action_Location(action_id);
CREATE INDEX IF NOT EXISTS idx_action_loc_location_id ON Action_Location(location_id);

CREATE INDEX IF NOT EXISTS idx_action_pa_action_id ON Action_ProtectedArea(action_id);
CREATE INDEX IF NOT EXISTS idx_action_pa_pa_id ON Action_ProtectedArea(pa_id);

CREATE INDEX IF NOT EXISTS idx_klcd_location_klcd_id ON KLCD_Location(KLCD_ID);
CREATE INDEX IF NOT EXISTS idx_klcd_location_location_id ON KLCD_Location(location_id);
