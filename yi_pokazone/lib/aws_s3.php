<?php
/**
 * AWS S3 업로드 설정 — 실제 키/버킷 정보를 입력하세요.
 * (aws_s3.example.php 참고)
 */

$s3_enabled = true;
$s3_region = 'ap-northeast-2';
$s3_bucket = 'img.pokazone.com';
$s3_access_key = 'AKIA6KK4KNUMLQUDUSMD';
$s3_secret_key = 'duRq9/a9YLpC3QhDiuNjGURB0KR+ycN6gMIPrl4U';
$s3_public_base_url = 'https://img.pokazone.com';
$s3_use_for_trade = true;

/** 경매게시판 첨부(uploads/auction) 를 S3로 보낼지 여부 */
$s3_use_for_auction = true;

/** 커뮤니티 에디터 첨부(uploads/community) 를 S3로 보낼지 여부 */
$s3_use_for_community = true;
