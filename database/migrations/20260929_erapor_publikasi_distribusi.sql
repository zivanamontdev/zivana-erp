-- Rapor terbit: salinan PDF resmi di bucket R2 private + link unduh untuk orang tua dan jejak pembagiannya.
-- Link unduh (token acak) mengarah ke aplikasi, yang lalu meneruskan ke URL R2 bertanda tangan berumur pendek.
CREATE TABLE erapor_publikasi_distribusi (
    sesi_id INT NOT NULL PRIMARY KEY,
    r2_bucket VARCHAR(100) NOT NULL,
    r2_key VARCHAR(255) NOT NULL,
    unduh_token CHAR(64) NOT NULL,
    unduh_url VARCHAR(500) NOT NULL,
    unduh_kedaluwarsa DATETIME NOT NULL,
    diterbitkan_oleh INT NOT NULL,
    diterbitkan_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    dibagikan_oleh INT NULL,
    dibagikan_pada DATETIME NULL,
    dibagikan_ke ENUM('AYAH','IBU') NULL,
    jumlah_unduh INT UNSIGNED NOT NULL DEFAULT 0,
    terakhir_unduh DATETIME NULL,
    UNIQUE KEY erapor_distribusi_token (unduh_token),
    FOREIGN KEY (sesi_id) REFERENCES erapor_publikasi_pdf(sesi_id) ON DELETE RESTRICT,
    FOREIGN KEY (diterbitkan_oleh) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (dibagikan_oleh) REFERENCES users(id) ON DELETE SET NULL,
    CHECK (unduh_token REGEXP '^[0-9a-f]{64}$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
