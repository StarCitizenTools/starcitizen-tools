/*
 * Sample Varnish configuration for EventLogging
 * ---------------------------------------------
 * This configuration specifies that requests for /event.gif?... be
 * handled by returning an empty response specifying HTTP 204 status
 * code ("No Content"). Varnish will still log the request in its shm
 * log, so it will be possible to consume the incoming event stream
 * using varnishncsa.
 *
 * This setup is currently deployed at the Wikimedia Foundation and
 * has been found to work well.
 *
 * Sample varnishncsa invocation:
 *     varnishncsa -m RxURL:^/event\.gif\?. -F "%q %l %t %h"
 *
 * See the varnishncsa(1) man page for details.
 *
 */
sub vcl_recv {
	if (req.url ~ "^/event\.gif") {
		error 204;
	}
}

sub vcl_error {
	/* 204 responses shouldn't contain a body */
	if (obj.status == 204) {
		return(deliver);
	}
}
