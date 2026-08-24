#!/bin/bash
# wizzpay 결제실패 (3103) 오류 발생수 모니터링

# 대상 로그 파일
logfile="/var/log/syslog"

# 발생 로그 수 확인
err_cnt=`/usr/bin/tail -300 ${logfile} | grep RES_DATA | grep WIZZPAY | grep '"result_code":"3103"' | wc -l`

if [ $err_cnt -gt 0 ]; then
	/usr/bin/php /home/ubuntu/suloko-web/cron/send-sms-alert.php 0 $err_cnt
	/usr/bin/logger -s -t "[WIZZPAY-FAILED]" "payment failed - ${err_cnt}"
else
	/usr/bin/logger -s -t "[WIZZPAY]" "fail check done"
fi
