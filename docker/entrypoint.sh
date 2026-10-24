#!/bin/sh
# Role-based entrypoint. Migrations are NOT run automatically: they are a
# deliberate release step (see docs/DEPLOYMENT.md).
set -eu

export PHP_FPM_MAX_CHILDREN="${PHP_FPM_MAX_CHILDREN:-20}"

as_app() {
    su-exec www-data "$@"
}

if [ "${APP_ENV:-production}" != "local" ]; then
    as_app php artisan config:cache
    as_app php artisan route:cache
    as_app php artisan view:cache
    as_app php artisan event:cache
fi

case "${ROLE:-web}" in
    web)
        # supervisord starts php-fpm (workers run as www-data) and nginx.
        exec supervisord -c /etc/supervisord.conf
        ;;
    horizon)
        exec su-exec www-data php artisan horizon
        ;;
    scheduler)
        exec su-exec www-data php artisan schedule:work
        ;;
    *)
        exec "$@"
        ;;
esac
