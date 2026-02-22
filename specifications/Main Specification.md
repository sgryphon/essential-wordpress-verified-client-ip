Verified Client IP - wordpress plugin
=====================================

A WordPress plugin that correctly verifies Forwarded For and other headers, to determine the verified client IP address, accepting only steps through trusted proxies, stopping at the first non-trusted address.

Behaviour
---------

- Handle different ways of forwarding, e.g. official Forwarded For headers, X-Forwarded-For, custom behaviours like HTTP_CF_CONNECTING_IP, HTTP_CLIENT_IP, or HTTP_X_REAL_IP.

- The general behaviour is as specified in RFC 7239

1. Start with the connected remote client address, as reported by the connection, i.e. REMOTE_ADDR.
2. If that is in the list of valid proxies (either by IP address range or specific address), then the next Forwarded For or similar header is verified (we trust the valid proxy).
3. The next address is then checked, and if it is a valid proxy, we follow the chain back another step, and so on.
4. Eventually we will reach a header that contains an address outside the valid proxy range. Because the previous one was a valid proxy, we trust this header, and report it as the verified client IP.
5. To do this we replace REMOTE_ADDR with the verified client IP, storing the original value in another header.

One approach is to first build a list of all the potential IP addresses in reverse order (starting with REMOTE_ADDR), and then find the first non-trusted one.

Some of this may require special handling, e.g. if there is one known address, that is forwarded from a known cloudflare address, then we would be expecting the next valid value to be the special HTTP_CF_CONNECTING_IP address.

In general for a single header we know they order that header was received, but don't know how it relates to other headers.

So, if the connecting address and the first two proxies are known valid, then the third forwarded-for is valid (and any others ignored).

If the connection and one proxy are valid, but the second forwarded-for matches cloudflare, then we skip any further forward headers and instead take the first (in reverse order) CF address. If there are additional CF addresses or additional Forwarded for then they are discarded (i.e. fake injections).

Configuration
-------------

Settings should be configured in the standard WordPress Settings menu, as a sub-menu item 'Verified Client IP'

There should be a main switch for enable/disable (so can disable parsing, whilst still having it installed, e.g. for diagnostics). There should also be a maximum number of forwards (Forward Limit), defaulting to 1.

The configuration should consist of a number of schemes, that can be prioritised in order. Each scheme should also be able to be turned on/off.

Each scheme should show a configuration panel that has a list of the valid Known Proxies addresses or address ranges, both IPv4 and IPv6, and a setting for the accepted header for that scheme.

The behaviour should check the initial REMOTE_ADDR against each of the schemes. When it finds a match, the accepted header is used to determine the next address (searching backwards). 

This next address is then run through the algorithm again, e.g. it may no longer match the original scheme, but might match a different scheme, determining the different header for the next address.

The system should also allow adding new custom schemes, with a list of addresses/ranges, and the corresponding header to accept.

### Default configurations

The default configurations should include:

1. RFC 7239 Forwarded header
   - This consists of one header 'Forwarded:' with elements of tokens for, proto, by, etc.
   - Subsequent proxies may append after a comma, or add a new header after
   - Slightly different from others as we aren't using an entire header, but just one token i.e. Header = Forwarded, Token = For.
   - Default addresses to IPv4 private ranges, localhost, and IPv6 ULA addresses (often used for internal networks). None of these would be routable from the external internet.
   - External proxy addresses should not be in the default config; the user would have to manually add them as explicit allowed values.
2. X-Forwarded-For headers
   - Uses separate headers for X-Forwarded-For and other elements
   - Can't necessarily rely on the matching of related X-Forwarded-By or other fields, so can probably only validate addresses, not other elements.
   - Default addresses to IPv4 private ranges, localhost, and IPv6 ULA addresses (often used for internal networks). None of these would be routable from the external internet.
3. CloudFlare
   - Default have cloudflare disabled
   - When an address matches a cloudflare proxy, then use the CF header.
   - This will have the known list of existing cloudflare proxies (these are public addresses, which is why it is turned off by default)

If there are other major/well known public proxies (e.g. f5, maybe aws/Microsoft, etc, that have well known proxy address ranges), then they can also be added (defaulted to disabled)

Note that Microsoft .NET has some good configuration options in their Forward For handling documentation. https://learn.microsoft.com/en-us/aspnet/core/host-and-deploy/proxy-load-balancer?view=aspnetcore-10.0

The Microsoft configuration has configurable values for the headers (default to X-Forwarded-For, X-ForwardedProto, X-Forwarded-Host, X-Forwarded-Prefix). While these can be options for X-Forwarded-For, if we want something different we don't have to change them, but could create a new custom scheme (and disable X-Forwarded-For if we wanted).

The top level configuration could have checkbox options for the different fields that a processed, defaulting to "For" and "Proto", i.e. don't process "Host" or "By" as the default config.

The microsoft settings also have an AllowedHosts configuration (if Empty, all hosts are allowed; "*" allows all non-empty hosts). Subdomain wild cards are allowed. Note that the default has Host processing turned off anyway.

Also add configuration options for original values; when REMOTE_ADDR is replaced, store the original in X-Original-Remote-Addr.

Diagnostics
-----------

Diagnostics should be available even if schemes are deactivated. This can be on a separate tab of the settings.

There should be a number of requests input, default to 10 (max 100), and a Start Diagnostics button.

This will record the next 10 incoming requests (or whatever the configured number is). It doesn't expand or rotate or anything, just record the next 10 requests and then stop recording. The button will stay disabled, keeping the diagnostics there.

Each request is the log should be listed, with a details panel shown when selected. The details panel should show all of the headers for that request, as well as what the algorithm would determine as the verified client IP address. Even if not active, the diagnostics should still calculate (just not use).

When showing the calculated address, show each step, e.g. step 1 is the REMOTE_ADDR a.b.c.d, matches scheme 1-ForwardedFor, step 2 uses the ForwardedFor Header e.f.g.h, and matches to scheme 3-CloudFlare. Step 3 uses the CF header, which is unmatched, so becomes the verified client IP.

There should also be a button at the bottom to Clear Diagnostics, which will remove all the entries and re-enable the Start Diagnostics button.

Architecture
------------

The component should follow best practices for a Wordpress plugin, including clear responsibilities between classes.

Wordpress plugins are primarily written in PHP.

Packaging should be provided for submission to Wordpress as a plug in, including documentation on how to run the packaging.

An appropriate code formatter should be used. I like highly opinionated formatters, like Prettier or CSharpier.

If relevant there should also be static linting/checking, if it exists for the platform.

Quality, e.g. formatting, tests, etc, should be checked as part of the automated build (also check them before committing code)

Testing
-------

Unit tests should be provided.

These should run as part of an automated github actions pipeline.

Include both simple examples, e.g. no headers, straight REMOTE_ADDR, and work up to more complex examples. Include examples using multiple steps, e.g. client -> cloudflare -> proxy 1 -> proxy 2 -> server.

Include both IPv4 and IPv6 test examples, including proxies that convert between the two, e.g. a cloudflare (or other) proxy that coverts 4 to 6 (or 6 to 4). Don't need to go too crazy, and keep realistic, e.g. never going to covert 6 to 4 and then 4 to 6 (in trusted proxies).

Handle both HTTP and HTTPS and proxies that convert (usually from HTTPS unwrapped to HTTP).

Include plenty of hacking / extra header examples, where someone might try to confuse the system by adding headers before hitting the valid proxies.

We don't need to worry much about the valid proxies making mistakes -- we assume they are working correctly, although we might want a test or two for graceful degrading if one of them has a bug/problem. e.g. a misconfigured valid proxy that sends the wrong headers shouldn't break things.

Break tests up across multiple files by type.

Where relevant test other aspects, e.g. the configuration or diagnostics. (But the majority should be the algorithm)

Examples
--------

Include a robust approach for local testing via examples. 

Have a container compose file that runs up wordpress along with several proxies (e.g. caddy, nginx, or something else). Use some sort of standard pattern for ports, e.g.

e.g. 
- Port 8100 is Wordpress
- Port 8101 is Proxy A, that forwards direct to Wordpress
- Port 8102 is Proxy B, that forwards to Proxy A
- Port 8103 is Proxy C, that forwards to Proxy B. (we probably don't need more than 3 in a chain)
- Port 8112 is a proxy that uses older X-Forward-For, and sends to Proxy A.
- Port 8122 is a proxy that uses Cloudflare-like headers, and sends to Proxy A.

Provide some clear instructions how to use.

May need to include a full functioning client as well, that can be remoted into, and run a browser for testing. (Because there are some limitations with Windows when connecting to containers from the host on IPv6). Should be okay if we remote into a Linux box and use a browser there.

Provide instructions how to install a development version of the plugin, e.g. if I make local changes to it, so that I can test.

Documentation
-------------

Provide an appropriate README with a brief overview of the project, build status badges. This will also have a link where to install the plugin (once submitted to wordpress)

Link to separate documentation for Development (how to develop), Packaging (how to submit to Wordpress gallery), guidance on running the Examples, etc.

Also include user-documentation. An introductory description to use when packaging, and help instructions of how to configure the different options, use diagnostics.

Background / Alternatives
-------------------------

I have some plugins used in Wordpress but they are fairly limited in what they can do, or have security issues.

e.g. I was using 'Real IP Detector', code at https://plugins.svn.wordpress.org/real-ip-detector/trunk/real-ip.php

The description says it handles cloudflare, central hosting, f5, etc, but it was very limited. All it did was set something like `$_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_CF_CONNECTING_IP'];`, with no checks (so easily spoofed) and a fixed priority order with no configuration.

(The component has now been closed.)

A partial implementation is also provided by 'Show Visitor IP', code at https://plugins.svn.wordpress.org/show-visitor-ip/trunk/show-visitor-ip.php

This replaces the short code [show_ip] with either HTTP_CLIENT_IP, HTTP_X_FORWARDED_FOR, or REMOTE_ADDR, again with no verification.
