# Product review translations — 2026-11-19

Implemented and verified in local EU runtime (127.0.0.1:8098). Public pilloway.eu is unchanged: live API product 2399 still reports 0 reviews. No production access was used.

Files to deploy together:
- ops/sync-product-review-translations.php
- ops/product-review-counters.php

Run from the EU WordPress installation with PHP CLI:

    php ops/sync-product-review-translations.php
    php ops/sync-product-review-translations.php --apply
    php ops/sync-product-review-translations.php

First/last commands are dry runs. Apply creates a private backup outside the web root in the system temporary directory /pilloway-review-backups. Preserve that folder outside the web root after migration. Local backups also copied to ../backups/review-translations-2026-11-19.

The migration uses existing approved product reviews for product identity and existing translated review archive cards for text. Identity is checked against author, date, rating, source product ID and original/translated text. It preserves original author/date/rating; does not mark imported reviews as verified purchases; rating-only reviews remain rating-only. Existing independent target-language reviews are not overwritten. The command is idempotent and refreshes WooCommerce counters and lookup data.

Local result: 763 translated review records, 324 translated product cards. EN 191, PL 191, FR 191, DE 190. Two ambiguous source reviews on product 783 and one mismatched DE archive identity are skipped. No guessed text or review associations.

Selected advertising products, counts EN / PL / FR / DE:
- BB-10213 powder Lyocell: 4 / 4 / 4 / 4 (rating 4.75)
- BB-10211 beige Lyocell: 3 / 3 / 3 / 3 (rating 5)
- BB-10222 beige silk: 3 / 3 / 3 / 3 (rating 5)
- CS-10011 Max Comfort black: 22 / 22 / 22 / 22 (rating 4.95)
- CS-10021 Model-1 black: 11 / 11 / 11 / 10 (one DE identity mismatch intentionally skipped)
- CS-10012 Max Comfort grey: 3 / 3 / 3 / 3 (rating 5)
- Mother's Dream powder: 1 / 1 / 1 / 1 (rating 5; rating-only review)

Validation: PHP syntax; idempotent dry-run planned 0; all seven advertising variants queried across all six languages; 16 product/language HTML review sections checked; English browser verified Reviews (4), rating 4.75 and translated text. After production migration, clear page/CDN cache and repeat public review checks. Changes to future reviews/archive translations require rerunning this migration; this is not an automatic translation service.
