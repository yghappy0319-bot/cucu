<?php
/**
 * AWS S3 업로드 설정 (이 파일을 aws_s3.php 로 복사한 뒤 값을 채우세요)
 *
 * IAM 사용자 권한 예: s3:PutObject, s3:DeleteObject, s3:GetObject (버킷 uploads/trade/*)
 * 버킷 정책 또는 퍼블릭 읽기 / CloudFront 중 하나로 이미지 URL 공개가 필요합니다.
 */

/** true 이면 아래 설정이 유효할 때 거래게시판 첨부를 S3에 저장합니다 */
$s3_enabled = true;

/** ap-northeast-2 (서울) 등 리전 */
$s3_region = 'ap-northeast-2';

/** S3 버킷 이름 */
$s3_bucket = 'your-bucket-name';

/** IAM Access Key / Secret Key */
$s3_access_key = '';
$s3_secret_key = '';

/**
 * 브라우저에서 이미지를 불러올 공개 URL (끝에 / 없음)
 * 예: https://img.pokazone.com  (S3/CloudFront 커스텀 도메인)
 *     https://your-bucket.s3.ap-northeast-2.amazonaws.com
 */
$s3_public_base_url = 'https://img.pokazone.com';

/** 거래게시판·거래채팅 첨부(uploads/trade) 를 S3로 보낼지 여부 */
$s3_use_for_trade = true;

/** 경매게시판 첨부(uploads/auction) 를 S3로 보낼지 여부 */
$s3_use_for_auction = true;

/** 커뮤니티 에디터 첨부(uploads/community) 를 S3로 보낼지 여부 */
$s3_use_for_community = true;

/** 1:1 문의 첨부(uploads/inquiry) 를 S3로 보낼지 여부 */
$s3_use_for_inquiry = true;
