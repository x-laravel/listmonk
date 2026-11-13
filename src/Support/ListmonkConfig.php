<?php

namespace XLaravel\Listmonk\Support;

use Illuminate\Support\Facades\Config;
use InvalidArgumentException;
use XLaravel\Listmonk\Models\Subscriber;

/**
 * @internal
 */
final class ListmonkConfig
{
    public static function enabled(): bool
    {
        return Config::boolean('listmonk.enabled', true) && self::url() !== '' && self::token() !== '';
    }

    public static function url(): string
    {
        return rtrim(self::string('url'), '/');
    }

    public static function username(): string
    {
        return self::string('username');
    }

    public static function token(): string
    {
        return self::string('token');
    }

    public static function timeout(): int
    {
        return max(1, Config::integer('listmonk.timeout', 10));
    }

    public static function preconfirm(): bool
    {
        return Config::boolean('listmonk.preconfirm', true);
    }

    public static function queueConnection(): ?string
    {
        return self::string('queue.connection') ?: null;
    }

    public static function queueName(): ?string
    {
        return self::string('queue.name') ?: null;
    }

    /**
     * @return class-string<Subscriber>
     */
    public static function subscriberModel(): string
    {
        $model = Config::get('listmonk.subscriber.model') ?? Subscriber::class;

        if (! is_string($model) || ! is_a($model, Subscriber::class, true)) {
            throw new InvalidArgumentException('Set listmonk.subscriber.model to a model that extends ' . Subscriber::class . '.');
        }

        return $model;
    }

    public static function subscriberTable(): string
    {
        return self::string('subscriber.table') ?: 'listmonk_subscribers';
    }

    public static function connection(): ?string
    {
        return self::string('connection') ?: null;
    }

    protected static function string(string $key): string
    {
        $value = Config::get("listmonk.{$key}");

        return is_string($value) ? trim($value) : '';
    }
}
