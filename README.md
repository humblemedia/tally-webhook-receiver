# Tally Webhook Receiver

A small WordPress plugin that receives form submissions from [Tally](https://tally.so) webhooks and stores them in WordPress. Every request's signature is checked before anything is saved.

## Setup

1. Install and activate the plugin. It requires WordPress 7.1+ and PHP 8.1+.
2. Add your signing secret to `wp-config.php`, above the "stop editing" line. Keeping it there keeps it out of the database and the repo.

   ```php
   define( 'TWR_TALLY_SECRET', 'your-secret' );
   ```

3. In Tally, add a webhook pointing at `https://your-site/wp-json/tally-webhook-receiver/v1/webhooks`, with the same signing secret.

Submissions appear under **Tally Submissions** in wp-admin. Other code can react to new ones with the `twr_submission_received` action.

## Responses

| Status | Meaning |
| ------ | ------- |
| 201 | New submission saved. Returns the post ID. |
| 200 | Duplicate event. Nothing saved. |
| 400 | Payload doesn't match the schema. |
| 401 | Missing or invalid signature, or no secret configured. |

## Key decisions

- **The signature is checked against the raw body.** Tally signs the exact bytes it sends. Decoding and re-encoding the JSON changes those bytes, so the check uses `get_body()`, never the parsed params. I confirmed the encoding (base64 HMAC-SHA256) against a real captured request.
- **Small classes, passed in.** The controller handles HTTP, the verifier checks signatures and the store handles saving. The main file builds them and passes them in. The verifier has no WordPress dependencies, so it can be unit tested on its own.
- **A custom post type for storage.** It uses core tools and gives an admin screen for free. The limits are listed below.
- **Duplicates return 200, not 409.** Tally retries on any non-2xx response, so an error code would only cause more retries.

## Known limits and next steps

- The duplicate check isn't race-safe, and `meta_value` isn't indexed. A custom table with a unique index on `event_id` would fix both.
- Heavy follow-up work should be queued with Action Scheduler or WP-Cron from the hook, so the endpoint answers fast.
- A settings page for the secret, and logging for rejected requests.

## Tests

```
composer install
composer test
```

The unit tests cover the signature verifier, including a tampered body, re-encoded JSON, a missing header and a missing secret. The endpoint was tested by hand on a live site with real Tally submissions, a bad signature (401) and a replayed event (200, duplicate).

## Notes

- The submission URLs in the test fixture are redacted, because they contained live access tokens.
- Tested on WordPress 7.1. The code should work back to WordPress 5.5, but that hasn't been tested.
- I wrote the plugin code by hand. The unit tests, docblocks and this README were drafted with Claude and reviewed by me.
