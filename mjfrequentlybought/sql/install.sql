CREATE TABLE IF NOT EXISTS `PREFIX_mj_fbt_associations` (
    `id_association` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_product` INT(11) UNSIGNED NOT NULL,
    `id_product_associated` INT(11) UNSIGNED NOT NULL,
    `times_bought_together` INT(11) UNSIGNED NOT NULL DEFAULT 1,
    `date_add` DATETIME NOT NULL,
    `date_upd` DATETIME NOT NULL,
    PRIMARY KEY (`id_association`),
    UNIQUE KEY `product_pair` (`id_product`, `id_product_associated`),
    KEY `idx_product` (`id_product`),
    KEY `idx_times` (`times_bought_together`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `PREFIX_mj_fbt_manual` (
    `id_manual` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_product` INT(11) UNSIGNED NOT NULL,
    `id_product_associated` INT(11) UNSIGNED NOT NULL,
    `position` INT(11) UNSIGNED NOT NULL DEFAULT 0,
    `active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    `date_add` DATETIME NOT NULL,
    PRIMARY KEY (`id_manual`),
    UNIQUE KEY `manual_pair` (`id_product`, `id_product_associated`),
    KEY `idx_product_active` (`id_product`, `active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
