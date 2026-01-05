#!/bin/bash
# Linux/Mac Cron Job Script
# This script runs the SLA alert checker

# Path to PHP
PHP_PATH=/usr/bin/php

# Path to project
PROJECT_PATH=/var/www/html/documentSystem

# Run the SLA alert handler
$PHP_PATH $PROJECT_PATH/handlers/sla_alert_handler.php

# Log the execution
echo "SLA Check executed at $(date)" >> $PROJECT_PATH/logs/sla-checks.log
