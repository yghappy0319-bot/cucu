ALTER TABLE `tb_draw_product`
    ADD COLUMN `dp_pack_type` varchar(20) NOT NULL DEFAULT 'expansion' AFTER `dp_code`,
    ADD COLUMN `dp_pack_count` tinyint unsigned NOT NULL DEFAULT '30' AFTER `dp_pack_type`;
