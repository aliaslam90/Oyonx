# Subscription deployment and release verification

## Current status — 27 October 2026

Billing is a draft implementation, separate from main and the published showcase. Local checks pass: 23 policy assertions, 48 database/API assertions (including ten simultaneous requests admitting exactly five), 15 subscription lifecycle fixture assertions, and 11 main-context runtime tests. PHP lint passes. Clean migrations, migration reruns and inactive catalogue seeding were verified against a second disposable MySQL 8.4 database. Seven plugin artifacts build successfully. The account sign-in page was visually inspected in a local browser.

These checks do not establish real Stripe checkout/webhook delivery, email delivery, or correct behavior inside Figma. None of the staged paid artifacts has been published to Figma. Existing free releases do not enforce this new licensing system.

## Configuration

Use PHP 8.3+, Apache with the existing .htaccess files, MySQL 8, PDO MySQL, cURL and OpenSSL. Confirm Hostinger's deployed PHP version/extensions, cron capability, outbound Stripe requests, mail delivery and Authorization forwarding. Keep account/API paths out of page caching.

Create a dedicated database/user and save credentials outside public_html in ../oyonx-private/billing.php, using config.example.php. Restrict file permissions. Alternatively OYONX_BILLING_CONFIG may identify an absolute private file path. Never commit keys or place them in plugin bundles, frontend JS, command arguments or browser URLs. Test and live require separate databases and secrets: account/trial data is otherwise not environment-partitioned.

With a database backup available, run:

```
php payments/install.php --apply
php payments/seed-catalog.php
php payments/setup-stripe.php --provision-test-prices
```

The private config must have enabled=true for these commands, initially environment=test. Plans/products remain inactive until explicitly activated in the test database. The provision command creates/reuses 16 sandbox recurring prices and maps them to immutable plan membership; it does not activate checkout. Never use it to provision live prices.

Register https://oyonx.com/api/stripe-webhook.php on the staged deployment, with the endpoint payload API version 2024-06-20 to match the pinned provider client. Store its signing secret privately. Subscribe to checkout.session.completed, checkout.session.async_payment_succeeded, customer.subscription.created/updated/deleted, invoice.paid, invoice.payment_failed, charge.refunded and charge.dispute.created. Match test/live mode. Verify redelivery, current provider retrieval and HTTP response logs without logging payment bodies or tokens.

Configure the Stripe portal for payment-method updates, invoice history and cancellation at period end. Disable plan switching and quantity updates: this version rejects implicit plan changes. Verify the actual merchant default portal configuration before enabling access to it. This follows Stripe's subscription webhook and portal guidance:

- https://docs.stripe.com/billing/subscriptions/webhooks
- https://docs.stripe.com/customer-management/configure-portal

Access ends at paid invoice line expiry. Failed renewal does not extend it; scheduled cancellation retains the paid period. Immediate cancellation blocks access. Refund/dispute on the latest invoice revokes the period, including partial refunds in this implementation; historical refunds do not revoke newer paid periods. Confirm this policy before launch. Invoice line pagination currently fails closed and needs an operator/reconciliation path if encountered.

## Plugin releases

```
node scripts/payments/build-plugin-releases.mjs
```

The machine-local source paths and compiler defaults are in the builder; environment overrides are supported. Artifacts are ignored under .release/paid-plugins/<slug> with source copies, manifest and bundle; the parent BUILD-REPORT.json records hashes. Keep original published plugin IDs when importing/updating. Design System Creator's local manifest ID needs verification against its actual Community listing.

For EACH plugin import its staged manifest in Figma and verify: account pairing, verified email requirement, successful primary action, failure/no-op without charge, five free actions, sixth blocked, individual paid access, monthly/yearly bundle access for exactly seven, cancellation/expiry, token revocation, network loss before/after mutation, and crash recovery. Inspect widget sync behavior and asynchronous callbacks in particular. Tokens stay in per-plugin clientStorage; each plugin must be paired separately. No document content is sent to billing.

An interrupted operation with unknown mutation outcome deliberately blocks further actions for support review. Finalization after the 15-minute reservation expiry can also require support intervention. A support resolution tool and customer recovery UX remain launch work; do not ship unresolved usage states to paying customers.

## Required launch work

1. Authenticated Stripe access and private sandbox configuration; real checkout, renewal/test clocks, portal cancellation, refund, event replay and payment failure tests.
2. Hostinger staging deployment/database and verified email delivery/reset links. Local HTTP preview does not validate HTTPS session behavior end to end.
3. Finish interrupted-operation recovery and reconciliation/operational alerts; add retention/cleanup jobs for expired tokens/rate buckets/usage history.
4. Figma validation and publication of each of the seven paid plugin updates. Original sources are not changed by staging builds.
5. Review product terms, refund behavior and live price mappings; configure live secrets and verified webhook separately. Enable only the tested plans/products, link the website showcase to account checkout, and verify live access after payment.

Do not merge this draft into Hostinger's main deployment branch before those requirements are complete. A checkout success URL alone never grants access. Do not run database/API fixture suites against production: they intentionally reject non-disposable hosts and toggle test catalogue state.
