@echo off
REM Windows Task Scheduler Batch File
REM This script runs the SLA alert checker every 15 minutes

REM Set the path to your PHP executable
SET PHP_PATH=C:\xampp\php\php.exe

REM Set the path to your project
SET PROJECT_PATH=C:\xampp\htdocs\documentSystem

REM Run the SLA alert handler
"%PHP_PATH%" "%PROJECT_PATH%\handlers\sla_alert_handler.php"

REM Log the execution
echo SLA Check executed at %date% %time% >> "%PROJECT_PATH%\logs\sla-checks.log"
