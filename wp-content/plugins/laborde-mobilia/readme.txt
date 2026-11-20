=== Laborde Mobilia ===
Requires at least: 6.5
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPL-2.0-or-later

Connects the Laborde Marset site to the Mobilia Gestión API.

== Setup ==

Add to wp-config.php, above "That's all, stop editing!":

    define( 'MOBILIA_CLIENT_ID', '...' );
    define( 'MOBILIA_CLIENT_SECRET', '...' );
    // Optional, defaults to https://api.mobiliagestion.es/api/v1
    // define( 'MOBILIA_BASE_URL', 'https://api.mobiliagestion.es/api/v1' );

== Usage ==

Tools → Mobilia → Test connection. Requests an OAuth2 token (client_credentials)
and makes one read-only GET request (default: first page of /inmuebles; paginate with numeroPagina + tamanoPagina, max 100), then
shows the HTTP status, headers and response body. Nothing is imported or saved
yet, apart from the cached token and the last result (a transient that expires
after 15 minutes). Credentials and tokens are never displayed.

== Changelog ==

= 0.1.0 =
* Read-only connection test screen.
