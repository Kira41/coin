#!/bin/bash

for i in {1..20}
do
    php /home/admin/web/c-trade.ca/public_html/cron/cron_process_orders.php
    php /home/admin/web/c-trade.ca/public_html/cron/cron_wallet_usd.php
    sleep 3
done