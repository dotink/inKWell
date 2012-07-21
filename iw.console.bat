@echo off
set IW_PHP_CMD=php
%IW_PHP_CMD% -q -d register_globals=0 -d magic_quotes_gpc=0 -d short_open_tag=0 -d asp_tags=1 -d display_errors=1 .console %1 %2 %3 %4 %5
