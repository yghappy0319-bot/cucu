#!/bin/bash

#대상 로그 파일
logfile="/var/log/syslog"

#체크기준
chkcnt=30

hostname=`hostname -s`
timestr=""
timestrs=""


# 코엠 실패 로그수
cnt=`/bin/cat ${logfile} | grep COAM | grep RETURNCODE | wc -l`


#비율 이상으로 요청온 아이피 처리
if [ $cnt -ge $chkcnt ]
then
        #logging
        /usr/bin/logger -s -t COAMCHKER "COAM Payment fail Count:${cnt}"
        #send mail
        echo "Subject: [COAM check] ${hostname} - COAM Payment fail Count:${cnt}" | /usr/sbin/sendmail jhwoo@gtip.co.kr
fi

/usr/bin/logger -s -t COAMCHKER "COAM Payment fail count:${cnt}ea done <<<<<"
