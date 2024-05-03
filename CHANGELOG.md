# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org).

## [Unreleased]
## [1.3.2] - 2024-05-03
- Prevent XSS through responses in question types such as shortanswer

## [1.3.1] - 2024-05-03
- Fix problem adding questions to quiz after interacting with question bank form

## [1.3.0] - 2024-04-28
- Support Moodle 4.3 (question bank changes)
- Remove some custom styling in favor of theme style

## [1.2.1] - 2023-08-21

- Changed submission button for student answer changed from Save to Submit.
- Fixed various codestyle issues.
- Added column in qbank view for displaying question text/media
- Swapped the order of firstname and lastname in csv export of attendance list
- Improvement of guest user feedback upon trying to attend a quiz which doesn't allow guests to attend
- Added new template for "guests_not_allowed" feedback

## [1.2.0] - 2023-03-31

- Prepared for Moodle 4.0

## [1.1.0] - 2022-08-17

- Added button to delete session report
- Prevent repetition of 
- Tested with Moodle 3.11

## [1.0.1] - 2021-02-05

- Tested and approved for Moodle 3.9 and 3.10

### Changed

- Continuous integration with github workflows
- Minor fixes to comply with moodle style requirements

## [1.0.0] - 2019-07-30

First version considered ready for field trial.

[Unreleased]: https://github.com/KQMATH/moodle-mod_jazzquiz/compare/v1.0.1...HEAD
[1.0.1]: https://github.com/KQMATH/moodle-qtype_mod_jazzquiz/compare/v1.0.0...v1.0.1
