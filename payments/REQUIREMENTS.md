# Oyonx paid plugin requirements

Owner-confirmed pricing (USD): individual $2/month or $24/year; current-seven bundle $15/month or $180/year. Future plugins are excluded. Seven individual plans total $14/month or $168/year; preserve the requested bundle pricing transparently.

Every user needs a verified account for free and paid actions. Each plugin has five successful primary actions free. Lifetime per account is the current implementation assumption; no monthly reset. Opening a plugin, previews, failed actions and unchanged selections do not consume a use.

The owner approved using the local projects under Downloads/Figma-Plugins and Downloads/FewClicks/work/repos/design-system-creator. The release builder stages copies without overwriting those originals. Paid releases require independent Figma verification and publication.

The showcase is already published. Billing stays on a separate review branch until the launch requirements in DEPLOYMENT.md are met. Stripe is the implemented provider; Ziina is not integrated, and must not be advertised as a working recurring payment option.

Implemented source includes accounts, verification/reset flows, schema migrations, atomic usage reservations, Stripe checkout/portal requests, signed webhook verification, recurring access rules, and staged plugin licensing guards. This is not yet an activated production payment system. No real merchant credentials, live payment database or live subscriptions were created by this work.
