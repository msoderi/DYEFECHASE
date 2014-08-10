#!/bin/sh

RC=1
while [ $RC -ne 0 ]; do
   php channelsd.php
   RC=$?
done

