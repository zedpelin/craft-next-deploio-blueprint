# Deploio / Heroku-style PHP buildpack.
#
# web: runs migrations then starts Apache.
#   migrate/all is a fast no-op when nothing is pending, so it's safe to run
#   on every start. The web pod has full DB service mesh access; the Deploio
#   deploy job pod does not, which is why migrations live here instead.

web: php craft migrate/all --interactive=0 && heroku-php-apache2 web/
