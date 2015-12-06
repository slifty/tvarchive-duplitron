sudo rm storage/audfprint/media_cache/*
sudo rm -fr storage/audfprint/pklz_cache/*
sudo rm storage/audfprint/afpt_cache/*
sudo rm storage/audfprint/flocks/*
php artisan migrate:refresh
