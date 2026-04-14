<?php
/**
 * YNJ_Push — Web Push notification system using VAPID (RFC 8292) and
 * payload encryption per RFC 8291 (aes128gcm).
 *
 * @package YourJannah
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class YNJ_Push {

    /* ------------------------------------------------------------------ */
    /*  VAPID key management                                              */
    /* ------------------------------------------------------------------ */

    /**
     * Generate an ECDSA P-256 key pair and store in wp_options.
     * Safe to call repeatedly — only writes once.
     *
     * @return bool True if keys were generated, false if they already exist.
     */
    public static function generate_vapid_keys(): bool {
        if ( get_option( 'ynj_vapid_public' ) && get_option( 'ynj_vapid_private' ) ) {
            return false;
        }

        $key = openssl_pkey_new( [
            'curve_name'       => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ] );

        if ( ! $key ) {
            error_log( '[YNJ_Push] Failed to generate ECDSA key pair.' );
            return false;
        }

        $details = openssl_pkey_get_details( $key );
        if ( ! $details || empty( $details['ec'] ) ) {
            error_log( '[YNJ_Push] Failed to extract EC key details.' );
            return false;
        }

        // Uncompressed public key: 0x04 || x || y  (65 bytes).
        $x   = str_pad( $details['ec']['x'], 32, "\0", STR_PAD_LEFT );
        $y   = str_pad( $details['ec']['y'], 32, "\0", STR_PAD_LEFT );
        $pub = "\x04" . $x . $y;

        // Private key scalar d (32 bytes).
        $priv = str_pad( $details['ec']['d'], 32, "\0", STR_PAD_LEFT );

        // Store as URL-safe base64 (no padding).
        update_option( 'ynj_vapid_public', self::base64url_encode( $pub ), false );
        update_option( 'ynj_vapid_private', self::base64url_encode( $priv ), false );

        // Export the full PEM private key for JWT signing.
        openssl_pkey_export( $key, $pem );
        update_option( 'ynj_vapid_private_pem', $pem, false );

        return true;
    }

    /**
     * Return the VAPID application server public key (URL-safe base64).
     */
    public static function get_public_key(): string {
        return (string) get_option( 'ynj_vapid_public', '' );
    }

    /* ------------------------------------------------------------------ */
    /*  Sending to a mosque audience                                      */
    /* ------------------------------------------------------------------ */

    /**
     * Send a push notification to every active subscriber of a mosque.
     *
     * @param int    $mosque_id Mosque post/row ID.
     * @param string $title     Notification title.
     * @param string $body      Notification body text.
     * @param string $url       URL to open on click.
     *
     * @return array{sent:int, failed:int, errors:string[]}
     */
    public static function send_to_mosque( int $mosque_id, string $title, string $body, string $url = '/' ): array {
        global $wpdb;

        $table = $wpdb->prefix . 'ynj_subscribers';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $subscribers = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT push_endpoint, push_p256dh, push_auth
                 FROM {$table}
                 WHERE mosque_id = %d
                   AND status     = 'active'
                   AND push_endpoint != ''",
                $mosque_id
            )
        );

        $result = [
            'sent'   => 0,
            'failed' => 0,
            'errors' => [],
        ];

        if ( empty( $subscribers ) ) {
            return $result;
        }

        $payload = wp_json_encode( [
            'title' => $title,
            'body'  => $body,
            'icon'  => '/wp-content/plugins/yn-jannah/assets/icons/icon-192.png',
            'url'   => $url,
        ] );

        foreach ( $subscribers as $sub ) {
            $ok = self::send_push(
                $sub->push_endpoint,
                $sub->push_p256dh,
                $sub->push_auth,
                $payload
            );

            if ( $ok ) {
                $result['sent']++;
            } else {
                $result['failed']++;
                $result['errors'][] = $sub->push_endpoint;
            }
        }

        return $result;
    }

    /* ------------------------------------------------------------------ */
    /*  Single push delivery                                              */
    /* ------------------------------------------------------------------ */

    /**
     * Send a single Web Push notification.
     *
     * @param string $endpoint Push subscription endpoint URL.
     * @param string $p256dh   Client public key (URL-safe base64).
     * @param string $auth     Client auth secret (URL-safe base64).
     * @param string $payload  JSON payload string.
     *
     * @return bool True on HTTP 201, false on any failure.
     */
    public static function send_push( string $endpoint, string $p256dh, string $auth, string $payload ): bool {
        if ( empty( $endpoint ) || empty( $p256dh ) || empty( $auth ) ) {
            return false;
        }

        $encrypted = self::encrypt_payload( $payload, $p256dh, $auth );
        if ( false === $encrypted ) {
            error_log( '[YNJ_Push] Payload encryption failed for ' . $endpoint );
            return false;
        }

        $jwt    = self::create_jwt( $endpoint );
        $vapid  = self::get_public_key();

        $response = wp_remote_post( $endpoint, [
            'timeout' => 15,
            'headers' => [
                'Content-Type'     => 'application/octet-stream',
                'Content-Encoding' => 'aes128gcm',
                'Content-Length'   => strlen( $encrypted ),
                'TTL'              => '86400',
                'Authorization'    => 'vapid t=' . $jwt . ', k=' . $vapid,
            ],
            'body' => $encrypted,
        ] );

        if ( is_wp_error( $response ) ) {
            error_log( '[YNJ_Push] wp_remote_post error: ' . $response->get_error_message() );
            return false;
        }

        $code = wp_remote_retrieve_response_code( $response );

        // 201 Created = success. 410 Gone = subscription expired → mark inactive.
        if ( 410 === $code || 404 === $code ) {
            self::deactivate_subscription( $endpoint );
            return false;
        }

        return ( 201 === $code );
    }

    /* ------------------------------------------------------------------ */
    /*  VAPID JWT (ES256)                                                 */
    /* ------------------------------------------------------------------ */

    /**
     * Create a VAPID JWT for the given push endpoint.
     *
     * @param string $endpoint Push service URL.
     * @return string Signed JWT (compact serialisation).
     */
    public static function create_jwt( string $endpoint ): string {
        $parsed = wp_parse_url( $endpoint );
        $aud    = $parsed['scheme'] . '://' . $parsed['host'];

        $header = self::base64url_encode( wp_json_encode( [
            'typ' => 'JWT',
            'alg' => 'ES256',
        ] ) );

        $claims = self::base64url_encode( wp_json_encode( [
            'aud' => $aud,
            'exp' => time() + 43200, // 12 hours.
            'sub' => 'mailto:noreply@yourjannah.com',
        ] ) );

        $signing_input = $header . '.' . $claims;

        $pem = get_option( 'ynj_vapid_private_pem', '' );
        if ( empty( $pem ) ) {
            error_log( '[YNJ_Push] VAPID private PEM not found. Run generate_vapid_keys() first.' );
            return '';
        }

        $key = openssl_pkey_get_private( $pem );
        if ( ! $key ) {
            error_log( '[YNJ_Push] Failed to load VAPID private key.' );
            return '';
        }

        $signature = '';
        if ( ! openssl_sign( $signing_input, $signature, $key, OPENSSL_ALGO_SHA256 ) ) {
            error_log( '[YNJ_Push] JWT signing failed.' );
            return '';
        }

        // openssl_sign returns DER-encoded ASN.1 for ECDSA. We need raw r||s (64 bytes).
        $raw_sig = self::der_to_raw( $signature );

        return $signing_input . '.' . self::base64url_encode( $raw_sig );
    }

    /* ------------------------------------------------------------------ */
    /*  RFC 8291 payload encryption (aes128gcm)                           */
    /* ------------------------------------------------------------------ */

    /**
     * Encrypt a Web Push payload per RFC 8291 using aes128gcm content encoding.
     *
     * @param string $payload Raw payload string (e.g. JSON).
     * @param string $p256dh  Subscriber's public key (URL-safe base64).
     * @param string $auth    Subscriber's auth secret (URL-safe base64).
     *
     * @return string|false Encrypted binary on success, false on failure.
     */
    public static function encrypt_payload( string $payload, string $p256dh, string $auth ) {
        // Decode subscriber keys.
        $ua_public  = self::base64url_decode( $p256dh );   // 65 bytes uncompressed point.
        $auth_secret = self::base64url_decode( $auth );     // 16 bytes.

        if ( strlen( $ua_public ) !== 65 || strlen( $auth_secret ) !== 16 ) {
            error_log( '[YNJ_Push] Invalid subscriber key lengths: p256dh=' . strlen( $ua_public ) . ' auth=' . strlen( $auth_secret ) );
            return false;
        }

        // Generate ephemeral ECDH key pair.
        $local_key = openssl_pkey_new( [
            'curve_name'       => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ] );

        if ( ! $local_key ) {
            return false;
        }

        $local_details = openssl_pkey_get_details( $local_key );
        $lx = str_pad( $local_details['ec']['x'], 32, "\0", STR_PAD_LEFT );
        $ly = str_pad( $local_details['ec']['y'], 32, "\0", STR_PAD_LEFT );
        $local_public = "\x04" . $lx . $ly;

        // ECDH shared secret via openssl.
        // We need to derive a shared secret between our ephemeral private key
        // and the subscriber's public key. PHP 8.1+ has openssl for this via
        // openssl_pkey_derive() if we can reconstruct a public key resource.
        $shared_secret = self::ecdh_agree( $local_key, $ua_public );
        if ( false === $shared_secret ) {
            error_log( '[YNJ_Push] ECDH key agreement failed.' );
            return false;
        }

        // --- RFC 8291 Section 3.4: Key derivation ---

        // IKM for auth HKDF: ecdh_secret
        // salt for auth HKDF: auth_secret
        // info for auth HKDF: "WebPush: info\0" || ua_public || local_public
        $info_auth = "WebPush: info\x00" . $ua_public . $local_public;
        $ikm       = self::hkdf( $auth_secret, $shared_secret, $info_auth, 32 );

        // Generate 16-byte random salt.
        $salt = random_bytes( 16 );

        // Derive Content Encryption Key (CEK) and nonce.
        // CEK: HKDF(salt, ikm, "Content-Encoding: aes128gcm\0", 16)
        $cek_info = "Content-Encoding: aes128gcm\x00";
        $cek      = self::hkdf( $salt, $ikm, $cek_info, 16 );

        // Nonce: HKDF(salt, ikm, "Content-Encoding: nonce\0", 12)
        $nonce_info = "Content-Encoding: nonce\x00";
        $nonce      = self::hkdf( $salt, $ikm, $nonce_info, 12 );

        // --- Encryption ---
        // Pad the payload: content || \x02 (delimiter for last record).
        $padded = $payload . "\x02";

        $encrypted = openssl_encrypt(
            $padded,
            'aes-128-gcm',
            $cek,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            '',
            16
        );

        if ( false === $encrypted ) {
            error_log( '[YNJ_Push] AES-128-GCM encryption failed.' );
            return false;
        }

        $ciphertext = $encrypted . $tag;

        // --- Build aes128gcm header ---
        // salt (16) || rs (4, uint32 record size) || idlen (1) || keyid (65, our public key)
        $rs     = pack( 'N', strlen( $ciphertext ) + 86 ); // record size = header + ciphertext.
        $idlen  = chr( 65 );
        $header = $salt . $rs . $idlen . $local_public;

        return $header . $ciphertext;
    }

    /* ------------------------------------------------------------------ */
    /*  Crypto helpers                                                    */
    /* ------------------------------------------------------------------ */

    /**
     * Perform ECDH key agreement between a local private key and a remote
     * uncompressed public key.
     *
     * @param OpenSSLAsymmetricKey $local_private Local EC private key resource.
     * @param string               $remote_public 65-byte uncompressed public key.
     *
     * @return string|false 32-byte shared secret, or false.
     */
    private static function ecdh_agree( $local_private, string $remote_public ) {
        // Build a PEM for the remote public key so we can use openssl_pkey_derive().
        $pem = self::ec_public_key_to_pem( $remote_public );
        if ( ! $pem ) {
            return false;
        }

        $remote_key = openssl_pkey_get_public( $pem );
        if ( ! $remote_key ) {
            error_log( '[YNJ_Push] Failed to load remote public key PEM.' );
            return false;
        }

        $shared = openssl_pkey_derive( $remote_key, $local_private, 32 );
        return ( false === $shared ) ? false : $shared;
    }

    /**
     * Convert a raw 65-byte uncompressed EC public key to PEM format.
     *
     * Wraps the raw point in an ASN.1 SubjectPublicKeyInfo structure for
     * the prime256v1 (P-256) curve.
     *
     * @param string $raw_key 65-byte uncompressed point (0x04 || x || y).
     * @return string|false PEM string or false.
     */
    private static function ec_public_key_to_pem( string $raw_key ) {
        if ( strlen( $raw_key ) !== 65 || $raw_key[0] !== "\x04" ) {
            return false;
        }

        // ASN.1 DER prefix for a P-256 SubjectPublicKeyInfo wrapping a 65-byte
        // BIT STRING (with 0x00 unused-bits byte = 66 bytes total for the BIT STRING value).
        // SEQUENCE {
        //   SEQUENCE { OID ecPublicKey, OID prime256v1 }
        //   BIT STRING (0 unused bits, 65 bytes of key data)
        // }
        $der_prefix = hex2bin(
            '3059'           // SEQUENCE (89 bytes)
            . '3013'         // SEQUENCE (19 bytes)
            . '0607'         // OID (7 bytes) — ecPublicKey 1.2.840.10045.2.1
            . '2a8648ce3d0201'
            . '0608'         // OID (8 bytes) — prime256v1 1.2.840.10045.3.1.7
            . '2a8648ce3d030107'
            . '0342'         // BIT STRING (66 bytes)
            . '00'           // 0 unused bits
        );

        $der = $der_prefix . $raw_key;
        $b64 = chunk_split( base64_encode( $der ), 64, "\n" );

        return "-----BEGIN PUBLIC KEY-----\n" . $b64 . "-----END PUBLIC KEY-----\n";
    }

    /**
     * HKDF (HMAC-based Key Derivation Function) — extract-then-expand.
     *
     * @param string $salt Salt value.
     * @param string $ikm  Input keying material.
     * @param string $info Context/application-specific info.
     * @param int    $len  Output length in bytes.
     *
     * @return string Derived key material.
     */
    private static function hkdf( string $salt, string $ikm, string $info, int $len ): string {
        // PHP 7.1.2+ has hash_hkdf().
        if ( function_exists( 'hash_hkdf' ) ) {
            return hash_hkdf( 'sha256', $ikm, $len, $info, $salt );
        }

        // Manual fallback.
        $prk = hash_hmac( 'sha256', $ikm, $salt, true );
        $t   = '';
        $out = '';
        for ( $i = 1; strlen( $out ) < $len; $i++ ) {
            $t    = hash_hmac( 'sha256', $t . $info . chr( $i ), $prk, true );
            $out .= $t;
        }

        return substr( $out, 0, $len );
    }

    /**
     * Convert a DER-encoded ECDSA signature to raw r||s format (64 bytes).
     *
     * @param string $der DER-encoded ASN.1 signature.
     * @return string 64-byte raw signature (r || s, each 32 bytes zero-padded).
     */
    private static function der_to_raw( string $der ): string {
        $pos = 0;

        // SEQUENCE tag (0x30).
        $pos++; // skip 0x30.
        $seq_len = ord( $der[ $pos++ ] );
        if ( $seq_len > 127 ) {
            $pos++; // long form (unlikely for P-256 but handle gracefully).
        }

        // First INTEGER (r).
        $pos++; // skip 0x02 tag.
        $r_len = ord( $der[ $pos++ ] );
        $r     = substr( $der, $pos, $r_len );
        $pos  += $r_len;

        // Second INTEGER (s).
        $pos++; // skip 0x02 tag.
        $s_len = ord( $der[ $pos++ ] );
        $s     = substr( $der, $pos, $s_len );

        // Remove leading zero bytes (ASN.1 sign padding) and pad to 32.
        $r = ltrim( $r, "\x00" );
        $s = ltrim( $s, "\x00" );

        return str_pad( $r, 32, "\x00", STR_PAD_LEFT )
             . str_pad( $s, 32, "\x00", STR_PAD_LEFT );
    }

    /* ------------------------------------------------------------------ */
    /*  Subscription management helpers                                   */
    /* ------------------------------------------------------------------ */

    /**
     * Mark a subscription as inactive (endpoint expired / gone).
     *
     * @param string $endpoint The push endpoint to deactivate.
     */
    private static function deactivate_subscription( string $endpoint ): void {
        global $wpdb;
        $table = $wpdb->prefix . 'ynj_subscribers';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->update(
            $table,
            [ 'status' => 'inactive' ],
            [ 'push_endpoint' => $endpoint ],
            [ '%s' ],
            [ '%s' ]
        );
    }

    /* ------------------------------------------------------------------ */
    /*  Base64url helpers                                                  */
    /* ------------------------------------------------------------------ */

    /**
     * URL-safe base64 encode (no padding).
     */
    public static function base64url_encode( string $data ): string {
        return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
    }

    /**
     * URL-safe base64 decode.
     */
    public static function base64url_decode( string $data ): string {
        $remainder = strlen( $data ) % 4;
        if ( $remainder ) {
            $data .= str_repeat( '=', 4 - $remainder );
        }
        return base64_decode( strtr( $data, '-_', '+/' ) );
    }
}
