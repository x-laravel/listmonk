<?php

namespace XLaravel\Listmonk\Contracts;

interface HasSubscriber
{
    /**
     * Null keeps the model out of Listmonk.
     */
    public function subscriberEmail(): ?string;

    public function subscriberName(): string;

    /**
     * @return list<int>
     */
    public function subscriberLists(): array;
}
