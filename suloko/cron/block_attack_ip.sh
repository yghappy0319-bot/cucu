#!/bin/bash

# 공격 아이피 리스트 가져오기
ip_list=`echo "select IP from ATTACK_IP limit 1000" | mysql --login-path=super --database=super -N`

for ip in $ip_list ;
do
	# 아이피 등록 여부 확인
	chk_ip=`/sbin/iptables -L -n | grep ${ip} | wc -l`
	if [ $chk_ip == 0 ] ;
	then
		# echo ">>>>>" $ip
		# 공격 아이피 차단 등록
		#/sbin/iptables -I RH-Firewall-1-INPUT -s $ip -j DROP
		/sbin/iptables -A INPUT -s $ip -j DROP
		/usr/bin/logger -s -t LOGCHKER "[BLOCK-ATTACK-IP] ${ip} drop"
	fi
done
