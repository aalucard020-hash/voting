CREATE DATABASE IF NOT EXISTS `db_voting_kakang_senduk`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `db_voting_kakang_senduk`;

CREATE TABLE IF NOT EXISTS `users` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `nama` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) NOT NULL,
    `no_hp` VARCHAR(20) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('admin', 'pemilih') NOT NULL DEFAULT 'pemilih',
    `status` ENUM('Aktif', 'Nonaktif') NOT NULL DEFAULT 'Aktif',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_email` (`email`),
    KEY `idx_users_role_status` (`role`, `status`)
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `periode_voting` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `nama_periode` VARCHAR(150) NOT NULL,
    `tanggal_mulai` DATETIME NOT NULL,
    `tanggal_selesai` DATETIME NOT NULL,
    `status` ENUM('Persiapan', 'Aktif', 'Selesai', 'Nonaktif')
        NOT NULL DEFAULT 'Persiapan',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_periode_status_waktu` (`status`, `tanggal_mulai`, `tanggal_selesai`)
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `kontestan` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `periode_id` INT NOT NULL,
    `nomor_peserta` VARCHAR(20) NOT NULL,
    `nama` VARCHAR(100) NOT NULL,
    `kategori` ENUM('Kakang', 'Senduk') NOT NULL,
    `foto` VARCHAR(255) DEFAULT NULL,
    `asal_instansi` VARCHAR(150) DEFAULT NULL,
    `tempat_lahir` VARCHAR(100) DEFAULT NULL,
    `tanggal_lahir` DATE DEFAULT NULL,
    `alamat` VARCHAR(255) DEFAULT NULL,
    `pendidikan` VARCHAR(150) DEFAULT NULL,
    `pekerjaan` VARCHAR(100) DEFAULT NULL,
    `visi` TEXT DEFAULT NULL,
    `misi` TEXT DEFAULT NULL,
    `deskripsi` TEXT DEFAULT NULL,
    `status` ENUM('Aktif', 'Nonaktif') NOT NULL DEFAULT 'Aktif',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_kontestan_nomor_periode_kategori`
        (`periode_id`, `kategori`, `nomor_peserta`),
    UNIQUE KEY `uq_kontestan_id_periode_kategori`
        (`id`, `periode_id`, `kategori`),
    KEY `idx_kontestan_periode_status` (`periode_id`, `status`),
    CONSTRAINT `fk_kontestan_periode`
        FOREIGN KEY (`periode_id`) REFERENCES `periode_voting` (`id`)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `voting` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `periode_id` INT NOT NULL,
    `kontestan_id` INT NOT NULL,
    `kategori` ENUM('Kakang', 'Senduk') NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_voting_pemilih_periode_kategori`
        (`user_id`, `periode_id`, `kategori`),
    KEY `idx_voting_hasil` (`periode_id`, `kategori`, `kontestan_id`),
    CONSTRAINT `fk_voting_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_voting_periode`
        FOREIGN KEY (`periode_id`) REFERENCES `periode_voting` (`id`)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_voting_kontestan_periode_kategori`
        FOREIGN KEY (`kontestan_id`, `periode_id`, `kategori`)
        REFERENCES `kontestan` (`id`, `periode_id`, `kategori`)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
