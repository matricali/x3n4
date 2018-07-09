# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

## [Unreleased]
### Added
- Command history. (thanks to @davidtavarez).
- Added support for multiple encryption algorithms. (b64, rb64).

### Changed
- Improved running user detection. (thanks to @davidtavarez).
- Dark terminal theme.

## [0.1.7-alpha] - 2018-06-30
### Added
- Command execution using "proc_open". (thanks to @davidtavarez).
- Command execution using "passthru".
- Command execution using "popen".

### Changed
- Fixed command execution using "exec".
- Improved MOTD. Now supports Linux, WIN and Darwin platforms. (thanks to @davidtavarez).
- Fixed missing decode.

## [0.1.6-alpha] - 2018-06-29
### Added
- Command encryption. (thanks to @davidtavarez).
- Added server name and server software. (thanks to @davidtavarez).
- HTTP Basic Auth protection. (thanks to @davidtavarez).

## [0.1.5-alpha] - 2017-07-16
### Changed
- Improvements on command execution.
- If json_encode isn't available then send output in plain text (including
    banner).
- Improvements on auto-update system.

### Removed
- File manager.

## [0.1.5-alpha] - 2017-06-20
### Changed
- Improvements on command execution.
- If json_encode isn't available then send output in plain text (including
    banner).
- Improvements on auto-update system.

### Removed
- File manager.

## [0.1.4-alpha] - 2017-06-29
### Added
- Disabled functions detection.
- Support for multiple functions and mechanisms in order to ensure the command execution.
- System information section. Including: platform, current user, server IP, client IP, PHP version, installed modules, disabled functions.
- File manager :WIP:.
- Font-awesome.

### Changed
- If the frontend doesn't receives a JSON, then print the whole output.

## [0.1.3-alpha] - 2017-06-20
### Added
- Automatically download latest x3n4.php from Github releases using 'upgrade' command.

### Changed
- Adopted use of "long" array syntax array() in order to be compatible with PHP
below 5.4.

## [0.1.0-alpha] - 2017-06-20
### Added
- Simple shell suport via shell_exec().
- Internal commands: "clear" and "exit".
- Partial support for "cd" using chdir().
- MOTD.
