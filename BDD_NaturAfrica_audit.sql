-- Generic audit log table
CREATE TABLE IF NOT EXISTS audit_log (
    audit_id      BIGSERIAL PRIMARY KEY,
    table_name    TEXT NOT NULL,
    op            TEXT NOT NULL, -- INSERT / UPDATE / DELETE
    changed_at    TIMESTAMPTZ NOT NULL DEFAULT now(),
    changed_by    TEXT NULL,     -- comes from current_setting('app.user', true)
    old_row       JSONB NULL,
    new_row       JSONB NULL
);

-- Trigger function for auditing
CREATE OR REPLACE FUNCTION audit_trigger_fn()
RETURNS TRIGGER AS $$
DECLARE
    v_user TEXT;
BEGIN
    v_user := current_setting('app.user', true);

    IF (TG_OP = 'INSERT') THEN
        INSERT INTO audit_log(table_name, op, changed_by, old_row, new_row)
        VALUES (TG_TABLE_NAME, TG_OP, v_user, NULL, to_jsonb(NEW));
        RETURN NEW;

    ELSIF (TG_OP = 'UPDATE') THEN
        INSERT INTO audit_log(table_name, op, changed_by, old_row, new_row)
        VALUES (TG_TABLE_NAME, TG_OP, v_user, to_jsonb(OLD), to_jsonb(NEW));
        RETURN NEW;

    ELSIF (TG_OP = 'DELETE') THEN
        INSERT INTO audit_log(table_name, op, changed_by, old_row, new_row)
        VALUES (TG_TABLE_NAME, TG_OP, v_user, to_jsonb(OLD), NULL);
        RETURN OLD;
    END IF;

    RETURN NULL;
END;
$$ LANGUAGE plpgsql;

-- Helper: drop and recreate triggers safely
DO $$
BEGIN
    IF to_regclass('Action') IS NOT NULL THEN
        DROP TRIGGER IF EXISTS tr_audit_action ON Action;
        CREATE TRIGGER tr_audit_action
        AFTER INSERT OR UPDATE OR DELETE ON Action
        FOR EACH ROW EXECUTE FUNCTION audit_trigger_fn();
    END IF;

    IF to_regclass('KLCD') IS NOT NULL THEN
        DROP TRIGGER IF EXISTS tr_audit_klcd ON KLCD;
        CREATE TRIGGER tr_audit_klcd
        AFTER INSERT OR UPDATE OR DELETE ON KLCD
        FOR EACH ROW EXECUTE FUNCTION audit_trigger_fn();
    END IF;

    IF to_regclass('Location') IS NOT NULL THEN
        DROP TRIGGER IF EXISTS tr_audit_location ON Location;
        CREATE TRIGGER tr_audit_location
        AFTER INSERT OR UPDATE OR DELETE ON Location
        FOR EACH ROW EXECUTE FUNCTION audit_trigger_fn();
    END IF;

    IF to_regclass('MemberState') IS NOT NULL THEN
        DROP TRIGGER IF EXISTS tr_audit_memberstate ON MemberState;
        CREATE TRIGGER tr_audit_memberstate
        AFTER INSERT OR UPDATE OR DELETE ON MemberState
        FOR EACH ROW EXECUTE FUNCTION audit_trigger_fn();
    END IF;

    IF to_regclass('ActivitySector') IS NOT NULL THEN
        DROP TRIGGER IF EXISTS tr_audit_activitysector ON ActivitySector;
        CREATE TRIGGER tr_audit_activitysector
        AFTER INSERT OR UPDATE OR DELETE ON ActivitySector
        FOR EACH ROW EXECUTE FUNCTION audit_trigger_fn();
    END IF;

    IF to_regclass('Activity') IS NOT NULL THEN
        DROP TRIGGER IF EXISTS tr_audit_activity ON Activity;
        CREATE TRIGGER tr_audit_activity
        AFTER INSERT OR UPDATE OR DELETE ON Activity
        FOR EACH ROW EXECUTE FUNCTION audit_trigger_fn();
    END IF;

    IF to_regclass('Action_Location') IS NOT NULL THEN
        DROP TRIGGER IF EXISTS tr_audit_action_location ON Action_Location;
        CREATE TRIGGER tr_audit_action_location
        AFTER INSERT OR UPDATE OR DELETE ON Action_Location
        FOR EACH ROW EXECUTE FUNCTION audit_trigger_fn();
    END IF;

    IF to_regclass('Action_KLCD') IS NOT NULL THEN
        DROP TRIGGER IF EXISTS tr_audit_action_klcd ON Action_KLCD;
        CREATE TRIGGER tr_audit_action_klcd
        AFTER INSERT OR UPDATE OR DELETE ON Action_KLCD
        FOR EACH ROW EXECUTE FUNCTION audit_trigger_fn();
    END IF;

    IF to_regclass('KLCD_Location') IS NOT NULL THEN
        DROP TRIGGER IF EXISTS tr_audit_klcd_location ON KLCD_Location;
        CREATE TRIGGER tr_audit_klcd_location
        AFTER INSERT OR UPDATE OR DELETE ON KLCD_Location
        FOR EACH ROW EXECUTE FUNCTION audit_trigger_fn();
    END IF;

    IF to_regclass('ProtectedArea_KLCD') IS NOT NULL THEN
        DROP TRIGGER IF EXISTS tr_audit_protectedarea_klcd ON ProtectedArea_KLCD;
        CREATE TRIGGER tr_audit_protectedarea_klcd
        AFTER INSERT OR UPDATE OR DELETE ON ProtectedArea_KLCD
        FOR EACH ROW EXECUTE FUNCTION audit_trigger_fn();
    END IF;

    IF to_regclass('Action_ProtectedArea') IS NOT NULL THEN
        DROP TRIGGER IF EXISTS tr_audit_action_protectedarea ON Action_ProtectedArea;
        CREATE TRIGGER tr_audit_action_protectedarea
        AFTER INSERT OR UPDATE OR DELETE ON Action_ProtectedArea
        FOR EACH ROW EXECUTE FUNCTION audit_trigger_fn();
    END IF;

    IF to_regclass('Action_Implementer') IS NOT NULL THEN
        DROP TRIGGER IF EXISTS tr_audit_action_implementer ON Action_Implementer;
        CREATE TRIGGER tr_audit_action_implementer
        AFTER INSERT OR UPDATE OR DELETE ON Action_Implementer
        FOR EACH ROW EXECUTE FUNCTION audit_trigger_fn();
    END IF;

    IF to_regclass('Action_MS_Funding') IS NOT NULL THEN
        DROP TRIGGER IF EXISTS tr_audit_action_ms_funding ON Action_MS_Funding;
        CREATE TRIGGER tr_audit_action_ms_funding
        AFTER INSERT OR UPDATE OR DELETE ON Action_MS_Funding
        FOR EACH ROW EXECUTE FUNCTION audit_trigger_fn();
    END IF;

    IF to_regclass('Action_Activity') IS NOT NULL THEN
        DROP TRIGGER IF EXISTS tr_audit_action_activity ON Action_Activity;
        CREATE TRIGGER tr_audit_action_activity
        AFTER INSERT OR UPDATE OR DELETE ON Action_Activity
        FOR EACH ROW EXECUTE FUNCTION audit_trigger_fn();
    END IF;

END $$;