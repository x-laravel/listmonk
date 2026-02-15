<?php

namespace XLaravel\Listmonk\Contracts;

interface NewsletterSubscriber
{
    public function getNewsletterEmail(): string;

    public function getNewsletterAttributes(): array;

    public function getNewsletterLists(): array;

    public function getNewsletterEmailColumn(): string;

    public function getNewsletterNameColumn(): string;

    public function getNewsletterPassiveListId(): ?int;
}
