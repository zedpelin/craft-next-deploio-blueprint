# Deploio / Heroku-style PHP buildpack.
#
# web: runs Craft's all-in-one up command then starts Apache.
#   craft up handles pending migrations and project-config changes in one call.
#   Using ; (not &&) so Apache always starts — on first deploy with an empty
#   database, up exits non-zero and the app returns errors until you run:
#     nctl exec app craft-backend -- php craft install/craft
#
#   The web pod has full DB service mesh access; the Deploio deploy job pod
#   does not, which is why DB operations live here instead of in a deploy job.

web: php craft up --interactive=0; heroku-php-apache2 web/
