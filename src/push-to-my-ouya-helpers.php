<?php
/**
 * Helper methods for the push-to-my-ouya download queue
 */

/**
 * Map local IPs to a single IP so that this the queue can be used
 * in the home network.
 *
 * @see RFC 3330: Special-Use IPv4 Addresses
 */
function mapIp($ip)
{
    $private = substr($ip, 0, 3) == '10.'
        || substr($ip, 0, 7) == '172.16.'
        || substr($ip, 0, 7) == '172.17.'
        || substr($ip, 0, 7) == '172.18.'
        || substr($ip, 0, 7) == '172.19.'
        || substr($ip, 0, 7) == '172.20.'
        || substr($ip, 0, 7) == '172.21.'
        || substr($ip, 0, 7) == '172.22.'
        || substr($ip, 0, 7) == '172.23.'
        || substr($ip, 0, 7) == '172.24.'
        || substr($ip, 0, 7) == '172.25.'
        || substr($ip, 0, 7) == '172.26.'
        || substr($ip, 0, 7) == '172.27.'
        || substr($ip, 0, 7) == '172.28.'
        || substr($ip, 0, 7) == '172.29.'
        || substr($ip, 0, 7) == '172.30.'
        || substr($ip, 0, 7) == '172.31.'
        || substr($ip, 0, 8) == '192.168.'
        || substr($ip, 0, 8) == '169.254.';
    $local = substr($ip, 0, 4) == '127.';

    if ($private || $local) {
        return 'local';
    }
    return $ip;
}
