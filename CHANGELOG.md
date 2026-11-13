# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-11-13

### Added

- `Contracts\HasSubscriber` and `Concerns\Subscribable` for the models that are Listmonk
  subscribers; a model names its e-mail, its name, its lists and optional attributes.
- A queued sync after the model is saved, deleted or restored, sent only when the e-mail, the
  name, the lists or the attributes changed and after the surrounding transaction commits.
- A local subscriber record per model, so subscribers are matched by id and the API user needs
  only the `subscribers:get` and `subscribers:manage` permissions.
- Updates that keep what Listmonk knows: lists joined elsewhere, attributes set elsewhere, a
  blocklisted status and unsubscribed lists.
- Adoption of a subscriber Listmonk already has for the e-mail, and one shared subscriber for
  models with the same e-mail.
- Removal of the subscriber when its model is deleted, soft deleted or loses its e-mail, and
  pruning of left-behind subscribers through `model:prune`, scheduled by the package with a
  configurable frequency and chunk size.
- `listmonk:sync` to queue a sync for existing records and `listmonk:health` to check the API user.
- `Events\SubscriberSynced` and `Events\SubscriberRemoved`, dispatched after commit.
- `Client` for the subscriber endpoints of Listmonk.
- Configurable API user, queue, connection, table and model.

[1.0.0]: https://github.com/x-laravel/listmonk/releases/tag/v1.0.0
