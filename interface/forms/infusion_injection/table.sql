CREATE TABLE IF NOT EXISTS `form_infusion_injection` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `uuid` binary(16) DEFAULT NULL,
    `date` datetime DEFAULT NULL,
    `pid` bigint(20) DEFAULT 0,
    `encounter` bigint(20) DEFAULT 0, -- Added encounter column
    `user` varchar(255) DEFAULT NULL,
    `groupname` varchar(255) DEFAULT NULL,
    `authorized` tinyint(4) DEFAULT 0,
    `activity` tinyint(4) DEFAULT 0,
    `assessment` TEXT DEFAULT NULL,
    `iv_access_type` varchar(255) DEFAULT NULL,
    `iv_access_location` varchar(255) DEFAULT NULL,
    `iv_access_blood_return` varchar(3) DEFAULT NULL, -- Yes/No
    `iv_access_needle_gauge` varchar(255) DEFAULT NULL,
    `iv_access_attempts` varchar(255) DEFAULT NULL,
    `iv_access_comments` TEXT DEFAULT NULL,
    `order_medication` varchar(255) DEFAULT NULL, -- RXCUI
    `order_dose` varchar(255) DEFAULT NULL,
    `order_lot_number` varchar(255) DEFAULT NULL,
    `order_ndc` varchar(255) DEFAULT NULL,
    `order_expiration_date` date DEFAULT NULL,
    `order_every_value` varchar(255) DEFAULT NULL, -- 1-30
    `order_every_unit` varchar(255) DEFAULT NULL, -- days, weeks, months, years
    `order_servicing_provider` varchar(255) DEFAULT 'Moussa El-hallak, M.D.',
    `order_npi` varchar(255) DEFAULT '1831381524',
    `order_note` TEXT DEFAULT NULL,
    `bp_systolic` varchar(40) DEFAULT NULL,
    `bp_diastolic` varchar(40) DEFAULT NULL,
    `pulse` varchar(40) DEFAULT NULL,
    `temperature_f` varchar(40) DEFAULT NULL,
    `oxygen_saturation` varchar(40) DEFAULT NULL,
    `administration_start` datetime DEFAULT NULL,
    `administration_end` datetime DEFAULT NULL,
    `administration_note` TEXT DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uuid` (`uuid`)
) ENGINE=InnoDB;
