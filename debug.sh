#!/bin/bash
rundir=$(dirname "$(readlink -e "$0")")
pidfile=$rundir/bot.pid
(nohup php7.0 -q bot.php -debug > debug.log) & echo $! > $pidfile &
