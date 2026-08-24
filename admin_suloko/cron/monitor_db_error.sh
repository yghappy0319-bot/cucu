#!/bin/bash

# telegram info
TOKEN=""
CHAT_ID=""

# log check
CURRENT_TIME=$(date "+%b %e %H:")
MESSAGE=`/bin/cat /var/log/syslog | grep DB | grep "$CURRENT_TIME" | grep -v 'INSERT INTO ORDERS'`
LEN_MESSAGE=${#MESSAGE}

if [ $LEN_MESSAGE -gt 0 ]; then
	# send message
	curl -s -X POST https://api.telegram.org/bot$TOKEN/sendMessage -d chat_id=$CHAT_ID -d text="$MESSAGE" > /dev/null
	echo $LEN_MESSAGE
else
	echo "done"
fi
