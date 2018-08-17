#!/bin/bash
rundir=$(dirname "$(readlink -e "$0")")
pidfile=$rundir/bot.pid
(nohup php -d xdebug.profiler_enable=1 -d xdebug.trace_output_dir="$rundir/debug" -d xdebug.profiler_output_dir="$rundir/debug" -q bot.php -debug > debug.log) & echo $! > $pidfile &
