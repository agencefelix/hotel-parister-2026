<?php

declare(strict_types=1);

namespace Maintenance;

/**
 * Gate.
 *
 * Portail de maintenance evalue avant le boot du Kernel : ne depend ni de Symfony,
 * ni de la base de donnees, et reste donc operationnel quand l'application ne l'est pas.
 *
 * @author Sébastien FOURNIER <fournier.sebastien@outlook.com>
 */
final class Gate
{
    /** Duree indiquee aux clients et aux robots avant de retenter la requete (secondes). */
    private const int RETRY_AFTER = 3600;

    /** Prefixe binaire des adresses IPv6 mappant une IPv4 (::ffff:0:0/96). */
    private const string V4_MAPPED_PREFIX = "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\xff\xff";

    /**
     * Verifie si le visiteur courant peut contourner la maintenance.
     *
     * @param string[] $allowedIps      IPs ou plages CIDR autorisees (IPv4 et IPv6)
     * @param string[] $trustedProxies  IPs ou plages CIDR des proxies dont l'entete X-Forwarded-For est fiable
     */
    public static function isAllowedIp(array $allowedIps, array $trustedProxies = []): bool
    {
        if ([] === $allowedIps) {
            return false;
        }

        $clientIp = self::clientIp($trustedProxies);

        return '' !== $clientIp && self::matches($clientIp, $allowedIps);
    }

    /**
     * Envoie la page de maintenance puis interrompt la requete.
     */
    public static function render(): never
    {
        if (!headers_sent()) {
            http_response_code(503);
            header('Retry-After: '.self::RETRY_AFTER);
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('X-Robots-Tag: noindex, nofollow');
            header('Content-Type: text/html; charset=UTF-8');
        }

        require __DIR__.'/page.php';

        exit;
    }

    /**
     * Resout l'IP du visiteur.
     *
     * X-Forwarded-For n'est lu que si la requete provient d'un proxy declare fiable,
     * sans quoi n'importe quel visiteur pourrait forger l'entete pour contourner la maintenance.
     *
     * @param string[] $trustedProxies
     */
    private static function clientIp(array $trustedProxies): string
    {
        $remoteAddr = self::normalize(isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '');

        if ('' === $remoteAddr || [] === $trustedProxies || !self::matches($remoteAddr, $trustedProxies)) {
            return $remoteAddr;
        }

        $forwarded = isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? (string) $_SERVER['HTTP_X_FORWARDED_FOR'] : '';

        // La chaine est parcourue de droite a gauche : la premiere adresse hors proxies fiables est le client reel.
        foreach (array_reverse(explode(',', $forwarded)) as $candidate) {
            $candidate = self::normalize($candidate);
            if ('' !== $candidate && !self::matches($candidate, $trustedProxies)) {
                return $candidate;
            }
        }

        return $remoteAddr;
    }

    /**
     * Compare une IP a une liste d'IPs ou de plages CIDR.
     *
     * @param string[] $patterns
     */
    private static function matches(string $ip, array $patterns): bool
    {
        $binaryIp = self::toBinary($ip);
        if (null === $binaryIp) {
            return false;
        }

        foreach ($patterns as $pattern) {
            $pattern = trim($pattern);
            if ('' === $pattern) {
                continue;
            }

            [$subnet, $bits] = array_pad(explode('/', $pattern, 2), 2, null);
            $binarySubnet = self::toBinary(self::normalize((string) $subnet));

            if (null === $binarySubnet || strlen($binaryIp) !== strlen($binarySubnet)) {
                continue;
            }

            $prefix = null === $bits ? 8 * strlen($binarySubnet) : (int) $bits;
            if ($prefix < 0 || $prefix > 8 * strlen($binarySubnet)) {
                continue;
            }

            if (self::inSubnet($binaryIp, $binarySubnet, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Compare les $prefix premiers bits de deux adresses binaires.
     */
    private static function inSubnet(string $binaryIp, string $binarySubnet, int $prefix): bool
    {
        $fullBytes = intdiv($prefix, 8);
        if ($fullBytes > 0 && 0 !== substr_compare($binaryIp, substr($binarySubnet, 0, $fullBytes), 0, $fullBytes)) {
            return false;
        }

        $remainingBits = $prefix % 8;
        if (0 === $remainingBits) {
            return true;
        }

        $mask = ~((1 << (8 - $remainingBits)) - 1) & 0xFF;

        return (ord($binaryIp[$fullBytes]) & $mask) === (ord($binarySubnet[$fullBytes]) & $mask);
    }

    /**
     * Convertit une IP en representation binaire, les IPv4 mappees en IPv6 etant ramenees sur 4 octets.
     */
    private static function toBinary(string $ip): ?string
    {
        $binary = @inet_pton($ip);
        if (false === $binary) {
            return null;
        }

        if (16 === strlen($binary) && str_starts_with($binary, self::V4_MAPPED_PREFIX)) {
            return substr($binary, 12);
        }

        return $binary;
    }

    /**
     * Nettoie une valeur d'IP : espaces, notation [IPv6], port eventuel et zone d'interface.
     */
    private static function normalize(string $ip): string
    {
        $ip = strtolower(trim($ip));

        if (preg_match('/^\[(?<ip>.+)](?::\d+)?$/', $ip, $matches)) {
            $ip = $matches['ip'];
        } elseif (preg_match('/^(?<ip>\d{1,3}(?:\.\d{1,3}){3}):\d+$/', $ip, $matches)) {
            $ip = $matches['ip'];
        }

        $zone = strpos($ip, '%');

        return false !== $zone ? substr($ip, 0, $zone) : $ip;
    }
}
