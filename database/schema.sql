
PRAGMA foreign_keys=ON;
BEGIN;
CREATE TABLE directorates(id INTEGER PRIMARY KEY AUTOINCREMENT,name TEXT NOT NULL,is_active INTEGER NOT NULL DEFAULT 1 CHECK(is_active IN(0,1)));
CREATE TABLE departments(id INTEGER PRIMARY KEY AUTOINCREMENT,directorate_id INTEGER NOT NULL,name TEXT NOT NULL,is_active INTEGER NOT NULL DEFAULT 1 CHECK(is_active IN(0,1)),FOREIGN KEY(directorate_id) REFERENCES directorates(id));
CREATE INDEX idx_department_directorate ON departments(directorate_id);

CREATE TABLE users(id INTEGER PRIMARY KEY AUTOINCREMENT,department_id INTEGER NULL,name TEXT NOT NULL,username TEXT NOT NULL COLLATE NOCASE UNIQUE,email TEXT NULL,password TEXT NOT NULL,role TEXT NOT NULL CHECK(role IN('SUPERADMIN','UNIT','APPROVER','IT_HEAD','IT_STAFF')),is_active INTEGER NOT NULL DEFAULT 1 CHECK(is_active IN(0,1)),last_login_at TEXT NULL,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TEXT NULL,FOREIGN KEY(department_id) REFERENCES departments(id) ON DELETE SET NULL);
CREATE INDEX idx_users_department ON users(department_id);
CREATE INDEX idx_users_role ON users(role);

CREATE TABLE program_categories(id INTEGER PRIMARY KEY AUTOINCREMENT,name TEXT NOT NULL,description TEXT NULL,is_active INTEGER NOT NULL DEFAULT 1 CHECK(is_active IN(0,1)));
CREATE TABLE document_sequences(id INTEGER PRIMARY KEY AUTOINCREMENT,sequence_key TEXT NOT NULL,sequence_year INTEGER NOT NULL,last_number INTEGER NOT NULL DEFAULT 0,UNIQUE(sequence_key,sequence_year));

CREATE TABLE program_requests(
 id INTEGER PRIMARY KEY AUTOINCREMENT,request_no TEXT NOT NULL UNIQUE,user_id INTEGER NOT NULL,department_id INTEGER NOT NULL,directorate_id INTEGER NOT NULL,category_id INTEGER NOT NULL,
 program_name TEXT NOT NULL,background TEXT NOT NULL,objectives TEXT NOT NULL,benefits TEXT NULL,current_condition TEXT NULL,requested_solution TEXT NOT NULL,
 urgency TEXT NOT NULL DEFAULT 'MEDIUM' CHECK(urgency IN('LOW','MEDIUM','HIGH','CRITICAL')),estimated_budget NUMERIC NOT NULL DEFAULT 0,target_date TEXT NULL,
 status TEXT NOT NULL DEFAULT 'DRAFT' CHECK(status IN('DRAFT','SUBMITTED','WAITING_APPROVAL','APPROVED','REJECTED','REVISION','IT_REVIEW','PLANNED','IN_PROGRESS','TESTING','COMPLETED','CANCELLED')),
 submitted_at TEXT NULL,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(user_id) REFERENCES users(id),FOREIGN KEY(department_id) REFERENCES departments(id),FOREIGN KEY(directorate_id) REFERENCES directorates(id),FOREIGN KEY(category_id) REFERENCES program_categories(id)
);
CREATE INDEX idx_request_status ON program_requests(status);
CREATE INDEX idx_request_department ON program_requests(department_id);
CREATE INDEX idx_request_directorate ON program_requests(directorate_id);
CREATE INDEX idx_request_created ON program_requests(created_at);

CREATE TABLE request_files(id INTEGER PRIMARY KEY AUTOINCREMENT,request_id INTEGER NOT NULL,file_type TEXT NOT NULL CHECK(file_type IN('GENERATED_PDF','SIGNED_PDF','SUPPORTING','FINAL_DOCUMENT')),original_name TEXT NOT NULL,stored_name TEXT NOT NULL,storage_path TEXT NOT NULL,mime_type TEXT NULL,file_size INTEGER NOT NULL DEFAULT 0,version INTEGER NOT NULL DEFAULT 1,uploaded_by INTEGER NOT NULL,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(request_id) REFERENCES program_requests(id) ON DELETE CASCADE,FOREIGN KEY(uploaded_by) REFERENCES users(id));
CREATE INDEX idx_request_files_request ON request_files(request_id);

CREATE TABLE request_approvals(id INTEGER PRIMARY KEY AUTOINCREMENT,request_id INTEGER NOT NULL,approver_id INTEGER NULL,approval_method TEXT NOT NULL CHECK(approval_method IN('DIGITAL','MANUAL')),decision TEXT NOT NULL DEFAULT 'PENDING' CHECK(decision IN('PENDING','APPROVED','REJECTED','REVISION')),approval_level INTEGER NOT NULL DEFAULT 1,approver_name_snapshot TEXT NULL,approver_role_snapshot TEXT NULL,notes TEXT NULL,document_file_id INTEGER NULL,approved_at TEXT NULL,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(request_id) REFERENCES program_requests(id) ON DELETE CASCADE,FOREIGN KEY(approver_id) REFERENCES users(id) ON DELETE SET NULL,FOREIGN KEY(document_file_id) REFERENCES request_files(id) ON DELETE SET NULL);
CREATE INDEX idx_approval_request ON request_approvals(request_id,approval_level);

CREATE TABLE it_tickets(id INTEGER PRIMARY KEY AUTOINCREMENT,ticket_no TEXT NOT NULL UNIQUE,request_id INTEGER NOT NULL UNIQUE,priority TEXT NOT NULL DEFAULT 'MEDIUM' CHECK(priority IN('LOW','MEDIUM','HIGH','CRITICAL')),status TEXT NOT NULL DEFAULT 'WAITING_APPROVAL' CHECK(status IN('WAITING_APPROVAL','APPROVED','REJECTED','REVISION','IT_REVIEW','PLANNED','IN_PROGRESS','TESTING','COMPLETED','CLOSED','CANCELLED')),created_by INTEGER NULL,reviewed_by INTEGER NULL,reviewed_at TEXT NULL,assigned_at TEXT NULL,started_at TEXT NULL,due_date TEXT NULL,completed_at TEXT NULL,closed_at TEXT NULL,resolution TEXT NULL,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(request_id) REFERENCES program_requests(id),FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,FOREIGN KEY(reviewed_by) REFERENCES users(id) ON DELETE SET NULL);
CREATE INDEX idx_ticket_status ON it_tickets(status);
CREATE INDEX idx_ticket_due ON it_tickets(due_date);

CREATE TABLE it_staff_members(id INTEGER PRIMARY KEY AUTOINCREMENT,name TEXT NOT NULL,position TEXT NULL,phone TEXT NULL,is_active INTEGER NOT NULL DEFAULT 1 CHECK(is_active IN(0,1)),created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TEXT NULL);
CREATE INDEX idx_it_staff_active ON it_staff_members(is_active,name);

CREATE TABLE ticket_assignments(id INTEGER PRIMARY KEY AUTOINCREMENT,ticket_id INTEGER NOT NULL,staff_id INTEGER NOT NULL,assigned_by INTEGER NOT NULL,assigned_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,is_active INTEGER NOT NULL DEFAULT 1 CHECK(is_active IN(0,1)),staff_member_id INTEGER NULL,FOREIGN KEY(ticket_id) REFERENCES it_tickets(id) ON DELETE CASCADE,FOREIGN KEY(staff_id) REFERENCES users(id),FOREIGN KEY(assigned_by) REFERENCES users(id),FOREIGN KEY(staff_member_id) REFERENCES it_staff_members(id) ON DELETE SET NULL);
CREATE INDEX idx_assignment_staff ON ticket_assignments(staff_id,is_active);
CREATE INDEX idx_assignment_ticket ON ticket_assignments(ticket_id,is_active);
CREATE INDEX idx_assignment_member ON ticket_assignments(staff_member_id,is_active);

CREATE TABLE ticket_comments(id INTEGER PRIMARY KEY AUTOINCREMENT,ticket_id INTEGER NOT NULL,user_id INTEGER NOT NULL,comment TEXT NOT NULL,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(ticket_id) REFERENCES it_tickets(id) ON DELETE CASCADE,FOREIGN KEY(user_id) REFERENCES users(id));
CREATE INDEX idx_comment_ticket ON ticket_comments(ticket_id,created_at);

CREATE TABLE ticket_history(id INTEGER PRIMARY KEY AUTOINCREMENT,ticket_id INTEGER NOT NULL,user_id INTEGER NOT NULL,old_status TEXT NULL,new_status TEXT NULL,activity TEXT NOT NULL,description TEXT NULL,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(ticket_id) REFERENCES it_tickets(id) ON DELETE CASCADE,FOREIGN KEY(user_id) REFERENCES users(id));
CREATE INDEX idx_history_ticket ON ticket_history(ticket_id,created_at);

CREATE TABLE audit_logs(id INTEGER PRIMARY KEY AUTOINCREMENT,user_id INTEGER NULL,action TEXT NOT NULL,entity_type TEXT NULL,entity_id INTEGER NULL,detail TEXT NULL,ip_address TEXT NULL,user_agent TEXT NULL,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL);
CREATE INDEX idx_audit_entity ON audit_logs(entity_type,entity_id);
CREATE INDEX idx_audit_created ON audit_logs(created_at);

INSERT INTO program_categories(name,description) VALUES
('Pengembangan Aplikasi','Pembuatan aplikasi atau modul baru'),
('Perubahan Fitur','Modifikasi fitur aplikasi yang sudah ada'),
('Perangkat Keras','Permohonan perangkat komputer dan periferal'),
('Jaringan dan Infrastruktur','Permohonan jaringan, server, dan infrastruktur'),
('Integrasi Sistem','Integrasi antar aplikasi atau layanan'),
('Dashboard dan Pelaporan','Pengembangan dashboard dan laporan'),
('Lainnya','Kategori permohonan lainnya');
COMMIT;
