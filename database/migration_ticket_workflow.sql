-- Jalankan sekali pada database lama untuk mengaktifkan daftar petugas IT mandiri.
BEGIN;

CREATE TABLE IF NOT EXISTS it_staff_members (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    position TEXT NULL,
    phone TEXT NULL,
    is_active INTEGER NOT NULL DEFAULT 1 CHECK(is_active IN(0,1)),
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NULL
);
CREATE INDEX IF NOT EXISTS idx_it_staff_active ON it_staff_members(is_active,name);

ALTER TABLE ticket_assignments ADD COLUMN staff_member_id INTEGER NULL REFERENCES it_staff_members(id) ON DELETE SET NULL;
CREATE INDEX IF NOT EXISTS idx_assignment_member ON ticket_assignments(staff_member_id,is_active);

COMMIT;