-- 테스트용 상품만 한 번에 삭제 (이름이 [테스트] 로 시작하는 행)
DELETE FROM `tb_shop_product` WHERE `sp_name` LIKE '[테스트]%';
