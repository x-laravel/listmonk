<?php

namespace XLaravel\Listmonk\Contracts;

interface NewsletterSubscriber
{
    public function getNewsletterEmail(): string;

    public function getNewsletterName(): string;

    public function getNewsletterAttributes(): array;

    public function getNewsletterLists(): array;
}
