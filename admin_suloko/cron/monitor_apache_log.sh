#!/bin/bash

#대상 로그 파일
logfile="/var/log/apache2/access_ssl.log"

#제외 로그 (번호 자동 선택)
excloude_pattern="POST /contents/mode.php.*power.*.html|POST /contents/mode.php.*mega.*.html|/contents/cs/imgsvc.html"

#체크기준
chkrate=20      #단위 %
chkcnt=300      #단윈 건수

hostname=`hostname -s`
timestr=""
timestrs=""


# 60분간 데이터를 가져오기 위한 날짜문자열
for m in {0..60..10}
do
        timestr=`date +%d/%b/%Y:%H:%M -d -${m}minute`
        if [ "${#timestrs}" -gt 0 ]
        then
                timestrs+="|"
        fi
        timestrs+="${timestr:0:-1}"
done

# 대상 로그 파일
if [ `date +%H` -eq "00" ]
then
        targetlog="${logfile}.1 ${logfile}"
else
        targetlog="${logfile}"
fi

# 1시간 총 로그수
totalline=`/bin/cat ${targetlog} | grep -E "${timestrs}" | wc -l`
# 요청많은 아이피 top 3
ipcount=`/bin/cat ${targetlog} | grep -E "${timestrs}" | grep -vE "${excloude_pattern}" | awk '{ print $1 }' | sort | uniq -c | sort -k1nr | head -n 3 > /tmp/ipcheck_tmp.txt`

i=0
while read line
do
        cnt=`echo $line | awk '{ print $1 }'`   #로그수
        ip=`echo $line | awk '{ print $2 }'`    #아이피
        rate=`echo $totalline $line | awk '{ print int($2/$1*100 + 0.5) }'` #비율

        #비율 이상으로 요청온 아이피 처리
        if [ $rate -ge $chkrate ] && [ $cnt -ge $chkcnt ]
        then
                # 아이피 등록 여부 확인
                chk_ip=`/sbin/iptables -L -n | grep ${ip} | wc -l`
                if [ $chk_ip == 0 ] ;
                then
                        #echo $ip
                        #logging
                        /usr/bin/logger -s -t LOGCHKER "Total:${totalline}, Count:${cnt}(${rate}%), IP:${ip}"
                        #send mail
                        echo "Subject: [IP blocking!!] ${hostname} - Total:${totalline}, Count:${cnt}(${rate}%), IP:${ip}" | /usr/sbin/sendmail jhwoo@gtip.co.kr
                        #ip block
                                #차단/해제 cmd 샘플
                                #iptables -I RH-Firewall-1-INPUT -s 59.11.2.195 -p tcp --dport 443 -j DROP
                                #iptables -D RH-Firewall-1-INPUT -s 59.11.2.195 -p tcp --dport 443 -j DROP
                        /sbin/iptables -I RH-Firewall-1-INPUT -s ${ip} -j DROP
                fi
        fi
        i=$((i+1))
        #test logging
        #/usr/bin/logger -s -t LOGCHKTEST "${i}. Total:${totalline}, Count:${cnt}(${rate}%), IP:${ip}"

done < /tmp/ipcheck_tmp.txt
/usr/bin/logger -s -t LOGCHKER "Target log count(1hour):${totalline}ea done <<<<<"
