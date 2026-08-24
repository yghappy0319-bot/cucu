ALTER TABLE `tb_draw_product_card`
    ADD COLUMN `dpc_orig_name` varchar(255) NOT NULL DEFAULT '' AFTER `dp_idx`,
    ADD KEY `idx_dp_idx_orig` (`dp_idx`,`dpc_orig_name`,`dpc_idx`);
