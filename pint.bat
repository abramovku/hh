@echo off
REM Laravel Pint Code Style Fixer
REM Usage: pint.bat [options]
REM Options:
REM   --test     Check without fixing
REM   --dirty    Only check uncommitted files

D:\OSPanel\modules\PHP-8.2\PHP\php.exe vendor\bin\pint %*
