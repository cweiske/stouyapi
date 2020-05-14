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
    if (strpos($ip, ':') !== false) {
        return mapIpv6($ip);
    }

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

/**
 * Map IPv6 addresses to their 64bit prefix, assuming that the PC and the OUYA
 * share the same prefix.
 * Map local IPs to a single IP so that this the queue can be used
 * in the home network.
 *
 * @see  RFC 6890: Special-Purpose IP Address Registries
 * @see  RFC 4291: IP Version 6 Addressing Architecture
 * @link https://en.wikipedia.org/wiki/Link-local_address
 */
function mapIpv6($ip)
{
    $ip = strtolower(expandIpv6($ip));
    if ($ip == '0000:0000:0000:0000:0000:0000:0000:0001') {
        //localhost
        return 'local';
    } else if (substr($ip, 0, 2) == 'fc' || substr($ip, 0, 2) == 'fd') {
        // fc00::/7 = Unique Local Unicast
        return 'local';
    } else if (substr($ip, 0, 3) == 'fe8'
        || substr($ip, 0, 3) == 'fe9'
        || substr($ip, 0, 3) == 'fea'
        || substr($ip, 0, 3) == 'feb'
    ) {
        // fe80::/10 = Link-Local unicast
        return 'local';
    }

    return substr($ip, 0, 19);
}

function expandIpv6($ip)
{
    $hex = unpack("H*hex", inet_pton($ip));
    return implode(':', str_split($hex['hex'], 4));
}
