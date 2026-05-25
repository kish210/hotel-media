#!/bin/bash
# SignageCMS Patch v1.1.1 — fix paginate() method
echo "Applying patch v1.1.1..."

docker cp app/Core/Database.php  signage_php:/var/www/html/app/Core/Database.php
docker cp app/Core/Response.php  signage_php:/var/www/html/app/Core/Response.php

echo "✅ Patch applied!"
echo "Refresh http://localhost to verify"
