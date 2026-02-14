<?php

namespace XLaravel\Listmonk\Contracts;

interface NewsletterSubscriber
{
    public function getNewsletterData(): array;

    public function getNewsletterEmail(): string;

    public function getNewsletterLists(): array;
}
