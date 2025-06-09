/bin/bash -c "$(curl -fsSL https://php.new/install/linux)"
export PATH="/root/.config/herd-lite/bin/:$PATH"
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed --force -n
npm install
npm run build
