================================================================================
  SIGN-NOW — FIRST TIME SETUP & DAILY USAGE
================================================================================

REQUIREMENTS
------------
  - Docker Desktop (running)
  - Git Bash or WSL (on Windows) to run the setup script
    OR you can follow the Manual Steps below if you prefer CMD/PowerShell

SERVICES & PORTS
----------------
  App (Laravel)   http://localhost:8027
  WebSocket       ws://localhost:3027
  phpMyAdmin      http://localhost:8127
  MailHog (email) http://localhost:8227
  MySQL           localhost:3327
  Redis           localhost:6427

================================================================================
  FIRST TIME SETUP (run once only)
================================================================================

1. Open a terminal and navigate to the project folder:

     cd path/to/sign-now

2. Run the setup script (Git Bash / WSL / Mac / Linux):

     bash setup.sh

   This will automatically:
     - Build all Docker containers
     - Wait for MySQL and Redis to be healthy
     - Create a fresh Laravel project inside the container
     - Install Laravel Breeze (auth scaffolding)
     - Install FPDI + TCPDF (PDF stamping libraries)
     - Configure the .env for all Docker services
     - Fix file permissions
     - Start all 7 containers
     - Run database migrations
     - Install Node.js dependencies for the WebSocket server

3. After the script finishes, open your browser:

     http://localhost:8027

4. Register an admin account at:

     http://localhost:8027/register

   Then log in and you are ready to use the app.

--------------------------------------------------------------------------------
  IF YOU CANNOT RUN THE BASH SCRIPT (Windows CMD / PowerShell)
--------------------------------------------------------------------------------

Run these commands one by one in order:

  Step 1 — Build and start MySQL + Redis first (they need to be healthy first):
    docker-compose up -d --build mysql redis

  Step 2 — Wait about 20 seconds, then create the Laravel project:
    docker-compose run --rm --no-deps app bash -c "composer create-project laravel/laravel /tmp/lv --prefer-dist && cp -a /tmp/lv/. /var/www/html/ && rm -rf /tmp/lv"

  Step 3 — Install Laravel Breeze:
    docker-compose run --rm --no-deps app bash -c "composer require laravel/breeze --dev && php artisan breeze:install blade"

  Step 4 — Install PDF libraries:
    docker-compose run --rm --no-deps app bash -c "composer require setasign/fpdi tecnickcom/tcpdf"

  Step 5 — Configure .env (run inside container):
    docker-compose run --rm --no-deps app bash -c "sed -i 's/DB_CONNECTION=.*/DB_CONNECTION=mysql/' .env && sed -i 's/DB_HOST=.*/DB_HOST=mysql/' .env && sed -i 's/DB_PORT=.*/DB_PORT=3306/' .env && sed -i 's/DB_DATABASE=.*/DB_DATABASE=signnow/' .env && sed -i 's/DB_USERNAME=.*/DB_USERNAME=signnow/' .env && sed -i 's/DB_PASSWORD=.*/DB_PASSWORD=secret/' .env && sed -i 's/REDIS_HOST=.*/REDIS_HOST=redis/' .env && sed -i 's/REDIS_PORT=.*/REDIS_PORT=6379/' .env && sed -i 's/MAIL_MAILER=.*/MAIL_MAILER=smtp/' .env && sed -i 's/MAIL_HOST=.*/MAIL_HOST=mailhog/' .env && sed -i 's/MAIL_PORT=.*/MAIL_PORT=1025/' .env && echo 'WEBSOCKET_URL=ws://localhost:3027' >> .env"

  Step 6 — Fix permissions:
    docker-compose run --rm --no-deps app bash -c "chmod -R 775 storage bootstrap/cache && chown -R www-data:www-data storage bootstrap/cache"

  Step 7 — Start all containers:
    docker-compose up -d --build

  Step 8 — Run migrations:
    docker-compose exec app php artisan migrate

  Step 9 — Install WebSocket dependencies:
    docker-compose exec node npm install

================================================================================
  DAILY USAGE (already set up, just starting/stopping)
================================================================================

  Start all containers:
    docker-compose up -d

  Stop all containers:
    docker-compose down

  View running containers:
    docker-compose ps

================================================================================
  USEFUL COMMANDS
================================================================================

  Run migrations after pulling new changes:
    docker-compose exec app php artisan migrate

  Clear all caches:
    docker-compose exec app php artisan optimize:clear

  View Laravel logs (live):
    docker-compose exec app tail -f storage/logs/laravel.log

  View Node.js WebSocket logs:
    docker-compose logs -f node

  Open a shell inside the PHP container:
    docker-compose exec app bash

  Open MySQL CLI:
    docker-compose exec mysql mysql -u signnow -psecret signnow

  Seed demo data (optional, dev only):
    docker-compose exec app php artisan db:seed --class=DemoDocumentSeeder

  Rebuild a single container (e.g. after Dockerfile change):
    docker-compose up -d --build app
    docker-compose up -d --build node

================================================================================
  RESET EVERYTHING (wipe database and start fresh)
================================================================================

  WARNING: This deletes all data including uploaded documents.

    docker-compose down -v
    docker-compose up -d --build
    docker-compose exec app php artisan migrate

================================================================================
  CREDENTIALS (default dev values)
================================================================================

  MySQL database : signnow
  MySQL user     : signnow
  MySQL password : secret
  MySQL root pw  : rootsecret

  phpMyAdmin     : http://localhost:8127
                   (auto-logged in as signnow)

  MailHog inbox  : http://localhost:8227
                   (catches all outgoing emails — no real mail is sent)

================================================================================
  TROUBLESHOOTING
================================================================================

  "Port already in use" error
  ----------------------------
    Another service on your machine is using one of the ports (8027, 3027, etc.).
    Stop that service, or edit docker-compose.yml to change the left-hand port
    number (e.g. "9027:80" instead of "8027:80").

  "Cannot connect to MySQL" during setup
  ----------------------------------------
    MySQL takes 10-20 seconds to initialise on first boot. The setup script
    waits for it automatically. If running manually, just wait and retry.

  App shows "500 Internal Server Error"
  ----------------------------------------
    Run:  docker-compose exec app php artisan optimize:clear
    Then: docker-compose exec app php artisan key:generate
    Check logs: docker-compose exec app tail -50 storage/logs/laravel.log

  Containers exit immediately on start
  ----------------------------------------
    Check logs:  docker-compose logs app
    Likely cause: storage/ folder is not writable. Run:
      docker-compose exec app chmod -R 775 storage bootstrap/cache

  WebSocket not connecting on dashboard
  ----------------------------------------
    Check node container is running:  docker-compose ps
    View node logs:  docker-compose logs -f node
    Confirm Redis is healthy:  docker-compose exec redis redis-cli ping
    Expected response: PONG

================================================================================
