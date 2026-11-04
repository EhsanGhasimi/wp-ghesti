# Plugin branding

For every WordPress plugin created or updated in this workspace, use the developer name exactly as provided by the user: `احسان قسیمی - Ehsan Ghasimi`.
The organization name is `وبینیو` (Webinew).
Include the developer and organization in the plugin Author header, documentation, and an appropriate admin about section. Introduce the organization's services briefly in the plugin description and about section.
Author URI: https://webinew.com/about-us/
Organization website: https://webinew.com/
The about page is the primary source for company service descriptions. It currently lists WordPress/custom website design, SEO, Google Ads, video content production, Telegram Ads, and Meta Ads. Verify the source when updating service claims; do not invent claims or testimonials.
Rebuild the installable ZIP after plugin changes.

# Development root
This is the canonical standalone wp-snapppay project. Plugin source is in ./wp-snapppay/. Build with ./scripts/build.ps1; installable archives and SHA256SUMS are in ./dist/. Do not edit the old snappay-persentage workspace. Read README-FA.md and tests/README.md before development. Do not change plugin folder identifiers when reorganizing files. Integration tests read both sibling projects. The authoritative shared license SDK is in the webinew-license-manager project under sdk/. Use build.ps1 -SyncSdk in the wp-snapppay project only when intentionally updating its bundled SDK.

