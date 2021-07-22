# Silverstripe S/MIME

**NOTE: This module is currently under active development, and is not yet ready
for use.**

This module enables Silverstripe CMS projects to use S/MIME when sending emails.
This is not a plug-and-play solution, as it depends on the provisioning and
configuration of a pair of keys for encrypting emails and optionally a pair of
keys for signing emails.

## Requirements

* Silverstripe framework ^4.5 (without file attachments)
* OpenSSL ^1.1.1
* Key pairs for encrypting and signing messages

### Emailing file attachments

There is a known issue with Swiftmailer 5.x wherein email clients are unable to
decrypt encrypted emails which contain attachments.

Any Silverstripe framework versions (>=4.0 <=4.8 at time of writing) which
require Swiftmailer 5.x (usually dependency `swiftmailer/swiftmailer: ~5.4`) are
susceptible to the issue.

If email attachments are required please look at this Silverstripe framework
[pull request for Swiftmailer v6
support](https://github.com/silverstripe/silverstripe-framework/pull/10031).

## Installation

```
composer require silverstripe/smime dev-master
```
## Configuration

See the [configuration guide](/docs/en/configuration.md) for a full guide.

## Docs

See the [documentation](/docs/en/index.md).

## Development and contribution

If you would like to make contributions to the module please ensure you raise a
pull request and discuss with the module maintainers.

## Bugtracker

Bugs are tracked in the issues section of this repository. Before submitting an
issue please read over existing issues to ensure yours is unique.

If the issue does look like a new bug:

 - Create a new issue
 - Describe the steps required to reproduce your issue, and the expected
   outcome. Unit tests, screenshots and screencasts can help here.
 - Describe your environment as detailed as possible: Silverstripe CMS version,
   PHP version, Operating System, any installed Silverstripe CMS modules.

Please report security issues to security directly. Please don't
file security issues in the bugtracker.

## License

See [License](license.md)
