CREATE TABLE erapor_penugasan_penyetuju_audit (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    penyetuju_id INT NOT NULL,
    user_id INT NOT NULL,
    actor_id INT NOT NULL,
    aktif_sebelum BOOLEAN NOT NULL,
    aktif_sesudah BOOLEAN NOT NULL,
    alasan VARCHAR(2000) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY erapor_assignment_audit_target (penyetuju_id,user_id,created_at),
    KEY erapor_assignment_audit_actor (actor_id,created_at),
    FOREIGN KEY (penyetuju_id) REFERENCES erapor_alur_penyetuju(id) ON DELETE RESTRICT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE RESTRICT,
    CHECK (aktif_sebelum IN (0,1) AND aktif_sesudah IN (0,1) AND aktif_sebelum<>aktif_sesudah)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
