<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use XLaravel\Listmonk\Support\ListmonkConfig;

return new class extends Migration
{
    public function up(): void
    {
        $subscribers = ListmonkConfig::subscriberTable();

        Schema::connection($this->getConnection())->create($subscribers, function (Blueprint $table) use ($subscribers) {
            $table->id();
            $table->morphs('subscribable');
            $table->unsignedBigInteger('listmonk_id')->index();
            $table->string('email');
            $table->json('lists');
            $table->string('hash', 64);
            $table->timestamps();

            $table->unique(['subscribable_type', 'subscribable_id'], "{$subscribers}_subscribable_unique");
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists(ListmonkConfig::subscriberTable());
    }

    public function getConnection(): ?string
    {
        return ListmonkConfig::connection();
    }
};
