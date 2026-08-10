# Release Checklist

## Before Tagging

- Confirm package versioning strategy for the release.
- Review `README.md` for any newly added public API or config keys.
- Review `composer.json` metadata, keywords and support links.
- Ensure no local-only files are intended for distribution.

## Validation

- Run `composer validate --strict`.
- Run `composer test`.
- Run `composer outdated --direct` and confirm direct dependencies are current.

## Compatibility

- Laravel support target: 11, 12, 13.
- Verify package behavior against at least one supported Laravel app before release.
- Testbench matrix used for package development:
  - `orchestra/testbench:^9` for Laravel 11
  - `orchestra/testbench:^10` for Laravel 12
  - `orchestra/testbench:^11` for Laravel 13

## Comgate Coverage

- Verify Merchant REST API flows you changed: create, recurring, status, refund, preauth.
- Verify Status API flows if touched: `health`, `outages`, availability reporting.
- Verify webhook handling if request or notification fields changed.

## Release

- Update changelog or release notes summary.
- Commit the final diff.
- Tag the release.
- Publish the release notes.
- If applicable, submit or refresh the package on Packagist.