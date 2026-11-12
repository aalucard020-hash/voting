USE `db_voting_kakang_senduk`;

ALTER TABLE `users`
    MODIFY `email` VARCHAR(150) NOT NULL,
    MODIFY `role` ENUM('admin', 'pemilih') NOT NULL DEFAULT 'pemilih',
    ADD COLUMN `status` ENUM('Aktif', 'Nonaktif') NOT NULL DEFAULT 'Aktif'
        AFTER `role`,
    ADD COLUMN `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`;

ALTER TABLE `periode_voting`
    MODIFY `nama_periode` VARCHAR(150) NOT NULL,
    MODIFY `status` ENUM('Persiapan', 'Aktif', 'Selesai', 'Nonaktif')
        NOT NULL DEFAULT 'Persiapan',
    ADD COLUMN `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ADD COLUMN `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP;

INSERT INTO `periode_voting`
    (`nama_periode`, `tanggal_mulai`, `tanggal_selesai`, `status`)
SELECT
    'Periode Data Lama',
    '2000-01-01 00:00:00',
    '2099-12-31 23:59:59',
    'Nonaktif'
WHERE NOT EXISTS (SELECT 1 FROM `periode_voting`)
  AND EXISTS (SELECT 1 FROM `kontestan`);

ALTER TABLE `kontestan`
    ADD COLUMN `periode_id` INT NULL AFTER `id`,
    MODIFY `nomor_peserta` VARCHAR(20) NOT NULL,
    ADD COLUMN `tempat_lahir` VARCHAR(100) DEFAULT NULL,
    ADD COLUMN `tanggal_lahir` DATE DEFAULT NULL,
    ADD COLUMN `alamat` VARCHAR(255) DEFAULT NULL,
    ADD COLUMN `pendidikan` VARCHAR(150) DEFAULT NULL,
    ADD COLUMN `pekerjaan` VARCHAR(100) DEFAULT NULL,
    ADD COLUMN `status` ENUM('Aktif', 'Nonaktif') NOT NULL DEFAULT 'Aktif',
    ADD COLUMN `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ADD COLUMN `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP;

UPDATE `kontestan`
SET `periode_id` = (SELECT MIN(`id`) FROM `periode_voting`)
WHERE `periode_id` IS NULL;

ALTER TABLE `kontestan`
    MODIFY `periode_id` INT NOT NULL,
    ADD UNIQUE KEY `uq_kontestan_nomor_periode_kategori`
        (`periode_id`, `kategori`, `nomor_peserta`),
    ADD UNIQUE KEY `uq_kontestan_id_periode_kategori`
        (`id`, `periode_id`, `kategori`),
    ADD KEY `idx_kontestan_periode_status` (`periode_id`, `status`),
    ADD CONSTRAINT `fk_kontestan_periode`
        FOREIGN KEY (`periode_id`) REFERENCES `periode_voting` (`id`)
        ON UPDATE CASCADE ON DELETE RESTRICT;

ALTER TABLE `voting`
    ADD COLUMN `periode_id` INT NULL AFTER `user_id`,
    MODIFY `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;

UPDATE `voting` AS v
JOIN `kontestan` AS k ON k.`id` = v.`kontestan_id`
SET v.`periode_id` = k.`periode_id`
WHERE v.`periode_id` IS NULL;

ALTER TABLE `voting`
    MODIFY `periode_id` INT NOT NULL,
    DROP INDEX `unik_voting`,
    ADD UNIQUE KEY `uq_voting_pemilih_periode_kategori`
        (`user_id`, `periode_id`, `kategori`),
    ADD KEY `idx_voting_hasil` (`periode_id`, `kategori`, `kontestan_id`),
    ADD CONSTRAINT `fk_voting_periode`
        FOREIGN KEY (`periode_id`) REFERENCES `periode_voting` (`id`)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    ADD CONSTRAINT `fk_voting_kontestan_periode_kategori`
        FOREIGN KEY (`kontestan_id`, `periode_id`, `kategori`)
        REFERENCES `kontestan` (`id`, `periode_id`, `kategori`)
        ON UPDATE CASCADE ON DELETE RESTRICT;
