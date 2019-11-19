<?php
/**
 * Needed for Apache 2.4 to handle the following requests:
 * - PUT /api/v1/gamers/key
 * - PUT /api/v1/gamers/me/agreements
 *
 * Apache's default request handler always throws a "405 Method not allowed"
 * error when a PUT request comes in.
 * This method is fine when a different content handler like PHP is used.
 *
 * Register this script as handler for put requests in the Apache vhost config: 
 *   Script PUT /empty-json.php
 */
?>
{}
