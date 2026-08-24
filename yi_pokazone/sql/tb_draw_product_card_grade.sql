ALTER TABLE `tb_draw_product_card`
    ADD COLUMN `dpc_grade` varchar(10) NOT NULL DEFAULT '' AFTER `dpc_orig_name`,
    ADD KEY `idx_dp_grade` (`dp_idx`, `dpc_grade`, `dpc_idx`);
