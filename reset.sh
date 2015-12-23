docker ps -a | awk '{print $1}' | xargs docker rm
sudo find storage/audfprint/media_cache/ -not -name ".gitkeep" -print0 | xargs -0 rm
sudo rm -fr storage/audfprint/pklz_cache/*
sudo find storage/audfprint/afpt_cache/ -not -name ".gitkeep" -print0 | xargs -0 rm
sudo rm storage/audfprint/flocks/*
php artisan migrate:refresh
