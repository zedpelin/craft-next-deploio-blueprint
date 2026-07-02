<?php
/**
 * Web-only app config.
 *
 * yii\redis\Session does not implement getAssetBundleFlashes() and other
 * methods that craft\web\Session (and Craft's view layer) expects. Using it
 * as the session component breaks the CP on first render.
 *
 * File-based sessions (PHP default, stored in /tmp) are fine for a PoC with
 * ephemeral storage — sessions survive within a container's lifetime. Revisit
 * this for production by subclassing craft\web\Session with Redis storage.
 */
return [];
