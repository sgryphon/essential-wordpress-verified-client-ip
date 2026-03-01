<?php

declare(strict_types=1);

namespace VerifiedClientIp;

/**
 * Parses proxy-forwarding HTTP headers into lists of IP addresses.
 *
 * Supports:
 * - RFC 7239 `Forwarded` header (token-based, e.g. for=1.2.3.4)
 * - `X-Forwarded-For` (comma-separated list)
 * - Single-value headers (e.g. CF-Connecting-IP)
 * - Multiple same-name headers concatenated per RFC 7230 §3.2.2
 */
final class HeaderParser
{
    /**
     * Extract IP addresses from a header value (or array of values).
     *
     * When a token is specified, the header is parsed as RFC 7239 structured
     * data and only the matching token values are returned.
     *
     * When no token is specified, the header value is treated as a
     * comma-separated list of addresses (X-Forwarded-For style) or a single
     * address.
     *
     * Multiple header values (array input) are concatenated per RFC 7230 §3.2.2
     * before parsing.
     *
     * Addresses are returned in **right-to-left** order (most recently added first).
     *
     * @param string|array<string> $headerValue The raw header value(s).
     * @param string|null          $token       Optional token to extract (e.g. "for").
     *
     * @return array<string> Extracted addresses, rightmost first.
     */
    public static function parse(string|array $headerValue, ?string $token = null): array
    {
        // Concatenate multiple header values per RFC 7230 §3.2.2.
        if (\is_array($headerValue)) {
            $headerValue = \implode(', ', $headerValue);
        }

        $headerValue = \trim($headerValue);
        if ($headerValue === '') {
            return [];
        }

        if ($token !== null && $token !== '') {
            return self::parseForwarded($headerValue, $token);
        }

        return self::parseCommaSeparated($headerValue);
    }

    /**
     * Parse an RFC 7239 `Forwarded` header, extracting values for a specific token.
     *
     * The header contains comma-separated forwarded-elements, each with
     * semicolon-separated parameters (token=value pairs).
     *
     * Example: `for=192.0.2.1;proto=https, for=198.51.100.1`
     *
     * Quoted values are unquoted. Bracketed IPv6 notation is handled.
     *
     * @param string $header The concatenated Forwarded header value.
     * @param string $token  The token to extract (e.g. "for").
     *
     * @return array<string> Extracted values, rightmost first.
     */
    private static function parseForwarded(string $header, string $token): array
    {
        $addresses = [];
        $token = \strtolower($token);

        // Split into forwarded-elements (comma-separated).
        $elements = self::splitOutsideQuotes($header, ',');

        foreach ($elements as $element) {
            $element = \trim($element);
            if ($element === '') {
                continue;
            }

            // Each element has semicolon-separated parameters.
            $params = self::splitOutsideQuotes($element, ';');

            foreach ($params as $param) {
                $param = \trim($param);
                if ($param === '') {
                    continue;
                }

                $eqPos = \strpos($param, '=');
                if ($eqPos === false) {
                    continue;
                }

                $key = \strtolower(\trim(\substr($param, 0, $eqPos)));
                $value = \trim(\substr($param, $eqPos + 1));

                if ($key !== $token) {
                    continue;
                }

                // Unquote if quoted.
                $value = self::unquote($value);

                $addresses[] = $value;
            }
        }

        // Return rightmost first.
        return \array_reverse($addresses);
    }

    /**
     * Parse a comma-separated list of IP addresses (X-Forwarded-For style).
     *
     * @return array<string> Addresses, rightmost first.
     */
    private static function parseCommaSeparated(string $header): array
    {
        $parts = \explode(',', $header);
        $addresses = [];

        foreach ($parts as $part) {
            $part = \trim($part);
            if ($part !== '') {
                $addresses[] = $part;
            }
        }

        // Return rightmost first.
        return \array_reverse($addresses);
    }

    /**
     * Split a string by a delimiter, but not inside quoted strings.
     *
     * @return array<string>
     */
    private static function splitOutsideQuotes(string $input, string $delimiter): array
    {
        $result = [];
        $current = '';
        $inQuote = false;
        $length = \strlen($input);

        for ($i = 0; $i < $length; $i++) {
            $char = $input[$i];

            if ($char === '"' && ($i === 0 || $input[$i - 1] !== '\\')) {
                $inQuote = !$inQuote;
                $current .= $char;
            } elseif ($char === $delimiter && !$inQuote) {
                $result[] = $current;
                $current = '';
            } else {
                $current .= $char;
            }
        }

        $result[] = $current;

        return $result;
    }

    /**
     * Remove surrounding quotes from a value and unescape.
     */
    private static function unquote(string $value): string
    {
        if (\strlen($value) >= 2 && $value[0] === '"' && $value[-1] === '"') {
            $value = \substr($value, 1, -1);
            // Unescape backslash-escaped characters.
            $value = (string) \preg_replace('/\\\\(.)/', '$1', $value);
        }

        return $value;
    }
}
